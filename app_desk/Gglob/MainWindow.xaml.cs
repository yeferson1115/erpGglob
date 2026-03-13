using System.Collections.ObjectModel;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;

namespace Gglob
{
    public partial class MainWindow : Window
    {
        private static readonly HttpClient HttpClient = new();
        private static readonly string ApiBaseUrl = "http://localhost:81/api";
        private static readonly string SessionCachePath = Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
            "Gglob",
            "offline-session.json");

        private readonly ObservableCollection<DestinationAccount> destinationAccounts = [];
        private readonly ObservableCollection<QrAccountOption> qrAccountOptions = [];
        private readonly ObservableCollection<VerifiedPaymentRecord> verifiedPayments = [];
        private bool isGglobPayEnabled;

        public MainWindow()
        {
            InitializeComponent();
            InitializeGglobPayModule();
        }

        private void InitializeGglobPayModule()
        {
            DestinationBankComboBox.ItemsSource = new[]
            {
                "Bancolombia",
                "Davivienda",
                "Banco de Bogotá",
                "BBVA Colombia",
                "Banco Popular",
                "Nequi",
                "Daviplata"
            };
            DestinationBankComboBox.SelectedIndex = 0;

            QrCashierComboBox.ItemsSource = new[] { "Caja Principal", "Caja Norte", "Cajero Ana", "Cajero Luis" };
            QrCashierComboBox.SelectedIndex = 0;

            var cashierFilter = new[] { "Todos", "Caja Principal", "Caja Norte", "Cajero Ana", "Cajero Luis" };
            VerifiedCashierComboBox.ItemsSource = cashierFilter;
            ReportCashierComboBox.ItemsSource = cashierFilter;
            VerifiedCashierComboBox.SelectedIndex = 0;
            ReportCashierComboBox.SelectedIndex = 0;

            DestinationAccountsListBox.ItemsSource = destinationAccounts;
            QrAccountComboBox.ItemsSource = qrAccountOptions;

            SetSelectedModule(null);
        }

        private void SeedVerifiedPayments()
        {
            verifiedPayments.Clear();
            verifiedPayments.Add(new VerifiedPaymentRecord(
                "GGPAY-20260614-080102-A1B2",
                "María Herrera",
                "30201984756",
                235000m,
                "Caja Principal",
                "Bancolombia",
                DateTime.Now.AddHours(-2)));
            verifiedPayments.Add(new VerifiedPaymentRecord(
                "GGPAY-20260614-084519-F4K8",
                "Julián Torres",
                "45782019431",
                115000m,
                "Cajero Ana",
                "Davivienda",
                DateTime.Now.AddHours(-1)));

            RefreshQrOptions();
        }

        private async void LoginButton_Click(object sender, RoutedEventArgs e)
        {
            var email = EmailTextBox.Text.Trim();
            var password = PasswordBox.Password;

            if (string.IsNullOrWhiteSpace(email) || string.IsNullOrWhiteSpace(password))
            {
                ShowStatus("Completa correo y contraseña.", isError: true, isWarning: true);
                return;
            }

            LoginButton.IsEnabled = false;
            ShowStatus("Validando credenciales...", isError: false);

            var authenticated = await TryOnlineLogin(email, password);
            if (!authenticated)
            {
                TryOfflineLogin(email, password);
            }

            LoginButton.IsEnabled = true;
        }

        private async Task<bool> TryOnlineLogin(string email, string password)
        {
            try
            {
                var payload = JsonSerializer.Serialize(new { email, password });
                using var content = new StringContent(payload, Encoding.UTF8, "application/json");
                using var response = await HttpClient.PostAsync($"{ApiBaseUrl}/login", content);
                var body = await response.Content.ReadAsStringAsync();

                if (!response.IsSuccessStatusCode)
                {
                    ShowStatus($"Error de autenticación ({(int)response.StatusCode}).", isError: true);
                    return false;
                }

                var options = new JsonSerializerOptions { PropertyNameCaseInsensitive = true };
                var authResult = JsonSerializer.Deserialize<AuthResponse>(body, options);
                if (authResult is null || string.IsNullOrWhiteSpace(authResult.AccessToken) || authResult.User is null)
                {
                    ShowStatus("No se pudo interpretar la respuesta del servidor.", isError: true);
                    return false;
                }

                HttpClient.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", authResult.AccessToken);

                var profile = await GetProfile(options);
                var user = profile ?? authResult.User;
                var permissions = authResult.Permissions;

                var accessValidation = ValidateDeskAccess(user);
                if (!accessValidation.IsValid)
                {
                    ShowStatus(accessValidation.Message, isError: true);
                    return true;
                }

                ShowDashboard(user, permissions, "Ingreso exitoso.");
                SaveOfflineSession(email, password, authResult.AccessToken, user, permissions);
                return true;
            }
            catch (Exception ex)
            {
                ShowStatus($"Sin conexión con app_web. Se intentará acceso offline. Detalle: {ex.Message}", isError: false, isWarning: true);
                return false;
            }
        }

