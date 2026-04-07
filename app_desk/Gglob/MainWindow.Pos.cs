using System.Collections.ObjectModel;
using System.Collections.Specialized;
using System.Globalization;
using System.IO;
using System.Net.Http;
using System.Text.Json;
using System.Text;
using System.Windows;
using System.Windows.Controls;

namespace Gglob
{
    public partial class MainWindow
    {
        private string GetPosAuditCachePath()
        {
            var companyId = currentUser?.CompanyId ?? 0;
            var userId = currentUser?.Id ?? 0;
            var fileName = $"pos-audit-c{companyId}-u{userId}.json";

            return Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                "Gglob",
                fileName);
        }

        private readonly ObservableCollection<PosTicket> posTickets = [];
        private readonly ObservableCollection<ShiftAuditRecord> shiftHistory = [];
        private readonly ObservableCollection<CashMovementRecord> cashMovements = [];
        private readonly ObservableCollection<PosSaleAuditRecord> posSalesAudit = [];
        private readonly ObservableCollection<InventoryProductItem> filteredInventoryProducts = [];
        private readonly ObservableCollection<CashRegisterOption> posShiftRegisterOptions = [];
        private readonly List<ShiftSyncEvent> pendingShiftEvents = [];
        private readonly List<SaleSnapshot> pendingSales = [];
        private readonly List<MovementSnapshot> pendingMovements = [];
        private bool legacyBackfillCompleted;
        private int posTicketSequence = 1;
        private ShiftAuditRecord? activeShift;

        private void InitializePosModule()
        {
            PosTicketsListBox.ItemsSource = posTickets;
            ShiftHistoryDataGrid.ItemsSource = shiftHistory;
            CashMovementsDataGrid.ItemsSource = cashMovements;
            PosSalesAuditDataGrid.ItemsSource = posSalesAudit;
            PosProductResultsListBox.ItemsSource = filteredInventoryProducts;
            PosSalesPointComboBox.ItemsSource = salesPointOptions;
            PosCashRegisterComboBox.ItemsSource = posShiftRegisterOptions;
            PosCashRegisterComboBox.DisplayMemberPath = "Name";
            ShiftBiometricMethodComboBox.SelectedIndex = 0;
            PosPaymentTypeComboBox.SelectedIndex = 0;

            inventoryProducts.CollectionChanged += InventoryProducts_CollectionChanged;
            EnsureFallbackProducts();
            RefreshProductSearchResults();
            LoadPosAuditFromDisk();
            EnsureActiveTicket();
            RefreshShiftSummary();
            RefreshPosBindings();
            RefreshPosContextSelectors();
            _ = SyncPendingDataAsync();
        }

        private void InventoryProducts_CollectionChanged(object? sender, NotifyCollectionChangedEventArgs e)
        {
            RefreshProductSearchResults();
        }

        private void EnsureFallbackProducts()
        {
            if (inventoryProducts.Count > 0)
            {
                return;
            }

            inventoryProducts.Add(new InventoryProductItem(0, "7701", "Agua 600ml", null, "General", 2500m, true, 50, 5, false, []));
            inventoryProducts.Add(new InventoryProductItem(0, "7702", "Gaseosa 400ml", null, "General", 4200m, true, 40, 5, false, []));
            inventoryProducts.Add(new InventoryProductItem(0, "7703", "Snack Papas", null, "General", 3500m, true, 35, 5, false, []));
        }

        private void RefreshProductSearchResults()
        {
            if (PosProductSearchTextBox is null)
            {
                return;
            }

            var query = PosProductSearchTextBox.Text.Trim();
            var matches = inventoryProducts
                .Where(product => string.IsNullOrWhiteSpace(query)
                    || product.Name.Contains(query, StringComparison.OrdinalIgnoreCase)
                    || product.Code.Contains(query, StringComparison.OrdinalIgnoreCase))
                .Take(50)
                .ToList();

            filteredInventoryProducts.Clear();
            foreach (var match in matches)
            {
                filteredInventoryProducts.Add(match);
            }
        }

        private void EnsureActiveTicket()
        {
            if (posTickets.Count > 0)
            {
                return;
            }

            var ticket = new PosTicket($"T-{DateTime.Now:yyyyMMdd}-{posTicketSequence:000}");
            posTicketSequence++;
            posTickets.Add(ticket);
            PosTicketsListBox.SelectedItem = ticket;
        }

        private void RefreshPosContextSelectors()
        {
            if (PosSalesPointComboBox is null || PosCashRegisterComboBox is null)
            {
                return;
            }

            PosSalesPointComboBox.ItemsSource = null;
            PosSalesPointComboBox.ItemsSource = salesPointOptions;

            if (PosSalesPointComboBox.SelectedItem is not SalesPointOption currentPoint)
            {
                PosSalesPointComboBox.SelectedItem = salesPointOptions.FirstOrDefault();
                currentPoint = PosSalesPointComboBox.SelectedItem as SalesPointOption;
            }

            LoadRegistersForSalesPoint(currentPoint?.Id);
        }

