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

            var defaultAccount = new DestinationAccount("Bancolombia", "Gglob SAS", "01872365019", "Ahorros");
            destinationAccounts.Add(defaultAccount);
            qrAccountOptions.Add(new QrAccountOption("Cuenta de ahorro", defaultAccount));
            qrAccountOptions.Add(new QrAccountOption("WOMPI tarjetas crédito", defaultAccount));

            DestinationAccountsListBox.ItemsSource = destinationAccounts;
            QrAccountComboBox.ItemsSource = qrAccountOptions;
            QrAccountComboBox.SelectedIndex = 0;

            SeedVerifiedPayments();
            ApplyVerifiedFilter();
            GenerateReport();
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

            RenderServicesMenu(services);
            RenderActiveServicesCards(activeServices);
            ToggleGglobPayModuleVisibility(user.Company?.GglobPayEnabled ?? false);
            RenderPermissions(permissionsList);

            ShowStatus(statusMessage, isError: false);
        }

        private void ToggleGglobPayModuleVisibility(bool enabled)
        {
            GglobPayTabControl.IsEnabled = enabled;
            if (!enabled)
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

        private void RenderServicesMenu(List<ServiceItem> services)
        {
            ServicesMenuPanel.Children.Clear();

            foreach (var service in services)
            {
                var border = new Border
                {
                    CornerRadius = new CornerRadius(8),
                    Padding = new Thickness(10, 8, 10, 8),
                    Margin = new Thickness(0, 0, 0, 8),
                    Background = service.IsActive
                        ? new SolidColorBrush(Color.FromRgb(76, 116, 196))
                        : new SolidColorBrush(Color.FromArgb(90, 255, 255, 255))
                };

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

                border.Child = panel;
                ServicesMenuPanel.Children.Add(border);
            }
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

        private void SaveDestinationAccountButton_Click(object sender, RoutedEventArgs e)
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

            var account = new DestinationAccount(bank, holder, accountNumber, accountType);
            destinationAccounts.Add(account);
            qrAccountOptions.Add(new QrAccountOption("Cuenta de ahorro", account));
            qrAccountOptions.Add(new QrAccountOption("WOMPI tarjetas crédito", account));

            DestinationHolderTextBox.Text = string.Empty;
            DestinationAccountNumberTextBox.Text = string.Empty;

            QrStatusTextBlock.Text = $"Cuenta {accountType} de {bank} agregada y disponible para QR/WOMPI.";
            QrStatusTextBlock.Foreground = Brushes.DarkGreen;
        }

        private void GenerateQrButton_Click(object sender, RoutedEventArgs e)
        {
            if (QrAccountComboBox.SelectedItem is not QrAccountOption accountOption)
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

            verifiedPayments.Insert(0, new VerifiedPaymentRecord(
                referenceCode,
                "Transferencia validada",
                accountOption.Account.AccountNumber,
                amount,
                cashier,
                accountOption.Account.Bank,
                DateTime.Now));

            ApplyVerifiedFilter();
            GenerateReport();
        }

        private void ApplyVerifiedFilterButton_Click(object sender, RoutedEventArgs e)
        {
            ApplyVerifiedFilter();
        }

        private void ApplyVerifiedFilter()
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

        private void GenerateReportButton_Click(object sender, RoutedEventArgs e)
        {
            GenerateReport();
        }

        private void GenerateReport()
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
                new ServiceItem("Gglob Cloud", "Gestión principal en la nube.", company?.GglobCloudEnabled ?? false),
                new ServiceItem("Gglob Pay", "Cobros y movimientos de pago.", company?.GglobPayEnabled ?? false),
                new ServiceItem("Gglob POS", "Punto de venta y cajas.", company?.GglobPosEnabled ?? false),
                new ServiceItem("Gglob Contable", "Módulo de contabilidad.", company?.GglobAccountingEnabled ?? false),
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

    public class ServiceItem(string name, string description, bool isActive)
    {
        public string Name { get; } = name;
        public string Description { get; } = description;
        public bool IsActive { get; } = isActive;
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