        private void TryOfflineLogin(string email, string password)
        {
            var cached = ReadOfflineSession();
            if (cached is null)
            {
                ShowStatus("No existe sesión guardada. Debes iniciar sesión con internet al menos una vez.", isError: true, isWarning: true);
                return;
            }

            var emailMatches = string.Equals(cached.Email, email, StringComparison.OrdinalIgnoreCase);
            var passwordMatches = VerifyPassword(password, cached.PasswordSalt, cached.PasswordHash);

            if (!emailMatches || !passwordMatches || cached.User is null)
            {
                ShowStatus("Credenciales offline inválidas. Conéctate a internet e inicia sesión nuevamente.", isError: true);
                return;
            }

            var accessValidation = ValidateDeskAccess(cached.User);
            if (!accessValidation.IsValid)
            {
                ShowStatus($"Acceso denegado (offline): {accessValidation.Message}", isError: true);
                return;
            }

            HttpClient.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", cached.AccessToken);
            ShowDashboard(cached.User, cached.Permissions, $"Ingreso offline habilitado. Última sincronización: {cached.CachedAt:yyyy-MM-dd HH:mm}");
        }

        private static async Task<ApiUser?> GetProfile(JsonSerializerOptions options)
        {
            try
            {
                using var response = await HttpClient.GetAsync($"{ApiBaseUrl}/profile");
                if (!response.IsSuccessStatusCode)
                {
                    return null;
                }

                var body = await response.Content.ReadAsStringAsync();
                return JsonSerializer.Deserialize<ApiUser>(body, options);
            }
            catch
            {
                return null;
            }
        }

        private static AccessValidation ValidateDeskAccess(ApiUser user)
        {
            if (user.CompanyId is null || user.Company is null)
            {
                return new AccessValidation(false, "No tienes negocio asociado. Solicita asignación de empresa para usar Desk.");
            }

            if (string.IsNullOrWhiteSpace(user.BusinessRole))
            {
                return new AccessValidation(false, "No tienes rol de negocio asignado. Debes ser Dueño o Cajero.");
            }

            var normalizedRole = user.BusinessRole.Trim().ToLowerInvariant();
            if (normalizedRole is not ("owner" or "cashier"))
            {
                return new AccessValidation(false, "Rol de negocio no permitido para Desk. Solo Dueño o Cajero.");
            }

            if (!string.Equals(user.Company.ServiceStatus, "active", StringComparison.OrdinalIgnoreCase))
            {
                return new AccessValidation(false, "Tu acceso está inactivo. El estado del negocio no está activo.");
            }

            if (!HasActivePlan(user.Company))
            {
                return new AccessValidation(false, "La empresa no tiene un plan activo asignado. Contacta al administrador.");
            }

            var dateValidation = ValidatePlanDates(user.Company);
            if (!dateValidation.IsValid)
            {
                return dateValidation;
            }

            return new AccessValidation(true, "OK");
        }

        private static bool HasActivePlan(ApiCompany company)
        {
            if (company.PlanId is null || company.PlanId <= 0)
            {
                return false;
            }

            return !string.IsNullOrWhiteSpace(company.PlanName)
                && !string.Equals(company.PlanName.Trim(), "Sin plan", StringComparison.OrdinalIgnoreCase);
        }

        private static AccessValidation ValidatePlanDates(ApiCompany company)
        {
            var today = DateTime.Today;

            if (TryParseDate(company.StartedAt, out var startedAt) && startedAt.Date > today)
            {
                return new AccessValidation(false, $"El plan de la empresa inicia el {startedAt:yyyy-MM-dd}. Aún no está vigente.");
            }

            if (!TryParseDate(company.ActiveUntil, out var activeUntil))
            {
                return new AccessValidation(false, "No se pudo validar la vigencia del plan (fecha final no configurada).");
            }

            if (activeUntil.Date < today)
            {
                return new AccessValidation(false, $"El plan de la empresa venció el {activeUntil:yyyy-MM-dd}. Renueva la vigencia para ingresar.");
            }

            return new AccessValidation(true, "OK");
        }

        private static bool TryParseDate(string? value, out DateTime parsedDate)
        {
            return DateTime.TryParse(value, out parsedDate);
        }

