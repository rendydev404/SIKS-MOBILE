<?php
/**
 * Pengaturan WhatsApp Gateway (Fonnte API)
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/wa_gateway.php';
checkLogin();

$pageTitle = 'Setting WA Gateway';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_token'])) {
        $token = trim($_POST['wa_token'] ?? '');
        saveWAGatewayToken($pdo, $token);
        $message = 'Token WA Gateway berhasil disimpan!';
        $messageType = 'success';
    } elseif (isset($_POST['test_wa'])) {
        $testNumber = trim($_POST['test_number'] ?? '');
        $testMsg = "Halo! Ini adalah pesan uji coba pengiriman otomatis dari Sistem SPP SMK Al Amin.";
        
        $res = kirimWA_Otomatis($pdo, $testNumber, $testMsg);
        if ($res['status']) {
            $message = 'Uji coba berhasil! ' . $res['message'];
            $messageType = 'success';
        } else {
            $message = 'Uji coba gagal: ' . $res['message'];
            $messageType = 'danger';
        }
    }
}

$currentToken = getWAGatewayToken($pdo);

include 'includes/header.php';
?>

<div class="container animate-slide-up" style="max-width: 650px; margin: 20px auto;">
    <div class="card glass">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fab fa-whatsapp" style="color: #25d366;"></i> Pengaturan WhatsApp Gateway (Pengiriman Otomatis)
            </h2>
        </div>
        <div class="card-body">
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>

            <div class="alert alert-info" style="border-left: 5px solid #3b82f6; font-size: 13px;">
                <h5 style="margin-top: 0; font-size: 15px;"><i class="fas fa-magic"></i> Fitur Pengiriman 100% Otomatis:</h5>
                Dengan mengintegrasikan <strong>Fonnte API Token</strong>, setiap verifikasi SPP / tagihan akan langsung mengirimkan <strong>Gambar Kwitansi & Pesan secara otomatis</strong> ke WhatsApp siswa tanpa perlu copy-paste manual!
                <br><br>
                <strong>Cara Memperoleh Token Fonnte (Gratis):</strong>
                <ol style="margin-bottom: 0; padding-left: 20px;">
                    <li>Daftar / Login gratis di <a href="https://fonnte.com" target="_blank" style="color: #60a5fa; font-weight: bold;">Fonnte.com</a></li>
                    <li>Hubungkan nomor WhatsApp sekolah Anda (Scan QR Code)</li>
                    <li>Salin <strong>API Token</strong> dan tempel pada kolom di bawah ini.</li>
                </ol>
            </div>

            <form method="POST" style="margin-top: 20px;">
                <div class="form-group mb-4">
                    <label style="font-weight: 600; margin-bottom: 8px;">Fonnte API Token</label>
                    <input type="password" name="wa_token" class="form-control form-control-simple" 
                           value="<?= e($currentToken) ?>" placeholder="Masukkan Token API Fonnte..." required>
                    <small style="color: var(--text-muted); display: block; margin-top: 5px;">
                        Status: <?= !empty($currentToken) ? '<span class="text-success" style="font-weight:bold;"><i class="fas fa-check-circle"></i> Token Aktif Terpasang</span>' : '<span class="text-warning"><i class="fas fa-exclamation-triangle"></i> Token Belum Diisi</span>' ?>
                    </small>
                </div>

                <div style="display: flex; justify-content: flex-end;">
                    <button type="submit" name="save_token" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Token WA
                    </button>
                </div>
            </form>

            <hr style="border-color: var(--border-color); margin: 30px 0;">

            <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 15px;">Uji Coba Pengiriman WA</h3>
            <form method="POST">
                <div class="form-group mb-3">
                    <label>Nomor WA Penerima Uji Coba</label>
                    <input type="text" name="test_number" class="form-control form-control-simple" 
                           placeholder="Contoh: 08123456789" required>
                </div>
                <button type="submit" name="test_wa" class="btn btn-success" style="background: #25d366; border-color: #25d366;">
                    <i class="fas fa-paper-plane"></i> Kirim Pesan Tes
                </button>
            </form>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
