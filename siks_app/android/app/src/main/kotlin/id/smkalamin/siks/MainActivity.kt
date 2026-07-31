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
                    shareToWhatsApp(imagePath, text, phone)
                    result.success(null)
                } else {
                    result.error("INVALID_ARGUMENTS", "Missing arguments", null)
                }
            } else {
                result.notImplemented()
            }
        }
    }

    private fun shareToWhatsApp(imagePath: String, text: String, phone: String) {
        try {
            val file = File(imagePath)
            if (!file.exists()) return

            val uri: Uri = FileProvider.getUriForFile(
                this,
                "${applicationContext.packageName}.fileprovider",
                file
            )

            val intent = Intent(Intent.ACTION_SEND)
            intent.type = "image/png"
            intent.putExtra(Intent.EXTRA_STREAM, uri)
            intent.putExtra(Intent.EXTRA_TEXT, text)
            intent.putExtra("jid", "$phone@s.whatsapp.net")
            intent.setPackage("com.whatsapp")
            intent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)

            startActivity(intent)
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }
}