        private void ShowDashboard(ApiUser user, List<ApiPermission>? permissionsList, string statusMessage)
        {
            LoginRoot.Visibility = Visibility.Collapsed;
            DashboardRoot.Visibility = Visibility.Visible;

            DashboardUserTextBlock.Text = $"{user.Name} ({user.Email})";
            DashboardBusinessTextBlock.Text = user.Company is null
                ? "Sin negocio asociado"
                : $"{user.Company.Name} • NIT {user.Company.Nit ?? "N/A"}";
            DashboardRoleTextBlock.Text = NormalizeBusinessRole(user.BusinessRole);

            PlanBadgeText.Text = $"Plan: {user.Company?.PlanName ?? "Sin plan"}";
            StatusBadgeText.Text = $"Estado: {user.Company?.ServiceStatus ?? "N/A"}";

            var services = BuildServiceItems(user.Company);
            var activeServices = services.Where(s => s.IsActive).ToList();
            ActiveServicesBadgeText.Text = $"Servicios activos: {activeServices.Count}";
            SummaryServicesText.Text = activeServices.Count.ToString();

            RenderServicesMenu(services);
            RenderActiveServicesCards(activeServices);
            isGglobPayEnabled = user.Company?.GglobPayEnabled ?? false;
            ToggleGglobPayModuleAvailability();
            RenderPermissions(permissionsList);

            _ = LoadGglobPayDataFromApi();
            SetSelectedModule(null);

            ShowStatus(statusMessage, isError: false);
        }

        private void ToggleGglobPayModuleAvailability()
        {
            GglobPayTabControl.IsEnabled = isGglobPayEnabled;
            if (!isGglobPayEnabled)
            {
                QrStatusTextBlock.Foreground = Brushes.DarkOrange;
                QrStatusTextBlock.Text = "Gglob Pay está inactivo en este negocio. Activa el servicio para operar pagos y verificación bancaria.";
            }
            else
            {
                QrStatusTextBlock.Foreground = Brushes.DarkGreen;
                QrStatusTextBlock.Text = "Gglob Pay activo: puedes generar QR y verificar transferencias inmediatas.";
            }
        }

        private void SetSelectedModule(string? moduleKey)
        {
            if (moduleKey == "gglob_pay")
            {
                if (!isGglobPayEnabled)
                {
                    QrStatusTextBlock.Foreground = Brushes.DarkOrange;
                    QrStatusTextBlock.Text = "El servicio Gglob Pay está inactivo para esta empresa.";
                    DefaultPanel.Visibility = Visibility.Visible;
                    GglobPayPanel.Visibility = Visibility.Collapsed;
                    return;
                }

                DefaultPanel.Visibility = Visibility.Collapsed;
                GglobPayPanel.Visibility = Visibility.Visible;
                return;
            }

            DefaultPanel.Visibility = Visibility.Visible;
            GglobPayPanel.Visibility = Visibility.Collapsed;
        }

        private void RenderServicesMenu(List<ServiceItem> services)
        {
            ServicesMenuPanel.Children.Clear();

            foreach (var service in services)
            {
                var button = new Button
                {
                    Padding = new Thickness(10, 8, 10, 8),
                    Margin = new Thickness(0, 0, 0, 8),
                    Background = service.IsActive
                        ? new SolidColorBrush(Color.FromRgb(76, 116, 196))
                        : new SolidColorBrush(Color.FromArgb(90, 255, 255, 255)),
                    BorderThickness = new Thickness(0),
                    Tag = service.Key,
                    Cursor = System.Windows.Input.Cursors.Hand
                };

                button.Click += (_, _) => SetSelectedModule(service.Key);

                var panel = new StackPanel();
                panel.Children.Add(new TextBlock
                {
                    Text = service.Name,
                    Foreground = Brushes.White,
                    FontWeight = FontWeights.SemiBold
                });
                panel.Children.Add(new TextBlock
                {
                    Text = service.IsActive ? "Activo" : "Inactivo",
                    Foreground = Brushes.White,
                    Opacity = 0.9,
                    FontSize = 12
                });

                button.Content = panel;
                ServicesMenuPanel.Children.Add(button);
            }

            SetSelectedModule(button.Tag?.ToString());
        }

        private void RenderActiveServicesCards(List<ServiceItem> activeServices)
        {
            ActiveServicesCardsPanel.Children.Clear();

            if (activeServices.Count == 0)
            {
                ActiveServicesCardsPanel.Children.Add(new TextBlock
                {
                    Text = "No tienes servicios activos. Actívalos para visualizar módulos en Desk.",
                    Foreground = new SolidColorBrush(Color.FromRgb(107, 114, 128))
                });

                return;
            }

            foreach (var service in activeServices)
            {
                var card = new Border
                {
                    Width = 220,
                    Margin = new Thickness(0, 0, 10, 10),
                    Padding = new Thickness(12),
                    Background = Brushes.White,
                    BorderBrush = new SolidColorBrush(Color.FromRgb(217, 228, 255)),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(10)
                };

                var panel = new StackPanel();
                panel.Children.Add(new TextBlock
                {
                    Text = service.Name,
                    FontWeight = FontWeights.Bold,
                    Foreground = new SolidColorBrush(Color.FromRgb(31, 41, 55))
                });
                panel.Children.Add(new TextBlock
                {
                    Text = service.Description,
                    Margin = new Thickness(0, 4, 0, 0),
                    FontSize = 12,
                    TextWrapping = TextWrapping.Wrap,
                    Foreground = new SolidColorBrush(Color.FromRgb(107, 114, 128))
                });

                card.Child = panel;
                ActiveServicesCardsPanel.Children.Add(card);
            }
        }

