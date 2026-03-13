using System.Diagnostics;
using System.Windows;

namespace Gglob
{
    public partial class MainWindow : Window
    {
        public MainWindow()
        {
            InitializeComponent();
        }

        private void btnDueno_Click(object sender, RoutedEventArgs e)
        {
            MessageBox.Show("Ingresando como Dueño del Local");
        }

        private void btnCajero_Click(object sender, RoutedEventArgs e)
        {
            MessageBox.Show("Ingresando como Cajero");
        }

        private void Hyperlink_Click(object sender, RoutedEventArgs e)
        {
            string url = "https://tusitio.com/registro";

            Process.Start(new ProcessStartInfo
            {
                FileName = url,
                UseShellExecute = true
            });
        }
    }
}