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
using System.Windows.Media.Imaging;
using QRCoder;

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
        private readonly ObservableCollection<CashRegisterOption> cashRegisterOptions = [];
        private readonly ObservableCollection<CashRegisterOption> cashRegisterManagementOptions = [];
        private readonly ObservableCollection<CashierOption> cashierOptions = [];
        private readonly ObservableCollection<BusinessCashierItem> businessCashiers = [];
        private readonly ObservableCollection<ProductCategoryItem> productCategories = [];
        private int? editingCashierId;
        private int? editingCategoryId;
        private ApiUser? currentUser;
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
                "Bancolombia"
            };
            DestinationBankComboBox.SelectedIndex = 0;

            QrCashierComboBox.ItemsSource = new[] { "Sin caja asignada" };
            QrCashierComboBox.SelectedIndex = 0;

            var cashierFilter = new[] { "Todos" };
            VerifiedCashierComboBox.ItemsSource = cashierFilter;
            ReportCashierComboBox.ItemsSource = cashierFilter;
            VerifiedCashierComboBox.SelectedIndex = 0;
            ReportCashierComboBox.SelectedIndex = 0;

            DestinationAccountsListBox.ItemsSource = destinationAccounts;
            QrAccountComboBox.ItemsSource = qrAccountOptions;
            CashRegistersDataGrid.ItemsSource = cashRegisterManagementOptions;
            CashiersManagementDataGrid.ItemsSource = businessCashiers;
            ProductCategoriesDataGrid.ItemsSource = productCategories;
            ResetCashierForm();
            ResetCategoryForm();

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
                DateTime.Now.AddHours(-2),
                1,
                1,
                "Caja Principal",
                "ahorros",
                0,
                "APPROVED"));
            verifiedPayments.Add(new VerifiedPaymentRecord(
                "GGPAY-20260614-084519-F4K8",
                "Julián Torres",
                "45782019431",
                115000m,
                "Cajero Ana",
                "Davivienda",
                DateTime.Now.AddHours(-1),
                1,
                1,
                "Caja Principal",
                "wompi_credit_card",
                null,
                "PENDING"));

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
                var payload = JsonSerializer.Serialize(new { email, password, app_context = "desk" });
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
            var isAdmin = IsAdmin(user);
            if (normalizedRole is not ("owner" or "cashier") && !isAdmin)
            {
                return new AccessValidation(false, "Rol no permitido para Desk. Solo Dueño, Cajero o Administrador.");
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

        private static bool IsOwner(ApiUser user)
        {
            return string.Equals(user.BusinessRole, "owner", StringComparison.OrdinalIgnoreCase);
        }

        private static bool IsAdmin(ApiUser user)
        {
            var roles = user.Roles ?? [];
            return roles.Any(r => string.Equals(r.Name, "admin", StringComparison.OrdinalIgnoreCase)
                || string.Equals(r.Name, "Administrador", StringComparison.OrdinalIgnoreCase));
        }

        private void ShowDashboard(ApiUser user, List<ApiPermission>? permissionsList, string statusMessage)
        {
            LoginRoot.Visibility = Visibility.Collapsed;
            DashboardRoot.Visibility = Visibility.Visible;

            currentUser = user;

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
            ApplyModulesVisibilityByRole();
            isGglobPayEnabled = user.Company?.GglobPayEnabled ?? false;
            ToggleGglobPayModuleAvailability();
            RenderPermissions(permissionsList);
            BindCashiersAndCashRegisters(user);
            ApplyConfigurationAccess(user);

            _ = LoadGglobPayDataFromApi();
            _ = LoadProviderSettingsFromApi();
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
            ApplyModulesVisibilityByRole(moduleKey);
            DefaultPanel.Visibility = Visibility.Visible;
            GglobPosPanel.Visibility = Visibility.Collapsed;
            ProductCategoriesPanel.Visibility = Visibility.Collapsed;
            GglobPayPanel.Visibility = Visibility.Collapsed;
            CashRegistersPanel.Visibility = Visibility.Collapsed;
            CashiersManagementPanel.Visibility = Visibility.Collapsed;

            if (moduleKey == "gglob_pos")
            {
                DefaultPanel.Visibility = Visibility.Collapsed;
                GglobPosPanel.Visibility = Visibility.Visible;
                return;
            }

            if (moduleKey == "product_categories")
            {
                DefaultPanel.Visibility = Visibility.Collapsed;
                ProductCategoriesPanel.Visibility = Visibility.Visible;
                _ = LoadProductCategoriesFromApi();
                return;
            }

            if (moduleKey == "gglob_pay")
            {
                if (!isGglobPayEnabled)
                {
                    QrStatusTextBlock.Foreground = Brushes.DarkOrange;
                    QrStatusTextBlock.Text = "El servicio Gglob Pay está inactivo para esta empresa.";
                    return;
                }

                DefaultPanel.Visibility = Visibility.Collapsed;
                GglobPayPanel.Visibility = Visibility.Visible;
                return;
            }

            if (moduleKey == "cash_register_management")
            {
                if (currentUser is null || !IsOwner(currentUser))
                {
                    QrStatusTextBlock.Text = "Solo el dueño del negocio puede acceder a gestión de cajas.";
                    QrStatusTextBlock.Foreground = Brushes.DarkOrange;
                    return;
                }

                DefaultPanel.Visibility = Visibility.Collapsed;
                CashRegistersPanel.Visibility = Visibility.Visible;
                return;
            }

            if (moduleKey == "cashier_management")
            {
                if (currentUser is null || !IsOwner(currentUser))
                {
                    QrStatusTextBlock.Text = "Solo el dueño puede gestionar usuarios cajeros.";
                    QrStatusTextBlock.Foreground = Brushes.DarkOrange;
                    return;
                }

                DefaultPanel.Visibility = Visibility.Collapsed;
                CashiersManagementPanel.Visibility = Visibility.Visible;
                _ = LoadBusinessCashiersFromApi();
                return;
            }
        }


        private void ApplyModulesVisibilityByRole(string? moduleKey = null)
        {
            if (currentUser is null)
            {
                AvailableModulesPanel.Visibility = Visibility.Visible;
                return;
            }

            var role = currentUser.BusinessRole?.Trim().ToLowerInvariant();
            var hideByRole = role is "cashier";
            var hideByModule = moduleKey is "gglob_pay" or "gglob_pos" or "product_categories" or "cash_register_management" or "cashier_management";
            AvailableModulesPanel.Visibility = (hideByRole || hideByModule) ? Visibility.Collapsed : Visibility.Visible;
        }

        private void RenderServicesMenu(List<ServiceItem> services)
        {
            ServicesMenuPanel.Children.Clear();

            var adminKeys = new HashSet<string>(StringComparer.OrdinalIgnoreCase)
            {
                "cash_register_management",
                "cashier_management"
            };

            var standardServices = services.Where(service => !adminKeys.Contains(service.Key)).ToList();
            foreach (var service in standardServices)
            {
                ServicesMenuPanel.Children.Add(CreateServiceMenuButton(service));
            }

            if (currentUser is not null && IsOwner(currentUser))
            {
                var adminServices = services.Where(service => adminKeys.Contains(service.Key)).ToList();
                if (adminServices.Count > 0)
                {
                    var adminItemsPanel = new StackPanel { Margin = new Thickness(0, 6, 0, 6) };
                    foreach (var service in adminServices)
                    {
                        var childButton = CreateServiceMenuButton(service);
                        childButton.Margin = new Thickness(12, 0, 0, 8);
                        adminItemsPanel.Children.Add(childButton);
                    }

                    var adminExpander = new Expander
                    {
                        Foreground = Brushes.White,
                        Background = new SolidColorBrush(Color.FromRgb(59, 100, 180)),
                        BorderBrush = Brushes.Transparent,
                        BorderThickness = new Thickness(0),
                        IsExpanded = false,
                        Content = adminItemsPanel,
                        Header = new TextBlock
                        {
                            Text = "Administración",
                            Foreground = Brushes.White,
                            FontWeight = FontWeights.SemiBold,
                            Margin = new Thickness(10, 8, 10, 8)
                        }
                    };

                    var adminContainer = new Border
                    {
                        CornerRadius = new CornerRadius(10),
                        Margin = new Thickness(0, 0, 0, 8),
                        Background = new SolidColorBrush(Color.FromRgb(59, 100, 180)),
                        BorderBrush = new SolidColorBrush(Color.FromArgb(90, 255, 255, 255)),
                        BorderThickness = new Thickness(1),
                        Child = adminExpander
                    };

                    ServicesMenuPanel.Children.Add(adminContainer);
                }
            }
        }

        private Button CreateServiceMenuButton(ServiceItem service)
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

            button.Click += OnServiceMenuClick;

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
            return button;
        }


        private void OnServiceMenuClick(object sender, RoutedEventArgs e)
        {
            if (sender is Button clickedButton && clickedButton.Tag is string moduleKey)
            {
                SetSelectedModule(moduleKey);
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

        private async Task LoadGglobPayDataFromApi()
        {
            await LoadCashRegistersFromApi("assigned");
            await LoadCashRegistersFromApi("all");
            cashierOptions.Clear();
            if (currentUser is not null && IsOwner(currentUser))
            {
                await LoadCashiersFromApi();
            }

            var loadedAccounts = await LoadDestinationAccountsFromApi();
            if (!loadedAccounts)
            {
                if (destinationAccounts.Count == 0)
                {
                    destinationAccounts.Add(new DestinationAccount(0, "Bancolombia", "Gglob SAS", "01872365019", "Ahorros"));
                }

                SeedVerifiedPayments();
                ApplyVerifiedFilterLocal();
                GenerateReportLocal();
                return;
            }

            await LoadVerifiedPaymentsFromApi();
            await GenerateReportFromApi();
        }

        private async Task<bool> LoadCashRegistersFromApi(string scope)
        {
            try
            {
                using var response = await HttpClient.GetAsync($"{ApiBaseUrl}/gglob-pay/cash-registers?scope={Uri.EscapeDataString(scope)}");
                if (!response.IsSuccessStatusCode)
                {
                    return false;
                }

                var content = await response.Content.ReadAsStringAsync();
                var result = JsonSerializer.Deserialize<ApiListResponse<ApiCashRegister>>(content, JsonOptions());
                if (result?.Data is null)
                {
                    return false;
                }

                var target = scope == "all" ? cashRegisterManagementOptions : cashRegisterOptions;
                target.Clear();

                foreach (var register in result.Data)
                {
                    if (register.Id is null)
                    {
                        continue;
                    }

                    target.Add(new CashRegisterOption(
                        register.Id.Value,
                        register.Name ?? "Caja",
                        register.Code ?? string.Empty,
                        register.Status ?? "active",
                        register.IsPrimary == 1));
                }

                if (scope != "all")
                {
                    if (cashRegisterOptions.Count == 0)
                    {
                        QrCashierComboBox.ItemsSource = new[] { "Sin caja asignada" };
                        QrCashierComboBox.SelectedIndex = 0;
                    }
                    else
                    {
                        QrCashierComboBox.ItemsSource = cashRegisterOptions;
                        var primaryIndex = cashRegisterOptions.ToList().FindIndex(x => x.IsPrimary);
                        QrCashierComboBox.SelectedIndex = primaryIndex >= 0 ? primaryIndex : 0;
                    }
                }

                CashRegistersDataGrid.ItemsSource = null;
                CashRegistersDataGrid.ItemsSource = cashRegisterManagementOptions;
                return true;
            }
            catch
            {
                return false;
            }
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
                        account.Id ?? 0,
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

                return new DestinationAccount(data.Id ?? 0, data.Bank ?? bank, data.HolderName ?? holder, data.AccountNumber ?? accountNumber, data.AccountType ?? accountType);
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
                    cash_register_id = payment.CashRegisterId,
                    cashier_user_id = payment.CashierUserId,
                    source_channel = payment.SourceChannel,
                    destination_account_id = payment.DestinationAccountId,
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

        private async Task<int> VerifyPendingWompiPaymentsApi()
        {
            try
            {
                using var response = await HttpClient.PostAsync($"{ApiBaseUrl}/gglob-pay/payments/verify-pending-wompi", new StringContent("{}", Encoding.UTF8, "application/json"));
                if (!response.IsSuccessStatusCode)
                {
                    return -1;
                }

                var content = await response.Content.ReadAsStringAsync();
                var result = JsonSerializer.Deserialize<ApiManualVerificationResponse>(content, JsonOptions());
                return result?.Updated ?? 0;
            }
            catch
            {
                return -1;
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
                if (string.Equals(account.Bank, "Bancolombia", StringComparison.OrdinalIgnoreCase))
                {
                    qrAccountOptions.Add(new QrAccountOption("bancolombia_ahorros", "Bancolombia - Ahorros", account));
                }
            }

            qrAccountOptions.Add(new QrAccountOption("wompi_credit_card", "Wompi - Tarjeta de Crédito", null));

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

        private void OpenCategoriesButton_Click(object sender, RoutedEventArgs e)
        {
            SetSelectedModule("product_categories");
        }

        private void BackToPosButton_Click(object sender, RoutedEventArgs e)
        {
            SetSelectedModule("gglob_pos");
        }

        private async void ReloadCategoriesButton_Click(object sender, RoutedEventArgs e)
        {
            await LoadProductCategoriesFromApi();
        }

        private async void SaveCategoryButton_Click(object sender, RoutedEventArgs e)
        {
            var name = CategoryNameTextBox.Text.Trim();
            var description = CategoryDescriptionTextBox.Text.Trim();
            var isActive = CategoryActiveCheckBox.IsChecked ?? true;

            if (string.IsNullOrWhiteSpace(name))
            {
                ShowAlert("El nombre de la categoría es obligatorio.");
                return;
            }

            var payload = JsonSerializer.Serialize(new
            {
                name,
                description,
                is_active = isActive
            });

            try
            {
                SetLoading(true);
                using var content = new StringContent(payload, Encoding.UTF8, "application/json");
                using var response = editingCategoryId.HasValue
                    ? await HttpClient.PutAsync($"{ApiBaseUrl}/product-categories/{editingCategoryId.Value}", content)
                    : await HttpClient.PostAsync($"{ApiBaseUrl}/product-categories", content);

                if (!response.IsSuccessStatusCode)
                {
                    var body = await response.Content.ReadAsStringAsync();
                    ShowAlert($"No fue posible guardar la categoría. {body}");
                    return;
                }

                var wasEditing = editingCategoryId.HasValue;
                await LoadProductCategoriesFromApi();
                ResetCategoryForm();
                QrStatusTextBlock.Text = wasEditing
                    ? "Categoría actualizada correctamente."
                    : "Categoría creada correctamente.";
                QrStatusTextBlock.Foreground = Brushes.DarkGreen;
            }
            catch (Exception ex)
            {
                ShowAlert($"Error al guardar categoría: {ex.Message}");
            }
            finally
            {
                SetLoading(false);
            }
        }

        private void CancelCategoryEditButton_Click(object sender, RoutedEventArgs e)
        {
            ResetCategoryForm();
        }

        private void EditCategoryRowButton_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not Button { Tag: ProductCategoryItem selected })
            {
                return;
            }

            editingCategoryId = selected.Id;
            CategoryFormTitleTextBlock.Text = "Editar categoría";
            CategoryNameTextBox.Text = selected.Name;
            CategoryDescriptionTextBox.Text = selected.Description;
            CategoryActiveCheckBox.IsChecked = selected.IsActive;
            SaveCategoryButton.Content = "💾 Actualizar categoría";
            CancelCategoryEditButton.Visibility = Visibility.Visible;
        }

        private async void DeleteCategoryRowButton_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not Button { Tag: ProductCategoryItem selected })
            {
                return;
            }

            var confirm = MessageBox.Show(
                $"¿Seguro que deseas eliminar la categoría '{selected.Name}'?",
                "Confirmar eliminación",
                MessageBoxButton.YesNo,
                MessageBoxImage.Warning);

            if (confirm != MessageBoxResult.Yes)
            {
                return;
            }

            try
            {
                SetLoading(true);
                using var response = await HttpClient.DeleteAsync($"{ApiBaseUrl}/product-categories/{selected.Id}");
                if (!response.IsSuccessStatusCode)
                {
                    ShowAlert("No se pudo eliminar la categoría en app_web.");
                    return;
                }

                await LoadProductCategoriesFromApi();
                ResetCategoryForm();
                QrStatusTextBlock.Text = "Categoría eliminada correctamente.";
                QrStatusTextBlock.Foreground = Brushes.DarkGreen;
            }
            catch (Exception ex)
            {
                ShowAlert($"Error al eliminar categoría: {ex.Message}");
            }
            finally
            {
                SetLoading(false);
            }
        }

        private async Task LoadProductCategoriesFromApi()
        {
            try
            {
                using var response = await HttpClient.GetAsync($"{ApiBaseUrl}/product-categories");
                if (!response.IsSuccessStatusCode)
                {
                    QrStatusTextBlock.Text = "No se pudieron cargar las categorías del negocio.";
                    QrStatusTextBlock.Foreground = Brushes.DarkOrange;
                    return;
                }

                var body = await response.Content.ReadAsStringAsync();
                var result = JsonSerializer.Deserialize<ApiListResponse<ApiProductCategory>>(body, JsonOptions());

                productCategories.Clear();
                foreach (var item in result?.Data ?? [])
                {
                    if (item.Id is null || string.IsNullOrWhiteSpace(item.Name))
                    {
                        continue;
                    }

                    productCategories.Add(new ProductCategoryItem(
                        item.Id.Value,
                        item.Name,
                        item.Description ?? string.Empty,
                        item.IsActive));
                }
            }
            catch (Exception ex)
            {
                QrStatusTextBlock.Text = $"Error cargando categorías: {ex.Message}";
                QrStatusTextBlock.Foreground = Brushes.DarkOrange;
            }
        }

        private void ResetCategoryForm()
        {
            editingCategoryId = null;
            CategoryFormTitleTextBlock.Text = "Nueva categoría";
            CategoryNameTextBox.Text = string.Empty;
            CategoryDescriptionTextBox.Text = string.Empty;
            CategoryActiveCheckBox.IsChecked = true;
            SaveCategoryButton.Content = "💾 Guardar categoría";
            CancelCategoryEditButton.Visibility = Visibility.Collapsed;
        }

        private static JsonSerializerOptions JsonOptions() => new()
        {
            PropertyNameCaseInsensitive = true,
            NumberHandling = JsonNumberHandling.AllowReadingFromString
        };

        private async void SaveDestinationAccountButton_Click(object sender, RoutedEventArgs e)
        {
            if (currentUser is null || !IsOwner(currentUser))
            {
                QrStatusTextBlock.Text = "Solo el dueño puede registrar cuentas destino de Bancolombia.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                ShowAlert(QrStatusTextBlock.Text);
                return;
            }

            if (DestinationBankComboBox.SelectedItem is not string bank || !string.Equals(bank, "Bancolombia", StringComparison.OrdinalIgnoreCase))
            {
                QrStatusTextBlock.Text = "Solo se permite configurar cuentas destino de Bancolombia.";
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

            SetLoading(true);
            var account = await SaveDestinationAccountApi(bank, holder, accountNumber, accountType);
            SetLoading(false);
            if (account is null)
            {
                QrStatusTextBlock.Text = "No se pudo guardar la cuenta en app_web.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                ShowAlert(QrStatusTextBlock.Text);
                return;
            }

            destinationAccounts.Insert(0, account);
            RefreshQrOptions();

            DestinationHolderTextBox.Text = string.Empty;
            DestinationAccountNumberTextBox.Text = string.Empty;

            QrStatusTextBlock.Text = $"Cuenta {accountType} de {bank} agregada y disponible para QR bancario.";
            QrStatusTextBlock.Foreground = Brushes.DarkGreen;
            ShowAlert(QrStatusTextBlock.Text);
        }

        private void ApplyConfigurationAccess(ApiUser user)
        {
            var isOwner = IsOwner(user);
            SaveWompiSettingsButton.IsEnabled = isOwner;
            SaveBancolombiaSettingsButton.IsEnabled = isOwner;
            SaveBancolombiaDestinationButton.IsEnabled = isOwner;
            CreateCashRegisterButton.IsEnabled = isOwner;

            WompiConfigTab.Visibility = isOwner ? Visibility.Visible : Visibility.Collapsed;
            BancolombiaConfigTab.Visibility = isOwner ? Visibility.Visible : Visibility.Collapsed;
        }

        private async Task LoadProviderSettingsFromApi()
        {
            if (currentUser is null || !IsOwner(currentUser))
            {
                WompiPublicKeyTextBox.Text = string.Empty;
                WompiPrivateKeyTextBox.Text = string.Empty;
                WompiEventsSecretTextBox.Text = string.Empty;
                BancolombiaBaseUrlTextBox.Text = string.Empty;
                BancolombiaClientIdTextBox.Text = string.Empty;
                BancolombiaClientSecretTextBox.Text = string.Empty;
                return;
            }

            await LoadWompiSettingsFromApi();
            await LoadBancolombiaSettingsFromApi();
        }

        private async Task LoadWompiSettingsFromApi()
        {
            var data = await GetProviderSettings("wompi");
            if (data is null)
            {
                return;
            }

            WompiPublicKeyTextBox.Text = data.PublicKey ?? string.Empty;
            WompiPrivateKeyTextBox.Text = data.PrivateKey ?? string.Empty;
            WompiEventsSecretTextBox.Text = data.EventsSecret ?? string.Empty;
        }

        private async Task LoadBancolombiaSettingsFromApi()
        {
            var data = await GetProviderSettings("bancolombia");
            if (data is null)
            {
                return;
            }

            BancolombiaBaseUrlTextBox.Text = data.BaseUrl ?? string.Empty;
            BancolombiaClientIdTextBox.Text = data.ClientId ?? string.Empty;
            BancolombiaClientSecretTextBox.Text = data.ClientSecret ?? string.Empty;
        }

        private async Task<ApiProviderSettingsResponse?> GetProviderSettings(string provider)
        {
            try
            {
                using var response = await HttpClient.GetAsync($"{ApiBaseUrl}/gglob-pay/provider-settings/{provider}");
                if (!response.IsSuccessStatusCode)
                {
                    return null;
                }

                var body = await response.Content.ReadAsStringAsync();
                return JsonSerializer.Deserialize<ApiProviderSettingsResponse>(body, JsonOptions());
            }
            catch
            {
                return null;
            }
        }

        private async Task<bool> SaveProviderSettings(string provider, object payload)
        {
            try
            {
                var json = JsonSerializer.Serialize(payload);
                using var content = new StringContent(json, Encoding.UTF8, "application/json");
                using var response = await HttpClient.PostAsync($"{ApiBaseUrl}/gglob-pay/provider-settings/{provider}", content);
                return response.IsSuccessStatusCode;
            }
            catch
            {
                return false;
            }
        }

        private async void SaveWompiSettingsButton_Click(object sender, RoutedEventArgs e)
        {
            if (currentUser is null || !IsOwner(currentUser))
            {
                QrStatusTextBlock.Text = "Solo el dueño puede configurar Wompi.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                return;
            }

            SetLoading(true);
            var ok = await SaveProviderSettings("wompi", new
            {
                public_key = WompiPublicKeyTextBox.Text.Trim(),
                private_key = WompiPrivateKeyTextBox.Text.Trim(),
                events_secret = WompiEventsSecretTextBox.Text.Trim(),
            });
            SetLoading(false);

            QrStatusTextBlock.Text = ok ? "Llaves de Wompi guardadas correctamente." : "No se pudieron guardar las llaves de Wompi.";
            QrStatusTextBlock.Foreground = ok ? Brushes.DarkGreen : Brushes.DarkRed;
            ShowAlert(QrStatusTextBlock.Text);
        }

        private async void SaveBancolombiaSettingsButton_Click(object sender, RoutedEventArgs e)
        {
            if (currentUser is null || !IsOwner(currentUser))
            {
                QrStatusTextBlock.Text = "Solo el dueño puede parametrizar Bancolombia API.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                return;
            }

            SetLoading(true);
            var ok = await SaveProviderSettings("bancolombia", new
            {
                base_url = BancolombiaBaseUrlTextBox.Text.Trim(),
                client_id = BancolombiaClientIdTextBox.Text.Trim(),
                client_secret = BancolombiaClientSecretTextBox.Text.Trim(),
            });
            SetLoading(false);

            QrStatusTextBlock.Text = ok ? "Parámetros de Bancolombia guardados correctamente." : "No se pudieron guardar los parámetros de Bancolombia.";
            QrStatusTextBlock.Foreground = ok ? Brushes.DarkGreen : Brushes.DarkRed;
            ShowAlert(QrStatusTextBlock.Text);
        }

        private async Task<bool> SaveCashRegisterApi(string name, string code, string status)
        {
            try
            {
                var payload = JsonSerializer.Serialize(new
                {
                    name,
                    code,
                    status,
                });

                using var content = new StringContent(payload, Encoding.UTF8, "application/json");
                using var response = await HttpClient.PostAsync($"{ApiBaseUrl}/gglob-pay/cash-registers", content);
                return response.IsSuccessStatusCode;
            }
            catch
            {
                return false;
            }
        }

        private async Task<bool> UpdateCashRegisterApi(int cashRegisterId, string name, string code, string status)
        {
            try
            {
                var payload = JsonSerializer.Serialize(new
                {
                    name,
                    code,
                    status,
                });

                using var content = new StringContent(payload, Encoding.UTF8, "application/json");
                using var response = await HttpClient.PutAsync($"{ApiBaseUrl}/gglob-pay/cash-registers/{cashRegisterId}", content);
                return response.IsSuccessStatusCode;
            }
            catch
            {
                return false;
            }
        }

        private async Task<bool> DeleteCashRegisterApi(int cashRegisterId)
        {
            try
            {
                using var response = await HttpClient.DeleteAsync($"{ApiBaseUrl}/gglob-pay/cash-registers/{cashRegisterId}");
                return response.IsSuccessStatusCode;
            }
            catch
            {
                return false;
            }
        }

        private async Task<bool> AssignCashRegisterApi(int cashRegisterId, int cashierId)
        {
            try
            {
                var payload = JsonSerializer.Serialize(new { user_id = cashierId, is_primary = true });
                using var content = new StringContent(payload, Encoding.UTF8, "application/json");
                using var response = await HttpClient.PostAsync($"{ApiBaseUrl}/gglob-pay/cash-registers/{cashRegisterId}/assign-user", content);
                return response.IsSuccessStatusCode;
            }
            catch
            {
                return false;
            }
        }

        private async void CreateCashRegisterButton_Click(object sender, RoutedEventArgs e)
        {
            if (currentUser is null || !IsOwner(currentUser))
            {
                QrStatusTextBlock.Text = "Solo el dueño puede crear cajas.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                return;
            }

            var form = ShowCashRegisterForm("Crear caja");
            if (form is null)
            {
                return;
            }

            SetLoading(true);
            var ok = await SaveCashRegisterApi(form.Name, form.Code, form.Status);
            SetLoading(false);
            if (!ok)
            {
                QrStatusTextBlock.Text = "No se pudo guardar la caja.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                ShowAlert(QrStatusTextBlock.Text);
                return;
            }

            await LoadCashRegistersFromApi("all");
            await LoadCashRegistersFromApi("assigned");
            QrStatusTextBlock.Text = "Caja guardada correctamente.";
            QrStatusTextBlock.Foreground = Brushes.DarkGreen;
            ShowAlert(QrStatusTextBlock.Text);
        }

        private async void EditCashRegisterRowButton_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not Button { Tag: CashRegisterOption selected })
            {
                return;
            }

            if (currentUser is null || !IsOwner(currentUser))
            {
                QrStatusTextBlock.Text = "Solo el dueño puede editar cajas.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                return;
            }

            var form = ShowCashRegisterForm("Editar caja", selected);
            if (form is null)
            {
                return;
            }

            SetLoading(true);
            var ok = await UpdateCashRegisterApi(selected.Id, form.Name, form.Code, form.Status);
            SetLoading(false);
            if (!ok)
            {
                QrStatusTextBlock.Text = "No se pudo editar la caja.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                ShowAlert(QrStatusTextBlock.Text);
                return;
            }

            await LoadCashRegistersFromApi("all");
            await LoadCashRegistersFromApi("assigned");
            QrStatusTextBlock.Text = "Caja editada correctamente.";
            QrStatusTextBlock.Foreground = Brushes.DarkGreen;
            ShowAlert(QrStatusTextBlock.Text);
        }

        private async void DeleteCashRegisterRowButton_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not Button { Tag: CashRegisterOption selected })
            {
                return;
            }

            if (currentUser is null || !IsOwner(currentUser))
            {
                QrStatusTextBlock.Text = "Solo el dueño puede eliminar cajas.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                return;
            }

            var confirm = MessageBox.Show(
                $"¿Seguro que deseas eliminar la caja '{selected.Name}' ({selected.Code})?",
                "Confirmar eliminación",
                MessageBoxButton.YesNo,
                MessageBoxImage.Warning);

            if (confirm != MessageBoxResult.Yes)
            {
                return;
            }

            SetLoading(true);
            var ok = await DeleteCashRegisterApi(selected.Id);
            SetLoading(false);
            if (!ok)
            {
                QrStatusTextBlock.Text = "No se pudo eliminar la caja.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                ShowAlert(QrStatusTextBlock.Text);
                return;
            }

            await LoadCashRegistersFromApi("all");
            await LoadCashRegistersFromApi("assigned");
            QrStatusTextBlock.Text = "Caja eliminada correctamente.";
            QrStatusTextBlock.Foreground = Brushes.DarkGreen;
            ShowAlert(QrStatusTextBlock.Text);
        }

        private async void AssignCashRegisterRowButton_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not Button { Tag: CashRegisterOption selected })
            {
                return;
            }

            if (currentUser is null || !IsOwner(currentUser))
            {
                QrStatusTextBlock.Text = "Solo el dueño puede asignar cajas a cajeros.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                return;
            }

            var cashier = ShowAssignCashierForm(selected);
            if (cashier is null)
            {
                return;
            }

            SetLoading(true);
            var ok = await AssignCashRegisterApi(selected.Id, cashier.Id);
            SetLoading(false);
            if (!ok)
            {
                QrStatusTextBlock.Text = "No se pudo asignar la caja al cajero.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                ShowAlert(QrStatusTextBlock.Text);
                return;
            }

            await LoadCashRegistersFromApi("all");
            await LoadCashRegistersFromApi("assigned");
            QrStatusTextBlock.Text = $"Caja asignada correctamente a {cashier.Name}.";
            QrStatusTextBlock.Foreground = Brushes.DarkGreen;
            ShowAlert(QrStatusTextBlock.Text);
        }

        private static CashRegisterFormResult? ShowCashRegisterForm(string title, CashRegisterOption? existing = null)
        {
            var dialog = new Window
            {
                Title = title,
                Width = 420,
                Height = 290,
                ResizeMode = ResizeMode.NoResize,
                WindowStartupLocation = WindowStartupLocation.CenterOwner,
                Background = Brushes.White
            };

            var panel = new StackPanel { Margin = new Thickness(18) };
            var nameBox = new TextBox { Text = existing?.Name ?? string.Empty, Height = 34, Margin = new Thickness(0, 4, 0, 12) };
            var codeBox = new TextBox { Text = existing?.Code ?? string.Empty, Height = 34, Margin = new Thickness(0, 4, 0, 12) };
            var statusCombo = new ComboBox { Height = 34, Margin = new Thickness(0, 4, 0, 12) };
            statusCombo.Items.Add("active");
            statusCombo.Items.Add("inactive");
            statusCombo.SelectedItem = existing?.Status ?? "active";

            panel.Children.Add(new TextBlock { Text = "Nombre", FontWeight = FontWeights.SemiBold });
            panel.Children.Add(nameBox);
            panel.Children.Add(new TextBlock { Text = "Código", FontWeight = FontWeights.SemiBold });
            panel.Children.Add(codeBox);
            panel.Children.Add(new TextBlock { Text = "Estado", FontWeight = FontWeights.SemiBold });
            panel.Children.Add(statusCombo);

            var buttonRow = new WrapPanel { HorizontalAlignment = HorizontalAlignment.Right };
            var cancelButton = new Button { Content = "Cancelar", Width = 100, Margin = new Thickness(0, 0, 8, 0), Background = new SolidColorBrush(Color.FromRgb(226, 232, 240)), Foreground = new SolidColorBrush(Color.FromRgb(30, 41, 59)), BorderBrush = new SolidColorBrush(Color.FromRgb(203, 213, 225)), Padding = new Thickness(10, 6, 10, 6) };
            var saveButton = new Button { Content = "Guardar", Width = 100, Background = new SolidColorBrush(Color.FromRgb(37, 99, 235)), Foreground = Brushes.White, BorderBrush = new SolidColorBrush(Color.FromRgb(29, 78, 216)), Padding = new Thickness(12, 6, 12, 6) };

            cancelButton.Click += (_, _) => dialog.Close();
            saveButton.Click += (_, _) =>
            {
                if (string.IsNullOrWhiteSpace(nameBox.Text) || string.IsNullOrWhiteSpace(codeBox.Text))
                {
                    MessageBox.Show("Nombre y código son obligatorios.", "Validación", MessageBoxButton.OK, MessageBoxImage.Warning);
                    return;
                }

                dialog.Tag = new CashRegisterFormResult(
                    nameBox.Text.Trim(),
                    codeBox.Text.Trim(),
                    statusCombo.SelectedItem?.ToString() ?? "active");

                dialog.DialogResult = true;
                dialog.Close();
            };

            buttonRow.Children.Add(cancelButton);
            buttonRow.Children.Add(saveButton);
            panel.Children.Add(buttonRow);

            dialog.Content = new ScrollViewer
            {
                VerticalScrollBarVisibility = ScrollBarVisibility.Auto,
                HorizontalScrollBarVisibility = ScrollBarVisibility.Disabled,
                Content = panel
            };

            if (Application.Current?.MainWindow is Window owner && owner != dialog)
            {
                dialog.Owner = owner;
            }

            var result = dialog.ShowDialog();
            return result == true ? dialog.Tag as CashRegisterFormResult : null;
        }

        private CashierOption? ShowAssignCashierForm(CashRegisterOption register)
        {
            if (cashierOptions.Count == 0)
            {
                ShowAlert("No tienes cajeros disponibles en tu negocio para asignar esta caja.");
                return null;
            }

            var dialog = new Window
            {
                Title = $"Asignar caja: {register.Name}",
                Width = 440,
                Height = 220,
                ResizeMode = ResizeMode.NoResize,
                WindowStartupLocation = WindowStartupLocation.CenterOwner,
                Background = Brushes.White
            };

            var panel = new StackPanel { Margin = new Thickness(18) };
            panel.Children.Add(new TextBlock
            {
                Text = "Selecciona el cajero al que deseas asignar esta caja.",
                Foreground = new SolidColorBrush(Color.FromRgb(31, 41, 55)),
                Margin = new Thickness(0, 0, 0, 10)
            });

            var cashierCombo = new ComboBox { Height = 34, DisplayMemberPath = "DisplayName", ItemsSource = cashierOptions.ToList() };
            cashierCombo.SelectedIndex = 0;
            panel.Children.Add(cashierCombo);

            var buttonRow = new WrapPanel { HorizontalAlignment = HorizontalAlignment.Right, Margin = new Thickness(0, 14, 0, 0) };
            var cancelButton = new Button { Content = "Cancelar", Width = 100, Margin = new Thickness(0, 0, 8, 0) };
            var assignButton = new Button { Content = "Asignar", Width = 100 };

            cancelButton.Click += (_, _) => dialog.Close();
            assignButton.Click += (_, _) =>
            {
                if (cashierCombo.SelectedItem is not CashierOption selectedCashier)
                {
                    MessageBox.Show("Selecciona un cajero.", "Validación", MessageBoxButton.OK, MessageBoxImage.Warning);
                    return;
                }

                dialog.Tag = selectedCashier;
                dialog.DialogResult = true;
                dialog.Close();
            };

            buttonRow.Children.Add(cancelButton);
            buttonRow.Children.Add(assignButton);
            panel.Children.Add(buttonRow);

            dialog.Content = new ScrollViewer
            {
                VerticalScrollBarVisibility = ScrollBarVisibility.Auto,
                HorizontalScrollBarVisibility = ScrollBarVisibility.Disabled,
                Content = panel
            };
            if (Application.Current?.MainWindow is Window owner && owner != dialog)
            {
                dialog.Owner = owner;
            }

            var result = dialog.ShowDialog();
            return result == true ? dialog.Tag as CashierOption : null;
        }

        private async Task LoadCashiersFromApi()
        {
            try
            {
                using var response = await HttpClient.GetAsync($"{ApiBaseUrl}/gglob-pay/cashiers");
                if (!response.IsSuccessStatusCode)
                {
                    return;
                }

                var content = await response.Content.ReadAsStringAsync();
                var result = JsonSerializer.Deserialize<ApiListResponse<ApiCashier>>(content, JsonOptions());
                if (result?.Data is null)
                {
                    return;
                }

                cashierOptions.Clear();
                foreach (var cashier in result.Data)
                {
                    if (cashier.Id is null)
                    {
                        continue;
                    }

                    cashierOptions.Add(new CashierOption(cashier.Id.Value, cashier.Name ?? "Cajero", cashier.Email ?? string.Empty));
                }
            }
            catch
            {
            }
        }

        private async Task LoadBusinessCashiersFromApi()
        {
            try
            {
                using var response = await HttpClient.GetAsync($"{ApiBaseUrl}/gglob-pay/cashiers");
                if (!response.IsSuccessStatusCode)
                {
                    return;
                }

                var content = await response.Content.ReadAsStringAsync();
                var result = JsonSerializer.Deserialize<ApiListResponse<ApiCashier>>(content, JsonOptions());
                if (result?.Data is null)
                {
                    return;
                }

                businessCashiers.Clear();
                foreach (var cashier in result.Data)
                {
                    if (cashier.Id is null)
                    {
                        continue;
                    }

                    businessCashiers.Add(new BusinessCashierItem(
                        cashier.Id.Value,
                        cashier.Name ?? "Cajero",
                        cashier.LastName ?? string.Empty,
                        cashier.Email ?? string.Empty,
                        cashier.Phone ?? string.Empty));
                }

                await LoadCashiersFromApi();
            }
            catch
            {
            }
        }

        private async void SaveCashierButton_Click(object sender, RoutedEventArgs e)
        {
            if (currentUser is null || !IsOwner(currentUser))
            {
                QrStatusTextBlock.Text = "Solo el dueño puede gestionar usuarios cajeros.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                return;
            }

            var name = CashierNameTextBox.Text.Trim();
            var email = CashierEmailTextBox.Text.Trim();
            var password = CashierPasswordBox.Password;

            if (string.IsNullOrWhiteSpace(name) || string.IsNullOrWhiteSpace(email))
            {
                ShowAlert("Nombre y correo son obligatorios.");
                return;
            }

            if (!string.IsNullOrWhiteSpace(password) && password.Length < 8)
            {
                ShowAlert("La contraseña debe tener al menos 8 caracteres.");
                return;
            }

            if (editingCashierId is null && string.IsNullOrWhiteSpace(password))
            {
                ShowAlert("La contraseña es obligatoria para crear un cajero.");
                return;
            }

            SetLoading(true);
            var ok = editingCashierId is null
                ? await CreateCashierApi(name, CashierLastNameTextBox.Text.Trim(), email, CashierPhoneTextBox.Text.Trim(), password)
                : await UpdateCashierApi(editingCashierId.Value, name, CashierLastNameTextBox.Text.Trim(), email, CashierPhoneTextBox.Text.Trim(), password);
            SetLoading(false);

            if (!ok)
            {
                QrStatusTextBlock.Text = "No se pudo guardar el usuario cajero.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                ShowAlert(QrStatusTextBlock.Text);
                return;
            }

            await LoadBusinessCashiersFromApi();
            QrStatusTextBlock.Text = editingCashierId is null
                ? "Usuario cajero creado correctamente."
                : "Usuario cajero actualizado correctamente.";
            QrStatusTextBlock.Foreground = Brushes.DarkGreen;
            ShowAlert(QrStatusTextBlock.Text);
            ResetCashierForm();
        }

        private async Task<bool> CreateCashierApi(string name, string lastName, string email, string phone, string password)
        {
            try
            {
                var payload = JsonSerializer.Serialize(new
                {
                    name,
                    last_name = lastName,
                    email,
                    phone,
                    password,
                });

                using var content = new StringContent(payload, Encoding.UTF8, "application/json");
                using var response = await HttpClient.PostAsync($"{ApiBaseUrl}/gglob-pay/cashiers", content);
                return response.IsSuccessStatusCode;
            }
            catch
            {
                return false;
            }
        }

        private async Task<bool> UpdateCashierApi(int cashierId, string name, string lastName, string email, string phone, string? password)
        {
            try
            {
                var payload = JsonSerializer.Serialize(new
                {
                    name,
                    last_name = lastName,
                    email,
                    phone,
                    password,
                });

                using var content = new StringContent(payload, Encoding.UTF8, "application/json");
                using var response = await HttpClient.PutAsync($"{ApiBaseUrl}/gglob-pay/cashiers/{cashierId}", content);
                return response.IsSuccessStatusCode;
            }
            catch
            {
                return false;
            }
        }

        private async Task<bool> DeleteCashierApi(int cashierId)
        {
            try
            {
                using var response = await HttpClient.DeleteAsync($"{ApiBaseUrl}/gglob-pay/cashiers/{cashierId}");
                return response.IsSuccessStatusCode;
            }
            catch
            {
                return false;
            }
        }

        private void EditCashierRowButton_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not Button { Tag: BusinessCashierItem cashier })
            {
                return;
            }

            editingCashierId = cashier.Id;
            CashierNameTextBox.Text = cashier.Name;
            CashierLastNameTextBox.Text = cashier.LastName;
            CashierEmailTextBox.Text = cashier.Email;
            CashierPhoneTextBox.Text = cashier.Phone;
            CashierPasswordBox.Password = string.Empty;
            CashierPasswordLabel.Text = "Contraseña (opcional para actualizar)";
            SaveCashierButton.Content = "💾 Actualizar cajero";
            ClearCashierFormButton.Content = "↩ Cancelar edición";
        }

        private async void DeleteCashierRowButton_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not Button { Tag: BusinessCashierItem cashier })
            {
                return;
            }

            var confirm = MessageBox.Show(
                $"¿Seguro que deseas eliminar el usuario cajero '{cashier.FullName}'?",
                "Confirmar eliminación",
                MessageBoxButton.YesNo,
                MessageBoxImage.Warning);

            if (confirm != MessageBoxResult.Yes)
            {
                return;
            }

            SetLoading(true);
            var ok = await DeleteCashierApi(cashier.Id);
            SetLoading(false);

            if (!ok)
            {
                QrStatusTextBlock.Text = "No se pudo eliminar el usuario cajero.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                ShowAlert(QrStatusTextBlock.Text);
                return;
            }

            await LoadBusinessCashiersFromApi();
            QrStatusTextBlock.Text = "Usuario cajero eliminado correctamente.";
            QrStatusTextBlock.Foreground = Brushes.DarkGreen;
            ShowAlert(QrStatusTextBlock.Text);

            if (editingCashierId == cashier.Id)
            {
                ResetCashierForm();
            }
        }

        private void ClearCashierFormButton_Click(object sender, RoutedEventArgs e)
        {
            ResetCashierForm();
        }

        private void ResetCashierForm()
        {
            editingCashierId = null;
            CashierNameTextBox.Text = string.Empty;
            CashierLastNameTextBox.Text = string.Empty;
            CashierEmailTextBox.Text = string.Empty;
            CashierPhoneTextBox.Text = string.Empty;
            CashierPasswordBox.Password = string.Empty;
            CashierPasswordLabel.Text = "Contraseña";
            SaveCashierButton.Content = "👤 Crear cajero";
            ClearCashierFormButton.Content = "🧹 Limpiar";
        }

        private async Task<ApiQrIntentResponse?> CreateQrIntentApi(string sourceChannel, decimal amount, int cashRegisterId, int? destinationAccountId)
        {
            try
            {
                var payload = JsonSerializer.Serialize(new
                {
                    source_channel = sourceChannel,
                    amount,
                    cash_register_id = cashRegisterId,
                    destination_account_id = destinationAccountId,
                });

                using var content = new StringContent(payload, Encoding.UTF8, "application/json");
                using var response = await HttpClient.PostAsync($"{ApiBaseUrl}/gglob-pay/qr/intents", content);
                if (!response.IsSuccessStatusCode)
                {
                    return null;
                }

                var body = await response.Content.ReadAsStringAsync();
                return JsonSerializer.Deserialize<ApiQrIntentResponse>(body, JsonOptions());
            }
            catch
            {
                return null;
            }
        }

        private async void GenerateQrButton_Click(object sender, RoutedEventArgs e)
        {
            var accountOption = QrAccountComboBox.SelectedItem as QrAccountOption;
            if (accountOption is null)
            {
                QrStatusTextBlock.Text = "Selecciona un origen para generar el QR.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                ShowAlert(QrStatusTextBlock.Text);
                return;
            }

            if (!decimal.TryParse(QrAmountTextBox.Text.Trim(), NumberStyles.Number, CultureInfo.InvariantCulture, out var amount) &&
                !decimal.TryParse(QrAmountTextBox.Text.Trim(), NumberStyles.Number, CultureInfo.GetCultureInfo("es-CO"), out amount))
            {
                QrStatusTextBlock.Text = "Precio inválido. Usa solo números.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                ShowAlert(QrStatusTextBlock.Text);
                return;
            }

            if (amount <= 0)
            {
                QrStatusTextBlock.Text = "El precio debe ser mayor a cero.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                ShowAlert(QrStatusTextBlock.Text);
                return;
            }

            if (currentUser is null)
            {
                QrStatusTextBlock.Text = "No hay usuario en sesión.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                ShowAlert(QrStatusTextBlock.Text);
                return;
            }

            var selectedCashRegister = QrCashierComboBox.SelectedItem as CashRegisterOption;
            if (selectedCashRegister is null)
            {
                QrStatusTextBlock.Text = "Debes tener una caja activa asignada para generar QR.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                ShowAlert(QrStatusTextBlock.Text);
                return;
            }

            var normalizedRole = (currentUser.BusinessRole ?? string.Empty).Trim().ToLowerInvariant();
            if (normalizedRole is not ("owner" or "cashier"))
            {
                QrStatusTextBlock.Text = "Solo el Dueño o Cajero puede generar QR.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                ShowAlert(QrStatusTextBlock.Text);
                return;
            }

            var cashier = currentUser.Name ?? "Cajero";
            SetLoading(true);
            var intent = await CreateQrIntentApi(accountOption.Channel, amount, selectedCashRegister.Id, accountOption.Account?.Id);
            SetLoading(false);
            if (intent is null || string.IsNullOrWhiteSpace(intent.ReferenceCode))
            {
                QrStatusTextBlock.Text = "No fue posible generar el QR. Verifica la parametrización de Wompi/Bancolombia.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                ShowAlert(QrStatusTextBlock.Text);
                return;
            }

            var checkoutUrl = ExtractCheckoutUrl(intent.QrPayload);
            var payload = JsonSerializer.Serialize(intent.QrPayload, new JsonSerializerOptions
            {
                WriteIndented = true
            });
            var qrText = string.IsNullOrWhiteSpace(checkoutUrl) ? payload : checkoutUrl;

            QrImage.Source = BuildQrImageFromText(qrText);
            QrCheckoutUrlTextBox.Text = checkoutUrl ?? "No aplica para este canal.";
            QrPayloadTextBox.Text = payload;
            QrStatusTextBlock.Text = $"QR generado con referencia {intent.ReferenceCode} para {accountOption.DisplayName}.";
            QrStatusTextBlock.Foreground = Brushes.DarkGreen;

            var payment = new VerifiedPaymentRecord(
                intent.ReferenceCode,
                "Transferencia validada",
                accountOption.Account?.AccountNumber ?? "WOMPI",
                amount,
                cashier,
                accountOption.Account?.Bank ?? "Wompi",
                DateTime.Now,
                selectedCashRegister.Id,
                currentUser.Id ?? 0,
                selectedCashRegister.Name,
                accountOption.Channel,
                accountOption.Account?.Id,
                "PENDING");

            SetLoading(true);
            var stored = await SaveVerifiedPaymentApi(payment);
            SetLoading(false);
            if (stored is null)
            {
                QrStatusTextBlock.Foreground = Brushes.DarkOrange;
                QrStatusTextBlock.Text += " No se pudo guardar en app_web, revisa la conexión.";
                ShowAlert(QrStatusTextBlock.Text);
                return;
            }

            verifiedPayments.Insert(0, stored);
            ApplyVerifiedFilterLocal();
            GenerateReportLocal();
            ShowAlert("QR generado y pago guardado en app_web correctamente.");
        }

        private static string? ExtractCheckoutUrl(object? qrPayload)
        {
            if (qrPayload is null)
            {
                return null;
            }

            if (qrPayload is JsonElement element &&
                element.ValueKind == JsonValueKind.Object &&
                element.TryGetProperty("checkout_url", out var checkoutElement) &&
                checkoutElement.ValueKind == JsonValueKind.String)
            {
                return checkoutElement.GetString();
            }

            try
            {
                using var document = JsonDocument.Parse(JsonSerializer.Serialize(qrPayload));
                if (document.RootElement.ValueKind == JsonValueKind.Object &&
                    document.RootElement.TryGetProperty("checkout_url", out var checkout) &&
                    checkout.ValueKind == JsonValueKind.String)
                {
                    return checkout.GetString();
                }
            }
            catch
            {
            }

            return null;
        }

        private static BitmapImage? BuildQrImageFromText(string qrText)
        {
            if (string.IsNullOrWhiteSpace(qrText))
            {
                return null;
            }

            using var generator = new QRCodeGenerator();
            using var data = generator.CreateQrCode(qrText, QRCodeGenerator.ECCLevel.Q);
            var png = new PngByteQRCode(data);
            var qrBytes = png.GetGraphic(10);

            using var stream = new MemoryStream(qrBytes);
            var bitmap = new BitmapImage();
            bitmap.BeginInit();
            bitmap.CacheOption = BitmapCacheOption.OnLoad;
            bitmap.StreamSource = stream;
            bitmap.EndInit();
            bitmap.Freeze();
            return bitmap;
        }

        private async void ApplyVerifiedFilterButton_Click(object sender, RoutedEventArgs e)
        {
            await LoadVerifiedPaymentsFromApi();
        }

        private async void ManualVerifyWompiButton_Click(object sender, RoutedEventArgs e)
        {
            SetLoading(true);
            var updated = await VerifyPendingWompiPaymentsApi();
            await LoadVerifiedPaymentsFromApi();
            SetLoading(false);

            if (updated >= 0)
            {
                QrStatusTextBlock.Text = $"Verificación manual Wompi finalizada. Pagos revisados: {updated}.";
                QrStatusTextBlock.Foreground = Brushes.DarkGreen;
                ShowAlert(QrStatusTextBlock.Text);
            }
            else
            {
                QrStatusTextBlock.Text = "No fue posible verificar pagos pendientes de Wompi.";
                QrStatusTextBlock.Foreground = Brushes.DarkRed;
                ShowAlert(QrStatusTextBlock.Text);
            }
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
                new ServiceItem("cash_register_management", "Gestión de Cajas", "Asignación de cajas y cajeros.", (company?.GglobPayEnabled ?? false) || (company?.GglobPosEnabled ?? false)),
                new ServiceItem("cashier_management", "Usuarios Cajeros", "Crear usuarios cajeros del negocio.", (company?.GglobPayEnabled ?? false) || (company?.GglobPosEnabled ?? false)),
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


        private void BindCashiersAndCashRegisters(ApiUser user)
        {
            var cashierName = string.IsNullOrWhiteSpace(user.Name) ? "Cajero" : user.Name;

            cashRegisterOptions.Clear();
            foreach (var register in user.CashRegisters ?? [])
            {
                if (register.Id is null)
                {
                    continue;
                }

                cashRegisterOptions.Add(new CashRegisterOption(
                    register.Id.Value,
                    register.Name ?? "Caja",
                    register.Code ?? string.Empty,
                    register.Status ?? "active",
                    register.IsPrimary == 1));
            }

            if (cashRegisterOptions.Count == 0)
            {
                QrCashierComboBox.ItemsSource = new[] { "Sin caja asignada" };
                QrCashierComboBox.SelectedIndex = 0;
            }
            else
            {
                QrCashierComboBox.ItemsSource = cashRegisterOptions;
                var primaryIndex = cashRegisterOptions.ToList().FindIndex(x => x.IsPrimary);
                QrCashierComboBox.SelectedIndex = primaryIndex >= 0 ? primaryIndex : 0;
            }

            CashRegistersDataGrid.ItemsSource = cashRegisterManagementOptions;

            var filters = new[] { "Todos", cashierName };
            VerifiedCashierComboBox.ItemsSource = filters;
            ReportCashierComboBox.ItemsSource = filters;
            VerifiedCashierComboBox.SelectedIndex = 0;
            ReportCashierComboBox.SelectedIndex = 0;
        }

        private void SetLoading(bool isLoading)
        {
            LoadingOverlay.Visibility = isLoading ? Visibility.Visible : Visibility.Collapsed;
        }

        private static void ShowAlert(string message, string title = "Gglob Desk")
        {
            MessageBox.Show(message, title, MessageBoxButton.OK, MessageBoxImage.Information);
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
        [JsonPropertyName("id")]
        public int? Id { get; set; }

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

        [JsonPropertyName("cash_registers")]
        public List<ApiCashRegister>? CashRegisters { get; set; }
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

        [JsonPropertyName("pos_mode")]
        public string? PosMode { get; set; }

        [JsonPropertyName("pos_boxes")]
        public int PosBoxes { get; set; }

        public bool IsMultiCaja => string.Equals(PosMode, "multi", StringComparison.OrdinalIgnoreCase)
            || PosBoxes > 1;
    }

    public class ApiCashRegister
    {
        [JsonPropertyName("id")]
        public int? Id { get; set; }

        [JsonPropertyName("name")]
        public string? Name { get; set; }

        [JsonPropertyName("code")]
        public string? Code { get; set; }

        [JsonPropertyName("status")]
        public string? Status { get; set; }

        [JsonPropertyName("is_primary")]
        public int IsPrimary { get; set; }
    }

    public class CashRegisterOption(int id, string name, string code, string status, bool isPrimary)
    {
        public int Id { get; } = id;
        public string Name { get; } = name;
        public string Code { get; } = code;
        public string Status { get; } = status;
        public string StatusLabel => string.Equals(Status, "active", StringComparison.OrdinalIgnoreCase) ? "Activa" : "Inactiva";
        public bool IsPrimary { get; } = isPrimary;

        public override string ToString() => $"{Name} ({Code})";
    }

    public class ApiCashier
    {
        [JsonPropertyName("id")]
        public int? Id { get; set; }

        [JsonPropertyName("name")]
        public string? Name { get; set; }

        [JsonPropertyName("last_name")]
        public string? LastName { get; set; }

        [JsonPropertyName("email")]
        public string? Email { get; set; }

        [JsonPropertyName("phone")]
        public string? Phone { get; set; }
    }

    public class CashierOption(int id, string name, string email)
    {
        public int Id { get; } = id;
        public string Name { get; } = name;
        public string Email { get; } = email;
        public string DisplayName => string.IsNullOrWhiteSpace(Email) ? Name : $"{Name} ({Email})";
        public override string ToString() => DisplayName;
    }

    public class BusinessCashierItem(int id, string name, string lastName, string email, string phone)
    {
        public int Id { get; } = id;
        public string Name { get; } = name;
        public string LastName { get; } = lastName;
        public string Email { get; } = email;
        public string Phone { get; } = phone;
        public string FullName => string.IsNullOrWhiteSpace(LastName) ? Name : $"{Name} {LastName}";
    }

    public class CashRegisterFormResult(string name, string code, string status)
    {
        public string Name { get; } = name;
        public string Code { get; } = code;
        public string Status { get; } = status;
    }

    public class CreateCashierFormResult(string name, string lastName, string email, string phone, string password)
    {
        public string Name { get; } = name;
        public string LastName { get; } = lastName;
        public string Email { get; } = email;
        public string Phone { get; } = phone;
        public string Password { get; } = password;
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
        [JsonPropertyName("message")]
        public string? Message { get; set; }

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
        [JsonPropertyName("id")]
        public int? Id { get; set; }

        [JsonPropertyName("bank")]
        public string? Bank { get; set; }

        [JsonPropertyName("holder_name")]
        public string? HolderName { get; set; }

        [JsonPropertyName("account_number")]
        public string? AccountNumber { get; set; }

        [JsonPropertyName("account_type")]
        public string? AccountType { get; set; }
    }

    public class ApiProductCategory
    {
        [JsonPropertyName("id")]
        public int? Id { get; set; }

        [JsonPropertyName("name")]
        public string? Name { get; set; }

        [JsonPropertyName("description")]
        public string? Description { get; set; }

        [JsonPropertyName("is_active")]
        public bool IsActive { get; set; }
    }

    public class ProductCategoryItem(int id, string name, string description, bool isActive)
    {
        public int Id { get; } = id;
        public string Name { get; } = name;
        public string Description { get; } = description;
        public bool IsActive { get; } = isActive;
        public string StatusLabel => IsActive ? "Activa" : "Inactiva";
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

        [JsonPropertyName("cash_register_id")]
        public int? CashRegisterId { get; set; }

        [JsonPropertyName("cashier_user_id")]
        public int? CashierUserId { get; set; }

        [JsonPropertyName("source_channel")]
        public string? SourceChannel { get; set; }

        [JsonPropertyName("cash_register_name")]
        public string? CashRegisterName { get; set; }

        [JsonPropertyName("destination_account_id")]
        public int? DestinationAccountId { get; set; }

        [JsonPropertyName("status")]
        public string? Status { get; set; }

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
                verifiedAt == default ? DateTime.Now : verifiedAt,
                CashRegisterId ?? 0,
                CashierUserId ?? 0,
                CashRegisterName ?? string.Empty,
                SourceChannel ?? "ahorros",
                DestinationAccountId,
                Status ?? "PENDING");
        }
    }

    public class DestinationAccount(int id, string bank, string holderName, string accountNumber, string accountType)
    {
        public int Id { get; } = id;
        public string Bank { get; } = bank;
        public string HolderName { get; } = holderName;
        public string AccountNumber { get; } = accountNumber;
        public string AccountType { get; } = accountType;

        public override string ToString() => $"{Bank} - {AccountType} - {AccountNumber} ({HolderName})";
    }

    public class QrAccountOption(string channel, string displayName, DestinationAccount? account)
    {
        public string Channel { get; } = channel;
        public DestinationAccount? Account { get; } = account;
        public string DisplayName { get; } = displayName;
    }

    public class VerifiedPaymentRecord(string referenceCode, string senderName, string accountNumber, decimal amount, string cashier, string bank, DateTime verifiedAt, int cashRegisterId, int cashierUserId, string cashRegisterName, string sourceChannel, int? destinationAccountId, string status)
    {
        public string ReferenceCode { get; } = referenceCode;
        public string SenderName { get; } = senderName;
        public string AccountNumber { get; } = accountNumber;
        public decimal Amount { get; } = amount;
        public string Cashier { get; } = cashier;
        public string Bank { get; } = bank;
        public DateTime VerifiedAt { get; } = verifiedAt;
        public int CashRegisterId { get; } = cashRegisterId;
        public int CashierUserId { get; } = cashierUserId;
        public string CashRegisterName { get; } = cashRegisterName;
        public string SourceChannel { get; } = sourceChannel;
        public int? DestinationAccountId { get; } = destinationAccountId;
        public string Status { get; } = (status ?? "PENDING").ToUpperInvariant();

        public string StatusLabel => Status switch
        {
            "APPROVED" => "APPROVED ✅",
            "DECLINED" => "DECLINED",
            "VOIDED" => "VOIDED",
            "ERROR" => "ERROR",
            _ => "PENDING",
        };

        public Brush StatusBrush => Status switch
        {
            "APPROVED" => Brushes.DarkGreen,
            "DECLINED" => Brushes.IndianRed,
            "VOIDED" => Brushes.SlateGray,
            "ERROR" => Brushes.DarkRed,
            _ => Brushes.DarkOrange,
        };

        public string AmountFormatted => Amount.ToString("C0", CultureInfo.GetCultureInfo("es-CO"));
        public string VerifiedAtFormatted => VerifiedAt.ToString("HH:mm:ss");
        public string VerifiedAtFullFormatted => VerifiedAt.ToString("yyyy-MM-dd HH:mm");
    }

    public class ApiManualVerificationResponse
    {
        [JsonPropertyName("updated")]
        public int Updated { get; set; }
    }

    public class ApiQrIntentResponse
    {
        [JsonPropertyName("reference_code")]
        public string? ReferenceCode { get; set; }

        [JsonPropertyName("source_channel")]
        public string? SourceChannel { get; set; }

        [JsonPropertyName("qr_payload")]
        public object? QrPayload { get; set; }
    }

    public class ApiProviderSettingsResponse
    {
        [JsonPropertyName("provider")]
        public string? Provider { get; set; }

        [JsonPropertyName("configured")]
        public bool Configured { get; set; }

        [JsonPropertyName("public_key")]
        public string? PublicKey { get; set; }

        [JsonPropertyName("private_key")]
        public string? PrivateKey { get; set; }

        [JsonPropertyName("events_secret")]
        public string? EventsSecret { get; set; }

        [JsonPropertyName("base_url")]
        public string? BaseUrl { get; set; }

        [JsonPropertyName("client_id")]
        public string? ClientId { get; set; }

        [JsonPropertyName("client_secret")]
        public string? ClientSecret { get; set; }
    }

    public record AccessValidation(bool IsValid, string Message);
}