        private void RenderPermissions(List<ApiPermission>? permissionsList)
        {
            var permissions = permissionsList?.Select(p => p.Name)
                .Where(x => !string.IsNullOrWhiteSpace(x))
                .Distinct()
                .ToList() ?? [];

            if (permissions.Count == 0)
            {
                return;
            }

            QrStatusTextBlock.Text += $" Permisos detectados: {string.Join(", ", permissions)}.";
        }

        private async Task LoadGglobPayDataFromApi()
        {
            var loadedAccounts = await LoadDestinationAccountsFromApi();
            if (!loadedAccounts)
            {
                if (destinationAccounts.Count == 0)
                {
                    destinationAccounts.Add(new DestinationAccount("Bancolombia", "Gglob SAS", "01872365019", "Ahorros"));
                }

                SeedVerifiedPayments();
                ApplyVerifiedFilterLocal();
                GenerateReportLocal();
                return;
            }

            await LoadVerifiedPaymentsFromApi();
            await GenerateReportFromApi();
        }

        private async Task<bool> LoadDestinationAccountsFromApi()
        {
            try
            {
                using var response = await HttpClient.GetAsync($"{ApiBaseUrl}/gglob-pay/destination-accounts");
                if (!response.IsSuccessStatusCode)
                {
                    return false;
                }

                var content = await response.Content.ReadAsStringAsync();
                var result = JsonSerializer.Deserialize<ApiListResponse<ApiDestinationAccount>>(content, JsonOptions());
                if (result?.Data is null)
                {
                    return false;
                }

                destinationAccounts.Clear();
                foreach (var account in result.Data)
                {
                    destinationAccounts.Add(new DestinationAccount(
                        account.Bank ?? "N/A",
                        account.HolderName ?? "N/A",
                        account.AccountNumber ?? "N/A",
                        account.AccountType ?? "Ahorros"));
                }

                RefreshQrOptions();
                return true;
            }
            catch
            {
                return false;
            }
        }

        private async Task<DestinationAccount?> SaveDestinationAccountApi(string bank, string holder, string accountNumber, string accountType)
        {
            try
            {
                var payload = JsonSerializer.Serialize(new
                {
                    bank,
                    holder_name = holder,
                    account_number = accountNumber,
                    account_type = accountType
                });

                using var content = new StringContent(payload, Encoding.UTF8, "application/json");
                using var response = await HttpClient.PostAsync($"{ApiBaseUrl}/gglob-pay/destination-accounts", content);

                if (!response.IsSuccessStatusCode)
                {
                    return null;
                }

                var body = await response.Content.ReadAsStringAsync();
                var result = JsonSerializer.Deserialize<ApiSingleResponse<ApiDestinationAccount>>(body, JsonOptions());
                var data = result?.Data;

                if (data is null)
                {
                    return null;
                }

                return new DestinationAccount(data.Bank ?? bank, data.HolderName ?? holder, data.AccountNumber ?? accountNumber, data.AccountType ?? accountType);
            }
            catch
            {
                return null;
            }
        }

        private async Task LoadVerifiedPaymentsFromApi()
        {
            try
            {
                var from = VerifiedFromDatePicker.SelectedDate?.ToString("yyyy-MM-dd");
                var to = VerifiedToDatePicker.SelectedDate?.ToString("yyyy-MM-dd");
                var cashier = VerifiedCashierComboBox.SelectedItem?.ToString();

                var query = BuildPaymentsQuery(from, to, cashier);
                using var response = await HttpClient.GetAsync($"{ApiBaseUrl}/gglob-pay/payments{query}");

                if (!response.IsSuccessStatusCode)
                {
                    ApplyVerifiedFilterLocal();
                    return;
                }

                var content = await response.Content.ReadAsStringAsync();
                var result = JsonSerializer.Deserialize<ApiListResponse<ApiVerifiedPayment>>(content, JsonOptions());

                if (result?.Data is null)
                {
                    ApplyVerifiedFilterLocal();
                    return;
                }

                verifiedPayments.Clear();
                foreach (var payment in result.Data)
                {
                    verifiedPayments.Add(payment.ToDesktopRecord());
                }

                VerifiedPaymentsDataGrid.ItemsSource = verifiedPayments.OrderByDescending(v => v.VerifiedAt).ToList();
            }
            catch
            {
                ApplyVerifiedFilterLocal();
            }
        }

        private async Task<VerifiedPaymentRecord?> SaveVerifiedPaymentApi(VerifiedPaymentRecord payment)
        {
            try
            {
                var payload = JsonSerializer.Serialize(new
                {
                    reference_code = payment.ReferenceCode,
                    sender_name = payment.SenderName,
                    account_number = payment.AccountNumber,
                    amount = payment.Amount,
                    cashier = payment.Cashier,
                    bank = payment.Bank,
                    verified_at = payment.VerifiedAt.ToString("yyyy-MM-dd HH:mm:ss")
                });

                using var content = new StringContent(payload, Encoding.UTF8, "application/json");
                using var response = await HttpClient.PostAsync($"{ApiBaseUrl}/gglob-pay/payments", content);
                if (!response.IsSuccessStatusCode)
                {
                    return null;
                }

                var body = await response.Content.ReadAsStringAsync();
                var result = JsonSerializer.Deserialize<ApiSingleResponse<ApiVerifiedPayment>>(body, JsonOptions());
                return result?.Data?.ToDesktopRecord() ?? payment;
            }
            catch
            {
                return null;
            }
        }

