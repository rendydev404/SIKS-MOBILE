<?php
/**
 * Visual Kwitansi & Kirim WA Biasa Verifikasi Pembayaran
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();

$id = $_GET['id'] ?? 0;
$redirectSource = $_GET['redirect'] ?? 'verifikasi';

// Get data pembayaran & siswa
$stmt = $pdo->prepare("
    SELECT p.*, s.nama as nama_siswa, s.nis, s.no_whatsapp, s.tahun_masuk, k.nama_kelas, 
           u.nama_lengkap as petugas, v.nama_lengkap as verifikator
    FROM pembayaran p
    JOIN siswa s ON p.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN users v ON p.verifikasi_admin_id = v.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$pembayaran = $stmt->fetch();

if (!$pembayaran) {
    setAlert('danger', 'Data pembayaran tidak ditemukan!');
    header('Location: index.php');
    exit;
}

$isLunas = ($pembayaran['status'] == 'lunas');
$isDitolak = ($pembayaran['status'] == 'ditolak');

$pageTitle = 'Verifikasi WA - ' . $pembayaran['nama_siswa'];

// Standardize WA Number
$noWaRaw = $pembayaran['no_whatsapp'] ?? '';
$noWa = formatNomorWA($noWaRaw);

// Format WhatsApp Message Text
$pesan = "Assalamu'alaikum Warahmatullahi Wabarakatuh.\n\n";
$pesan .= "Yth. Orang Tua/Wali dari:\n";
$pesan .= "Nama Siswa : *" . $pembayaran['nama_siswa'] . "*\n";
$pesan .= "Kelas : *" . ($pembayaran['nama_kelas'] ?? '-') . "*\n\n";

if ($isLunas) {
    $pesan .= "Diberitahukan bahwa pembayaran *" . $pembayaran['jenis_pembayaran'] . "* ";
    $pesan .= "periode *" . $pembayaran['bulan'] . " " . $pembayaran['tahun'] . "* ";
    $pesan .= "sebesar *" . formatRupiah($pembayaran['jumlah_bayar']) . "* ";
    $pesan .= "telah *DIVERIFIKASI LUNAS* oleh bagian Keuangan SMK Al Amin.\n\n";
    $pesan .= "📌 *Bukti / Kwitansi pembayaran terlampir pada gambar.*";
} elseif ($isDitolak) {
    $pesan .= "Diberitahukan bahwa laporan pembayaran *" . $pembayaran['jenis_pembayaran'] . "* ";
    $pesan .= "periode *" . $pembayaran['bulan'] . " " . $pembayaran['tahun'] . "* ";
    $pesan .= "sebesar *" . formatRupiah($pembayaran['jumlah_bayar']) . "* ";
    $pesan .= "*BELUM DAPAT DITERIMA / DITOLAK*.\n\n";
    $pesan .= "Alasan Penolakan: *" . ($pembayaran['admin_note'] ?: 'Bukti tidak terbaca / tidak sesuai') . "*\n\n";
    $pesan .= "Mohon periksa kembali dan upload ulang bukti pembayaran yang valid. Terima kasih.";
} else {
    $pesan .= "Status pembayaran *" . $pembayaran['jenis_pembayaran'] . "*: *MENUNGGU VERIFIKASI*.";
}

$pesan .= "\n\nWassalamu'alaikum Warahmatullahi Wabarakatuh.\n";
$pesan .= "*Keuangan SMK Al Amin*";

$waLink = $noWa ? "https://wa.me/" . $noWa . "?text=" . rawurlencode($pesan) : "#";

include '../includes/header.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<style>
.wa-verif-container {
    max-width: 540px;
    margin: 0 auto;
    padding: 10px 0 30px;
}

.action-bar-top {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.action-bar-top .btn {
    flex: 1;
    min-width: 140px;
    padding: 12px 18px;
    font-weight: 600;
    font-size: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border-radius: 12px;
}

/* Card Kwitansi Visual */
.kwitansi-card {
    background: #ffffff;
    color: #1e293b;
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    position: relative;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.kwitansi-header {
    background: <?= $isLunas ? 'linear-gradient(135deg, #059669 0%, #10b981 100%)' : ($isDitolak ? 'linear-gradient(135deg, #dc2626 0%, #ef4444 100%)' : 'linear-gradient(135deg, #d97706 0%, #f59e0b 100%)') ?>;
    color: #ffffff;
    padding: 24px;
    text-align: center;
    position: relative;
}

.kwitansi-header-logo {
    width: 64px;
    height: 64px;
    margin: 0 auto 10px;
    object-fit: contain;
}

.kwitansi-header h2 {
    font-size: 20px;
    font-weight: 800;
    margin: 0 0 4px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.kwitansi-header p {
    font-size: 13px;
    margin: 0;
    opacity: 0.9;
}

.kwitansi-badge-status {
    display: inline-block;
    margin-top: 14px;
    padding: 6px 18px;
    background: rgba(255, 255, 255, 0.25);
    backdrop-filter: blur(5px);
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.kwitansi-body {
    padding: 24px;
    background: #ffffff;
}

.kwitansi-info-box {
    background: #f8fafc;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 20px;
    border: 1px solid #e2e8f0;
}

.kwitansi-info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px dashed #e2e8f0;
    font-size: 14px;
}

.kwitansi-info-row:last-child {
    border-bottom: none;
}

.kwitansi-label {
    color: #64748b;
    font-size: 13px;
    font-weight: 500;
}

.kwitansi-value {
    color: #0f172a;
    font-weight: 600;
    text-align: right;
}

.kwitansi-amount-box {
    background: <?= $isLunas ? '#f0fdf4' : ($isDitolak ? '#fef2f2' : '#fffbeb') ?>;
    border: 2px dashed <?= $isLunas ? '#22c55e' : ($isDitolak ? '#ef4444' : '#f59e0b') ?>;
    border-radius: 14px;
    padding: 18px;
    text-align: center;
    margin-bottom: 20px;
}

.kwitansi-amount-label {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    color: <?= $isLunas ? '#15803d' : ($isDitolak ? '#b91c1c' : '#b45309') ?>;
    letter-spacing: 0.5px;
}

.kwitansi-amount-val {
    font-size: 30px;
    font-weight: 800;
    color: <?= $isLunas ? '#166534' : ($isDitolak ? '#991b1b' : '#92400e') ?>;
    margin: 4px 0;
}

.kwitansi-footer {
    padding: 16px 24px;
    background: #f1f5f9;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    color: #64748b;
}

#toastMsg {
    visibility: hidden;
    min-width: 280px;
    background-color: #0f172a;
    color: #fff;
    text-align: center;
    border-radius: 12px;
    padding: 14px 20px;
    position: fixed;
    z-index: 9999;
    left: 50%;
    bottom: 30px;
    transform: translateX(-50%);
    font-size: 14px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.5);
    border: 1px solid var(--border-color);
}

