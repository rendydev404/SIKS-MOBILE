import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'constants.dart';

Future<void>? _firebaseInitialization;

/// Starts Firebase once and shares the same work between startup and FCM.
/// This lets the first Flutter frame render while Firebase initializes.
Future<void> initializeFirebase() {
  return _firebaseInitialization ??= Firebase.initializeApp();
}

const AndroidNotificationChannel _paymentChannel = AndroidNotificationChannel(
  'siks_channel',
  'SIKS Notifikasi',
  description: 'Notifikasi pembayaran SPP',
  importance: Importance.high,
);
const AndroidNotificationChannel _announcementChannel = AndroidNotificationChannel(
  'announcement_channel',
  'Pengumuman Sekolah',
  description: 'Pengumuman penting dari sekolah',
  importance: Importance.high,
);

/// Creates both notification channels. Android 8+ silently drops a message
/// naming a channel that does not exist, so this has to run before any message
/// can arrive - not once the WebView happens to have finished painting.
/// Creating a channel that already exists is a no-op.
Future<void> ensureNotificationChannels() async {
  final android = FlutterLocalNotificationsPlugin()
      .resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin>();
  if (android == null) return;
  await android.createNotificationChannel(_paymentChannel);
  await android.createNotificationChannel(_announcementChannel);
}

/// Handler untuk pesan FCM ketika app dalam state terminated/background
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await initializeFirebase();
  // Runs in its own isolate, which has never seen the channels created on the
  // main one.
  await ensureNotificationChannels();
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

  /// Token must be registered through the authenticated WebView session.
  /// WebViewScreen assigns this after its bridge is ready.
  Function(String token)? onTokenAvailable;

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
      await _saveToken(token);
    }

    // 4. Listen token refresh
    _messaging.onTokenRefresh.listen(_saveToken);

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

    await ensureNotificationChannels();
  }

  // ─── Foreground Message Handler ──────────────────────────────────────────
  Future<void> _handleForegroundMessage(RemoteMessage message) async {
    debugPrint('[FCM] Foreground: ${message.notification?.title}');
    final notif = message.notification;
    if (notif == null) return;

    final data = message.data;
    final url = data['url'] as String? ?? AppConstants.baseUrl;
    final isAnnouncement = data['type'] == 'announcement';
    final notificationKey = data['notification_id'] as String?;
    if (isAnnouncement && notificationKey != null && !await _markAnnouncementShown(notificationKey)) {
      return;
    }
    final channelId = isAnnouncement ? 'announcement_channel' : 'siks_channel';
    final channelName = isAnnouncement ? 'Pengumuman Sekolah' : 'SIKS Notifikasi';
    final channelDescription = isAnnouncement ? 'Pengumuman penting dari sekolah' : 'Notifikasi pembayaran SPP';

    await _localNotif.show(
      notificationKey == null ? message.hashCode : _notificationId(notificationKey),
      notif.title,
      notif.body,
      NotificationDetails(
        android: AndroidNotificationDetails(
          channelId,
          channelName,
          channelDescription: channelDescription,
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
  Future<bool> _markAnnouncementShown(String notificationKey) async {
    final prefs = await SharedPreferences.getInstance();
    final shown = prefs.getStringList('shown_announcement_notifications') ?? [];
    if (shown.contains(notificationKey)) return false;
    shown.add(notificationKey);
    // Keep persistent deduplication bounded while still covering FCM retries.
    await prefs.setStringList('shown_announcement_notifications', shown.length > 100 ? shown.sublist(shown.length - 100) : shown);
    return true;
  }

  int _notificationId(String key) {
    var hash = 0;
    for (final codeUnit in key.codeUnits) {
      hash = ((hash * 31) + codeUnit) & 0x7fffffff;
    }
    return hash;
  }

  Future<void> _saveToken(String token) async {
    final prefs = await SharedPreferences.getInstance();
    final oldToken = prefs.getString(AppConstants.keyFcmToken);

    if (oldToken != token) {
      await prefs.setString(AppConstants.keyFcmToken, token);
    }
    // Do not use a standalone HTTP request here: it does not carry the web
    // login cookie and could associate a device with the wrong account.
    onTokenAvailable?.call(token);
  }

  Future<String?> get currentToken async => _messaging.getToken();
}