        private async Task GenerateReportFromApi()
        {
            try
            {
                var from = ReportFromDatePicker.SelectedDate?.ToString("yyyy-MM-dd");
                var to = ReportToDatePicker.SelectedDate?.ToString("yyyy-MM-dd");
                var cashier = ReportCashierComboBox.SelectedItem?.ToString();
                var query = BuildPaymentsQuery(from, to, cashier);

                using var response = await HttpClient.GetAsync($"{ApiBaseUrl}/gglob-pay/report{query}");
                if (!response.IsSuccessStatusCode)
                {
                    GenerateReportLocal();
                    return;
                }

                var content = await response.Content.ReadAsStringAsync();
                var result = JsonSerializer.Deserialize<ApiReportResponse>(content, JsonOptions());
                if (result?.Summary is null)
                {
                    GenerateReportLocal();
                    return;
                }

                ReportTotalAmountTextBlock.Text = result.Summary.Total.ToString("C0", CultureInfo.GetCultureInfo("es-CO"));
                ReportPaymentsCountTextBlock.Text = result.Summary.Count.ToString();
                ReportAverageTextBlock.Text = result.Summary.Average.ToString("C0", CultureInfo.GetCultureInfo("es-CO"));
                ReportPaymentsDataGrid.ItemsSource = (result.Data ?? [])
                    .Select(x => x.ToDesktopRecord())
                    .OrderByDescending(x => x.VerifiedAt)
                    .ToList();
            }
            catch
            {
                GenerateReportLocal();
            }
        }

        private void RefreshQrOptions()
        {
            qrAccountOptions.Clear();
            foreach (var account in destinationAccounts)
            {
                qrAccountOptions.Add(new QrAccountOption("Cuenta de ahorro", account));
                qrAccountOptions.Add(new QrAccountOption("WOMPI tarjetas crédito", account));
            }

            if (qrAccountOptions.Count > 0)
            {
                QrAccountComboBox.SelectedIndex = 0;
            }
        }

        private static string BuildPaymentsQuery(string? from, string? to, string? cashier)
        {
            var parts = new List<string>();
            if (!string.IsNullOrWhiteSpace(from)) parts.Add($"from={Uri.EscapeDataString(from)}");
            if (!string.IsNullOrWhiteSpace(to)) parts.Add($"to={Uri.EscapeDataString(to)}");
            if (!string.IsNullOrWhiteSpace(cashier) && cashier != "Todos") parts.Add($"cashier={Uri.EscapeDataString(cashier)}");

            return parts.Count == 0 ? string.Empty : $"?{string.Join("&", parts)}";
        }

        private static JsonSerializerOptions JsonOptions() => new()
        {
            PropertyNameCaseInsensitive = true
        };

        private async void SaveDestinationAccountButton_Click(object sender, RoutedEventArgs e)
        {
            if (DestinationBankComboBox.SelectedItem is not string bank)
            {
                QrStatusTextBlock.Text = "Selecciona un banco destino válido.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                return;
            }

            var holder = DestinationHolderTextBox.Text.Trim();
            var accountNumber = DestinationAccountNumberTextBox.Text.Trim();
            var accountType = (DestinationAccountTypeComboBox.SelectedItem as ComboBoxItem)?.Content?.ToString() ?? "Ahorros";

            if (string.IsNullOrWhiteSpace(holder) || string.IsNullOrWhiteSpace(accountNumber))
            {
                QrStatusTextBlock.Text = "Completa titular y número de cuenta.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                return;
            }

            var account = await SaveDestinationAccountApi(bank, holder, accountNumber, accountType);
            if (account is null)
            {
                QrStatusTextBlock.Text = "No se pudo guardar la cuenta en app_web.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                return;
            }

            destinationAccounts.Insert(0, account);
            RefreshQrOptions();

            DestinationHolderTextBox.Text = string.Empty;
            DestinationAccountNumberTextBox.Text = string.Empty;

            QrStatusTextBlock.Text = $"Cuenta {accountType} de {bank} agregada y disponible para QR/WOMPI.";
            QrStatusTextBlock.Foreground = Brushes.DarkGreen;
        }

