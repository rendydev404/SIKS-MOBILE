using System;
using System.Text.RegularExpressions;

namespace SiksWhatsAppHelper
{
    internal static class SiksWaProtocol
    {
        private static readonly Regex IndonesianWhatsAppNumber =
            new Regex(@"^62\d{9,13}$", RegexOptions.Compiled | RegexOptions.CultureInvariant);

        internal static bool TryParseComposeUri(string rawUri, out string phone)
        {
            phone = null;

            Uri uri;
            if (!Uri.TryCreate(rawUri, UriKind.Absolute, out uri) ||
                !string.Equals(uri.Scheme, "sikswa", StringComparison.OrdinalIgnoreCase) ||
                !string.Equals(uri.Host, "compose", StringComparison.OrdinalIgnoreCase) ||
                !string.IsNullOrEmpty(uri.UserInfo) || uri.Port != -1 ||
                !string.IsNullOrEmpty(uri.Fragment) || uri.AbsolutePath != "/")
            {
                return false;
            }

            bool foundPhone = false;
            string query = uri.Query.TrimStart('?');
            if (string.IsNullOrEmpty(query))
            {
                return false;
            }

            foreach (string item in query.Split('&'))
            {
                string[] parts = item.Split(new[] { '=' }, 2);
                if (parts.Length != 2 || !string.Equals(Uri.UnescapeDataString(parts[0]), "phone", StringComparison.Ordinal))
                {
                    return false;
                }

                if (foundPhone)
                {
                    return false;
                }

                phone = Uri.UnescapeDataString(parts[1]);
                foundPhone = true;
            }

            return foundPhone && IndonesianWhatsAppNumber.IsMatch(phone);
        }
    }
}
