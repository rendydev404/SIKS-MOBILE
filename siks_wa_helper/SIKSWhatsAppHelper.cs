using Microsoft.Win32;
using System;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Runtime.InteropServices;
using System.Threading;
using System.Windows.Automation;
using System.Windows.Forms;

namespace SiksWhatsAppHelper
{
    internal static class Program
    {
        private const string ProtocolName = "sikswa";
        private const string ProductName = "SIKS WhatsApp Helper";
        [STAThread]
        private static int Main(string[] args)
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);

            if (args.Length == 0 ||
                (args.Length == 1 && string.Equals(args[0], "--install", StringComparison.OrdinalIgnoreCase)))
            {
                return Install();
            }

            if (args.Length == 1 && string.Equals(args[0], "--uninstall", StringComparison.OrdinalIgnoreCase))
            {
                return Uninstall();
            }

            if (args.Length != 1)
            {
                ShowError("Parameter helper tidak valid.");
                return 1;
            }

            return ComposeDraft(args[0]);
        }

        private static int Install()
        {
            try
            {
                string source = Application.ExecutablePath;
                string installDirectory = Path.Combine(
                    Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                    ProductName);
                string installedExecutable = Path.Combine(installDirectory, "SIKSWhatsAppHelper.exe");

                Directory.CreateDirectory(installDirectory);
                if (!PathsEqual(source, installedExecutable))
                {
                    File.Copy(source, installedExecutable, true);
                }

                using (RegistryKey protocol = Registry.CurrentUser.CreateSubKey(@"Software\Classes\" + ProtocolName))
                {
                    protocol.SetValue(string.Empty, "URL:" + ProductName + " Protocol");
                    protocol.SetValue("URL Protocol", string.Empty);

                    using (RegistryKey command = protocol.CreateSubKey(@"shell\open\command"))
                    {
                        command.SetValue(string.Empty, Quote(installedExecutable) + " \"%1\"");
                    }
                }

                MessageBox.Show(
                    "Helper siap dipakai. Kembali ke SIKS Web lalu tekan tombol WhatsApp pada invoice.",
                    ProductName,
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Information);
                return 0;
            }
            catch (Exception exception)
            {
                ShowError("Gagal memasang helper: " + exception.Message);
                return 1;
            }
        }

        private static int Uninstall()
        {
            try
            {
                Registry.CurrentUser.DeleteSubKeyTree(@"Software\Classes\" + ProtocolName, false);
                MessageBox.Show(
                    "Helper telah dilepas dari browser. File program tetap disimpan agar dapat dipasang kembali.",
                    ProductName,
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Information);
                return 0;
            }
            catch (Exception exception)
            {
                ShowError("Gagal melepas helper: " + exception.Message);
                return 1;
            }
        }

        private static int ComposeDraft(string rawUri)
        {
            string phone;
            if (!SiksWaProtocol.TryParseComposeUri(rawUri, out phone))
            {
                ShowError("Tautan atau nomor WhatsApp tujuan tidak valid.");
                return 1;
            }

            Image invoiceImage;
            string caption;
            if (!TryReadInvoiceFromClipboard(out invoiceImage, out caption))
            {
                ShowError(
                    "Gambar invoice tidak ditemukan di clipboard. Kembali ke SIKS, izinkan akses clipboard di browser, lalu coba lagi.");
                return 1;
            }

            try
            {
                OpenWhatsAppChat(phone);
                IntPtr window = WaitForForegroundWhatsAppWindow(TimeSpan.FromSeconds(12));
                if (window == IntPtr.Zero)
                {
                    ShowError("WhatsApp Desktop tidak terbuka. Pastikan aplikasinya terpasang, sudah login, lalu coba lagi.");
                    return 1;
                }

                FocusWindow(window);
                Thread.Sleep(300);

                // The website just put the image and caption on the clipboard.
                // Keep only the image while WhatsApp creates the attachment preview.
                Clipboard.SetImage(invoiceImage);
                SendControlV();
                Thread.Sleep(1100);

                if (!string.IsNullOrWhiteSpace(caption) && !TrySetCaption(window, caption))
                {
                    ShowError(
                        "Gambar sudah ditempelkan ke WhatsApp, tetapi caption belum bisa diisi otomatis. Tulis caption di kotak keterangan lalu klik Kirim.");
                    return 1;
                }

                // Deliberately never send Enter or click WhatsApp's Send button.
                // The admin reviews the image/caption and makes the final send.
                return 0;
            }
            catch (Exception exception)
            {
                ShowError("Gagal menyiapkan draft WhatsApp: " + exception.Message);
                return 1;
            }
            finally
            {
                if (invoiceImage != null)
                {
                    invoiceImage.Dispose();
                }
            }
        }

        private static bool TryReadInvoiceFromClipboard(out Image image, out string caption)
        {
            image = null;
            caption = string.Empty;

            for (int attempt = 0; attempt < 8; attempt++)
            {
                try
                {
                    if (!Clipboard.ContainsImage())
                    {
                        return false;
                    }

                    using (Image clipboardImage = Clipboard.GetImage())
                    {
                        if (clipboardImage == null)
                        {
                            return false;
                        }

                        image = new Bitmap(clipboardImage);
                    }

                    if (Clipboard.ContainsText(TextDataFormat.UnicodeText))
                    {
                        caption = Clipboard.GetText(TextDataFormat.UnicodeText);
                    }
                    return true;
                }
                catch (ExternalException)
                {
                    Thread.Sleep(125);
                }
            }

            return false;
        }

        private static void OpenWhatsAppChat(string phone)
        {
            Process.Start(new ProcessStartInfo
            {
                FileName = "whatsapp://send?phone=" + phone,
                UseShellExecute = true
            });
        }

        private static bool TrySetCaption(IntPtr whatsappWindow, string caption)
        {
            AutomationElement captionBox = FindCaptionBox(whatsappWindow);
            if (captionBox == null)
            {
                return false;
            }

            try
            {
                object pattern;
                if (captionBox.TryGetCurrentPattern(ValuePattern.Pattern, out pattern))
                {
                    ((ValuePattern)pattern).SetValue(caption);
                    return true;
                }

                captionBox.SetFocus();
                Clipboard.SetText(caption, TextDataFormat.UnicodeText);
                SendControlV();
                return true;
            }
            catch (ElementNotAvailableException)
            {
                return false;
            }
            catch (InvalidOperationException)
            {
                return false;
            }
        }

        private static AutomationElement FindCaptionBox(IntPtr whatsappWindow)
        {
            try
            {
                AutomationElement focused = AutomationElement.FocusedElement;
                if (IsVisibleEdit(focused) && BelongsToWindow(focused, whatsappWindow))
                {
                    return focused;
                }

                AutomationElement root = AutomationElement.FromHandle(whatsappWindow);
                AutomationElementCollection edits = root.FindAll(
                    TreeScope.Descendants,
                    new PropertyCondition(AutomationElement.ControlTypeProperty, ControlType.Edit));

                AutomationElement bottomMost = null;
                double bottom = double.MinValue;
                foreach (AutomationElement edit in edits)
                {
                    if (!IsVisibleEdit(edit))
                    {
                        continue;
                    }

                    System.Windows.Rect bounds = edit.Current.BoundingRectangle;
                    if (bounds.Bottom > bottom)
                    {
                        bottom = bounds.Bottom;
                        bottomMost = edit;
                    }
                }

                return bottomMost;
            }
            catch (ElementNotAvailableException)
            {
                return null;
            }
        }

        private static bool BelongsToWindow(AutomationElement element, IntPtr whatsappWindow)
        {
            try
            {
                AutomationElement current = element;
                while (current != null)
                {
                    if (new IntPtr(current.Current.NativeWindowHandle) == whatsappWindow)
                    {
                        return true;
                    }
                    current = TreeWalker.RawViewWalker.GetParent(current);
                }
            }
            catch (ElementNotAvailableException)
            {
                return false;
            }

            return false;
        }

        private static bool IsVisibleEdit(AutomationElement element)
        {
            if (element == null)
            {
                return false;
            }

            try
            {
                return !element.Current.IsOffscreen && element.Current.IsEnabled &&
                    element.Current.BoundingRectangle.Width > 0 && element.Current.BoundingRectangle.Height > 0 &&
                    element.Current.ControlType == ControlType.Edit;
            }
            catch (ElementNotAvailableException)
            {
                return false;
            }
        }

        private static IntPtr WaitForForegroundWhatsAppWindow(TimeSpan timeout)
        {
            DateTime deadline = DateTime.UtcNow.Add(timeout);
            while (DateTime.UtcNow < deadline)
            {
                IntPtr foreground = GetForegroundWindow();
                if (IsWhatsAppWindow(foreground))
                {
                    return foreground;
                }
                Thread.Sleep(200);
            }

            return IntPtr.Zero;
        }

        private static bool IsWhatsAppWindow(IntPtr window)
        {
            if (window == IntPtr.Zero)
            {
                return false;
            }

            return GetWindowTitle(window).IndexOf("WhatsApp", StringComparison.OrdinalIgnoreCase) >= 0;
        }

        private static void FocusWindow(IntPtr window)
        {
            ShowWindow(window, 9); // SW_RESTORE
            BringWindowToTop(window);
            SetForegroundWindow(window);
        }

        private static string GetWindowTitle(IntPtr window)
        {
            System.Text.StringBuilder builder = new System.Text.StringBuilder(512);
            GetWindowText(window, builder, builder.Capacity);
            return builder.ToString();
        }

        private static void SendControlV()
        {
            INPUT[] inputs = new INPUT[]
            {
                KeyboardInput(VirtualKey.Control, 0),
                KeyboardInput(VirtualKey.V, 0),
                KeyboardInput(VirtualKey.V, KeyUp),
                KeyboardInput(VirtualKey.Control, KeyUp)
            };

            uint sent = SendInput((uint)inputs.Length, inputs, Marshal.SizeOf(typeof(INPUT)));
            if (sent != inputs.Length)
            {
                throw new InvalidOperationException("Windows menolak perintah paste.");
            }
        }

        private static INPUT KeyboardInput(ushort virtualKey, uint flags)
        {
            return new INPUT
            {
                type = 1, // INPUT_KEYBOARD
                data = new InputUnion
                {
                    keyboard = new KEYBDINPUT
                    {
                        wVk = virtualKey,
                        wScan = 0,
                        dwFlags = flags,
                        time = 0,
                        dwExtraInfo = IntPtr.Zero
                    }
                }
            };
        }

        private static bool PathsEqual(string first, string second)
        {
            return string.Equals(
                Path.GetFullPath(first).TrimEnd(Path.DirectorySeparatorChar),
                Path.GetFullPath(second).TrimEnd(Path.DirectorySeparatorChar),
                StringComparison.OrdinalIgnoreCase);
        }

        private static string Quote(string value)
        {
            return "\"" + value.Replace("\"", "\\\"") + "\"";
        }

        private static void ShowError(string message)
        {
            MessageBox.Show(message, ProductName, MessageBoxButtons.OK, MessageBoxIcon.Warning);
        }

        private const uint KeyUp = 0x0002;

        private static class VirtualKey
        {
            internal const ushort Control = 0x11;
            internal const ushort V = 0x56;
        }

        [StructLayout(LayoutKind.Sequential)]
        private struct INPUT
        {
            internal uint type;
            internal InputUnion data;
        }

        [StructLayout(LayoutKind.Explicit)]
        private struct InputUnion
        {
            [FieldOffset(0)]
            internal KEYBDINPUT keyboard;
        }

        [StructLayout(LayoutKind.Sequential)]
        private struct KEYBDINPUT
        {
            internal ushort wVk;
            internal ushort wScan;
            internal uint dwFlags;
            internal uint time;
            internal IntPtr dwExtraInfo;
        }

        [DllImport("user32.dll", SetLastError = true)]
        private static extern uint SendInput(uint numberOfInputs, INPUT[] inputs, int sizeOfInputStructure);

        [DllImport("user32.dll")]
        private static extern IntPtr GetForegroundWindow();

        [DllImport("user32.dll")]
        private static extern bool SetForegroundWindow(IntPtr window);

        [DllImport("user32.dll")]
        private static extern bool BringWindowToTop(IntPtr window);

        [DllImport("user32.dll")]
        private static extern bool ShowWindow(IntPtr window, int command);

        [DllImport("user32.dll", CharSet = CharSet.Unicode)]
        private static extern int GetWindowText(IntPtr window, System.Text.StringBuilder text, int maximumCount);
    }
}