        private async void GenerateQrButton_Click(object sender, RoutedEventArgs e)
        {
            var accountOption = QrAccountComboBox.SelectedItem as QrAccountOption;
            if (accountOption is null)
            {
                QrStatusTextBlock.Text = "Selecciona una cuenta para generar el QR.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                return;
            }

            if (!decimal.TryParse(QrAmountTextBox.Text.Trim(), NumberStyles.Number, CultureInfo.InvariantCulture, out var amount) &&
                !decimal.TryParse(QrAmountTextBox.Text.Trim(), NumberStyles.Number, CultureInfo.GetCultureInfo("es-CO"), out amount))
            {
                QrStatusTextBlock.Text = "Precio inválido. Usa solo números.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                return;
            }

            if (amount <= 0)
            {
                QrStatusTextBlock.Text = "El precio debe ser mayor a cero.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                return;
            }

            var cashier = QrCashierComboBox.SelectedItem?.ToString() ?? "Caja Principal";
            var referenceCode = $"GGPAY-{DateTime.Now:yyyyMMdd-HHmmss}-{Guid.NewGuid().ToString()[..4].ToUpperInvariant()}";

            var payloadObject = new
            {
                reference = referenceCode,
                channel = accountOption.Channel,
                amount = decimal.Round(amount, 2),
                currency = "COP",
                cashier,
                destination_bank = accountOption.Account.Bank,
                destination_account = accountOption.Account.AccountNumber,
                destination_type = accountOption.Account.AccountType,
                verification = "instant_bank_callback"
            };

            var payload = JsonSerializer.Serialize(payloadObject, new JsonSerializerOptions
            {
                WriteIndented = true
            });

            QrPayloadTextBox.Text = payload;
            QrStatusTextBlock.Text = $"QR generado con referencia {referenceCode}. Verificación inmediata configurada para {accountOption.Account.Bank}.";
            QrStatusTextBlock.Foreground = Brushes.DarkGreen;

            var payment = new VerifiedPaymentRecord(
                referenceCode,
                "Transferencia validada",
                accountOption.Account.AccountNumber,
                amount,
                cashier,
                accountOption.Account.Bank,
                DateTime.Now);

            var stored = await SaveVerifiedPaymentApi(payment);
            if (stored is null)
            {
                QrStatusTextBlock.Foreground = Brushes.DarkOrange;
                QrStatusTextBlock.Text += " No se pudo guardar en app_web, revisa la conexión.";
                return;
            }

            verifiedPayments.Insert(0, stored);
            ApplyVerifiedFilterLocal();
            GenerateReportLocal();
        }

        private async void ApplyVerifiedFilterButton_Click(object sender, RoutedEventArgs e)
        {
            await LoadVerifiedPaymentsFromApi();
        }

        private void ApplyVerifiedFilterLocal()
        {
            var from = VerifiedFromDatePicker.SelectedDate?.Date;
            var to = VerifiedToDatePicker.SelectedDate?.Date;
            var cashier = VerifiedCashierComboBox.SelectedItem?.ToString();

            var filtered = verifiedPayments.Where(record =>
                (!from.HasValue || record.VerifiedAt.Date >= from.Value) &&
                (!to.HasValue || record.VerifiedAt.Date <= to.Value) &&
                (string.IsNullOrWhiteSpace(cashier) || cashier == "Todos" || record.Cashier == cashier))
                .OrderByDescending(record => record.VerifiedAt)
                .ToList();

            VerifiedPaymentsDataGrid.ItemsSource = filtered;
        }

        private async void GenerateReportButton_Click(object sender, RoutedEventArgs e)
        {
            await GenerateReportFromApi();
        }

        private void GenerateReportLocal()
        {
            var from = ReportFromDatePicker.SelectedDate?.Date;
            var to = ReportToDatePicker.SelectedDate?.Date;
            var cashier = ReportCashierComboBox.SelectedItem?.ToString();

            var filtered = verifiedPayments.Where(record =>
                (!from.HasValue || record.VerifiedAt.Date >= from.Value) &&
                (!to.HasValue || record.VerifiedAt.Date <= to.Value) &&
                (string.IsNullOrWhiteSpace(cashier) || cashier == "Todos" || record.Cashier == cashier))
                .OrderByDescending(record => record.VerifiedAt)
                .ToList();

            var total = filtered.Sum(x => x.Amount);
            var count = filtered.Count;
            var average = count == 0 ? 0 : total / count;

            ReportTotalAmountTextBlock.Text = total.ToString("C0", CultureInfo.GetCultureInfo("es-CO"));
            ReportPaymentsCountTextBlock.Text = count.ToString();
            ReportAverageTextBlock.Text = average.ToString("C0", CultureInfo.GetCultureInfo("es-CO"));
            ReportPaymentsDataGrid.ItemsSource = filtered;
        }

        private static List<ServiceItem> BuildServiceItems(ApiCompany? company)
        {
            return
            [
                new ServiceItem("gglob_cloud", "Gglob Cloud", "Gestión principal en la nube.", company?.GglobCloudEnabled ?? false),
                new ServiceItem("gglob_pay", "Gglob Pay", "Cobros y movimientos de pago.", company?.GglobPayEnabled ?? false),
                new ServiceItem("gglob_pos", "Gglob POS", "Punto de venta y cajas.", company?.GglobPosEnabled ?? false),
                new ServiceItem("gglob_accounting", "Gglob Contable", "Módulo de contabilidad.", company?.GglobAccountingEnabled ?? false),
            ];
        }

