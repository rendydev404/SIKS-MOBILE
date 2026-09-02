import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../core/constants.dart';
import '../../widgets/loading_overlay.dart';

/// A minimal hand-off from Android's native splash to the first app screen.
/// Keeping this screen static avoids an extra multi-second animation before the
/// WebView starts loading, which is especially noticeable on lower-end phones.
class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  @override
  void initState() {
    super.initState();
    _navigate();
  }

  Future<void> _navigate() async {
    // Default to the app itself: if storage cannot be read there is no reason
    // to hold someone on a blank screen, and the worst case is that onboarding
    // is skipped once.
    var onboardingDone = true;
    try {
      final prefs = await SharedPreferences.getInstance()
          .timeout(const Duration(seconds: 5));
      onboardingDone = prefs.getBool(AppConstants.keyOnboardingDone) ?? false;
    } catch (error) {
      // Reading preferences can fail or hang on the launch right after the
      // app's data is cleared. This screen draws nothing, so an error escaping
      // here used to leave the app sitting on a white screen for good.
      debugPrint('[Splash] Could not read preferences: $error');
    }
    if (!mounted) return;

    Navigator.pushReplacementNamed(
      context,
      onboardingDone ? '/home' : '/onboarding',
    );
  }

  @override
  Widget build(BuildContext context) {
    // Draws the logo and a spinner rather than an empty Scaffold. An empty one
    // is indistinguishable from the app having rendered nothing at all, which
    // made every startup problem look like the same white screen.
    return const Scaffold(
      backgroundColor: Color(0xFFF8FAFC),
      body: LoadingOverlay(),
    );
  }
}