#toastMsg.show {
    visibility: visible;
    animation: fadein 0.5s, fadeout 0.5s 3.5s;
}

@keyframes fadein { from { bottom: 0; opacity: 0; } to { bottom: 30px; opacity: 1; } }
@keyframes fadeout { from { bottom: 30px; opacity: 1; } to { bottom: 0; opacity: 0; } }
</style>

<div class="wa-verif-container animate-slide-up">

    <div class="action-bar-top">
        <a href="<?= $redirectSource == 'fitur' ? '../keuangan/fitur.php?tab=pending' : 'verifikasi.php' ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
        <button id="btnSendWaImage" type="button" class="btn btn-success" style="background: #25d366; border-color: #25d366; color: #fff;">
            <i class="fab fa-whatsapp" style="font-size: 18px;"></i> Siapkan di WhatsApp
        </button>
    </div>

    <div class="alert alert-info" role="note" style="margin-bottom: 20px;">
        <i class="fas fa-info-circle"></i>
        Caption akan terisi otomatis dan gambar kwitansi disalin. Di chat siswa,
        tekan <strong>Ctrl+V</strong> untuk melampirkan gambar, lalu klik <strong>Kirim</strong>.
    </div>

    <?php if (!$noWa): ?>
        <div class="alert alert-warning" style="margin-bottom: 20px;">
            <i class="fas fa-exclamation-circle"></i> <strong>Nomor WA Siswa Belum Diisi!</strong><br>
            Siswa ini belum memiliki nomor WhatsApp terdaftar. Lengkapi nomor siswa agar kwitansi dapat disiapkan ke chat tujuan.
        </div>
    <?php endif; ?>

    <!-- Target Element to Convert into PNG Image -->
    <div class="kwitansi-card" id="kwitansiArea">
        <div class="kwitansi-header">
            <img src="../assets/img/logo_sekolah.png" alt="Logo" class="kwitansi-header-logo" onerror="this.style.display='none'">
            <h2>SMK AL AMIN</h2>
            <p>BUKTI VERIFIKASI PEMBAYARAN</p>
            <div class="kwitansi-badge-status">
                <i class="fas <?= $isLunas ? 'fa-check-circle' : ($isDitolak ? 'fa-times-circle' : 'fa-clock') ?>"></i>
                <?= $isLunas ? 'Verifikasi Lunas' : ($isDitolak ? 'Pembayaran Ditolak' : 'Menunggu Verifikasi') ?>
            </div>
        </div>

        <div class="kwitansi-body">
            <div class="kwitansi-info-box">
                <div class="kwitansi-info-row">
                    <span class="kwitansi-label">No. Transaksi</span>
                    <span class="kwitansi-value">TRX-<?= str_pad($pembayaran['id'], 6, '0', STR_PAD_LEFT) ?></span>
                </div>
                <div class="kwitansi-info-row">
                    <span class="kwitansi-label">Nama Siswa</span>
                    <span class="kwitansi-value"><?= e($pembayaran['nama_siswa']) ?></span>
                </div>
                <div class="kwitansi-info-row">
                    <span class="kwitansi-label">NIS / Kelas</span>
                    <span class="kwitansi-value"><?= e($pembayaran['nis']) ?> - <?= e($pembayaran['nama_kelas'] ?? '-') ?></span>
                </div>
                <div class="kwitansi-info-row">
                    <span class="kwitansi-label">Jenis Pembayaran</span>
                    <span class="kwitansi-value"><?= e($pembayaran['jenis_pembayaran']) ?></span>
                </div>
                <div class="kwitansi-info-row">
                    <span class="kwitansi-label">Periode Tagihan</span>
                    <span class="kwitansi-value"><?= e($pembayaran['bulan']) ?> <?= e($pembayaran['tahun']) ?></span>
                </div>
                <div class="kwitansi-info-row">
                    <span class="kwitansi-label">Metode Pembayaran</span>
                    <span class="kwitansi-value"><?= e($pembayaran['metode_bayar']) ?></span>
                </div>
                <div class="kwitansi-info-row">
                    <span class="kwitansi-label">Tanggal Verifikasi</span>
                    <span class="kwitansi-value"><?= formatTanggal($pembayaran['tanggal_verifikasi'] ?? date('Y-m-d H:i:s'), 'd M Y H:i') ?> WIB</span>
                </div>
                <?php if ($isDitolak && !empty($pembayaran['admin_note'])): ?>
                <div class="kwitansi-info-row" style="color: #dc2626;">
                    <span class="kwitansi-label" style="color: #dc2626; font-weight: 700;">Catatan Admin</span>
                    <span class="kwitansi-value" style="color: #dc2626; font-weight: 700;"><?= e($pembayaran['admin_note']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="kwitansi-amount-box">
                <div class="kwitansi-amount-label">Jumlah Pembayaran</div>
                <div class="kwitansi-amount-val"><?= formatRupiah($pembayaran['jumlah_bayar']) ?></div>
            </div>
        </div>

        <div class="kwitansi-footer">
            <span>Petugas: <?= e($pembayaran['verifikator'] ?? $pembayaran['petugas'] ?? 'Admin Keuangan') ?></span>
            <span>SMK Al Amin Official Receipt</span>
        </div>
    </div>

</div>

<div id="toastMsg">Notification Message</div>

<script>
const receiptWaLink = <?= json_encode($waLink, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const receiptMessage = <?= json_encode($pesan, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function showToast(msg) {
    const toast = document.getElementById('toastMsg');
    toast.innerText = msg;
    toast.className = "show";
    setTimeout(() => { toast.className = toast.className.replace("show", ""); }, 4000);
}

function canvasToBlob(canvas) {
    return new Promise((resolve, reject) => {
        canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('Browser tidak dapat membuat gambar kwitansi.')), 'image/png');
    });
}

async function copyReceiptImageToClipboard(blobOrPromise) {
    if (!window.isSecureContext || !navigator.clipboard || !window.ClipboardItem) return false;

    try {
        // Caption comes from the wa.me link. Keep only the PNG here so Ctrl+V
        // adds the image without replacing the pre-filled caption.
        // Passing the render Promise lets Clipboard.write start during the
        // original button click, before the browser drops user activation.
        await navigator.clipboard.write([new ClipboardItem({ 'image/png': blobOrPromise })]);
        return true;
    } catch (error) {
        console.warn('Clipboard gambar tidak tersedia:', error);
        return false;
    }
}

function openWhatsApp(waWindow) {
    if (receiptWaLink === '#') {
        if (waWindow && !waWindow.closed) waWindow.close();
        showToast('Nomor WhatsApp belum diisi. Invoice sudah disiapkan; buka WhatsApp secara manual.');
        return;
    }

    if (waWindow && !waWindow.closed) {
        waWindow.location.href = receiptWaLink;
        return;
    }

    const fallbackWindow = window.open(receiptWaLink, '_blank');
    if (!fallbackWindow) window.location.href = receiptWaLink;
}

document.getElementById('btnSendWaImage').addEventListener('click', function() {
    const btn = this;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses Kwitansi...';

    // Buka tab dari klik asli agar navigasi ke wa.me tidak diblokir setelah
    // html2canvas dan Clipboard API selesai.
    let waWindow = null;
    if (!window.WhatsAppShareChannel && receiptWaLink !== '#') {
        try { waWindow = window.open('about:blank', '_blank'); } catch (error) { console.warn(error); }
    }

    const useNativeShare = !!window.WhatsAppShareChannel;
    const imagePromise = html2canvas(document.getElementById('kwitansiArea'), {
        useCORS: true,
        scale: 2,
        backgroundColor: '#ffffff'
    }).then(canvasToBlob);

    // Start clipboard access immediately from the user gesture so the browser
    // does not reject it after html2canvas finishes.
    const clipboardPromise = useNativeShare
        ? null
        : copyReceiptImageToClipboard(imagePromise);

    imagePromise.then(async blob => {

        // WebView Android: attachment dikirim melalui bridge native.
        if (useNativeShare) {
            showToast('Mengirim gambar ke WhatsApp via Aplikasi...');
            window.WhatsAppShareChannel.postMessage(JSON.stringify({
                base64: canvas.toDataURL('image/png').split(',')[1],
                fileName: 'Kwitansi_Verifikasi_<?= preg_replace('/[^a-zA-Z0-9_]/', '', $pembayaran['nama_siswa']) ?>.png',
                phone: <?= json_encode($noWa ?: '') ?>,
                text: receiptMessage
            }));
            btn.disabled = false;
            btn.innerHTML = originalText;
            return;
        }

        // Browser biasa: wa.me mengisi caption, sedangkan gambar disalin ke
        // clipboard supaya admin bisa menekan Ctrl+V di chat siswa.
        // Retry with the resolved Blob for browsers that do not accept a
        // Promise as a ClipboardItem value.
        let copied = clipboardPromise ? await clipboardPromise : false;
        if (!copied) copied = await copyReceiptImageToClipboard(blob);
        if (!copied) {
            if (waWindow && !waWindow.closed) waWindow.close();
            showToast('Gambar kwitansi belum tersalin. WhatsApp tidak dibuka agar gambar lama tidak ikut. Coba lagi.');
            btn.disabled = false;
            btn.innerHTML = originalText;
            return;
        }

        showToast('Caption siap. Gambar kwitansi disalin. Di chat tekan Ctrl+V lalu Kirim.');
        openWhatsApp(waWindow);
        btn.disabled = false;
        btn.innerHTML = originalText;
    }).catch(error => {
        if (waWindow && !waWindow.closed) waWindow.close();
        console.error('Gagal memproses gambar kwitansi:', error);
        showToast('Gagal memproses gambar kwitansi.');
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});
</script>

<?php include '../includes/footer.php'; ?>