        private static string NormalizeBusinessRole(string? businessRole)
        {
            return businessRole?.ToLowerInvariant() switch
            {
                "owner" => "Dueño",
                "cashier" => "Cajero",
                _ => "Sin rol de negocio"
            };
        }

        private static void SaveOfflineSession(string email, string password, string accessToken, ApiUser user, List<ApiPermission>? permissions)
        {
            var saltBytes = RandomNumberGenerator.GetBytes(16);
            var hash = ComputePasswordHash(password, saltBytes);

            var model = new OfflineSession
            {
                Email = email,
                PasswordSalt = Convert.ToBase64String(saltBytes),
                PasswordHash = hash,
                AccessToken = accessToken,
                User = user,
                Permissions = permissions,
                CachedAt = DateTime.Now
            };

            var directory = Path.GetDirectoryName(SessionCachePath);
            if (!string.IsNullOrWhiteSpace(directory))
            {
                Directory.CreateDirectory(directory);
            }

            var json = JsonSerializer.Serialize(model);
            File.WriteAllText(SessionCachePath, json);
        }

        private static OfflineSession? ReadOfflineSession()
        {
            if (!File.Exists(SessionCachePath))
            {
                return null;
            }

            try
            {
                var json = File.ReadAllText(SessionCachePath);
                return JsonSerializer.Deserialize<OfflineSession>(json, new JsonSerializerOptions
                {
                    PropertyNameCaseInsensitive = true
                });
            }
            catch
            {
                return null;
            }
        }

        private static string ComputePasswordHash(string password, byte[] saltBytes)
        {
            using var sha = SHA256.Create();
            var passwordBytes = Encoding.UTF8.GetBytes(password);
            var combined = saltBytes.Concat(passwordBytes).ToArray();
            var hash = sha.ComputeHash(combined);
            return Convert.ToBase64String(hash);
        }

        private static bool VerifyPassword(string password, string? saltBase64, string? expectedHash)
        {
            if (string.IsNullOrWhiteSpace(saltBase64) || string.IsNullOrWhiteSpace(expectedHash))
            {
                return false;
            }

            var saltBytes = Convert.FromBase64String(saltBase64);
            var hash = ComputePasswordHash(password, saltBytes);
            return string.Equals(hash, expectedHash, StringComparison.Ordinal);
        }

        private void ShowStatus(string message, bool isError, bool isWarning = false)
        {
            StatusTextBlock.Text = message;
            StatusTextBlock.Foreground = isError
                ? Brushes.DarkRed
                : isWarning
                    ? Brushes.DarkOrange
                    : Brushes.DarkGreen;
        }

        private void LogoutButton_Click(object sender, RoutedEventArgs e)
        {
            DashboardRoot.Visibility = Visibility.Collapsed;
            LoginRoot.Visibility = Visibility.Visible;
            PasswordBox.Password = string.Empty;
            ShowStatus("Sesión cerrada.", isError: false);
        }

