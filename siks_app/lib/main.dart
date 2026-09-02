import 'dart:async';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'app.dart';
import 'core/fcm_service.dart';

Future<void> main() async {
  // Any uncaught error still has to leave something on screen. Without this a
  // failure during startup shows as a white screen with no way forward and
  // nothing to report.
  FlutterError.onError = (details) {
    FlutterError.presentError(details);
    debugPrint('[Startup] Flutter error: ${details.exception}');
  };
  ErrorWidget.builder = (details) => _StartupErrorScreen(details.exception.toString());

  runZonedGuarded(_start, (error, stack) {
    debugPrint('[Startup] Uncaught: $error');
  });
}

void _start() {
  WidgetsFlutterBinding.ensureInitialized();

  // Not awaited: this is a platform channel call, and every await before
  // runApp is a chance for the app to render nothing at all. Portrait lock
  // applies a frame later, which nobody can see.
  unawaited(
    SystemChrome.setPreferredOrientations([
      DeviceOrientation.portraitUp,
      DeviceOrientation.portraitDown,
    ]).catchError((Object error) {
      debugPrint('[Startup] Orientation lock failed: $error');
    }),
  );

  // Firebase still starts in parallel with Flutter's first frame, but the
  // background handler is registered only once it is ready: reading
  // FirebaseMessaging.instance throws while the default app does not exist,
  // and every line here runs before runApp - an exception at this point means
  // no widget is ever built and the app shows nothing but a white screen.
  // Android normally creates the default app through its own content provider
  // before Dart starts, which is why the old order usually survived, but that
  // is not something to depend on.
  unawaited(
    initializeFirebase().then((_) {
      FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);
    }).catchError((Object error) {
      debugPrint('[Startup] Firebase init failed: $error');
    }),
  );

  // Channels have to exist before the first message arrives, otherwise Android
  // drops it without a trace. Cheap, and independent of Firebase being ready.
  unawaited(
    ensureNotificationChannels().catchError((Object error) {
      debugPrint('[Startup] Notification channels failed: $error');
    }),
  );

  runApp(const SiksApp());
}

/// Shown in place of a widget that failed to build, so a crash is readable
/// instead of being a blank screen.
class _StartupErrorScreen extends StatelessWidget {
  const _StartupErrorScreen(this.message);

  final String message;

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.ltr,
      child: Container(
        color: const Color(0xFFF8FAFC),
        alignment: Alignment.center,
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline_rounded,
                color: Color(0xFFEF4444), size: 56),
            const SizedBox(height: 16),
            const Text(
              'Aplikasi gagal dimuat',
              style: TextStyle(
                color: Color(0xFF0F172A),
                fontSize: 18,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(color: Color(0xFF64748B), fontSize: 12),
            ),
            const SizedBox(height: 8),
            const Text(
              'Tutup aplikasi lalu buka kembali. Kalau tetap muncul, '
              'kirimkan pesan di atas ini.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Color(0xFF64748B), fontSize: 13),
            ),
          ],
        ),
      ),
    );
  }
}
