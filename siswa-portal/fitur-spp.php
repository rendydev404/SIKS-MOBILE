<?php
/**
 * Fitur SPP Bulanan - Portal Siswa
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['siswa_id'])) {
    header('Location: index.php');
    exit;
}

$siswaId = $_SESSION['siswa_id'];
$siswa = $pdo->prepare("SELECT s.*, k.nama_kelas, k.tingkat FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
$siswa->execute([$siswaId]);
$siswa = $siswa->fetch();

$pageTitle = 'Manajemen SPP Bulanan';

// Logic Kalender SPP (Copied from former dashboard)
$bulanList = getBulanIndonesia();
$tahunReal = date('Y');
$bulanReal = (int)date('n');
$startYear = (int)($siswa['tahun_masuk'] ?? 2024);
// Academic year starts in July. If current month < 7, we are in the second half of previous start year.
$academicYearNow = ($bulanReal < 7) ? $tahunReal - 1 : $tahunReal;

$tingkatSiswaInfo = $siswa['tingkat'] ?? '';
$maxGradeIndex = 0; // Kelas 10
if (in_array($tingkatSiswaInfo, ['XI'])) $maxGradeIndex = 1;
elseif (in_array($tingkatSiswaInfo, ['XII', 'Alumni'])) $maxGradeIndex = 2;

$tahunMax = $startYear + $maxGradeIndex;

// Clamp to student's allowed range
$tahunDefault = max($startYear, min($tahunMax, $academicYearNow));
$tahunFilter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : $tahunDefault;

// Proteksi server-side: blokir akses ke tahun kelas yang belum dijalani
if ($tahunFilter > $tahunMax) {
    header('Location: ?tahun=' . $tahunDefault);
    exit;
}

$stmtPembayaran = $pdo->prepare("SELECT * FROM pembayaran WHERE siswa_id = ? AND tahun = ? AND jenis_pembayaran = 'SPP' ORDER BY FIELD(bulan, 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'), created_at DESC");
$stmtPembayaran->execute([$siswaId, $tahunFilter]);
$pembayaranList = $stmtPembayaran->fetchAll();

// Get notes and raw status for display in table
$notasBayar = [];
$pendingCount = [];
$hasLunas = [];

// Analisa status per bulan dari riwayat
foreach ($pembayaranList as $p) {
    if ($p['status'] == 'lunas') {
        $hasLunas[$p['bulan']] = true;
    }
    if ($p['status'] == 'pending') {
        $pendingCount[$p['bulan']] = true;
    }
}

// Hanya ambil catatan penolakan jika tidak ada transaksi yang sukses/proses untuk bulan tsb
foreach ($pembayaranList as $p) {
    if ($p['status'] == 'ditolak' && !isset($hasLunas[$p['bulan']]) && !isset($pendingCount[$p['bulan']])) {
        // Ambil yang paling baru (created_at DESC)
        if (!isset($notasBayar[$p['bulan']])) {
            $notasBayar[$p['bulan']] = $p['admin_note'];
        }
    }
}

$nominalSpp = getNominalPembayaran($pdo, 'SPP', $startYear);
$dataTunggakan = hitungTunggakan($pdo, $siswaId, true);

// Hitung total SPP-only (jangan campur dengan biaya lain)
$tunggakanSppList  = $dataTunggakan['spp'] ?? [];
$jumlahBulanTunggak = count($tunggakanSppList);
$totalTunggakanSpp  = $jumlahBulanTunggak * $nominalSpp;
// Kurangi yang sudah nyicil (sisa) agar tidak dobel hitung
// Lebih akurat: hitung sisa per bulan dari cekPembayaran sudah dilakukan di hitungTunggakan
// Ambil dari sisa yang real (total field) dikurangi lainnya
$totalLainnya = 0;
foreach (($dataTunggakan['lainnya'] ?? []) as $l) {
    $totalLainnya += $l['sisa'] ?? 0;
}
$totalTunggakanSpp = max(0, $dataTunggakan['total'] - $totalLainnya);

include '../includes/header-siswa.php';
?>

<div class="container" style="padding-top: 20px;">
    <div class="toolbar" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
        <a href="bayar.php?type=SPP" class="btn btn-primary">
            <i class="fas fa-plus"></i> Kirim Bukti Bayar / Cicil SPP
        </a>
    </div>

    <!-- Rejection Alert (SPP Specific) -->
    <?php 
    $stmtRej = $pdo->prepare("SELECT * FROM pembayaran WHERE siswa_id = ? AND jenis_pembayaran = 'SPP' AND status = 'ditolak' ORDER BY created_at DESC LIMIT 1");
    $stmtRej->execute([$siswaId]);
    $lastRej = $stmtRej->fetch();
    
    if ($lastRej): 
        // Hanya tampilkan jika belum ada transaksi yang mengover (lebih baru dan sukses/pending untuk bulan yang sama)
        $stmtCover = $pdo->prepare("SELECT 1 FROM pembayaran WHERE siswa_id = ? AND jenis_pembayaran = 'SPP' AND bulan = ? AND tahun = ? AND created_at > ? AND status IN ('pending', 'lunas')");
        $stmtCover->execute([$siswaId, $lastRej['bulan'], $lastRej['tahun'], $lastRej['created_at']]);
        if (!$stmtCover->fetch()):
    ?>
        <div class="alert alert-danger animate-slide-up" style="margin-bottom: 25px; border-left: 6px solid #ef4444; background: rgba(239, 68, 68, 0.1); padding: 20px; border-radius: 16px;">
            <div style="display: flex; gap: 15px; align-items: flex-start;">
                <i class="fas fa-times-circle" style="font-size: 24px; color: #ef4444; margin-top: 2px;"></i>
                <div style="flex: 1;">
                    <strong style="display: block; color: #fff; margin-bottom: 5px; font-size: 16px;">Pembayaran SPP <?= e($lastRej['bulan']) ?> <?= e($lastRej['tahun']) ?> Ditolak</strong>
                    <div style="background: rgba(0,0,0,0.2); padding: 12px; border-radius: 10px; margin-top: 10px; border: 1px solid rgba(239, 68, 68, 0.2);">
                        <small style="display: block; color: #fca5a5; text-transform: uppercase; font-size: 10px; font-weight: 800; margin-bottom: 4px;">Alasan Admin:</small>
                        <span style="color: #fecaca; font-size: 14px; font-weight: 500;"><?= e($lastRej['admin_note'] ?: 'Bukti tidak valid atau tidak terbaca.') ?></span>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; endif; ?>

    <div class="stats-grid" style="margin-bottom: 25px;">
        <div class="stat-card glass">
            <div class="stat-icon success">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-info">
                <div class="stat-label">Total Tunggakan SPP</div>
                <div class="stat-value" style="color: var(--danger);"><?= formatRupiah($totalTunggakanSpp) ?></div>
                <p style="font-size: 11px; color: var(--text-secondary);"><?= $jumlahBulanTunggak ?> bulan belum lunas</p>
            </div>
        </div>
        <div class="stat-card glass">
            <div class="stat-icon primary">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-info">
                <div class="stat-label">Tarif Bulanan</div>
                <div class="stat-value"><?= formatRupiah($nominalSpp) ?></div>
                <p style="font-size: 11px; color: var(--text-secondary);">SPP Tetap</p>
            </div>
        </div>
    </div>

    <div class="card glass">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <h2 class="card-title"><i class="fas fa-calendar-check" style="color: var(--primary);"></i> Kalender Pembayaran SPP</h2>
            <div class="year-selector" style="display: flex; gap: 8px;">
                <?php 
                $startYear = (int)($siswa['tahun_masuk'] ?? 2024);
                // SMK memiliki 3 tahun masa studi (Kelas 10, 11, 12)
                $grades = [10, 11, 12];
                for($i = 0; $i < 3; $i++): 
                    $y = $startYear + $i;
                    $isLocked = ($i > $maxGradeIndex);
                ?>
                    <?php if ($isLocked): ?>
                        <span class="pill" style="opacity: 0.35; cursor: not-allowed; user-select: none; text-decoration: none; padding: 6px 15px;" title="Belum naik ke Kelas <?= $grades[$i] ?>">
                            <i class="fas fa-lock" style="font-size: 10px; margin-right: 4px;"></i>Kelas <?= $grades[$i] ?> (<?= $y ?>/<?= $y + 1 ?>)
                        </span>
                    <?php else: ?>
                        <a href="?tahun=<?= $y ?>" class="pill <?= $y == $tahunFilter ? 'active' : '' ?>" style="text-decoration: none; padding: 6px 15px; <?= $y == $tahunFilter ? 'background: var(--primary); color: white;' : '' ?>">
                            Kelas <?= $grades[$i] ?> (<?= $y ?>/<?= $y + 1 ?>)
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="month-flow">
                <?php 
                // Kalender Pendidikan dimulai bulan Juli dan berakhir di Juni tahun berikutnya
                $bulanPendidikan = [
                    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
                    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni'
                ];
                
                foreach ($bulanPendidikan as $i => $bulan): 
                    // Tentukan tahun riil untuk mengecek database (Jan-Jun berarti masuk tahun depannya dari tahun akademis)
                    $cekTahunDatabase = $tahunFilter;
                    if (in_array($bulan, ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni'])) {
                        $cekTahunDatabase = $tahunFilter + 1;
                    }
                
                    $cek = cekPembayaran($pdo, $siswaId, $bulan, $cekTahunDatabase, 'SPP', (int)($siswa['tahun_masuk'] ?? 0));
                    $status = $cek['status']; // lunas, nyicil, belum
                    
                    // Prioritaskan status 'pending' jika ada transaksi pending bulan ini
                    if (isset($pendingCount[$bulan]) && $status != 'lunas') {
                        $status = 'pending';
                    }
                    
                    $sdhBayar = ($cek['total_dibayar'] > 0) || $status == 'pending';
                    
                    // Logic Tanggal 25 (Hanya untuk batas penagihan)
                    $tglSekarang = (int)date('j');
                    $isLate = $tglSekarang > 25;
                    
                    // Batas Penagihan (Effective Billing Boundary)
                    $effTime = strtotime($isLate ? "+1 month" : "now");
                    $effYear = (int)date('Y', $effTime);
                    $effMonth = (int)date('n', $effTime);
                    
                    // Konversi nama bulan ke angka untuk komparasi future/past
                    $loopMonthNumeric = array_search($bulan, $bulanList) + 1;
                    
                    $isFuture = ($cekTahunDatabase > $effYear) || ($cekTahunDatabase == $effYear && $loopMonthNumeric > $effMonth);
                    $isBeforeEnroll = ($tahunFilter == $startYear) && ($i < 0); // Selalu valid karena Juli min index 0

                    $class = 'belum';
                    $customLabel = "";

                    if ($sdhBayar) {
                        $class = $status == 'pending' ? 'pending' : ($status == 'nyicil' ? 'nyicil' : 'lunas');
                    } elseif ($isBeforeEnroll) {
                        $class = 'disabled';
                    } elseif ($isFuture) {
                        $class = 'upcoming';
                    } else {
                        // Ini berarti belum bayar dan sudah jatuh tempo
                        if ($isLate && $loopMonthNumeric == $effMonth && $cekTahunDatabase == $effYear) {
                            $customLabel = "Tagihan Baru"; // Label khusus untuk bulan depan yang baru masuk
                        }
                    }

                    $icon = "fa-times"; $color = "var(--danger)"; $txt = "Tunggak";
                    if ($class == 'lunas') { $icon = "fa-check-double"; $color = "var(--success)"; $txt = "Lunas"; }
                    if ($class == 'nyicil') { $icon = "fa-adjust"; $color = "#0ea5e9"; $txt = "Kurang " . number_format($cek['sisa'] / 1000, 0, ',', '.') . "k"; }
                    if ($class == 'pending') { $icon = "fa-clock"; $color = "var(--warning)"; $txt = "Proses"; }
                    if ($class == 'disabled') { $icon = "fa-minus"; $color = "var(--text-muted)"; $txt = "-"; }
                    if ($class == 'upcoming') { $icon = "fa-calendar-minus"; $color = "var(--accent-yellow)"; $txt = "Nanti"; }
                    
                    // Override text jika ada custom label (misal Tagihan Baru)
                    if ($customLabel) { $txt = $customLabel; $color = "#8b5cf6"; $icon = "fa-exclamation-circle"; }
                    
                    $adminNote = $notasBayar[$bulan] ?? '';
                ?>
                <div class="month-node <?= $class ?>" <?= ($adminNote) ? 'onclick="showRejection(\''.e($bulan).'\', \''.e($adminNote).'\')"' : '' ?>>
                    <div class="month-name"><?= substr($bulan, 0, 3) ?></div>
                    <div class="month-status-icon" style="color: <?= $color ?>;"><i class="fas <?= $icon ?>"></i></div>
                    <span class="status-badge" style="background: <?= $color ?>22; color: <?= $color ?>;"><?= $txt ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Riwayat SPP -->
    <div class="card glass" style="margin-top: 25px;">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-history"></i> Riwayat Pembayaran SPP</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($pembayaranList)): ?>
                <p style="padding: 20px; text-align: center; color: var(--text-muted);">Belum ada riwayat pembayaran untuk tahun <?= $tahunFilter ?>.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table" style="color: #fff;">
                        <thead>
                            <tr>
                                <th>Bulan/Tahun</th>
                                <th>Tanggal</th>
                                <th>Jumlah</th>
                                <th>Metode</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pembayaranList as $p): ?>
                            <tr>
                                <td><?= e($p['bulan']) ?> <?= e($p['tahun']) ?></td>
                                <td><?= formatTanggal($p['tanggal_bayar'], 'd/m/Y') ?></td>
                                <td><?= formatRupiah($p['jumlah_bayar']) ?></td>
                                <td><?= e($p['metode_bayar']) ?></td>
                                <td>
                                    <?php if ($p['status'] == 'lunas'): ?>
                                        <span class="badge badge-success">Selesai</span>
                                    <?php elseif ($p['status'] == 'pending'): ?>
                                        <span class="badge badge-warning">Verifikasi</span>
                                    <?php else: ?>
                                        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 5px;">
                                            <span class="badge badge-danger">Ditolak</span>
                                            <small style="color: #ef4444; font-size: 11px; font-style: italic; max-width: 150px; text-align: right;">
                                                Ket: <?= e($p['admin_note'] ?: '-') ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
// Modals and scripts can be added here or in footer-siswa.php
include '../includes/footer-siswa.php'; 
?>
