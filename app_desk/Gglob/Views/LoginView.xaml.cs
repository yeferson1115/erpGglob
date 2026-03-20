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

    public void SetLoginEnabled(bool isEnabled) => LoginButton.IsEnabled = isEnabled;

    public void SetStatus(string message, Brush foreground)
    {
        StatusTextBlock.Text = message;
        StatusTextBlock.Foreground = foreground;
    }

    public void ClearPassword() => PasswordBox.Password = string.Empty;

    private void LoginButton_Click(object sender, RoutedEventArgs e)
    {
        LoginRequested?.Invoke(this, e);
    }

    private void RegisterHyperlink_Click(object sender, RoutedEventArgs e)
    {
        RegisterRequested?.Invoke(this, e);
    }
}