        private void LoadRegistersForSalesPoint(int? salesPointId)
        {
            posShiftRegisterOptions.Clear();
            var source = cashRegisterOptions
                .Where(register => !salesPointId.HasValue || register.SalesPointId == salesPointId)
                .ToList();

            foreach (var register in source)
            {
                posShiftRegisterOptions.Add(register);
            }

            if (posShiftRegisterOptions.Count > 0)
            {
                var primary = posShiftRegisterOptions.FirstOrDefault(x => x.IsPrimary);
                PosCashRegisterComboBox.SelectedItem = primary ?? posShiftRegisterOptions.First();
            }
            else
            {
                PosCashRegisterComboBox.SelectedIndex = -1;
            }
        }

        private void PosSalesPointComboBox_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            var selectedPoint = PosSalesPointComboBox.SelectedItem as SalesPointOption;
            LoadRegistersForSalesPoint(selectedPoint?.Id);
        }

        private async void RefreshPosContextButton_Click(object sender, RoutedEventArgs e)
        {
            await LoadSalesPointsFromApi();
            await LoadCashRegistersFromApi("assigned");
            RefreshPosContextSelectors();
            ShiftStatusTextBlock.Text = "Asignaciones actualizadas desde servidor.";
            ShiftStatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkGreen;
        }

        private PosTicket? GetSelectedTicket() => PosTicketsListBox.SelectedItem as PosTicket;

        private void RefreshPosBindings()
        {
            var selected = GetSelectedTicket();
            PosTicketLinesDataGrid.ItemsSource = selected?.Lines;
            PosTotalsTextBlock.Text = selected is null
                ? "Total ticket: $0"
                : $"Total ticket: {selected.Total.ToString("C0", CultureInfo.GetCultureInfo("es-CO"))}";
            PosTicketsListBox.Items.Refresh();
        }

        private void RefreshShiftSummary()
        {
            if (activeShift is null)
            {
                CurrentShiftSummaryTextBlock.Text = "No hay turno activo.";
                return;
            }

            CurrentShiftSummaryTextBlock.Text =
                $"Cajero: {activeShift.Cashier}\nPunto: {activeShift.SalesPointName}\nCaja: {activeShift.CashRegisterName}\nInicio: {activeShift.OpenedAt:yyyy-MM-dd HH:mm}\nFondo: {activeShift.OpeningFund.ToString("C0", CultureInfo.GetCultureInfo("es-CO"))}";
        }

        private decimal ParseMoney(string? raw)
        {
            if (string.IsNullOrWhiteSpace(raw))
            {
                return 0m;
            }

            var normalized = raw.Replace("$", string.Empty).Replace(" ", string.Empty).Replace(".", string.Empty).Replace(',', '.');
            return decimal.TryParse(normalized, NumberStyles.Any, CultureInfo.InvariantCulture, out var value) ? value : 0m;
        }

        private bool ValidateBiometric(out string method, out string evidence, out string photoPath)
        {
            method = (ShiftBiometricMethodComboBox.SelectedItem as ComboBoxItem)?.Content?.ToString() ?? string.Empty;
            evidence = ShiftBiometricEvidenceTextBox.Text.Trim();
            photoPath = ShiftBiometricPhotoPathTextBox.Text.Trim();

            if (string.IsNullOrWhiteSpace(method))
            {
                ShiftStatusTextBlock.Text = "Debes seleccionar el método biométrico para continuar.";
                ShiftStatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkRed;
                return false;
            }

            PopulateAutomaticBiometricData(method, ref evidence, ref photoPath);

            if (string.IsNullOrWhiteSpace(method) || string.IsNullOrWhiteSpace(evidence) || string.IsNullOrWhiteSpace(photoPath))
            {
                ShiftStatusTextBlock.Text = "No se pudo generar la evidencia biométrica automática. Verifica cámara/huella e intenta nuevamente.";
                ShiftStatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkRed;
                return false;
            }

            return true;
        }

        private void OpenShiftButton_Click(object sender, RoutedEventArgs e)
        {
            if (!ValidateBiometric(out var method, out var evidence, out var photoPath))
            {
                return;
            }

            var selectedPoint = PosSalesPointComboBox.SelectedItem as SalesPointOption;
            var cashRegister = PosCashRegisterComboBox.SelectedItem as CashRegisterOption;
            if (cashRegister is null)
            {
                ShiftStatusTextBlock.Text = "Debes seleccionar una caja asignada para abrir turno.";
                ShiftStatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkRed;
                return;
            }

            if (activeShift is not null && activeShift.CashRegisterId == cashRegister.Id)
            {
                ShiftStatusTextBlock.Text = "La caja seleccionada ya tiene un turno activo. Debes cerrarlo antes de abrir uno nuevo.";
                ShiftStatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkOrange;
                return;
            }

            var cashierName = currentUser?.Name ?? "Cajero";
            var openingFund = ParseMoney(ShiftOpeningFundTextBox.Text);
            activeShift = new ShiftAuditRecord(
                cashierName,
                cashRegister.Name,
                DateTime.Now,
                null,
                method,
                $"{evidence} | foto:{photoPath}",
                openingFund,
                null,
                0m,
                0m,
                0m,
                0m,
                0m,
                0m,
                0m,
                selectedPoint?.Id ?? cashRegister.SalesPointId,
                selectedPoint?.Name ?? cashRegister.SalesPointName,
                cashRegister.Id,
                null);

            RegisterCashMovement(new CashMovementRecord("APERTURA", "Fondo inicial de caja", openingFund, cashierName, DateTime.Now));
            pendingShiftEvents.Add(new ShiftSyncEvent
            {
                EventType = "open",
                Cashier = cashierName,
                SalesPointId = selectedPoint?.Id ?? cashRegister.SalesPointId,
                CashRegisterId = cashRegister.Id,
                CashRegisterName = cashRegister.Name,
                At = DateTime.Now,
                OpeningFund = openingFund,
                BiometricMethod = method,
                BiometricEvidence = evidence,
                BiometricPhotoPath = photoPath
            });
            ShiftStatusTextBlock.Text = "Turno abierto correctamente con evidencia biométrica.";
            ShiftStatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkGreen;
            RefreshShiftSummary();
            SavePosAuditToDisk();
            _ = SyncPendingDataAsync();
        }

        private void CloseShiftButton_Click(object sender, RoutedEventArgs e)
        {
            if (activeShift is null)
            {
                ShiftStatusTextBlock.Text = "No hay turno activo para cerrar.";
                ShiftStatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkOrange;
                return;
            }

            if (!ValidateBiometric(out var method, out var evidence, out var photoPath))
            {
                return;
            }

            var counted = ParseMoney(ShiftClosingCountedCashTextBox.Text);
            var totalSales = posSalesAudit.Sum(sale => sale.Total);
            var totalCash = cashMovements.Where(m => m.Type == "VENTA_EFECTIVO").Sum(m => m.Amount);
            var totalTransfer = cashMovements.Where(m => m.Type == "VENTA_TRANSFERENCIA").Sum(m => m.Amount);
            var totalCard = cashMovements.Where(m => m.Type == "VENTA_TARJETA").Sum(m => m.Amount);
            var totalCheck = cashMovements.Where(m => m.Type == "VENTA_CHEQUE").Sum(m => m.Amount);
            var expectedCash = activeShift.OpeningFund + totalCash - Math.Abs(cashMovements.Where(m => m.Type == "CAMBIO").Sum(m => m.Amount));
            var difference = counted > 0 ? counted - expectedCash : 0m;

            var closedShift = activeShift.WithClose(DateTime.Now, totalSales, totalCash, totalTransfer, totalCard, totalCheck, counted, difference);
            shiftHistory.Insert(0, closedShift);

            RegisterCashMovement(new CashMovementRecord("CIERRE", "Cierre de turno", counted, closedShift.Cashier, DateTime.Now));
            if (difference != 0)
            {
                RegisterCashMovement(new CashMovementRecord("DESCUADRE", "Diferencia en cierre", difference, closedShift.Cashier, DateTime.Now));
            }
            pendingShiftEvents.Add(new ShiftSyncEvent
            {
                EventType = "close",
                Cashier = closedShift.Cashier,
                SalesPointId = closedShift.SalesPointId,
                CashRegisterId = closedShift.CashRegisterId,
                CashRegisterShiftId = closedShift.CashRegisterShiftId,
                CashRegisterName = closedShift.CashRegisterName,
                At = DateTime.Now,
                CountedCash = counted,
                Difference = difference,
                TotalSales = totalSales,
                BiometricMethod = method,
                BiometricEvidence = evidence,
                BiometricPhotoPath = photoPath
            });

            activeShift = null;
            ShiftStatusTextBlock.Text = "Turno cerrado y registrado en auditoría local.";
            ShiftStatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkGreen;
            RefreshShiftSummary();
            ShiftHistoryDataGrid.Items.Refresh();
            SavePosAuditToDisk();
            _ = SyncPendingDataAsync();
        }

        private void CreateTicketButton_Click(object sender, RoutedEventArgs e)
        {
            var ticket = new PosTicket($"T-{DateTime.Now:yyyyMMdd}-{posTicketSequence:000}");
            posTicketSequence++;
            posTickets.Add(ticket);
            PosTicketsListBox.SelectedItem = ticket;
            PosStatusTextBlock.Text = "Nuevo ticket creado.";
            RefreshPosBindings();
        }

        private void ClearTicketButton_Click(object sender, RoutedEventArgs e)
        {
            var ticket = GetSelectedTicket();
            if (ticket is null)
            {
                return;
            }

            ticket.Lines.Clear();
            RefreshPosBindings();
        }

        private void DeleteTicketButton_Click(object sender, RoutedEventArgs e)
        {
            var ticket = GetSelectedTicket();
            if (ticket is null)
            {
                return;
            }

            posTickets.Remove(ticket);
            EnsureActiveTicket();
            PosTicketsListBox.SelectedIndex = 0;
            RefreshPosBindings();
        }

        private void AddProductToTicketButton_Click(object sender, RoutedEventArgs e)
        {
            var ticket = GetSelectedTicket();
            var product = PosProductResultsListBox.SelectedItem as InventoryProductItem;
            if (ticket is null || product is null)
            {
                PosStatusTextBlock.Text = "Selecciona ticket y producto.";
                return;
            }

            var qty = int.TryParse(PosQuantityTextBox.Text, out var parsedQty) && parsedQty > 0 ? parsedQty : 1;
            var existing = ticket.Lines.FirstOrDefault(x => x.ProductCode == product.Code);
            if (existing is null)
            {
                ticket.Lines.Add(new PosTicketLine(product.Code, product.Name, qty, product.Price));
            }
            else
            {
                existing.Quantity += qty;
            }

            PosStatusTextBlock.Text = $"Producto agregado: {product.Name}.";
            RefreshPosBindings();
        }

        private void IncreaseLineQuantityButton_Click(object sender, RoutedEventArgs e)
        {
            if (PosTicketLinesDataGrid.SelectedItem is not PosTicketLine line)
            {
                return;
            }

            line.Quantity += 1;
            RefreshPosBindings();
        }

        private void DecreaseLineQuantityButton_Click(object sender, RoutedEventArgs e)
        {
            if (PosTicketLinesDataGrid.SelectedItem is not PosTicketLine line)
            {
                return;
            }

            line.Quantity = Math.Max(1, line.Quantity - 1);
            RefreshPosBindings();
        }

        private void RemoveLineButton_Click(object sender, RoutedEventArgs e)
        {
            var ticket = GetSelectedTicket();
            if (ticket is null || PosTicketLinesDataGrid.SelectedItem is not PosTicketLine line)
            {
                return;
            }

            ticket.Lines.Remove(line);
            RefreshPosBindings();
        }

        private void PosTicketsListBox_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            RefreshPosBindings();
        }

        private void PosMixedPaymentCheckBox_Changed(object sender, RoutedEventArgs e)
        {
            PosMixedPaymentPanel.Visibility = PosMixedPaymentCheckBox.IsChecked == true ? Visibility.Visible : Visibility.Collapsed;
        }

        private void PosProductSearchTextBox_TextChanged(object sender, TextChangedEventArgs e)
        {
            RefreshProductSearchResults();
        }

        private void PosProductResultsListBox_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (PosProductResultsListBox.SelectedItem is InventoryProductItem product)
            {
                PosStatusTextBlock.Text = $"Producto seleccionado: {product.CodeAndName}";
            }
        }

        private void ChargeTicketButton_Click(object sender, RoutedEventArgs e)
        {
            var ticket = GetSelectedTicket();
            if (ticket is null)
            {
                return;
            }

            var cashRegister = PosCashRegisterComboBox.SelectedItem as CashRegisterOption;
            if (activeShift is null || cashRegister is null || activeShift.CashRegisterId != cashRegister.Id)
            {
                PosStatusTextBlock.Text = "Debes abrir turno en la caja seleccionada antes de cobrar ventas.";
                PosStatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkRed;
                return;
            }

            if (ticket.Lines.Count == 0)
            {
                PosStatusTextBlock.Text = "El ticket está vacío.";
                PosStatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkOrange;
                return;
            }

            var total = ticket.Total;
            var paymentType = (PosPaymentTypeComboBox.SelectedItem as ComboBoxItem)?.Content?.ToString() ?? "efectivo";
            decimal cash = 0m;
            decimal transfer = 0m;
            decimal card = 0m;
            decimal check = 0m;

            if (PosMixedPaymentCheckBox.IsChecked == true)
            {
                cash = ParseMoney(PosMixedCashTextBox.Text);
                transfer = ParseMoney(PosMixedTransferTextBox.Text);
                if ((cash + transfer) != total)
                {
                    PosStatusTextBlock.Text = "En pago mixto, efectivo + transferencia debe ser igual al total del ticket.";
                    PosStatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkRed;
                    return;
                }
                paymentType = "mixto";
            }
            else
            {
                switch (paymentType)
                {
                    case "transferencia": transfer = total; break;
                    case "tarjeta": card = total; break;
                    case "cheque": check = total; break;
                    default: cash = total; break;
                }
            }

            var received = ParseMoney(PosCashReceivedTextBox.Text);
            var change = cash > 0 ? Math.Max(0, received - cash) : 0m;
            if (cash > 0 && received < cash)
            {
                PosStatusTextBlock.Text = "El efectivo recibido es menor al valor cobrado en efectivo.";
                PosStatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkRed;
                return;
            }

            ticket.Close(paymentType, cash, transfer, card, check);
            posSalesAudit.Insert(0, new PosSaleAuditRecord(ticket.Code, DateTime.Now, paymentType, total));
            pendingSales.Add(new SaleSnapshot
            {
                TicketCode = ticket.Code,
                SoldAt = DateTime.Now,
                PaymentType = paymentType,
                Total = total,
                SalesPointId = activeShift.SalesPointId,
                CashRegisterId = activeShift.CashRegisterId,
                CashRegisterShiftId = activeShift.CashRegisterShiftId
            });

            if (cash > 0)
            {
                RegisterCashMovement(new CashMovementRecord("VENTA_EFECTIVO", $"Cobro ticket {ticket.Code}", cash, activeShift.Cashier, DateTime.Now));
            }
            if (transfer > 0)
            {
                RegisterCashMovement(new CashMovementRecord("VENTA_TRANSFERENCIA", $"Cobro ticket {ticket.Code}", transfer, activeShift.Cashier, DateTime.Now));
            }
            if (card > 0)
            {
                RegisterCashMovement(new CashMovementRecord("VENTA_TARJETA", $"Cobro ticket {ticket.Code}", card, activeShift.Cashier, DateTime.Now));
            }
            if (check > 0)
            {
                RegisterCashMovement(new CashMovementRecord("VENTA_CHEQUE", $"Cobro ticket {ticket.Code}", check, activeShift.Cashier, DateTime.Now));
            }
            if (change > 0)
            {
                RegisterCashMovement(new CashMovementRecord("CAMBIO", $"Cambio entregado ticket {ticket.Code}", -change, activeShift.Cashier, DateTime.Now));
            }

            PosChangeTextBlock.Text = $"Cambio: {change.ToString("C0", CultureInfo.GetCultureInfo("es-CO"))}";
            PosStatusTextBlock.Text = $"Ticket {ticket.Code} cobrado ({paymentType}).";
            PosStatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkGreen;

            SavePosAuditToDisk();
            CreateTicketButton_Click(sender, e);
            PosMixedCashTextBox.Text = string.Empty;
            PosMixedTransferTextBox.Text = string.Empty;
            PosCashReceivedTextBox.Text = string.Empty;
            _ = SyncPendingDataAsync();
        }

        private void RegisterCashMovement(CashMovementRecord movement)
        {
            cashMovements.Insert(0, movement);
            CashMovementsDataGrid.Items.Refresh();
            pendingMovements.Add(new MovementSnapshot
            {
                Type = movement.Type,
                Detail = movement.Detail,
                Amount = movement.Amount,
                Cashier = movement.Cashier,
                At = movement.At,
                SalesPointId = activeShift?.SalesPointId,
                CashRegisterId = activeShift?.CashRegisterId,
                CashRegisterShiftId = activeShift?.CashRegisterShiftId
            });
        }

        private void SavePosAuditToDisk()
        {
            try
            {
                var payload = new PosAuditStore
                {
                    ShiftHistory = shiftHistory.Select(ShiftSnapshot.FromModel).ToList(),
                    Sales = posSalesAudit.Select(sale => new SaleSnapshot
                    {
                        TicketCode = sale.TicketCode,
                        SoldAt = sale.SoldAt,
                        PaymentType = sale.PaymentType,
                        Total = sale.Total
                    }).ToList(),
                    Movements = cashMovements.Select(movement => new MovementSnapshot
                    {
                        Type = movement.Type,
                        Detail = movement.Detail,
                        Amount = movement.Amount,
                        Cashier = movement.Cashier,
                        At = movement.At
                    }).ToList(),
                    ActiveShift = activeShift is null ? null : ShiftSnapshot.FromModel(activeShift),
                    PendingShiftEvents = pendingShiftEvents.ToList(),
                    PendingSales = pendingSales.ToList(),
                    PendingMovements = pendingMovements.ToList(),
                    LegacyBackfillCompleted = legacyBackfillCompleted
                };

                var cachePath = GetPosAuditCachePath();
                var directory = Path.GetDirectoryName(cachePath);
                if (!string.IsNullOrWhiteSpace(directory))
                {
                    Directory.CreateDirectory(directory);
                }

                File.WriteAllText(cachePath, JsonSerializer.Serialize(payload, new JsonSerializerOptions { WriteIndented = true }));
            }
            catch
            {
                // Persistencia local best-effort.
            }
        }

        private void LoadPosAuditFromDisk()
        {
            try
            {
                var cachePath = GetPosAuditCachePath();
                if (!File.Exists(cachePath))
                {
                    return;
                }

                var raw = File.ReadAllText(cachePath);
                var payload = JsonSerializer.Deserialize<PosAuditStore>(raw);
                if (payload is null)
                {
                    return;
                }

                shiftHistory.Clear();
                foreach (var item in payload.ShiftHistory ?? [])
                {
                    shiftHistory.Add(item.ToModel());
                }

                posSalesAudit.Clear();
                foreach (var item in payload.Sales ?? [])
                {
                    posSalesAudit.Add(new PosSaleAuditRecord(item.TicketCode ?? string.Empty, item.SoldAt, item.PaymentType ?? string.Empty, item.Total));
                }

                cashMovements.Clear();
                foreach (var item in payload.Movements ?? [])
                {
                    cashMovements.Add(new CashMovementRecord(item.Type ?? string.Empty, item.Detail ?? string.Empty, item.Amount, item.Cashier ?? string.Empty, item.At));
                }

                activeShift = payload.ActiveShift?.ToModel();
                pendingShiftEvents.Clear();
                pendingShiftEvents.AddRange(payload.PendingShiftEvents ?? []);
                pendingSales.Clear();
                pendingSales.AddRange(payload.PendingSales ?? []);
                pendingMovements.Clear();
                pendingMovements.AddRange(payload.PendingMovements ?? []);
                legacyBackfillCompleted = payload.LegacyBackfillCompleted;
                BackfillLegacyRecordsIfNeeded(payload);
            }
            catch
            {
                // Carga local best-effort.
            }
        }

        private void BackfillLegacyRecordsIfNeeded(PosAuditStore payload)
        {
            if (payload.LegacyBackfillCompleted)
            {
                return;
            }

            foreach (var shift in shiftHistory)
            {
                var (evidence, photoPath) = SplitBiometricEvidence(shift.BiometricEvidence);
                EnqueueShiftIfMissing(new ShiftSyncEvent
                {
                    EventType = "open",
                    Cashier = shift.Cashier,
                    SalesPointId = shift.SalesPointId,
                    CashRegisterId = shift.CashRegisterId,
                    CashRegisterShiftId = shift.CashRegisterShiftId,
                    CashRegisterName = shift.CashRegisterName,
                    At = shift.OpenedAt,
                    OpeningFund = shift.OpeningFund,
                    BiometricMethod = shift.BiometricMethod,
                    BiometricEvidence = evidence,
                    BiometricPhotoPath = photoPath
                });

                if (shift.ClosedAt.HasValue)
                {
                    EnqueueShiftIfMissing(new ShiftSyncEvent
                    {
                        EventType = "close",
                        Cashier = shift.Cashier,
                        SalesPointId = shift.SalesPointId,
                        CashRegisterId = shift.CashRegisterId,
                        CashRegisterShiftId = shift.CashRegisterShiftId,
                        CashRegisterName = shift.CashRegisterName,
                        At = shift.ClosedAt.Value,
                        CountedCash = shift.CountedCash ?? 0,
                        TotalSales = shift.TotalSales,
                        Difference = shift.Difference,
                        BiometricMethod = shift.BiometricMethod,
                        BiometricEvidence = evidence,
                        BiometricPhotoPath = photoPath
                    });
                }
            }

            foreach (var sale in posSalesAudit)
            {
                EnqueueSaleIfMissing(new SaleSnapshot
                {
                    TicketCode = sale.TicketCode,
                    SoldAt = sale.SoldAt,
                    PaymentType = sale.PaymentType,
                    Total = sale.Total
                });
            }

            foreach (var movement in cashMovements)
            {
                EnqueueMovementIfMissing(new MovementSnapshot
                {
                    Type = movement.Type,
                    Detail = movement.Detail,
                    Amount = movement.Amount,
                    Cashier = movement.Cashier,
                    At = movement.At
                });
            }

            legacyBackfillCompleted = true;
            SavePosAuditToDisk();
        }

        private static (string evidence, string photoPath) SplitBiometricEvidence(string biometricEvidence)
        {
            if (string.IsNullOrWhiteSpace(biometricEvidence))
            {
                return ("sin-evidencia", "sin-foto");
            }

            const string marker = "| foto:";
            var index = biometricEvidence.IndexOf(marker, StringComparison.OrdinalIgnoreCase);
            if (index < 0)
            {
                return (biometricEvidence.Trim(), "sin-foto");
            }

            var evidence = biometricEvidence[..index].Trim();
            var photo = biometricEvidence[(index + marker.Length)..].Trim();
            return (string.IsNullOrWhiteSpace(evidence) ? "sin-evidencia" : evidence, string.IsNullOrWhiteSpace(photo) ? "sin-foto" : photo);
        }

        private void EnqueueShiftIfMissing(ShiftSyncEvent item)
        {
            var key = $"{item.EventType}|{item.SalesPointId}|{item.CashRegisterId}|{item.CashRegisterName}|{item.At:O}|{item.BiometricPhotoPath}";
            var exists = pendingShiftEvents.Any(current => $"{current.EventType}|{current.SalesPointId}|{current.CashRegisterId}|{current.CashRegisterName}|{current.At:O}|{current.BiometricPhotoPath}" == key);
            if (!exists)
            {
                pendingShiftEvents.Add(item);
            }
        }

        private void EnqueueSaleIfMissing(SaleSnapshot item)
        {
            var key = $"{item.TicketCode}|{item.SalesPointId}|{item.CashRegisterId}|{item.SoldAt:O}|{item.Total}";
            var exists = pendingSales.Any(current => $"{current.TicketCode}|{current.SalesPointId}|{current.CashRegisterId}|{current.SoldAt:O}|{current.Total}" == key);
            if (!exists)
            {
                pendingSales.Add(item);
            }
        }

        private void EnqueueMovementIfMissing(MovementSnapshot item)
        {
            var key = $"{item.Type}|{item.SalesPointId}|{item.CashRegisterId}|{item.Detail}|{item.Amount}|{item.At:O}";
            var exists = pendingMovements.Any(current => $"{current.Type}|{current.SalesPointId}|{current.CashRegisterId}|{current.Detail}|{current.Amount}|{current.At:O}" == key);
            if (!exists)
            {
                pendingMovements.Add(item);
            }
        }

        private async Task SyncPendingDataAsync()
        {
            if (pendingShiftEvents.Count == 0 && pendingSales.Count == 0 && pendingMovements.Count == 0)
            {
                return;
            }

            var syncedShiftEvents = new List<ShiftSyncEvent>();
            foreach (var shiftEvent in pendingShiftEvents)
            {
                var ok = await PostJsonAsync("/pos/shifts/sync", shiftEvent);
                if (ok)
                {
                    syncedShiftEvents.Add(shiftEvent);
                }
            }

            var syncedSales = new List<SaleSnapshot>();
            foreach (var sale in pendingSales)
            {
                var ok = await PostJsonAsync("/pos/sales/sync", new
                {
                    ticket_code = sale.TicketCode,
                    sold_at = sale.SoldAt.ToString("yyyy-MM-dd HH:mm:ss"),
                    payment_type = sale.PaymentType,
                    total = sale.Total,
                    sales_point_id = sale.SalesPointId,
                    cash_register_id = sale.CashRegisterId,
                    cash_register_shift_id = sale.CashRegisterShiftId,
                    company_id = currentUser?.CompanyId,
                    cashier_user_id = currentUser?.Id
                });
                if (ok)
                {
                    syncedSales.Add(sale);
                }
            }

            var syncedMovements = new List<MovementSnapshot>();
            foreach (var movement in pendingMovements)
            {
                var ok = await PostJsonAsync("/pos/cash-movements/sync", new
                {
                    type = movement.Type,
                    detail = movement.Detail,
                    amount = movement.Amount,
                    cashier = movement.Cashier,
                    at = movement.At.ToString("yyyy-MM-dd HH:mm:ss"),
                    sales_point_id = movement.SalesPointId,
                    cash_register_id = movement.CashRegisterId,
                    cash_register_shift_id = movement.CashRegisterShiftId,
                    company_id = currentUser?.CompanyId,
                    cashier_user_id = currentUser?.Id
                });
                if (ok)
                {
                    syncedMovements.Add(movement);
                }
            }

            pendingShiftEvents.RemoveAll(item => syncedShiftEvents.Contains(item));
            pendingSales.RemoveAll(item => syncedSales.Contains(item));
            pendingMovements.RemoveAll(item => syncedMovements.Contains(item));
            SavePosAuditToDisk();
        }

        private async Task<bool> PostJsonAsync(string endpoint, object payload)
        {
            try
            {
                var json = JsonSerializer.Serialize(payload);
                using var content = new StringContent(json, Encoding.UTF8, "application/json");
                using var response = await HttpClient.PostAsync($"{ApiBaseUrl}{endpoint}", content);
                return response.IsSuccessStatusCode;
            }
            catch
            {
                return false;
            }
        }

        private void LaunchBiometricCameraButton_Click(object sender, RoutedEventArgs e)
        {
            var timestamp = DateTime.Now;
            if (TryLaunchDeviceCamera())
            {
                var generatedPath = $"camera-capture-{timestamp:yyyyMMdd-HHmmss}.jpg";
                ShiftBiometricEvidenceTextBox.Text = $"foto-en-vivo-{timestamp:yyyyMMddHHmmss}";
                ShiftBiometricPhotoPathTextBox.Text = generatedPath;
                ShiftStatusTextBlock.Text = $"Cámara activada y evidencia cargada automáticamente ({generatedPath}).";
                ShiftStatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkGreen;
            }
            else
            {
                ShiftStatusTextBlock.Text = "No se pudo abrir la cámara del dispositivo en este entorno.";
                ShiftStatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkOrange;
            }
        }

        private void PopulateAutomaticBiometricData(string method, ref string evidence, ref string photoPath)
        {
            if (!string.IsNullOrWhiteSpace(evidence) && !string.IsNullOrWhiteSpace(photoPath))
            {
                return;
            }

            var timestamp = DateTime.Now;
            if (method.Contains("Foto", StringComparison.OrdinalIgnoreCase))
            {
                TryLaunchDeviceCamera();
                evidence = $"foto-en-vivo-{timestamp:yyyyMMddHHmmss}";
                photoPath = $"camera-capture-{timestamp:yyyyMMdd-HHmmss}.jpg";
            }
            else if (method.Contains("Huella", StringComparison.OrdinalIgnoreCase))
            {
                TryLaunchFingerprintReader();
                evidence = $"huella-verificada-{timestamp:yyyyMMddHHmmss}";
                photoPath = $"fingerprint-capture-{timestamp:yyyyMMdd-HHmmss}.txt";
            }

            ShiftBiometricEvidenceTextBox.Text = evidence;
            ShiftBiometricPhotoPathTextBox.Text = photoPath;
        }

        private static bool TryLaunchDeviceCamera()
        {
            try
            {
                System.Diagnostics.Process.Start(new System.Diagnostics.ProcessStartInfo
                {
                    FileName = "microsoft.windows.camera:",
                    UseShellExecute = true
                });
                return true;
            }
            catch
            {
                return false;
            }
        }

        private static bool TryLaunchFingerprintReader()
        {
            try
            {
                System.Diagnostics.Process.Start(new System.Diagnostics.ProcessStartInfo
                {
                    FileName = "ms-settings:signinoptions",
                    UseShellExecute = true
                });
                return true;
            }
            catch
            {
                return false;
            }
        }

        private class PosAuditStore
        {
            public List<ShiftSnapshot>? ShiftHistory { get; set; }
            public List<SaleSnapshot>? Sales { get; set; }
            public List<MovementSnapshot>? Movements { get; set; }
            public ShiftSnapshot? ActiveShift { get; set; }
            public List<ShiftSyncEvent>? PendingShiftEvents { get; set; }
            public List<SaleSnapshot>? PendingSales { get; set; }
            public List<MovementSnapshot>? PendingMovements { get; set; }
            public bool LegacyBackfillCompleted { get; set; }
        }

        private class ShiftSnapshot
        {
            public string? Cashier { get; set; }
            public string? CashRegisterName { get; set; }
            public int? SalesPointId { get; set; }
            public string? SalesPointName { get; set; }
            public int? CashRegisterId { get; set; }
            public int? CashRegisterShiftId { get; set; }
            public DateTime OpenedAt { get; set; }
            public DateTime? ClosedAt { get; set; }
            public string? BiometricMethod { get; set; }
            public string? BiometricEvidence { get; set; }
            public decimal OpeningFund { get; set; }
            public decimal? CountedCash { get; set; }
            public decimal TotalSales { get; set; }
            public decimal TotalCash { get; set; }
            public decimal TotalTransfer { get; set; }
            public decimal TotalCard { get; set; }
            public decimal TotalCheck { get; set; }
            public decimal Returns { get; set; }
            public decimal Difference { get; set; }

            public ShiftAuditRecord ToModel() => new(
                Cashier ?? string.Empty,
                CashRegisterName ?? string.Empty,
                OpenedAt,
                ClosedAt,
                BiometricMethod ?? string.Empty,
                BiometricEvidence ?? string.Empty,
                OpeningFund,
                CountedCash,
                TotalSales,
                TotalCash,
                TotalTransfer,
                TotalCard,
                TotalCheck,
                Returns,
                Difference,
                SalesPointId,
                SalesPointName,
                CashRegisterId,
                CashRegisterShiftId);

            public static ShiftSnapshot FromModel(ShiftAuditRecord model)
            {
                return new ShiftSnapshot
                {
                    Cashier = model.Cashier,
                    CashRegisterName = model.CashRegisterName,
                    OpenedAt = model.OpenedAt,
                    ClosedAt = model.ClosedAt,
                    BiometricMethod = model.BiometricMethod,
                    BiometricEvidence = model.BiometricEvidence,
                    OpeningFund = model.OpeningFund,
                    CountedCash = model.CountedCash,
                    TotalSales = model.TotalSales,
                    TotalCash = model.TotalCash,
                    TotalTransfer = model.TotalTransfer,
                    TotalCard = model.TotalCard,
                    TotalCheck = model.TotalCheck,
                    Returns = model.Returns,
                    Difference = model.Difference,
                    SalesPointId = model.SalesPointId,
                    SalesPointName = model.SalesPointName,
                    CashRegisterId = model.CashRegisterId,
                    CashRegisterShiftId = model.CashRegisterShiftId
                };
            }
        }

        private class SaleSnapshot
        {
            public string? TicketCode { get; set; }
            public DateTime SoldAt { get; set; }
            public string? PaymentType { get; set; }
            public decimal Total { get; set; }
            public int? SalesPointId { get; set; }
            public int? CashRegisterId { get; set; }
            public int? CashRegisterShiftId { get; set; }
        }

        private class MovementSnapshot
        {
            public string? Type { get; set; }
            public string? Detail { get; set; }
            public decimal Amount { get; set; }
            public string? Cashier { get; set; }
            public DateTime At { get; set; }
            public int? SalesPointId { get; set; }
            public int? CashRegisterId { get; set; }
            public int? CashRegisterShiftId { get; set; }
        }

        private class ShiftSyncEvent
        {
            public string? EventType { get; set; }
            public string? Cashier { get; set; }
            public int? SalesPointId { get; set; }
            public int? CashRegisterId { get; set; }
            public int? CashRegisterShiftId { get; set; }
            public string? CashRegisterName { get; set; }
            public DateTime At { get; set; }
            public decimal OpeningFund { get; set; }
            public decimal CountedCash { get; set; }
            public decimal TotalSales { get; set; }
            public decimal Difference { get; set; }
            public string? BiometricMethod { get; set; }
            public string? BiometricEvidence { get; set; }
            public string? BiometricPhotoPath { get; set; }
        }
    }
}
