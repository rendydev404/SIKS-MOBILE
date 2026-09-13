using System;

namespace SiksWhatsAppHelper
{
    internal static class SiksWaProtocolTests
    {
        private static int failures;

        private static int Main()
        {
            ValidUriIsAccepted();
            BoundaryLengthsAreAccepted();
            InvalidUrisAreRejected();

            if (failures > 0)
            {
                Console.Error.WriteLine("FAILED: " + failures + " protocol test(s).");
                return 1;
            }

            Console.WriteLine("PASS: SiksWaProtocol tests");
            return 0;
        }

        private static void ValidUriIsAccepted()
        {
            string phone;
            AssertTrue(
                SiksWaProtocol.TryParseComposeUri("sikswa://compose?phone=628123456789", out phone),
                "valid URI accepted");
            AssertEqual("628123456789", phone, "valid URI preserves phone");

            AssertTrue(
                SiksWaProtocol.TryParseComposeUri("SIKSWA://COMPOSE/?phone=628123456789", out phone),
                "scheme and host are case-insensitive");
        }

        private static void BoundaryLengthsAreAccepted()
        {
            string phone;
            AssertTrue(
                SiksWaProtocol.TryParseComposeUri("sikswa://compose?phone=62812345678", out phone),
                "minimum valid length accepted");
            AssertTrue(
                SiksWaProtocol.TryParseComposeUri("sikswa://compose?phone=628123456789012", out phone),
                "maximum valid length accepted");
        }

        private static void InvalidUrisAreRejected()
        {
            string phone;
            string[] invalid = new[]
            {
                "https://compose?phone=628123456789",
                "sikswa://other?phone=628123456789",
                "sikswa://compose/path?phone=628123456789",
                "sikswa://compose?phone=08123456789",
                "sikswa://compose?phone=628123",
                "sikswa://compose?phone=6281234567890123",
                "sikswa://compose?phone=628123456789&phone=628987654321",
                "sikswa://compose?phone=628123456789&extra=value",
                "sikswa://compose?phone=628123456789#unexpected",
                "sikswa://compose"
            };

            foreach (string uri in invalid)
            {
                AssertFalse(SiksWaProtocol.TryParseComposeUri(uri, out phone), "reject " + uri);
            }
        }

        private static void AssertTrue(bool value, string name)
        {
            if (!value)
            {
                failures++;
                Console.Error.WriteLine("FAIL: " + name);
            }
        }

        private static void AssertFalse(bool value, string name)
        {
            AssertTrue(!value, name);
        }

        private static void AssertEqual(string expected, string actual, string name)
        {
            AssertTrue(string.Equals(expected, actual, StringComparison.Ordinal), name);
        }
    }
}
