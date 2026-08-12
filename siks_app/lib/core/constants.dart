/// Konstanta global aplikasi SIKS Al Amin
class AppConstants {
  AppConstants._();

  // ─── URL ─────────────────────────────────────────────────────────────────
  static const String baseUrl =
      'https://sikssmkalamin.absensismkalamin.my.id/';

  static const String siswaPortalUrl =
      'https://sikssmkalamin.absensismkalamin.my.id/siswa-portal/';

  // ─── App Info ────────────────────────────────────────────────────────────
  static const String appName = 'SIKS Al Amin';
  static const String packageName = 'id.smkalamin.siks';
  static const String userAgentSuffix = 'SIKSApp/1.0 Android';

  // ─── Deep Link Scheme ────────────────────────────────────────────────────
  static const String deepLinkScheme = 'siks';
  static const String deepLinkHost = 'sikssmkalamin.absensismkalamin.my.id';

  // ─── Storage Keys ────────────────────────────────────────────────────────
  static const String keyOnboardingDone = 'onboarding_done';
  static const String keyFcmToken = 'fcm_token';

  // ─── External URLs yang harus dibuka di luar WebView ─────────────────────
  static const List<String> externalUrlPrefixes = [
    'https://wa.me',
    'https://api.whatsapp.com',
    'tel:',
    'mailto:',
    'whatsapp:',
  ];

  // ─── URL yang trigger download (intercept oleh Flutter) ──────────────────
  static const List<String> downloadExtensions = [
    '.pdf', '.xls', '.xlsx', '.csv', '.doc', '.docx',
  ];
}
