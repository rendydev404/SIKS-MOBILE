import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../core/constants.dart';

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
    final prefs = await SharedPreferences.getInstance();
    final onboardingDone = prefs.getBool(AppConstants.keyOnboardingDone) ?? false;
    if (!mounted) return;

    Navigator.pushReplacementNamed(
      context,
      onboardingDone ? '/home' : '/onboarding',
    );
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(backgroundColor: Color(0xFFF8FAFC));
  }
}
