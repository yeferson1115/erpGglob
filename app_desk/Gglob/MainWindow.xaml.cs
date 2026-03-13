using System.Diagnostics;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;
using System.Windows;

namespace Gglob
{
    public partial class MainWindow : Window
    {
        private static readonly HttpClient HttpClient = new();

        public MainWindow()
        {
            InitializeComponent();
        }

        private async void LoginButton_Click(object sender, RoutedEventArgs e)
        {
            var baseUrl = ApiBaseUrlTextBox.Text.Trim().TrimEnd('/');
            var email = EmailTextBox.Text.Trim();
            var password = PasswordBox.Password;

            if (string.IsNullOrWhiteSpace(baseUrl) || string.IsNullOrWhiteSpace(email) || string.IsNullOrWhiteSpace(password))
            {
                StatusTextBlock.Text = "Completa la URL API, correo y contraseña.";
                StatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkOrange;
                return;
            }

            LoginButton.IsEnabled = false;
            StatusTextBlock.Text = "Validando credenciales...";
            StatusTextBlock.Foreground = System.Windows.Media.Brushes.DimGray;
            SessionPanel.Visibility = Visibility.Collapsed;

            try
            {
                var payload = JsonSerializer.Serialize(new { email, password });
                using var content = new StringContent(payload, Encoding.UTF8, "application/json");
                using var response = await HttpClient.PostAsync($"{baseUrl}/login", content);
                var body = await response.Content.ReadAsStringAsync();

                if (!response.IsSuccessStatusCode)
                {
                    StatusTextBlock.Text = $"Error de autenticación ({(int)response.StatusCode}): {body}";
                    StatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkRed;
                    return;
                }

                var options = new JsonSerializerOptions
                {
                    PropertyNameCaseInsensitive = true
                };

                var authResult = JsonSerializer.Deserialize<AuthResponse>(body, options);
                if (authResult is null || string.IsNullOrWhiteSpace(authResult.AccessToken) || authResult.User is null)
                {
                    StatusTextBlock.Text = "No se pudo interpretar la respuesta del servidor.";
                    StatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkRed;
                    return;
                }

                HttpClient.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", authResult.AccessToken);

                var profile = await GetProfile(baseUrl, options);
                var user = profile ?? authResult.User;

                var permissions = authResult.Permissions?.Select(p => p.Name).Where(x => !string.IsNullOrWhiteSpace(x)).ToList() ?? [];
                var roles = user.Roles?.Select(r => r.Name).Where(x => !string.IsNullOrWhiteSpace(x)).ToList() ?? [];

                UserNameTextBlock.Text = $"Usuario: {user.Name} ({user.Email})";
                BusinessTextBlock.Text = user.Company is null
                    ? $"Negocio: No asociado (company_id: {user.CompanyId?.ToString() ?? "N/A"})"
                    : $"Negocio: {user.Company.Name} (NIT: {user.Company.Nit ?? "N/A"})";
                BusinessRoleTextBlock.Text = $"Rol en negocio: {NormalizeBusinessRole(user.BusinessRole)}";
                RolesTextBlock.Text = roles.Count == 0
                    ? "Roles de seguridad: Sin roles asignados"
                    : $"Roles de seguridad: {string.Join(", ", roles)}";
                PermissionsTextBlock.Text = permissions.Count == 0
                    ? "Permisos: Sin permisos"
                    : $"Permisos: {string.Join(", ", permissions)}";

                SessionPanel.Visibility = Visibility.Visible;
                StatusTextBlock.Text = "Inicio de sesión exitoso.";
                StatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkGreen;
            }
            catch (Exception ex)
            {
                StatusTextBlock.Text = $"No fue posible conectar con app_web: {ex.Message}";
                StatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkRed;
            }
            finally
            {
                LoginButton.IsEnabled = true;
            }
        }

        private static async Task<ApiUser?> GetProfile(string baseUrl, JsonSerializerOptions options)
        {
            try
            {
                using var response = await HttpClient.GetAsync($"{baseUrl}/profile");
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

        private static string NormalizeBusinessRole(string? businessRole)
        {
            return businessRole?.ToLowerInvariant() switch
            {
                "owner" => "Dueño",
                "cashier" => "Cajero",
                _ => "Sin rol de negocio"
            };
        }

        private void Hyperlink_Click(object sender, RoutedEventArgs e)
        {
            string url = "http://localhost:8000/registro-negocio";

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
    }
}
