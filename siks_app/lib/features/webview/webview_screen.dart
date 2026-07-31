import 'dart:convert';
import 'dart:io';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:flutter_cache_manager/flutter_cache_manager.dart';
import 'package:image_picker/image_picker.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:flutter/services.dart';
import 'package:path_provider/path_provider.dart';
import '../../core/constants.dart';
import '../../core/fcm_service.dart';
import '../../widgets/loading_overlay.dart';
import '../../widgets/no_internet_page.dart';
class WebViewScreen extends StatefulWidget {
  final String? initialUrl;
  const WebViewScreen({super.key, this.initialUrl});

  @override
  State<WebViewScreen> createState() => WebViewScreenState();
}

class WebViewScreenState extends State<WebViewScreen> {
  late final WebViewController _controller;
  final ImagePicker _imagePicker = ImagePicker();
  static const MethodChannel _waChannel = MethodChannel('id.smkalamin.siks/whatsapp');

  bool _isLoading = true;
  bool _hasError = false;
  bool _isOffline = false;

  @override
  void initState() {
    super.initState();
    _checkConnectivity();
    _initWebView();
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Connectivity
  // ─────────────────────────────────────────────────────────────────────────
  Future<void> _checkConnectivity() async {
    final result = await Connectivity().checkConnectivity();
    if (mounted) {
      setState(() => _isOffline = result.contains(ConnectivityResult.none));
    }
    Connectivity().onConnectivityChanged.listen((results) {
      if (!mounted) return;
      final offline = results.contains(ConnectivityResult.none);
      setState(() => _isOffline = offline);
      if (!offline && _hasError) _controller.reload();
    });
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Init WebView
  // ─────────────────────────────────────────────────────────────────────────
  void _initWebView() {
    final startUrl = widget.initialUrl ?? AppConstants.baseUrl;
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(const Color(0xFF0f172a))
      ..setUserAgent(
        'Mozilla/5.0 (Linux; Android 11; Mobile) AppleWebKit/537.36 '
        '(KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36 '
        '${AppConstants.userAgentSuffix}',
      )
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (_) {
            if (mounted) setState(() { _isLoading = true; _hasError = false; });
          },
          onPageFinished: (_) {
            if (mounted) setState(() => _isLoading = false);
            _injectBridge();
          },
          onWebResourceError: (_) {
            if (mounted) setState(() { _isLoading = false; _hasError = true; });
          },
          onNavigationRequest: _handleNavigation,
        ),
      )
      ..addJavaScriptChannel('ShareChannel',
          onMessageReceived: (m) => _downloadAndShare(m.message))
      ..addJavaScriptChannel('CameraChannel',
          onMessageReceived: (_) => _showImageSourceDialog())
      ..addJavaScriptChannel('NotificationChannel',
          onMessageReceived: (_) => _sendFcmTokenToWeb())
      ..addJavaScriptChannel('WhatsAppShareChannel',
          onMessageReceived: (m) => _shareToWhatsAppNative(m.message))
      ..loadRequest(Uri.parse(startUrl));
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Navigation delegate
  // ─────────────────────────────────────────────────────────────────────────
  NavigationDecision _handleNavigation(NavigationRequest req) {
    final url = req.url;
    for (final p in AppConstants.externalUrlPrefixes) {
      if (url.startsWith(p)) { _launchExternal(url); return NavigationDecision.prevent; }
    }
    for (final ext in AppConstants.downloadExtensions) {
      if (url.toLowerCase().contains(ext)) { _downloadAndShare(url); return NavigationDecision.prevent; }
    }
    return NavigationDecision.navigate;
  }

  // ─────────────────────────────────────────────────────────────────────────
  // JS Bridge injection
  // ─────────────────────────────────────────────────────────────────────────
  Future<void> _injectBridge() async {
    await _controller.runJavaScript(r'''
      (function() {
        document.body.classList.add('native-app-mode');
        window.isNativeApp = true;

        // Intercept file inputs
        function attachFileInputs() {
          document.querySelectorAll('input[type="file"]').forEach(function(input) {
            if (input._siksHooked) return;
            input._siksHooked = true;
            input.addEventListener('click', function(e) {
              e.preventDefault();
              e.stopPropagation();
              window.CameraChannel.postMessage(input.getAttribute('accept') || 'image/*');
            });
          });
        }
        attachFileInputs();

        // Observe DOM mutations for dynamically added inputs
        var obs = new MutationObserver(attachFileInputs);
        obs.observe(document.body, { childList: true, subtree: true });

        // Intercept download links
        document.querySelectorAll('a[href]').forEach(function(a) {
          if (/\.(pdf|xlsx|xls|csv|doc|docx)(\?|$)/i.test(a.href)) {
            a.addEventListener('click', function(e) {
              e.preventDefault();
              window.ShareChannel.postMessage(a.href);
            });
          }
        });

        // Request FCM token
        if (window.NotificationChannel) {
          window.NotificationChannel.postMessage('request_token');
        }
      })();
    ''');
  }

  // ─────────────────────────────────────────────────────────────────────────
  // FCM token → web
  // ─────────────────────────────────────────────────────────────────────────
  Future<void> _sendFcmTokenToWeb() async {
    final token = await FcmService.instance.currentToken;
    if (token != null) {
      await _controller.runJavaScript(
          "window.onFcmToken && window.onFcmToken('$token');");
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // WhatsApp Share Native
  // ─────────────────────────────────────────────────────────────────────────
  Future<void> _shareToWhatsAppNative(String message) async {
    try {
      final data = jsonDecode(message);
      final base64Image = data['base64'];
      final text = data['text'] ?? '';
      var phone = data['phone'] ?? '';

      // Clean phone number: remove non-digits
      phone = phone.replaceAll(RegExp(r'[^0-9]'), '');
      // Force start with 62 if starts with 0
      if (phone.startsWith('0')) {
        phone = '62${phone.substring(1)}';
      }

      // write base64 to temp file
      final bytes = base64Decode(base64Image);
      final dir = await getTemporaryDirectory();
      final file = File('${dir.path}/kwitansi.png');
      await file.writeAsBytes(bytes);

      await _waChannel.invokeMethod('shareToWhatsApp', {
        'imagePath': file.path,
        'text': text,
        'phone': phone,
      });
    } catch (e) {
      debugPrint('[WebView] WA Share Error: $e');
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Image picker
  // ─────────────────────────────────────────────────────────────────────────
  void _showImageSourceDialog() {
    showModalBottomSheet(
      context: context,
      backgroundColor: const Color(0xFF1e293b),
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 40, height: 4,
              margin: const EdgeInsets.symmetric(vertical: 12),
              decoration: BoxDecoration(color: Colors.white24,
                  borderRadius: BorderRadius.circular(2)),
            ),
            const Text('Pilih Sumber Gambar',
                style: TextStyle(color: Colors.white, fontSize: 16,
                    fontWeight: FontWeight.w600)),
            const SizedBox(height: 8),
            ListTile(
              leading: const Icon(Icons.camera_alt_rounded,
                  color: Color(0xFF6366f1)),
              title: const Text('Kamera',
                  style: TextStyle(color: Colors.white)),
              onTap: () {
                Navigator.pop(context);
                _pickImage(ImageSource.camera);
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_rounded,
                  color: Color(0xFF6366f1)),
              title: const Text('Galeri',
                  style: TextStyle(color: Colors.white)),
              onTap: () {
                Navigator.pop(context);
                _pickImage(ImageSource.gallery);
              },
            ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
  }

  Future<void> _pickImage(ImageSource source) async {
    try {
      final picked = await _imagePicker.pickImage(
          source: source, imageQuality: 85, maxWidth: 1920);
      if (picked == null) return;

      final bytes = await picked.readAsBytes();
      final b64 = base64Encode(bytes);

      await _controller.runJavaScript('''
        (function() {
          var b64 = "$b64";
          var byteStr = atob(b64);
          var ab = new ArrayBuffer(byteStr.length);
          var ia = new Uint8Array(ab);
          for (var i = 0; i < byteStr.length; i++) ia[i] = byteStr.charCodeAt(i);
          var blob = new Blob([ab], {type: "image/jpeg"});
          var file = new File([blob], "bukti_transfer.jpg", {type: "image/jpeg"});
          var dt = new DataTransfer();
          dt.items.add(file);
          var input = document.querySelector('input[type="file"]');
          if (input) {
            input.files = dt.files;
            input.dispatchEvent(new Event('change', {bubbles: true}));
          }
        })();
      ''');
    } catch (e) {
      debugPrint('[WebView] Image pick error: $e');
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Download & Share
  // ─────────────────────────────────────────────────────────────────────────
  Future<void> _downloadAndShare(String url) async {
    if (!mounted) return;
    final messenger = ScaffoldMessenger.of(context);
    messenger.showSnackBar(const SnackBar(
      content: Row(children: [
        SizedBox(width: 16, height: 16,
            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)),
        SizedBox(width: 12),
        Text('Menyiapkan file...'),
      ]),
      duration: Duration(seconds: 30),
    ));
    try {
      final file = await DefaultCacheManager().getSingleFile(url);
      messenger.hideCurrentSnackBar();
      await Share.shareXFiles([XFile(file.path)],
          text: 'Laporan SIKS SMK Al Amin');
    } catch (e) {
      messenger.hideCurrentSnackBar();
      messenger.showSnackBar(SnackBar(content: Text('Gagal: $e')));
    }
  }

  Future<void> _launchExternal(String url) async {
    try {
      final uri = Uri.parse(url);
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      }
    } catch (e) {
      debugPrint('[WebView] Launch error: $e');
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Public: navigate from outside (FCM / deep link)
  // ─────────────────────────────────────────────────────────────────────────
  void navigateTo(String url) =>
      _controller.loadRequest(Uri.parse(url));

  // ─────────────────────────────────────────────────────────────────────────
  // Back button
  // ─────────────────────────────────────────────────────────────────────────
  Future<bool> _onWillPop() async {
    if (await _controller.canGoBack()) {
      _controller.goBack();
      return false;
    }
    final exit = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        backgroundColor: const Color(0xFF1e293b),
        title: const Text('Keluar App?',
            style: TextStyle(color: Colors.white)),
        content: const Text(
            'Yakin ingin keluar dari SIKS Al Amin?',
            style: TextStyle(color: Colors.white70)),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Batal')),
          TextButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Keluar',
                  style: TextStyle(color: Colors.redAccent))),
        ],
      ),
    );
    return exit ?? false;
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Build
  // ─────────────────────────────────────────────────────────────────────────
  @override
  Widget build(BuildContext context) {
    if (_isOffline) {
      return NoInternetPage(onRetry: () {
        setState(() { _isOffline = false; _hasError = false; });
        _controller.reload();
      });
    }

    return WillPopScope(
      onWillPop: _onWillPop,
      child: Scaffold(
        backgroundColor: const Color(0xFF0f172a),
        body: Stack(
          children: [
            WebViewWidget(controller: _controller),
            if (_isLoading) const LoadingOverlay(),
            if (_hasError && !_isLoading)
              NoInternetPage(onRetry: () {
                setState(() => _hasError = false);
                _controller.reload();
              }),
          ],
        ),
      ),
    );
  }
}
