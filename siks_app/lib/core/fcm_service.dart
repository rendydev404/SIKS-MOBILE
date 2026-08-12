import 'dart:convert';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'constants.dart';

Future<void>? _firebaseInitialization;

/// Starts Firebase once and shares the same work between startup and FCM.
/// This lets the first Flutter frame render while Firebase initializes.
Future<void> initializeFirebase() {
  return _firebaseInitialization ??= Firebase.initializeApp();
}

/// Handler untuk pesan FCM ketika app dalam state terminated/background
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await initializeFirebase();
  debugPrint('[FCM] Background message: ${message.messageId}');
}

class FcmService {
  FcmService._();
  static final FcmService instance = FcmService._();

  final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  final FlutterLocalNotificationsPlugin _localNotif =
      FlutterLocalNotificationsPlugin();

  /// Callback ketika notifikasi di-tap → membuka URL tertentu di WebView
  Function(String url)? onNotificationTap;

  // ─── Init ────────────────────────────────────────────────────────────────
  Future<void> init({required Function(String url) onTap}) async {
    await initializeFirebase();
    onNotificationTap = onTap;

    // 1. Request permission (Android 13+)
    final settings = await _messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );
    debugPrint('[FCM] Permission: ${settings.authorizationStatus}');

    // 2. Init local notifications channel
    await _initLocalNotifications();

    // 3. Get & save token
    final token = await _messaging.getToken();
    if (token != null) {
      await _saveAndRegisterToken(token);
    }

    // 4. Listen token refresh
    _messaging.onTokenRefresh.listen(_saveAndRegisterToken);

    // 5. Foreground messages
    FirebaseMessaging.onMessage.listen(_handleForegroundMessage);

    // 6. Background message tap
    FirebaseMessaging.onMessageOpenedApp.listen(_handleNotificationTap);

    // 7. Terminated state tap
    final initial = await _messaging.getInitialMessage();
    if (initial != null) _handleNotificationTap(initial);
  }

  // ─── Local Notifications Setup ───────────────────────────────────────────
  Future<void> _initLocalNotifications() async {
    const androidInit = AndroidInitializationSettings('@mipmap/ic_launcher');
    const initSettings = InitializationSettings(android: androidInit);

    await _localNotif.initialize(
      initSettings,
      onDidReceiveNotificationResponse: (details) {
        final payload = details.payload;
        if (payload != null && payload.isNotEmpty) {
          onNotificationTap?.call(payload);
        }
      },
    );

    // Buat notification channel Android
    const channel = AndroidNotificationChannel(
      'siks_channel',
      'SIKS Notifikasi',
      description: 'Notifikasi pembayaran SPP dan pengumuman sekolah',
      importance: Importance.high,
    );
    await _localNotif
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);
  }

  // ─── Foreground Message Handler ──────────────────────────────────────────
  Future<void> _handleForegroundMessage(RemoteMessage message) async {
    debugPrint('[FCM] Foreground: ${message.notification?.title}');
    final notif = message.notification;
    if (notif == null) return;

    final url = message.data['url'] as String? ?? AppConstants.baseUrl;

    await _localNotif.show(
      message.hashCode,
      notif.title,
      notif.body,
      NotificationDetails(
        android: AndroidNotificationDetails(
          'siks_channel',
          'SIKS Notifikasi',
          channelDescription: 'Notifikasi pembayaran SPP dan pengumuman sekolah',
          importance: Importance.high,
          priority: Priority.high,
          icon: '@mipmap/ic_launcher',
        ),
      ),
      payload: url,
    );
  }

  // ─── Notification Tap Handler ─────────────────────────────────────────────
  void _handleNotificationTap(RemoteMessage message) {
    final url = message.data['url'] as String? ?? AppConstants.baseUrl;
    debugPrint('[FCM] Notification tapped, navigating to: $url');
    onNotificationTap?.call(url);
  }

  // ─── Token Management ─────────────────────────────────────────────────────
  Future<void> _saveAndRegisterToken(String token) async {
    debugPrint('[FCM] Token: $token');
    final prefs = await SharedPreferences.getInstance();
    final oldToken = prefs.getString(AppConstants.keyFcmToken);

    if (oldToken == token) return; // Token tidak berubah

    await prefs.setString(AppConstants.keyFcmToken, token);
    await _registerTokenToServer(token);
  }

  Future<void> _registerTokenToServer(String token) async {
    try {
      final response = await http.post(
        Uri.parse(AppConstants.fcmRegisterUrl),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({'fcm_token': token}),
      );
      debugPrint('[FCM] Register token response: ${response.statusCode}');
    } catch (e) {
      debugPrint('[FCM] Failed to register token: $e');
    }
  }

  Future<String?> get currentToken async => _messaging.getToken();
}
