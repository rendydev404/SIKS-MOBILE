import 'dart:async';
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
  final VoidCallback? onFirstPageFinished;

  const WebViewScreen({
    super.key,
    this.initialUrl,
    this.onFirstPageFinished,
  });

  @override
  State<WebViewScreen> createState() => WebViewScreenState();
}

class WebViewScreenState extends State<WebViewScreen> {
  late final WebViewController _controller;
  final ImagePicker _imagePicker = ImagePicker();
  static const MethodChannel _waChannel = MethodChannel('id.smkalamin.siks/whatsapp');

  final ValueNotifier<bool> _isLoading = ValueNotifier(true);
  final ValueNotifier<bool> _hasError = ValueNotifier(false);
  final ValueNotifier<bool> _isOffline = ValueNotifier(false);
  StreamSubscription<List<ConnectivityResult>>? _connectivitySubscription;
  bool _hasCompletedInitialLoad = false;
  bool _reportedFirstPageFinished = false;

  @override
  void dispose() {
    FcmService.instance.onTokenAvailable = null;
    _isLoading.dispose();
    _hasError.dispose();
    _isOffline.dispose();
    _connectivitySubscription?.cancel();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    FcmService.instance.onTokenAvailable = (_) => unawaited(_sendFcmTokenToWeb());
    _checkConnectivity();
    _initWebView();
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Connectivity
  // ─────────────────────────────────────────────────────────────────────────
  Future<void> _checkConnectivity() async {
    final result = await Connectivity().checkConnectivity();
    if (mounted) {
      _isOffline.value = result.contains(ConnectivityResult.none);
    }
    _connectivitySubscription = Connectivity().onConnectivityChanged.listen((results) {
      if (!mounted) return;
      final offline = results.contains(ConnectivityResult.none);
      if (_isOffline.value != offline) {
        _isOffline.value = offline;
        if (!offline && _hasError.value) _controller.reload();
      }
    });
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Init WebView
  // ─────────────────────────────────────────────────────────────────────────
  void _initWebView() {
    final startUrl = widget.initialUrl ?? AppConstants.baseUrl;
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(const Color(0xFFF8FAFC))
      ..setUserAgent(
        'Mozilla/5.0 (Linux; Android 11; Mobile) AppleWebKit/537.36 '
        '(KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36 '
        '${AppConstants.userAgentSuffix}',
      )
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (_) {
            if (mounted) _hasError.value = false;
            if (!_hasCompletedInitialLoad && mounted) _isLoading.value = true;
          },
          onPageFinished: (_) {
            _hasCompletedInitialLoad = true;
            if (mounted) _isLoading.value = false;
            _injectBridge();
            unawaited(_sendFcmTokenToWeb());
            _injectImageCaptureScript();
            if (!_reportedFirstPageFinished) {
              _reportedFirstPageFinished = true;
              widget.onFirstPageFinished?.call();
            }
          },
          onWebResourceError: (error) {
            if (!error.isForMainFrame) return;
            if (mounted) {
              _isLoading.value = false;
              _hasError.value = true;
            }
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

  DateTime _lastWaShareTime = DateTime.fromMillisecondsSinceEpoch(0);

  // ─────────────────────────────────────────────────────────────────────────
  // Navigation delegate
  // ─────────────────────────────────────────────────────────────────────────
  NavigationDecision _handleNavigation(NavigationRequest req) {
    final url = req.url;
    for (final p in AppConstants.externalUrlPrefixes) {
      if (url.startsWith(p)) {
        // Prevent URL Launcher if we just triggered the Native Image Share
        if (url.contains('wa.me') || url.contains('api.whatsapp.com') || url.contains('whatsapp:')) {
          if (DateTime.now().difference(_lastWaShareTime).inSeconds < 3) {
            debugPrint('[WebView] Blocked URL Launcher WA because Native Share is in progress');
            return NavigationDecision.prevent;
          }
        }
        _launchExternal(url);
        return NavigationDecision.prevent;
      }
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
        if (window._siksInitialized) return;
        window._siksInitialized = true;
        document.documentElement.classList.add('native-app-mode');
        document.body.classList.add('native-app-mode');
        window.isNativeApp = true;

        // Some portal pages use their own stylesheet. Apply the same lightweight
        // rendering rules directly so scrolling stays smooth in the WebView.
        if (!document.getElementById('siks-native-performance')) {
          var style = document.createElement('style');
          style.id = 'siks-native-performance';
          style.textContent =
            'html.native-app-mode{-webkit-tap-highlight-color:transparent}' +
            'html.native-app-mode *,html.native-app-mode *::before,html.native-app-mode *::after{' +
            'animation-duration:.01ms!important;animation-iteration-count:1!important;' +
            'transition-duration:.01ms!important;scroll-behavior:auto!important;' +
            '-webkit-backdrop-filter:none!important;backdrop-filter:none!important}';
          style.textContent +=
            'html.native-app-mode .card,html.native-app-mode .glass,' +
            'html.native-app-mode [class*="shadow"],html.native-app-mode .siswa-portal-header{' +
            'box-shadow:none!important}';
          document.head.appendChild(style);
        }

        // Use event delegation for file inputs
        document.addEventListener('click', function(e) {
          var target = e.target.closest('input[type="file"]');
          if (target) {
            e.preventDefault();
            e.stopPropagation();
            window.CameraChannel.postMessage(target.getAttribute('accept') || 'image/*');
          }
        }, true);

        // Use event delegation for download links
        document.addEventListener('click', function(e) {
          var a = e.target.closest('a[href]');
          if (a && /\.(pdf|xlsx|xls|csv|doc|docx)(\?|$)/i.test(a.href)) {
            e.preventDefault();
            window.ShareChannel.postMessage(a.href);
          }
        }, true);

        // Request FCM token
        if (window.NotificationChannel) {
          window.NotificationChannel.postMessage('request_token');
        }
      })();
    ''');
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Image Capture Injection (Intercept WA button)
  // ─────────────────────────────────────────────────────────────────────────
  void _injectImageCaptureScript() {
    _controller.runJavaScript('''
      (function() {
        if (window._siksImageInjected) return;
        window._siksImageInjected = true;

        var btn = document.getElementById('copyAndWaBtn');
        if (btn) {
            btn.addEventListener('click', function(e) {
                // STOP the website's original click handler so it doesn't run!
                e.preventDefault();
                e.stopImmediatePropagation();
                
                var card = document.getElementById('invoiceArea');
                if (card) {
                    var originalText = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Proses...';
                    
                    if (typeof html2canvas === 'undefined') {
                        // Load html2canvas if not exists
                        var script = document.createElement('script');
                        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
                        script.onload = function() {
                            captureAndSend(card, btn, originalText);
                        };
                        document.head.appendChild(script);
                    } else {
                        captureAndSend(card, btn, originalText);
                    }
                }
                return false;
            }, true); // Use capture phase to intercept before normal listeners
        }

        function captureAndSend(card, btn, originalText) {
            html2canvas(card, {
                useCORS: true, 
                scale: Math.min(window.devicePixelRatio || 1, 1.5),
                backgroundColor: '#ffffff',
                logging: false
            }).then(function(canvas) {
                var base64 = canvas.toDataURL('image/png');
                
                // Get the WA link parameters
                // Try to find the phone number and text from the PHP script if possible
                var phone = "";
                var text = "";
                
                // Try to grab the link from the original script fallback logic
                // usually inside a window.location.href or similar
                // We'll extract it directly from the html content if possible
                var pageHtml = document.documentElement.innerHTML;
                var waMatch = pageHtml.match(/https:\\/\\/wa\\.me\\/([0-9]+)\\?text=([^"']+)/);
                if (waMatch && waMatch.length >= 3) {
                    phone = waMatch[1];
                    text = decodeURIComponent(waMatch[2].replace(/\\+/g, ' '));
                }
                
                // Send to Flutter
                if (window.WhatsAppShareChannel) {
                    WhatsAppShareChannel.postMessage(JSON.stringify({
                        base64: base64,
                        phone: phone,
                        text: text
                    }));
                }
                
                // Re-enable button
                setTimeout(function() {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }, 3000);
            }).catch(function(err) {
                console.error("Canvas error:", err);
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        }
      })();
    ''');
  }

  // ─────────────────────────────────────────────────────────────────────────
  // FCM token → web
  // ─────────────────────────────────────────────────────────────────────────
  Future<void> _sendFcmTokenToWeb() async {
    // The registration endpoint relies on the current WebView login cookie.
    // A token arriving before the first page is loaded is sent onPageFinished.
    if (!_hasCompletedInitialLoad) return;
    final token = await FcmService.instance.currentToken;
    if (token != null) {
      final encodedToken = jsonEncode(token);
      await _controller.runJavaScript('''
        (function() {
          var token = $encodedToken;
          fetch('/fcm/register_token.php', {
            method: 'POST', credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({fcm_token: token, platform: 'android'})
          }).catch(function(error) { console.debug('FCM token registration failed', error); });
        })();
      ''');
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // WhatsApp Share Native
  // ─────────────────────────────────────────────────────────────────────────
  Future<void> _shareToWhatsAppNative(String message) async {
    try {
      _lastWaShareTime = DateTime.now();

      final data = jsonDecode(message);
      var base64Image = data['base64']?.toString() ?? '';
      if (base64Image.isEmpty) throw Exception('Base64 image is kosong');
      
      final text = data['text'] ?? '';
      var phone = data['phone'] ?? '';

      // Clean phone number: remove non-digits
      phone = phone.replaceAll(RegExp(r'[^0-9]'), '');
      // Force start with 62 if starts with 0
      if (phone.startsWith('0')) {
        phone = '62${phone.substring(1)}';
      }

      // Strip data URI prefix if present (e.g. data:image/png;base64,...)
      if (base64Image.contains(',')) {
        base64Image = base64Image.split(',').last;
      }
      // Remove any whitespaces/newlines from base64
      base64Image = base64Image.replaceAll(RegExp(r'\s+'), '');

      // write base64 to temp file
      final bytes = base64Decode(base64Image);
      // Use ApplicationDocumentsDirectory
      final dir = await getApplicationDocumentsDirectory();
      final file = File('${dir.path}/kwitansi.png');
      await file.writeAsBytes(bytes, flush: true);

      await _waChannel.invokeMethod('shareToWhatsApp', {
        'imagePath': file.path,
        'text': text,
        'phone': phone,
      });
    } catch (e) {
      debugPrint('[WebView] WA Share Error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text('Gagal WA: ${e.toString()}'),
        ));
      }
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Image picker
  // ─────────────────────────────────────────────────────────────────────────
  void _showImageSourceDialog() {
    showModalBottomSheet(
      context: context,
      backgroundColor: const Color(0xFFFFFFFF),
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 40, height: 4,
              margin: const EdgeInsets.symmetric(vertical: 12),
              decoration: BoxDecoration(color: const Color(0xFFE2E8F0),
                  borderRadius: BorderRadius.circular(2)),
            ),
            const Text('Pilih Sumber Gambar',
                style: TextStyle(color: Color(0xFF0F172A), fontSize: 16,
                    fontWeight: FontWeight.w600)),
            const SizedBox(height: 8),
            ListTile(
              leading: const Icon(Icons.camera_alt_rounded,
                  color: Color(0xFF6366f1)),
              title: const Text('Kamera',
                  style: TextStyle(color: Color(0xFF0F172A))),
              onTap: () {
                Navigator.pop(context);
                _pickImage(ImageSource.camera);
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_rounded,
                  color: Color(0xFF6366f1)),
              title: const Text('Galeri',
                  style: TextStyle(color: Color(0xFF0F172A))),
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
          source: source, imageQuality: 75, maxWidth: 1024);
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

  /// Sends a freshly available FCM token after deferred startup work finishes.
  Future<void> sendFcmToken() => _sendFcmTokenToWeb();

  // ─────────────────────────────────────────────────────────────────────────
  // Back button
  // ─────────────────────────────────────────────────────────────────────────
  Future<void> _handlePop() async {
    if (await _controller.canGoBack()) {
      _controller.goBack();
      return;
    }
    if (!mounted) return;
    final exit = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        backgroundColor: const Color(0xFFFFFFFF),
        title: const Text('Keluar App?',
            style: TextStyle(color: Color(0xFF0F172A))),
        content: const Text(
            'Yakin ingin keluar dari SIKS Al Amin?',
            style: TextStyle(color: Color(0xFF475569))),
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
    if (exit == true) {
      SystemNavigator.pop();
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Build
  // ─────────────────────────────────────────────────────────────────────────
  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvoked: (didPop) async {
        if (didPop) return;
        await _handlePop();
      },
      child: Scaffold(
        backgroundColor: const Color(0xFFF8FAFC),
        body: Stack(
          children: [
            WebViewWidget(controller: _controller),
            ValueListenableBuilder<bool>(
              valueListenable: _isOffline,
              builder: (context, isOffline, child) {
                if (isOffline) {
                  return NoInternetPage(onRetry: () {
                    _isOffline.value = false;
                    _hasError.value = false;
                    _isLoading.value = true;
                    _controller.reload();
                  });
                }
                return ValueListenableBuilder<bool>(
                  valueListenable: _hasError,
                  builder: (context, hasError, child) {
                    if (hasError) {
                      return NoInternetPage(onRetry: () {
                        _hasError.value = false;
                        _isLoading.value = true;
                        _controller.reload();
                      });
                    }
                    return ValueListenableBuilder<bool>(
                      valueListenable: _isLoading,
                      builder: (context, isLoading, child) {
                        return isLoading
                            ? const LoadingOverlay()
                            : const SizedBox.shrink();
                      },
                    );
                  },
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}
