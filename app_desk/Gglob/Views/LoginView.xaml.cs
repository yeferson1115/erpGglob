using System.Windows;
using System.Windows.Controls;
using System.Windows.Media;

namespace Gglob.Views;

public partial class LoginView : UserControl
{
    public event RoutedEventHandler? LoginRequested;
    public event RoutedEventHandler? RegisterRequested;

    public LoginView()
    {
        InitializeComponent();
    }

    public string Email => EmailTextBox.Text.Trim();
    public string Password => PasswordBox.Password;

    public RegistrationRequestData RegistrationData => new(
        CompanyNameTextBox.Text.Trim(),
        NitTextBox.Text.Trim(),
        CompanyEmailTextBox.Text.Trim(),
        AddressTextBox.Text.Trim(),
        OwnerNameTextBox.Text.Trim(),
        OwnerLastNameTextBox.Text.Trim(),
        OwnerPhoneTextBox.Text.Trim(),
        OwnerEmailTextBox.Text.Trim(),
        RegisterPasswordBox.Password,
        RegisterPasswordConfirmBox.Password);

    public void SetLoginEnabled(bool isEnabled)
    {
        LoginButton.IsEnabled = isEnabled;
        RegisterButton.IsEnabled = isEnabled;
    }

    public void SetStatus(string message, Brush foreground)
    {
        StatusTextBlock.Text = message;
        StatusTextBlock.Foreground = foreground;
    }

    public void ClearPassword() => PasswordBox.Password = string.Empty;

    public void SwitchToLogin()
    {
        LoginPanel.Visibility = Visibility.Visible;
        RegisterPanel.Visibility = Visibility.Collapsed;
    }

    public void SwitchToRegister()
    {
        LoginPanel.Visibility = Visibility.Collapsed;
        RegisterPanel.Visibility = Visibility.Visible;
    }

    public void ClearRegistrationForm()
    {
        CompanyNameTextBox.Clear();
        NitTextBox.Clear();
        CompanyEmailTextBox.Clear();
        AddressTextBox.Clear();
        OwnerNameTextBox.Clear();
        OwnerLastNameTextBox.Clear();
        OwnerPhoneTextBox.Clear();
        OwnerEmailTextBox.Clear();
        RegisterPasswordBox.Clear();
        RegisterPasswordConfirmBox.Clear();
    }

    private void LoginButton_Click(object sender, RoutedEventArgs e)
    {
        LoginRequested?.Invoke(this, e);
    }

    private void RegisterButton_Click(object sender, RoutedEventArgs e)
    {
        RegisterRequested?.Invoke(this, e);
    }

    private void ShowRegisterHyperlink_Click(object sender, RoutedEventArgs e)
    {
        SwitchToRegister();
        SetStatus("Completa los datos para crear tu negocio desde Desk.", Brushes.DarkSlateBlue);
    }

    private void ShowLoginHyperlink_Click(object sender, RoutedEventArgs e)
    {
        SwitchToLogin();
        SetStatus(string.Empty, Brushes.DarkGreen);
    }
}

public record RegistrationRequestData(
    string CompanyName,
    string Nit,
    string CompanyEmail,
    string Address,
    string OwnerName,
    string OwnerLastName,
    string OwnerPhone,
    string OwnerEmail,
    string Password,
    string PasswordConfirmation);
