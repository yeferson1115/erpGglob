using System.Globalization;
using System.Text.Json;
using System.Text.Json.Serialization;
using System.Windows.Media;

namespace Gglob
{
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

        [JsonPropertyName("sales_point_id")]
        public int? SalesPointId { get; set; }

        [JsonPropertyName("sales_point_name")]
        public string? SalesPointName { get; set; }
    }

    public class CashRegisterOption(int id, string name, string code, string status, bool isPrimary, int? salesPointId, string salesPointName)
    {
        public int Id { get; } = id;
        public string Name { get; } = name;
        public string Code { get; } = code;
        public string Status { get; } = status;
        public int? SalesPointId { get; } = salesPointId;
        public string SalesPointName { get; } = salesPointName;
        public string StatusLabel => string.Equals(Status, "active", StringComparison.OrdinalIgnoreCase) ? "Activa" : "Inactiva";
        public bool IsPrimary { get; } = isPrimary;

        public override string ToString() => $"{Name} ({Code}) - {SalesPointName}";
    }

    public class ApiSalesPoint
    {
        [JsonPropertyName("id")]
        public int? Id { get; set; }

        [JsonPropertyName("name")]
        public string? Name { get; set; }

        [JsonPropertyName("code")]
        public string? Code { get; set; }

        [JsonPropertyName("status")]
        public string? Status { get; set; }
    }

    public class SalesPointOption(int id, string name, string code, string status)
    {
        public int Id { get; } = id;
        public string Name { get; } = name;
        public string Code { get; } = code;
        public string Status { get; } = status;
        public string StatusLabel => string.Equals(Status, "active", StringComparison.OrdinalIgnoreCase) ? "Activo" : "Inactivo";
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

    public class CashRegisterFormResult(string name, string code, string status, int? salesPointId)
    {
        public string Name { get; } = name;
        public string Code { get; } = code;
        public string Status { get; } = status;
        public int? SalesPointId { get; } = salesPointId;
    }

    public class SalesPointFormResult(string name, string code, string status)
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

    public class ApiInventoryProduct
    {
        [JsonPropertyName("id")]
        public int? Id { get; set; }

        [JsonPropertyName("code")]
        public string? Code { get; set; }

        [JsonPropertyName("name")]
        public string? Name { get; set; }

        [JsonPropertyName("product_category_id")]
        public int? ProductCategoryId { get; set; }

        [JsonPropertyName("category_name")]
        public string? CategoryName { get; set; }

        [JsonPropertyName("price")]
        public decimal Price { get; set; }

        [JsonPropertyName("tracks_inventory")]
        public bool TracksInventory { get; set; }

        [JsonPropertyName("stock_quantity")]
        public int? StockQuantity { get; set; }

        [JsonPropertyName("minimum_stock")]
        public int? MinimumStock { get; set; }

        [JsonPropertyName("is_combo")]
        public bool IsCombo { get; set; }

        [JsonPropertyName("combo_product_codes")]
        public JsonElement ComboProductCodesRaw { get; set; }

        private List<string> ParseComboProductCodes()
        {
            if (ComboProductCodesRaw.ValueKind == JsonValueKind.Array)
            {
                return ComboProductCodesRaw
                    .EnumerateArray()
                    .Select(code => code.GetString()?.Trim())
                    .Where(code => !string.IsNullOrWhiteSpace(code))
                    .Cast<string>()
                    .Distinct(StringComparer.OrdinalIgnoreCase)
                    .ToList();
            }

            if (ComboProductCodesRaw.ValueKind == JsonValueKind.String)
            {
                var raw = ComboProductCodesRaw.GetString() ?? string.Empty;
                return raw
                    .Split([',', ';', '\n', '\r'], StringSplitOptions.RemoveEmptyEntries | StringSplitOptions.TrimEntries)
                    .Where(code => !string.IsNullOrWhiteSpace(code))
                    .Distinct(StringComparer.OrdinalIgnoreCase)
                    .ToList();
            }

            return [];
        }

        public InventoryProductItem ToDesktopRecord()
        {
            var comboCodes = ParseComboProductCodes();
            var isComboResolved = IsCombo || comboCodes.Count > 0;
            return new InventoryProductItem(
                Id ?? 0,
                Code ?? string.Empty,
                Name ?? string.Empty,
                ProductCategoryId,
                CategoryName ?? "Sin categoría",
                Price,
                TracksInventory,
                StockQuantity,
                MinimumStock,
                isComboResolved,
                comboCodes);
        }
    }

    public class InventoryProductItem(
        int id,
        string code,
        string name,
        int? productCategoryId,
        string categoryName,
        decimal price,
        bool tracksInventory,
        int? stockQuantity,
        int? minimumStock,
        bool isCombo,
        List<string> comboProductCodes)
    {
        public int Id { get; } = id;
        public string Code { get; } = code;
        public string Name { get; } = name;
        public int? ProductCategoryId { get; } = productCategoryId;
        public string CategoryName { get; } = categoryName;
        public decimal Price { get; } = price;
        public bool TracksInventory { get; } = tracksInventory;
        public int? StockQuantity { get; } = stockQuantity;
        public int? MinimumStock { get; } = minimumStock;
        public bool IsCombo { get; } = isCombo;
        public List<string> ComboProductCodes { get; } = comboProductCodes;
        public string PriceLabel => Price.ToString("C2", CultureInfo.GetCultureInfo("es-CO"));
        public string TypeLabel => IsCombo ? "KIT / COMBO" : "Normal";
        public string CodeAndName => $"[{Code}] {Name}";
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

        [JsonPropertyName("sales_point_id")]
        public int? SalesPointId { get; set; }

        [JsonPropertyName("sales_point_name")]
        public string? SalesPointName { get; set; }

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
                SalesPointName ?? string.Empty,
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

    public class VerifiedPaymentRecord(string referenceCode, string senderName, string accountNumber, decimal amount, string cashier, string bank, DateTime verifiedAt, int cashRegisterId, int cashierUserId, string cashRegisterName, string salesPointName, string sourceChannel, int? destinationAccountId, string status)
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
        public string SalesPointName { get; } = salesPointName;
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

    public class ApiPosBlueprint
    {
        [JsonPropertyName("analysis_text")]
        public string? AnalysisText { get; set; }

        [JsonPropertyName("payload")]
        public string? Payload { get; set; }
    }

    public record AccessValidation(bool IsValid, string Message);
}
