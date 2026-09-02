import 'dart:async';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'app.dart';
import 'core/fcm_service.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Lock orientation to portrait
  await SystemChrome.setPreferredOrientations([
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
  ]);

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
