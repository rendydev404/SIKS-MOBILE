# Proguard rules untuk SIKS App

# Flutter
-keep class io.flutter.app.** { *; }
-keep class io.flutter.plugin.** { *; }
-keep class io.flutter.util.** { *; }
-keep class io.flutter.view.** { *; }
-keep class io.flutter.** { *; }
-keep class io.flutter.plugins.** { *; }

# Firebase
-keep class com.google.firebase.** { *; }
-keep class com.google.android.gms.** { *; }

# WebView
-keepclassmembers class * {
    @android.webkit.JavascriptInterface <methods>;
}

# Keep MainActivity
-keep class id.smkalamin.siks.** { *; }

# Ignore warnings for missing Play Core classes referenced by Flutter plugins
-dontwarn com.google.android.play.core.**
-keep class com.google.android.play.core.** { *; }
