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

            val waPackage = installedWhatsAppPackage()
            if (waPackage == null) {
                result.error("APP_NOT_FOUND", "WhatsApp is not installed", null)
                return
            }

            // Image and caption in one message. The "jid" extra asks WhatsApp to
            // skip its contact picker and open the student's chat directly; it
            // only ever had a chance when the number was actually filled in,
            // which the page now sends straight from PHP instead of scraping it
            // back out of the rendered HTML. WhatsApp falls back to the picker
            // on its own if it does not honour the extra, and the message is
            // ready to send either way.
            val intent = Intent(Intent.ACTION_SEND)
            intent.type = "image/png"
            intent.putExtra(Intent.EXTRA_STREAM, uri)
            intent.putExtra(Intent.EXTRA_TEXT, text)
            intent.clipData = android.content.ClipData.newRawUri("", uri)
            intent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
            intent.setPackage(waPackage)

            if (phone.isNotEmpty()) {
                intent.putExtra("jid", phone + "@s.whatsapp.net")
                // The receipt also goes on the clipboard, so nothing is lost if
                // WhatsApp drops the attachment on the way to the chat.
                copyImageToClipboard(uri)
            }

            startActivity(intent)
            result.success(if (phone.isNotEmpty()) "direct" else "picker")
        } catch (e: Exception) {
            e.printStackTrace()
            result.error("SHARE_ERROR", e.message, null)
        }
    }

    /** The installed WhatsApp, preferring the regular app over Business. */
    private fun installedWhatsAppPackage(): String? {
        val pm = packageManager
        for (candidate in listOf("com.whatsapp", "com.whatsapp.w4b")) {
            try {
                pm.getPackageInfo(candidate, android.content.pm.PackageManager.GET_META_DATA)
                return candidate
            } catch (e: android.content.pm.PackageManager.NameNotFoundException) {
                // Try the next one.
            }
        }
        return null
    }

    /**
     * Puts the receipt on the clipboard. A clipboard URI carries its own read
     * grant, so WhatsApp can open a file owned by this app's FileProvider.
     */
    private fun copyImageToClipboard(uri: Uri) {
        val clipboard = getSystemService(android.content.Context.CLIPBOARD_SERVICE)
                as android.content.ClipboardManager
        clipboard.setPrimaryClip(
            android.content.ClipData.newUri(contentResolver, "Kwitansi", uri)
        )
    }
}
