# SIKS App Setup Guide

## Prasyarat

1. **Flutter SDK** — [Download Flutter](https://flutter.dev/docs/get-started/install)
2. **Android Studio** atau VS Code dengan Flutter extension
3. **Firebase Project** — buat di [console.firebase.google.com](https://console.firebase.google.com)
4. **JDK 17+**

---

## Setup Awal (WAJIB sebelum build)

### 1. Install Flutter & tambah ke PATH

```powershell
# Setelah download & extract Flutter SDK ke misal C:\flutter
$env:PATH += ";C:\flutter\bin"
# Atau tambah permanen lewat System Properties > Environment Variables
```

### 2. Download dependencies

```bash
cd d:\PROJECT-APPS-NATIVE\siks_app
flutter pub get
```

### 3. Setup Firebase

1. Buka [Firebase Console](https://console.firebase.google.com)
2. Buat project baru: **"SIKS Al Amin"**
3. Tambah Android app dengan package name: `id.smkalamin.siks`
4. Download `google-services.json` → taruh di `android/app/google-services.json`
5. Di Firebase Console: **Cloud Messaging** → copy **Server Key (legacy)**
6. Paste Server Key ke `SIKS/fcm/send_notification.php` line `FCM_SERVER_KEY`

### 4. Generate Splash Screen

```bash
flutter pub run flutter_native_splash:create
```

### 5. Build APK Debug (untuk testing)

```bash
flutter build apk --debug
# APK ada di: build\app\outputs\flutter-apk\app-debug.apk
```

### 6. Build APK Release (untuk distribusi)

```bash
# Buat keystore dulu (sekali saja):
keytool -genkey -v -keystore siks_release.jks -keyalg RSA -keysize 2048 -validity 10000 -alias siks

# Buat file key.properties di folder android/:
# storePassword=PASSWORD_KAMU
# keyPassword=PASSWORD_KAMU
# keyAlias=siks
# storeFile=../siks_release.jks

flutter build apk --release --split-per-abi
# APK ada di: build\app\outputs\flutter-apk\
```

---

## Struktur Project

```
siks_app/
├── lib/
│   ├── main.dart              # Entry point
│   ├── app.dart               # Router & service init
│   ├── core/
│   │   ├── constants.dart     # BASE_URL, config
│   │   ├── fcm_service.dart   # Push notification
│   │   └── deep_link_service.dart
│   ├── features/
│   │   ├── splash/            # Animated splash screen
│   │   ├── onboarding/        # 3-slide onboarding
│   │   └── webview/           # Main WebView + JS bridge
│   └── widgets/
│       ├── loading_overlay.dart
│       └── no_internet_page.dart
├── assets/
│   └── images/logo.png        # Logo sekolah
└── android/
    ├── app/
    │   ├── google-services.json  # ⚠️ GANTI dengan yang asli dari Firebase
    │   └── build.gradle
    └── ...
```

---

## Test Checklist

- [ ] `flutter pub get` sukses
- [ ] `flutter analyze` tanpa error fatal
- [ ] APK debug terbuild
- [ ] Login admin berfungsi di WebView
- [ ] Login siswa berfungsi di WebView  
- [ ] Upload foto bukti transfer (galeri & kamera)
- [ ] Share/download PDF
- [ ] Back button Android wajar
- [ ] Splashscreen muncul
- [ ] Onboarding muncul di first launch, tidak di launch berikutnya
- [ ] Offline → muncul halaman no-internet
- [ ] Notif FCM muncul (setelah Firebase dikonfigurasi)

---

## Notes

- Website tidak perlu diubah signifikan — semua logic PHP tetap berjalan
- WebView load URL production: `https://sikssmkalamin.absensismkalamin.my.id/`
- Untuk FCM: upload `SIKS/fcm/` ke hosting dan isi Server Key di `send_notification.php`
- APK di-distribute manual via WhatsApp / Google Drive (tidak perlu Play Store)
