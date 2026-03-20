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
    public partial class MainWindow
    {
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


    }
}
