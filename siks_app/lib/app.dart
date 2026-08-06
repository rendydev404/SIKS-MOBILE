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
          brightness: Brightness.dark,
        ),
        useMaterial3: true,
        fontFamily: 'Roboto',
      ),
      // Full screen (behind status bar)
      builder: (context, child) {
        SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
          statusBarColor: Colors.transparent,
          statusBarIconBrightness: Brightness.light,
          systemNavigationBarColor: Color(0xFF0f172a),
          systemNavigationBarIconBrightness: Brightness.light,
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

  @override
  void initState() {
    super.initState();
    _initServices();
  }

  Future<void> _initServices() async {
    // FCM
    await FcmService.instance.init(
      onTap: (url) {
        final state = _webViewKey.currentState;
        if (state != null) {
          state.navigateTo(url);
        } else {
          _pendingUrl = url;
        }
      },
    );

    // Deep links
    await DeepLinkService.instance.init(
      onLink: (url) {
        final state = _webViewKey.currentState;
        if (state != null) {
          state.navigateTo(url);
        } else {
          _pendingUrl = url;
        }
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return WebViewScreen(
      key: _webViewKey,
      initialUrl: _pendingUrl,
    );
  }
}
