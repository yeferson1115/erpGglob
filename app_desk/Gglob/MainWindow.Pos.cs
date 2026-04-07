using System.Collections.ObjectModel;
using System.Globalization;
using System.Windows;
using System.Windows.Controls;

namespace Gglob
{
    public partial class MainWindow
    {
        private readonly ObservableCollection<PosTicket> posTickets = [];
        private readonly ObservableCollection<ShiftAuditRecord> shiftHistory = [];
        private int posTicketSequence = 1;
        private ShiftAuditRecord? activeShift;

        private void InitializePosModule()
        {
            PosProductPickerComboBox.ItemsSource = inventoryProducts;
            PosTicketsListBox.ItemsSource = posTickets;
            ShiftHistoryDataGrid.ItemsSource = shiftHistory;
            ShiftBiometricMethodComboBox.SelectedIndex = 0;
            PosPaymentTypeComboBox.SelectedIndex = 0;
            EnsureActiveTicket();
            RefreshShiftSummary();
            RefreshPosBindings();
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

        private PosTicket? GetSelectedTicket() => PosTicketsListBox.SelectedItem as PosTicket;

        private void RefreshPosBindings()
        {
            var selected = GetSelectedTicket();
            PosTicketLinesDataGrid.ItemsSource = selected?.Lines;
            if (selected is null)
            {
                PosTotalsTextBlock.Text = "Total ticket: $0";
                return;
            }

            PosTotalsTextBlock.Text = $"Total ticket: {selected.Total.ToString("C0", CultureInfo.GetCultureInfo("es-CO"))}";
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
                $"Cajero: {activeShift.Cashier}\nCaja: {activeShift.CashRegisterName}\nInicio: {activeShift.OpenedAt:yyyy-MM-dd HH:mm}\nFondo: {activeShift.OpeningFund.ToString("C0", CultureInfo.GetCultureInfo("es-CO"))}";
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

        private bool ValidateBiometric(out string method, out string evidence)
        {
            method = (ShiftBiometricMethodComboBox.SelectedItem as ComboBoxItem)?.Content?.ToString() ?? string.Empty;
            evidence = ShiftBiometricEvidenceTextBox.Text.Trim();

            if (string.IsNullOrWhiteSpace(method) || string.IsNullOrWhiteSpace(evidence))
            {
                ShiftStatusTextBlock.Text = "Debes indicar método biométrico y evidencia en vivo para continuar.";
                ShiftStatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkRed;
                return false;
            }

            return true;
        }

        private void OpenShiftButton_Click(object sender, RoutedEventArgs e)
        {
            if (activeShift is not null)
            {
                ShiftStatusTextBlock.Text = "Ya existe un turno activo. Debes cerrarlo antes de abrir uno nuevo.";
                ShiftStatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkOrange;
                return;
            }

            if (!ValidateBiometric(out var method, out var evidence))
            {
                return;
            }

            var cashRegister = cashRegisterOptions.FirstOrDefault();
            var cashierName = currentUser?.Name ?? "Cajero";
            var openingFund = ParseMoney(ShiftOpeningFundTextBox.Text);
            activeShift = new ShiftAuditRecord(
                cashierName,
                cashRegister?.Name ?? "Caja sin asignar",
                DateTime.Now,
                null,
                method,
                evidence,
                openingFund,
                null,
                0m,
                0m,
                0m,
                0m,
                0m,
                0m,
                0m);

            ShiftStatusTextBlock.Text = "Turno abierto correctamente con evidencia biométrica.";
            ShiftStatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkGreen;
            RefreshShiftSummary();
        }

        private void CloseShiftButton_Click(object sender, RoutedEventArgs e)
        {
            if (activeShift is null)
            {
                ShiftStatusTextBlock.Text = "No hay turno activo para cerrar.";
                ShiftStatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkOrange;
                return;
            }

            if (!ValidateBiometric(out _, out _))
            {
                return;
            }

            var counted = ParseMoney(ShiftClosingCountedCashTextBox.Text);
            var totalSales = posTickets.Where(ticket => ticket.IsClosed).Sum(ticket => ticket.Total);
            var expectedCash = activeShift.OpeningFund + posTickets.Where(ticket => ticket.IsClosed).Sum(ticket => ticket.CashAmount);
            var difference = counted > 0 ? counted - expectedCash : 0m;

            var closedShift = activeShift.WithClose(
                DateTime.Now,
                totalSales,
                posTickets.Where(ticket => ticket.IsClosed).Sum(ticket => ticket.CashAmount),
                posTickets.Where(ticket => ticket.IsClosed).Sum(ticket => ticket.TransferAmount),
                posTickets.Where(ticket => ticket.IsClosed).Sum(ticket => ticket.CardAmount),
                posTickets.Where(ticket => ticket.IsClosed).Sum(ticket => ticket.CheckAmount),
                counted,
                difference);

            shiftHistory.Insert(0, closedShift);
            activeShift = null;
            ShiftStatusTextBlock.Text = "Turno cerrado y registrado en auditoría local.";
            ShiftStatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkGreen;
            RefreshShiftSummary();
            ShiftHistoryDataGrid.Items.Refresh();
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
            var product = PosProductPickerComboBox.SelectedItem as InventoryProductItem;
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

        private void ChargeTicketButton_Click(object sender, RoutedEventArgs e)
        {
            var ticket = GetSelectedTicket();
            if (ticket is null)
            {
                return;
            }

            if (activeShift is null)
            {
                PosStatusTextBlock.Text = "Debes abrir turno antes de cobrar ventas.";
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
            PosChangeTextBlock.Text = $"Cambio: {change.ToString("C0", CultureInfo.GetCultureInfo("es-CO"))}";
            PosStatusTextBlock.Text = $"Ticket {ticket.Code} cobrado ({(PosMixedPaymentCheckBox.IsChecked == true ? "mixto" : paymentType)}).";
            PosStatusTextBlock.Foreground = System.Windows.Media.Brushes.DarkGreen;

            CreateTicketButton_Click(sender, e);
            PosMixedCashTextBox.Text = string.Empty;
            PosMixedTransferTextBox.Text = string.Empty;
            PosCashReceivedTextBox.Text = string.Empty;
        }
    }
}
