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
        private readonly ObservableCollection<SalesPointOption> salesPointOptions = [];
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
            DeskTracksInventoryCheck_Changed(this, new RoutedEventArgs());
            DeskIsComboCheck_Changed(this, new RoutedEventArgs());
        }

        private void DeskTracksInventoryCheck_Changed(object sender, RoutedEventArgs e)
        {
            if (DeskInventoryStockPanel is null || DeskTracksInventoryCheck is null)
            {
                return;
            }

            DeskInventoryStockPanel.IsEnabled = DeskTracksInventoryCheck.IsChecked == true;
            DeskInventoryStockPanel.Opacity = DeskTracksInventoryCheck.IsChecked == true ? 1 : 0.55;
        }

        private void DeskIsComboCheck_Changed(object sender, RoutedEventArgs e)
        {
            if (DeskComboPanel is null || DeskIsComboCheck is null)
            {
                return;
            }

            DeskComboPanel.Visibility = DeskIsComboCheck.IsChecked == true ? Visibility.Visible : Visibility.Collapsed;
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
            VerifiedSalesPointComboBox.ItemsSource = salesPointOptions;
            ReportSalesPointComboBox.ItemsSource = salesPointOptions;

            DestinationAccountsListBox.ItemsSource = destinationAccounts;
            QrAccountComboBox.ItemsSource = qrAccountOptions;
            CashRegistersDataGrid.ItemsSource = cashRegisterManagementOptions;
            SalesPointsDataGrid.ItemsSource = salesPointOptions;
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
                "Principal",
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
                "Principal",
                "wompi_credit_card",
                null,
                "PENDING"));

            RefreshQrOptions();
        }

        private async void LoginViewControl_LoginRequested(object sender, RoutedEventArgs e)
        {
            var email = LoginViewControl.Email;
            var password = LoginViewControl.Password;

            if (string.IsNullOrWhiteSpace(email) || string.IsNullOrWhiteSpace(password))
            {
                ShowStatus("Completa correo y contraseña.", isError: true, isWarning: true);
                return;
            }

            LoginViewControl.SetLoginEnabled(false);
            ShowStatus("Validando credenciales...", isError: false);

            var authenticated = await TryOnlineLogin(email, password);
            if (!authenticated)
            {
                TryOfflineLogin(email, password);
            }

            LoginViewControl.SetLoginEnabled(true);
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
            LoginViewControl.Visibility = Visibility.Collapsed;
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
            SalesPointsPanel.Visibility = Visibility.Collapsed;
            PosBlueprintPanel.Visibility = Visibility.Collapsed;

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

            if (moduleKey == "gglob_pos_blueprint")
            {
                DefaultPanel.Visibility = Visibility.Collapsed;
                PosBlueprintPanel.Visibility = Visibility.Visible;
                _ = LoadPosBlueprintFromApi();
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

            if (moduleKey == "sales_point_management")
            {
                if (currentUser is null || !IsOwner(currentUser))
                {
                    QrStatusTextBlock.Text = "Solo el dueño puede gestionar puntos de venta.";
                    QrStatusTextBlock.Foreground = Brushes.DarkOrange;
                    return;
                }

                DefaultPanel.Visibility = Visibility.Collapsed;
                SalesPointsPanel.Visibility = Visibility.Visible;
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
            var hideByModule = moduleKey is "gglob_pay" or "gglob_pos" or "gglob_pos_blueprint" or "product_categories" or "cash_register_management" or "cashier_management" or "sales_point_management";
            AvailableModulesPanel.Visibility = (hideByRole || hideByModule) ? Visibility.Collapsed : Visibility.Visible;
        }

        private void RenderServicesMenu(List<ServiceItem> services)
        {
            ServicesMenuPanel.Children.Clear();

            var adminKeys = new HashSet<string>(StringComparer.OrdinalIgnoreCase)
            {
                "cash_register_management",
                "cashier_management",
                "sales_point_management"
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
            await LoadSalesPointsFromApi();
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
                        register.IsPrimary == 1,
                        register.SalesPointId,
                        register.SalesPointName ?? "Sin punto de venta"));
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

        private async Task<bool> LoadSalesPointsFromApi()
        {
            try
            {
                using var response = await HttpClient.GetAsync($"{ApiBaseUrl}/gglob-pay/sales-points");
                if (!response.IsSuccessStatusCode)
                {
                    return false;
                }

                var content = await response.Content.ReadAsStringAsync();
                var result = JsonSerializer.Deserialize<ApiListResponse<ApiSalesPoint>>(content, JsonOptions());
                if (result?.Data is null)
                {
                    return false;
                }

                salesPointOptions.Clear();
                foreach (var point in result.Data)
                {
                    if (point.Id is null)
                    {
                        continue;
                    }

                    salesPointOptions.Add(new SalesPointOption(
                        point.Id.Value,
                        point.Name ?? "Punto de venta",
                        point.Code ?? string.Empty,
                        point.Status ?? "active"));
                }

                VerifiedSalesPointComboBox.ItemsSource = null;
                VerifiedSalesPointComboBox.ItemsSource = salesPointOptions;
                VerifiedSalesPointComboBox.SelectedIndex = -1;

                ReportSalesPointComboBox.ItemsSource = null;
                ReportSalesPointComboBox.ItemsSource = salesPointOptions;
                ReportSalesPointComboBox.SelectedIndex = -1;

                SalesPointsDataGrid.ItemsSource = null;
                SalesPointsDataGrid.ItemsSource = salesPointOptions;
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
                var salesPointId = (VerifiedSalesPointComboBox.SelectedItem as SalesPointOption)?.Id;
                var query = BuildPaymentsQuery(from, to, cashier, salesPointId);
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
                var salesPointId = (ReportSalesPointComboBox.SelectedItem as SalesPointOption)?.Id;
                var query = BuildPaymentsQuery(from, to, cashier, salesPointId);

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

        private static string BuildPaymentsQuery(string? from, string? to, string? cashier, int? salesPointId = null)
        {
            var parts = new List<string>();
            if (!string.IsNullOrWhiteSpace(from)) parts.Add($"from={Uri.EscapeDataString(from)}");
            if (!string.IsNullOrWhiteSpace(to)) parts.Add($"to={Uri.EscapeDataString(to)}");
            if (!string.IsNullOrWhiteSpace(cashier) && cashier != "Todos") parts.Add($"cashier={Uri.EscapeDataString(cashier)}");
            if (salesPointId.HasValue) parts.Add($"sales_point_id={salesPointId.Value}");

            return parts.Count == 0 ? string.Empty : $"?{string.Join("&", parts)}";
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
                    register.IsPrimary == 1,
                    register.SalesPointId,
                    register.SalesPointName ?? "Sin punto de venta"));
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
            var foreground = isError
                ? Brushes.DarkRed
                : isWarning
                    ? Brushes.DarkOrange
                    : Brushes.DarkGreen;

            LoginViewControl.SetStatus(message, foreground);
        }

        private void LogoutButton_Click(object sender, RoutedEventArgs e)
        {
            DashboardRoot.Visibility = Visibility.Collapsed;
            LoginViewControl.Visibility = Visibility.Visible;
            LoginViewControl.ClearPassword();
            ShowStatus("Sesión cerrada.", isError: false);
        }

        private void LoginViewControl_RegisterRequested(object sender, RoutedEventArgs e)
        {
            const string url = "http://localhost:81/registro-negocio";

            Process.Start(new ProcessStartInfo
            {
                FileName = url,
                UseShellExecute = true
            });
        }
    }


}
