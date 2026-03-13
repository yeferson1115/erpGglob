using System.Diagnostics;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;
using System.Windows;

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

        public MainWindow()
        {
            InitializeComponent();
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
            SessionPanel.Visibility = Visibility.Collapsed;

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

                RenderSession(user, authResult.Permissions, "Inicio de sesión exitoso (online).");
                SaveOfflineSession(email, password, authResult.AccessToken, user, authResult.Permissions);

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

            HttpClient.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", cached.AccessToken);
            RenderSession(cached.User, cached.Permissions, $"Ingreso offline habilitado. Última sincronización: {cached.CachedAt:yyyy-MM-dd HH:mm}");
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

        private void RenderSession(ApiUser user, List<ApiPermission>? permissionsList, string statusMessage)
        {
            var permissions = permissionsList?.Select(p => p.Name).Where(x => !string.IsNullOrWhiteSpace(x)).ToList() ?? [];
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
            ShowStatus(statusMessage, isError: false);
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
                ? System.Windows.Media.Brushes.DarkRed
                : isWarning
                    ? System.Windows.Media.Brushes.DarkOrange
                    : System.Windows.Media.Brushes.DarkGreen;
        }

        private void Hyperlink_Click(object sender, RoutedEventArgs e)
        {
            string url = "http://localhost:81/registro-negocio";

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
}
