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

  // Start Firebase in parallel with Flutter's first frame. FCM waits for this
  // shared initialization before it accesses Firebase APIs.
  unawaited(initializeFirebase());

  // Register background message handler (must be top-level)
  FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

  // Channels have to exist before the first message arrives, otherwise Android
  // drops it without a trace. Cheap, and independent of Firebase being ready.
  unawaited(ensureNotificationChannels());

  runApp(const SiksApp());
}
