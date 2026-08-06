import 'package:app_links/app_links.dart';
import 'package:flutter/material.dart';
import 'constants.dart';

/// Service untuk handle deep links: siks:// dan https://sikssmkalamin...
class DeepLinkService {
  DeepLinkService._();
  static final DeepLinkService instance = DeepLinkService._();

  final AppLinks _appLinks = AppLinks();
  Function(String url)? onDeepLink;

  Future<void> init({required Function(String url) onLink}) async {
    onDeepLink = onLink;

    // Handle initial link (app dibuka dari link ketika terminated)
    try {
      final initialLink = await _appLinks.getInitialLink();
      if (initialLink != null) {
        _handleLink(initialLink.toString());
      }
    } catch (e) {
      debugPrint('[DeepLink] Initial link error: $e');
    }

    // Handle link ketika app sudah running
    _appLinks.uriLinkStream.listen(
      (uri) => _handleLink(uri.toString()),
      onError: (e) => debugPrint('[DeepLink] Stream error: $e'),
    );
  }

  void _handleLink(String link) {
    debugPrint('[DeepLink] Received: $link');

    // Konversi siks:// ke https://
    String url = link;
    if (link.startsWith('${AppConstants.deepLinkScheme}://')) {
      url = link.replaceFirst(
        '${AppConstants.deepLinkScheme}://${AppConstants.deepLinkHost}',
        AppConstants.baseUrl.replaceAll(RegExp(r'/$'), ''),
      );
    }

    // Pastikan URL valid
    if (url.startsWith('http://') || url.startsWith('https://')) {
      onDeepLink?.call(url);
    }
  }
}
