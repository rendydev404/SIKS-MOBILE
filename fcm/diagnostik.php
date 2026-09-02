<?php
/**
 * Diagnostik Notifikasi (khusus admin)
 *
 * Push notification bisa gagal tanpa meninggalkan jejak error di mana pun:
 * konfigurasi kosong, tidak ada perangkat terdaftar, atau Google menolak
 * kredensial. Halaman ini memeriksa ketiganya dan bisa menjalankan kiriman uji
 * ke perangkat admin yang sedang login.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/announcement_notifications.php';
require_once __DIR__ . '/../includes/fcm_sender.php';
require_once __DIR__ . '/../config/firebase.php';
checkLogin();
checkRole(['admin']);
ensureAnnouncementNotificationSchema($pdo);

$checks = [];
function tambahCek(&$checks, $label, $ok, $detail, $opsional = false) {
    $checks[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail, 'opsional' => $opsional];
}

// 1. Konfigurasi. Nilai rahasia tidak pernah ditampilkan, hanya statusnya.
$projectId = FCM_PROJECT_ID;
tambahCek($checks, 'FCM_PROJECT_ID', $projectId !== '',
    $projectId !== '' ? $projectId
        : 'Belum diisi. Set lewat environment hosting, atau isi config/firebase.local.php (salin dari firebase.local.example.php).');

$saPath = FCM_SERVICE_ACCOUNT_PATH;
if ($saPath === '') {
    $dicari = [];
    $localCfg = is_readable(__DIR__ . '/../config/firebase.local.php')
        ? require __DIR__ . '/../config/firebase.local.php' : [];
    $kandidat = $localCfg['service_account_path'] ?? [];
    foreach ((array) $kandidat as $k) { $dicari[] = $k; }
    tambahCek($checks, 'FCM_SERVICE_ACCOUNT_PATH', false,
        'File service account tidak ditemukan. Sudah dicari di: '
        . ($dicari ? implode(' , ', $dicari) : '(tidak ada kandidat)')
        . ' -- taruh service.json di salah satunya, atau sesuaikan daftarnya di config/firebase.local.php.');
} elseif (!is_readable($saPath)) {
    tambahCek($checks, 'FCM_SERVICE_ACCOUNT_PATH', false, 'Sudah diset, tetapi file tidak ditemukan atau tidak bisa dibaca.');
} else {
    $sa = json_decode((string) file_get_contents($saPath), true);
    $valid = !empty($sa['client_email']) && !empty($sa['private_key']);
    tambahCek($checks, 'FCM_SERVICE_ACCOUNT_PATH', $valid,
        $valid ? 'Terbaca. Service account: ' . $sa['client_email']
               : 'File terbaca tetapi bukan service-account JSON yang valid.');
    // Subdomain membuat DOCUMENT_ROOT lebih dalam dari public_html, sehingga
    // file di public_html tetap bisa diunduh lewat domain utama meski berada
    // di luar DOCUMENT_ROOT. Jadi yang diperiksa adalah ada tidaknya segmen
    // public_html di dalam path.
    $saReal = realpath($saPath);
    // Tidak perlu regex: cukup samakan pemisah folder lalu cari segmennya.
    $saNormal = $saReal ? str_replace(DIRECTORY_SEPARATOR, '/', $saReal) : '';
    $diWebRoot = $saNormal !== '' && strpos($saNormal, '/public_html/') !== false;
    if ($valid && $diWebRoot) {
        tambahCek($checks, 'Keamanan service account', false,
            'File berada di dalam web root sehingga bisa diunduh siapa pun lewat browser. Pindahkan ke luar public_html dan perbarui path-nya.');
    }
    if ($valid && $projectId !== '' && !empty($sa['project_id']) && $sa['project_id'] !== $projectId) {
        tambahCek($checks, 'Kecocokan project', false,
            'FCM_PROJECT_ID (' . $projectId . ') berbeda dari project_id pada service account (' . $sa['project_id'] . ').');
    }
}

$cronSecretDiset = FCM_CRON_SECRET !== 'GANTI_DENGAN_SECRET_CRON_YANG_PANJANG';
tambahCek($checks, 'FCM_CRON_SECRET', $cronSecretDiset,
    $cronSecretDiset ? 'Sudah diset. Dipakai mengamankan cron pengumuman.'
                     : 'Masih nilai bawaan, sehingga cron pengumuman ditolak 403. Opsional - pengumuman tetap terkirim seketika dari halaman admin, cron hanya jaring pengaman untuk percobaan ulang. Isi lewat config/firebase.secret.php di server.', true);

// 2. Kredensial benar-benar diterima Google?
$accessToken = fcmAccessToken();
tambahCek($checks, 'Akses ke Google (OAuth)', $accessToken !== null,
    $accessToken !== null
        ? 'Berhasil menukar service account dengan access token.'
        : 'Gagal. Periksa service account, jam server, dan izin Firebase Cloud Messaging API.');

// 3. Perangkat terdaftar. Tanpa ini tidak ada tujuan pengiriman.
$devices = $pdo->query("SELECT owner_type, COUNT(*) AS jumlah FROM fcm_devices WHERE is_active = 1 GROUP BY owner_type")->fetchAll();
$aktifSiswa = 0;
$aktifAdmin = 0;
foreach ($devices as $row) {
    if ($row['owner_type'] === 'siswa') $aktifSiswa = (int) $row['jumlah'];
    if ($row['owner_type'] === 'user') $aktifAdmin = (int) $row['jumlah'];
}
tambahCek($checks, 'Perangkat siswa terdaftar', $aktifSiswa > 0,
    $aktifSiswa > 0 ? $aktifSiswa . ' perangkat aktif.'
                    : 'Belum ada. Siswa harus login lewat aplikasi Android minimal sekali.');
tambahCek($checks, 'Perangkat admin terdaftar', $aktifAdmin > 0,
    $aktifAdmin > 0 ? $aktifAdmin . ' perangkat aktif.'
                    : 'Belum ada. Login sebagai admin lewat aplikasi Android untuk mendaftarkan perangkat.');

// 4. Kiriman uji ke perangkat admin yang sedang login.
$testResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test') {
    $stmt = $pdo->prepare("SELECT fcm_token FROM fcm_devices WHERE owner_type = 'user' AND user_id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['user_id']]);
    $myTokens = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$myTokens) {
        $testResult = ['ok' => false, 'pesan' => 'Akun ini belum punya perangkat terdaftar. Buka aplikasi Android, login sebagai admin, lalu ulangi.'];
    } else {
        $pesan = [];
        $semuaBerhasil = true;
        $mati = 0;
        foreach ($myTokens as $t) {
            $r = sendFCMNotification($t, 'Tes Notifikasi SIKS',
                'Kalau pesan ini muncul, jalur notifikasi sudah berfungsi.', [
                    'type' => 'payment',
                    'url' => BASE_URL . 'pages/dashboard.php',
                ]);
            if ($r['success']) {
                $pesan[] = 'Terkirim ke satu perangkat (HTTP ' . $r['http_code'] . ').';
            } elseif (!empty($r['invalid_token'])) {
                // Token sisa aplikasi yang sudah di-uninstall atau dipasang
                // ulang. Dinonaktifkan supaya hitungan perangkat jujur dan
                // pengiriman berikutnya tidak membuang waktu ke sana.
                $pdo->prepare('UPDATE fcm_devices SET is_active = 0 WHERE fcm_token = ?')->execute([$t]);
                $mati++;
            } else {
                $semuaBerhasil = false;
                $pesan[] = 'Gagal: ' . $r['error'] . ' (HTTP ' . $r['http_code'] . ').';
            }
        }
        if ($mati > 0) {
            $pesan[] = $mati . ' token lama sudah tidak terdaftar (aplikasi di-uninstall atau dipasang ulang) dan dinonaktifkan.';
        }
        $testResult = ['ok' => $semuaBerhasil, 'pesan' => implode(' ', $pesan)];
    }
}

// 5. Antrean pengumuman: apakah cron benar-benar berjalan?
$antrean = $pdo->query("SELECT status, COUNT(*) AS jumlah FROM announcement_notification_deliveries GROUP BY status")->fetchAll();
$errors = $pdo->query("SELECT pengumuman_id, attempts, last_error, updated_at FROM announcement_notification_deliveries WHERE last_error IS NOT NULL ORDER BY updated_at DESC LIMIT 5")->fetchAll();

$semuaOk = true;
foreach ($checks as $c) {
    if (!$c['ok'] && empty($c['opsional'])) $semuaOk = false;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Diagnostik Notifikasi - SIKS SMK Al Amin</title>
<style>
  body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f1f5f9; color: #0f172a; margin: 0; padding: 24px 16px 64px; }
  .wrap { max-width: 760px; margin: 0 auto; }
  h1 { font-size: 20px; margin: 0 0 4px; }
  p.sub { color: #64748b; margin: 0 0 24px; font-size: 14px; }
  .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 18px; margin-bottom: 16px; }
  .row { display: flex; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
  .row:last-child { border-bottom: 0; }
  .badge { flex: none; width: 22px; height: 22px; border-radius: 999px; display: grid; place-items: center; color: #fff; font-size: 13px; font-weight: 700; }
  .ok { background: #10b981; }
  .bad { background: #ef4444; }
  .warn { background: #f59e0b; }
  .label { font-weight: 600; font-size: 14px; }
  .detail { color: #475569; font-size: 13px; margin-top: 2px; word-break: break-word; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th, td { text-align: left; padding: 8px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
  th { color: #64748b; font-weight: 600; }
  .btn { background: #6366f1; color: #fff; border: 0; border-radius: 10px; padding: 11px 18px; font-size: 14px; font-weight: 600; cursor: pointer; }
  .note { border-radius: 10px; padding: 12px 14px; font-size: 14px; margin-bottom: 16px; }
  .note.good { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
  .note.bad { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
  a.back { color: #6366f1; font-size: 14px; text-decoration: none; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Diagnostik Notifikasi</h1>
  <p class="sub">Memeriksa penyebab notifikasi tidak sampai ke HP, dari konfigurasi sampai antrean.</p>

  <?php if ($testResult): ?>
    <div class="note <?= $testResult['ok'] ? 'good' : 'bad' ?>"><?= e($testResult['pesan']) ?></div>
  <?php endif; ?>

  <?php if (!$semuaOk): ?>
    <div class="note bad">Ada pemeriksaan yang gagal di bawah. Selama itu belum beres, tidak ada notifikasi yang akan sampai.</div>
  <?php endif; ?>

  <div class="card">
    <?php foreach ($checks as $c): ?>
      <div class="row">
        <span class="badge <?= $c['ok'] ? 'ok' : (empty($c['opsional']) ? 'bad' : 'warn') ?>"><?= $c['ok'] ? 'v' : '!' ?></span>
        <div>
          <div class="label"><?= e($c['label']) ?></div>
          <div class="detail"><?= e($c['detail']) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <div class="label" style="margin-bottom:10px;">Kiriman uji</div>
    <div class="detail" style="margin-bottom:12px;">Mengirim satu notifikasi ke perangkat Android tempat akun admin ini login. Siswa tidak menerima apa pun.</div>
    <form method="POST">
      <input type="hidden" name="action" value="test">
      <button type="submit" class="btn">Kirim notifikasi tes ke HP saya</button>
    </form>
  </div>

  <div class="card">
    <div class="label" style="margin-bottom:10px;">Antrean pengumuman</div>
    <?php if (!$antrean): ?>
      <div class="detail">Antrean kosong. Belum ada pengumuman yang dikirim, atau belum ada perangkat siswa terdaftar saat pengumuman dibuat.</div>
    <?php else: ?>
      <table>
        <tr><th>Status</th><th>Jumlah</th></tr>
        <?php foreach ($antrean as $a): ?>
          <tr><td><?= e($a['status']) ?></td><td><?= (int) $a['jumlah'] ?></td></tr>
        <?php endforeach; ?>
      </table>
      <div class="detail" style="margin-top:10px;">Kalau <strong>pending</strong> atau <strong>retry</strong> menumpuk dan tidak pernah menjadi <strong>sent</strong>, berarti cron belum berjalan.</div>
    <?php endif; ?>
  </div>

  <?php if ($errors): ?>
  <div class="card">
    <div class="label" style="margin-bottom:10px;">Error terakhir dari antrean</div>
    <table>
      <tr><th>Pengumuman</th><th>Percobaan</th><th>Error</th><th>Waktu</th></tr>
      <?php foreach ($errors as $er): ?>
        <tr>
          <td>#<?= (int) $er['pengumuman_id'] ?></td>
          <td><?= (int) $er['attempts'] ?></td>
          <td><?= e($er['last_error']) ?></td>
          <td><?= e($er['updated_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>

  <a class="back" href="<?= BASE_URL ?>pages/dashboard.php">&larr; Kembali ke dashboard</a>
</div>
</body>
</html>
