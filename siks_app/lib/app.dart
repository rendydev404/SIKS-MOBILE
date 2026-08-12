import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'core/fcm_service.dart';
import 'core/deep_link_service.dart';
import 'features/splash/splash_screen.dart';
import 'features/onboarding/onboarding_screen.dart';
import 'features/webview/webview_screen.dart';

class SiksApp extends StatelessWidget {
  const SiksApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'SIKS Al Amin',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF6366f1),
          brightness: Brightness.light,
        ),
        useMaterial3: true,
        fontFamily: 'Roboto',
      ),
      // Full screen (behind status bar)
      builder: (context, child) {
        SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
          statusBarColor: Colors.transparent,
          statusBarIconBrightness: Brightness.dark,
          systemNavigationBarColor: Color(0xFFF8FAFC),
          systemNavigationBarIconBrightness: Brightness.dark,
        ));
        return child!;
      },
      initialRoute: '/splash',
      routes: {
        '/splash': (_) => const SplashScreen(),
        '/onboarding': (_) => const OnboardingScreen(),
        '/home': (_) => const _HomeWrapper(),
      },
    );
  }
}

/// Wrapper yang setup FCM & DeepLink setelah WebView mount
class _HomeWrapper extends StatefulWidget {
  const _HomeWrapper();

  @override
  State<_HomeWrapper> createState() => _HomeWrapperState();
}

class _HomeWrapperState extends State<_HomeWrapper> {
  final GlobalKey<WebViewScreenState> _webViewKey = GlobalKey();
  String? _pendingUrl;
  bool _deferredServicesStarted = false;

  @override
  void initState() {
    super.initState();
    unawaited(_initDeepLinks());
  }

  Future<void> _initDeepLinks() async {
    await DeepLinkService.instance.init(
      onLink: _navigateFromExternal,
    );
  }

  void _navigateFromExternal(String url) {
    final state = _webViewKey.currentState;
    if (state != null) {
      state.navigateTo(url);
    } else {
      _pendingUrl = url;
    }
  }

  void _startDeferredServices() {
    if (_deferredServicesStarted) return;
    _deferredServicesStarted = true;
    unawaited(_initFcmAfterFirstPaint());
  }

  Future<void> _initFcmAfterFirstPaint() async {
    // Avoid competing with the first WebView render on lower-end devices.
    await Future<void>.delayed(const Duration(milliseconds: 800));
    try {
      await FcmService.instance.init(
        onTap: _navigateFromExternal,
      );
      await _webViewKey.currentState?.sendFcmToken();
    } catch (error) {
      debugPrint('[FCM] Deferred initialization failed: $error');
    }
  }

  @override
  Widget build(BuildContext context) {
    return WebViewScreen(
      key: _webViewKey,
      initialUrl: _pendingUrl,
      onFirstPageFinished: _startDeferredServices,
    );
  }
}
