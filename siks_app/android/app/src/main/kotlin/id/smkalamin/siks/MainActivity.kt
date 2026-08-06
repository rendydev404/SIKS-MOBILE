package id.smkalamin.siks

import android.content.Intent
import android.net.Uri
import androidx.core.content.FileProvider
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import java.io.File

class MainActivity : FlutterActivity() {
    private val CHANNEL = "id.smkalamin.siks/whatsapp"

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, CHANNEL).setMethodCallHandler { call, result ->
            if (call.method == "shareToWhatsApp") {
                val imagePath = call.argument<String>("imagePath")
                val text = call.argument<String>("text")
                val phone = call.argument<String>("phone")

                if (imagePath != null && text != null && phone != null) {
                    shareToWhatsApp(imagePath, text, phone, result)
                } else {
                    result.error("INVALID_ARGUMENTS", "Missing arguments", null)
                }
            } else {
                result.notImplemented()
            }
        }
    }

    private fun shareToWhatsApp(imagePath: String, text: String, phone: String, result: io.flutter.plugin.common.MethodChannel.Result) {
        try {
            val sourceFile = File(imagePath)
            if (!sourceFile.exists()) {
                result.error("FILE_NOT_FOUND", "Image file does not exist", null)
                return
            }

            // Copy file to external files directory so WhatsApp has no permission issues reading it
            val externalDir = getExternalFilesDir(android.os.Environment.DIRECTORY_PICTURES)
            if (externalDir != null && !externalDir.exists()) {
                externalDir.mkdirs()
            }
            val file = File(externalDir, "kwitansi_share.png")
            sourceFile.copyTo(file, overwrite = true)

            val uri: Uri = FileProvider.getUriForFile(
                this,
                "${applicationContext.packageName}.fileprovider",
                file
            )

            val intent = Intent(Intent.ACTION_SEND)
            intent.type = "image/png"
            intent.putExtra(Intent.EXTRA_STREAM, uri)
            intent.putExtra(Intent.EXTRA_TEXT, text)
            
            // Add ClipData for Android 11+ URI permission
            intent.clipData = android.content.ClipData.newRawUri("", uri)
            intent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)

            if (phone.isNotEmpty()) {
                intent.putExtra("jid", "$phone@s.whatsapp.net")
            }

            // Check if WhatsApp is installed, fallback to WhatsApp Business
            val pm = packageManager
            var waPackage = "com.whatsapp"
            var isInstalled = true
            try {
                pm.getPackageInfo(waPackage, android.content.pm.PackageManager.GET_META_DATA)
            } catch (e: android.content.pm.PackageManager.NameNotFoundException) {
                waPackage = "com.whatsapp.w4b"
                try {
                    pm.getPackageInfo(waPackage, android.content.pm.PackageManager.GET_META_DATA)
                } catch (e2: android.content.pm.PackageManager.NameNotFoundException) {
                    isInstalled = false
                }
            }

            if (!isInstalled) {
                result.error("APP_NOT_FOUND", "WhatsApp is not installed", null)
                return
            }

            intent.setPackage(waPackage)
            startActivity(intent)
            result.success(null)
        } catch (e: Exception) {
            e.printStackTrace()
            result.error("SHARE_ERROR", e.message, null)
        }
    }
}
