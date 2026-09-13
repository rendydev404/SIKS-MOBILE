using System;
using System.Reflection;
using System.Runtime.InteropServices;

namespace SiksWhatsAppHelper.Tests
{
    internal static class SiksWaNativeLayoutTests
    {
        private static int Main(string[] args)
        {
            if (args.Length != 1)
            {
                Console.Error.WriteLine("Path helper hasil build wajib diberikan.");
                return 1;
            }

            Assembly helper = Assembly.LoadFrom(args[0]);
            Type inputType = helper.GetType("SiksWhatsAppHelper.Program+INPUT", true);
            int actualSize = Marshal.SizeOf(inputType);
            int expectedSize = IntPtr.Size == 8 ? 40 : 28;

            if (actualSize != expectedSize)
            {
                Console.Error.WriteLine(
                    "Ukuran INPUT salah: aktual " + actualSize + ", seharusnya " + expectedSize + ".");
                return 1;
            }

            Console.WriteLine("PASS: INPUT layout " + actualSize + " bytes");
            return 0;
        }
    }
}