        private void Hyperlink_Click(object sender, RoutedEventArgs e)
        {
            const string url = "http://localhost:81/registro-negocio";

            Process.Start(new ProcessStartInfo
            {
                FileName = url,
                UseShellExecute = true
            });
        }
    }

    public class AuthResponse
    {
        [JsonPropertyName("access_token")]
        public string? AccessToken { get; set; }

        [JsonPropertyName("user")]
        public ApiUser? User { get; set; }

        [JsonPropertyName("permissions")]
        public List<ApiPermission>? Permissions { get; set; }
    }

    public class ApiUser
    {
        [JsonPropertyName("name")]
        public string? Name { get; set; }

        [JsonPropertyName("email")]
        public string? Email { get; set; }

        [JsonPropertyName("company_id")]
        public int? CompanyId { get; set; }

        [JsonPropertyName("business_role")]
        public string? BusinessRole { get; set; }

        [JsonPropertyName("roles")]
        public List<ApiRole>? Roles { get; set; }

        [JsonPropertyName("company")]
        public ApiCompany? Company { get; set; }
    }

    public class ApiRole
    {
        [JsonPropertyName("name")]
        public string? Name { get; set; }
    }

    public class ApiPermission
    {
        [JsonPropertyName("name")]
        public string? Name { get; set; }
    }

    public class ApiCompany
    {
        [JsonPropertyName("name")]
        public string? Name { get; set; }

        [JsonPropertyName("nit")]
        public string? Nit { get; set; }

        [JsonPropertyName("plan_name")]
        public string? PlanName { get; set; }

        [JsonPropertyName("service_status")]
        public string? ServiceStatus { get; set; }

        [JsonPropertyName("plan_id")]
        public int? PlanId { get; set; }

        [JsonPropertyName("started_at")]
        public string? StartedAt { get; set; }

        [JsonPropertyName("active_until")]
        public string? ActiveUntil { get; set; }

        [JsonPropertyName("gglob_cloud_enabled")]
        public bool GglobCloudEnabled { get; set; }

        [JsonPropertyName("gglob_pay_enabled")]
        public bool GglobPayEnabled { get; set; }

        [JsonPropertyName("gglob_pos_enabled")]
        public bool GglobPosEnabled { get; set; }

        [JsonPropertyName("gglob_accounting_enabled")]
        public bool GglobAccountingEnabled { get; set; }
    }

    public class OfflineSession
    {
        public string? Email { get; set; }
        public string? PasswordSalt { get; set; }
        public string? PasswordHash { get; set; }
        public string? AccessToken { get; set; }
        public ApiUser? User { get; set; }
        public List<ApiPermission>? Permissions { get; set; }
        public DateTime CachedAt { get; set; }
    }

    public class ServiceItem(string key, string name, string description, bool isActive)
    {
        public string Key { get; } = key;
        public string Name { get; } = name;
        public string Description { get; } = description;
        public bool IsActive { get; } = isActive;
    }

    public class ApiListResponse<T>
    {
        [JsonPropertyName("data")]
        public List<T>? Data { get; set; }
    }

    public class ApiSingleResponse<T>
    {
        [JsonPropertyName("data")]
        public T? Data { get; set; }
    }

    public class ApiReportSummary
    {
        [JsonPropertyName("total")]
        public decimal Total { get; set; }

        [JsonPropertyName("count")]
        public int Count { get; set; }

        [JsonPropertyName("average")]
        public decimal Average { get; set; }
    }

    public class ApiReportResponse
    {
        [JsonPropertyName("data")]
        public List<ApiVerifiedPayment>? Data { get; set; }

        [JsonPropertyName("summary")]
        public ApiReportSummary? Summary { get; set; }
    }

    public class ApiDestinationAccount
    {
        [JsonPropertyName("bank")]
        public string? Bank { get; set; }

        [JsonPropertyName("holder_name")]
        public string? HolderName { get; set; }

        [JsonPropertyName("account_number")]
        public string? AccountNumber { get; set; }

        [JsonPropertyName("account_type")]
        public string? AccountType { get; set; }
    }

    public class ApiVerifiedPayment
    {
        [JsonPropertyName("reference_code")]
        public string? ReferenceCode { get; set; }

        [JsonPropertyName("sender_name")]
        public string? SenderName { get; set; }

        [JsonPropertyName("account_number")]
        public string? AccountNumber { get; set; }

        [JsonPropertyName("amount")]
        public decimal Amount { get; set; }

        [JsonPropertyName("cashier")]
        public string? Cashier { get; set; }

        [JsonPropertyName("bank")]
        public string? Bank { get; set; }

        [JsonPropertyName("verified_at")]
        public string? VerifiedAt { get; set; }

        public VerifiedPaymentRecord ToDesktopRecord()
        {
            _ = DateTime.TryParse(VerifiedAt, out var verifiedAt);
            return new VerifiedPaymentRecord(
                ReferenceCode ?? string.Empty,
                SenderName ?? string.Empty,
                AccountNumber ?? string.Empty,
                Amount,
                Cashier ?? string.Empty,
                Bank ?? string.Empty,
                verifiedAt == default ? DateTime.Now : verifiedAt);
        }
    }

    public class DestinationAccount(string bank, string holderName, string accountNumber, string accountType)
    {
        public string Bank { get; } = bank;
        public string HolderName { get; } = holderName;
        public string AccountNumber { get; } = accountNumber;
        public string AccountType { get; } = accountType;

        public override string ToString() => $"{Bank} - {AccountType} - {AccountNumber} ({HolderName})";
    }

    public class QrAccountOption(string channel, DestinationAccount account)
    {
        public string Channel { get; } = channel;
        public DestinationAccount Account { get; } = account;
        public string DisplayName => $"{channel} | {account.Bank} {account.AccountType} {account.AccountNumber}";
    }

    public class VerifiedPaymentRecord(string referenceCode, string senderName, string accountNumber, decimal amount, string cashier, string bank, DateTime verifiedAt)
    {
        public string ReferenceCode { get; } = referenceCode;
        public string SenderName { get; } = senderName;
        public string AccountNumber { get; } = accountNumber;
        public decimal Amount { get; } = amount;
        public string Cashier { get; } = cashier;
        public string Bank { get; } = bank;
        public DateTime VerifiedAt { get; } = verifiedAt;

        public string AmountFormatted => Amount.ToString("C0", CultureInfo.GetCultureInfo("es-CO"));
        public string VerifiedAtFormatted => VerifiedAt.ToString("HH:mm:ss");
        public string VerifiedAtFullFormatted => VerifiedAt.ToString("yyyy-MM-dd HH:mm");
    }

    public record AccessValidation(bool IsValid, string Message);
}
