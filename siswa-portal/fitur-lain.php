<?php
/**
 * Fitur Pembayaran Lain-lain - Portal Siswa
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['siswa_id'])) {
    header('Location: index.php');
    exit;
}
$type = $_GET['type'] ?? '';
if (!$type) {
    header('Location: dashboard.php');
    exit;
}

$siswaId = $_SESSION['siswa_id'];
$siswa = $pdo->prepare("SELECT s.*, k.nama_kelas, k.tingkat, k.jurusan FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
$siswa->execute([$siswaId]);
$siswa = $siswa->fetch();

if (!$siswa) {
    header('Location: dashboard.php');
    exit;
}

if (isPembayaranKhususTKJ($type) && !isJurusanTKJ($siswa['jurusan'] ?? '')) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Detail Pembayaran: ' . $type;
$tingkatSiswaInfo = $siswa['tingkat'] ?? '';
$bulanReal = (int)date('n');
$tahunReal = (int)date('Y');
$currentAcademicYear = ($bulanReal < 7) ? $tahunReal - 1 : $tahunReal;

$startYear = (int)($siswa['tahun_masuk'] ?? 0);
if ($startYear <= 0) {
    $startYear = date('Y');
    $statusSiswa = $siswa['status'] ?? 'aktif';
    if ($statusSiswa === 'aktif') {
        if ($tingkatSiswaInfo === 'X') {
            $startYear = $currentAcademicYear;
        } elseif ($tingkatSiswaInfo === 'XI') {
            $startYear = $currentAcademicYear - 1;
        } elseif ($tingkatSiswaInfo === 'XII' || $tingkatSiswaInfo === 'Alumni') {
            $startYear = $currentAcademicYear - 2;
        }
    }
}

$tahunDefault = $startYear;
if ($tingkatSiswaInfo === 'XI') $tahunDefault = $startYear + 1;
elseif ($tingkatSiswaInfo === 'XII' || $tingkatSiswaInfo === 'Alumni') $tahunDefault = $startYear + 2;

$tahunFilter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : $tahunDefault;

// Untuk biaya tahunan: pakai tahun pelaksanaan. Untuk sekali bayar: pakai angkatan.
$isYearly     = isYearlyPayment($type);
$lookupTahun  = $isYearly ? $tahunFilter : $startYear;
$nominalWajib = getNominalPembayaran($pdo, $type, $lookupTahun);

// Batas tahun maksimum yang boleh diakses (sesuai kelas saat ini)
$tahunMax = $tahunDefault;

// Proteksi server-side: blokir akses ke tahun kelas yang belum dijalani
if ($isYearly && $tahunFilter > $tahunMax) {
    header('Location: ?type=' . urlencode($type) . '&tahun=' . $tahunDefault);
    exit;
}


// Get riwayat pembayaran (urutkan ASC supaya label cicilan ke-N konsisten)
if ($isYearly) {
    $stmtHist = $pdo->prepare("
        SELECT * FROM pembayaran 
        WHERE siswa_id = ? AND jenis_pembayaran = ? AND tahun = ?
        ORDER BY tanggal_bayar ASC, id ASC
    ");
    $stmtHist->execute([$siswaId, $type, $tahunFilter]);
} else {
    $stmtHist = $pdo->prepare("
        SELECT * FROM pembayaran 
        WHERE siswa_id = ? AND jenis_pembayaran = ?
        ORDER BY tanggal_bayar ASC, id ASC
    ");
    $stmtHist->execute([$siswaId, $type]);
}
$riwayat = $stmtHist->fetchAll();

$totalVerified = 0;
$totalPending  = 0;
foreach ($riwayat as $r) { 
    if ($r['status'] == 'lunas') {
        $totalVerified += $r['jumlah_bayar']; 
    } elseif ($r['status'] == 'pending') {
        $totalPending += $r['jumlah_bayar'];
    }
}

$totalTerbayar    = $totalVerified + $totalPending;
$sisaTagihan      = max(0, $nominalWajib - $totalTerbayar);
$persenBayar      = ($nominalWajib > 0) ? min(100, round(($totalTerbayar / $nominalWajib) * 100)) : 0;
$lunasVerified    = ($totalVerified >= $nominalWajib && $nominalWajib > 0);
$isPendingComplete = (!$lunasVerified && ($totalTerbayar >= $nominalWajib));
$isNyicil         = (!$lunasVerified && !$isPendingComplete && $totalTerbayar > 0);

// Hitung nomor cicilan valid (abaikan yang ditolak)
$cicilanKe = 0;
foreach ($riwayat as $r) {
    if ($r['status'] !== 'ditolak') $cicilanKe++;
}

include '../includes/header-siswa.php';
?>

<style>
    .btn-year { padding: 6px 15px; border-radius: 8px; color: var(--text-secondary); text-decoration: none; font-size: 13px; transition: all 0.2s; }
    .btn-year.active { background: var(--primary); color: #fff; font-weight: 600; }
    .btn-year:hover:not(.active) { background: rgba(255,255,255,0.05); color: #fff; }
    .cicil-progress-wrap { background: rgba(0,0,0,0.3); border-radius: 50px; height: 14px; overflow: hidden; margin: 14px auto; max-width: 440px; }
    .cicil-progress-bar { height: 100%; border-radius: 50px; transition: width 0.6s ease; background: linear-gradient(90deg, #10b981, #34d399); }
    .cicil-progress-bar.pending { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
    .cicil-progress-bar.partial  { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
    .cicil-stats { display: flex; justify-content: center; gap: 30px; flex-wrap: wrap; margin-top: 16px; }
    .cicil-stat { text-align: center; }
    .cicil-stat .lbl { font-size: 10px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
    .cicil-stat .val { font-size: 17px; font-weight: 700; color: #fff; }
    .badge-ke { background: rgba(99,102,241,0.15); color: #a5b4fc; border: 1px solid rgba(99,102,241,0.3); border-radius: 20px; font-size: 11px; padding: 3px 9px; font-weight: 600; white-space: nowrap; }
</style>

<div class="container" style="padding-top: 20px;">
    <!-- Toolbar -->
    <div class="toolbar" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
        </a>
        
        <?php if ($isYearly): ?>
        <div class="year-filter" style="display: flex; background: rgba(0,0,0,0.2); padding: 4px; border-radius: 10px; gap: 2px;">
            <?php 
            $grades = [10, 11, 12];
            // Tentukan indeks kelas maksimum yang boleh diakses siswa sekarang
            $maxGradeIndex = 0; // default: Kelas 10 saja
            if (in_array($tingkatSiswaInfo, ['XI'])) $maxGradeIndex = 1;
            elseif (in_array($tingkatSiswaInfo, ['XII', 'Alumni'])) $maxGradeIndex = 2;

            for ($i = 0; $i < 3; $i++): 
                if ($type === 'Daftar Ulang' && $i === 0) continue;
                $y = $startYear + $i;
                $label = "Kelas " . $grades[$i];
                $isLocked = ($i > $maxGradeIndex); // Kelas yang belum dijalani
            ?>
                <?php if ($isLocked): ?>
                    <span class="btn-year" style="opacity:0.35; cursor:not-allowed; user-select:none;" title="Belum naik ke <?= $label ?>">
                        <i class="fas fa-lock" style="font-size:10px; margin-right:4px;"></i><?= $label ?> (<?= $y ?>)
                    </span>
                <?php else: ?>
                    <a href="?type=<?= urlencode($type) ?>&tahun=<?= $y ?>" class="btn-year <?= $y == $tahunFilter ? 'active' : '' ?>">
                        <?= $label ?> (<?= $y ?>)
                    </a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    <!-- Rejection Alert (Feature Specific) -->
    <?php 
    $stmtRej = $pdo->prepare("SELECT * FROM pembayaran WHERE siswa_id = ? AND jenis_pembayaran = ? AND status = 'ditolak' ORDER BY created_at DESC LIMIT 1");
    $stmtRej->execute([$siswaId, $type]);
    $lastRej = $stmtRej->fetch();
    
    if ($lastRej): 
        // Hanya tampilkan jika belum ada transaksi yang mengover (lebih baru dan sukses/pending)
        $stmtCover = $pdo->prepare("SELECT 1 FROM pembayaran WHERE siswa_id = ? AND jenis_pembayaran = ? AND created_at > ? AND status IN ('pending', 'lunas')");
        $stmtCover->execute([$siswaId, $type, $lastRej['created_at']]);
        if (!$stmtCover->fetch()):
    ?>
        <div class="alert alert-danger animate-slide-up" style="margin-bottom: 25px; border-left: 6px solid #ef4444; background: rgba(239, 68, 68, 0.1); padding: 20px; border-radius: 16px;">
            <div style="display: flex; gap: 15px; align-items: flex-start;">
                <i class="fas fa-times-circle" style="font-size: 24px; color: #ef4444; margin-top: 2px;"></i>
                <div style="flex: 1;">
                    <strong style="display: block; color: #fff; margin-bottom: 5px; font-size: 16px;">Pembayaran Sebelumnya Ditolak</strong>
                    <div style="background: rgba(0,0,0,0.2); padding: 12px; border-radius: 10px; margin-top: 10px; border: 1px solid rgba(239, 68, 68, 0.2);">
                        <small style="display: block; color: #fca5a5; text-transform: uppercase; font-size: 10px; font-weight: 800; margin-bottom: 4px;">Alasan Admin:</small>
                        <span style="color: #fecaca; font-size: 14px; font-weight: 500;"><?= e($lastRej['admin_note'] ?: 'Bukti tidak valid atau tidak terbaca.') ?></span>
                    </div>
                    <p style="font-size: 13px; color: var(--text-secondary); margin-top: 10px;">Silakan upload bukti pembayaran yang baru dan pastikan foto terlihat jelas.</p>
                </div>
            </div>
        </div>
    <?php endif; endif; ?>
    </div>

    <!-- Status Hero Card -->
    <div class="status-hero animate-slide-up" style="
        background: <?= $lunasVerified ? 'rgba(16,185,129,0.1)' : ($isPendingComplete ? 'rgba(59,130,246,0.1)' : ($isNyicil ? 'rgba(245,158,11,0.07)' : 'rgba(239,68,68,0.07)')) ?>;
        border: 1px solid <?= $lunasVerified ? 'var(--success)' : ($isPendingComplete ? '#3b82f6' : ($isNyicil ? '#f59e0b' : 'var(--danger)')) ?>;
        border-radius: 20px; padding: 28px; text-align: center;">
        
        <div style="font-size: 20px; font-weight: 800; color: #fff; margin-bottom: 4px;"><?= e($type) ?></div>
        <?php if ($isYearly): ?>
        <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 14px;">Tahun Ajaran <?= $tahunFilter ?>/<?= $tahunFilter + 1 ?></div>
        <?php endif; ?>

        <?php if (stripos($type, 'Daftar Ulang') !== false): ?>
        <div class="alert alert-info" style="text-align: left; max-width: 580px; margin: 0 auto 16px; border-left: 4px solid #3b82f6; background: rgba(59,130,246,0.1);">
            <h5 style="margin-top: 0; color: #60a5fa;"><i class="fas fa-info-circle"></i> Instruksi Berkas:</h5>
            <p style="margin-bottom: 8px; font-size: 13px;">Harap membawa berkas berikut saat melakukan daftar ulang:</p>
            <ul style="margin-bottom: 10px; font-size: 13px; list-style-position: inside;">
                <li>KK (Kartu Keluarga)</li>
                <li>KTP Orang Tua</li>
                <li>Ijazah SMP</li>
            </ul>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <span class="badge" style="background:#ef4444;color:white;">MPLB: Merah</span>
                <span class="badge" style="background:#eab308;color:black;">BDP: Kuning</span>
                <span class="badge" style="background:#3b82f6;color:white;">TKJ: Biru</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Progress Bar -->
        <div style="max-width: 440px; margin: 0 auto;">
            <div style="display:flex; justify-content:space-between; font-size:11px; color:var(--text-secondary); margin-bottom:5px;">
                <span>Progress Cicilan</span>
                <span style="font-weight:700; color:<?= $lunasVerified ? 'var(--success)' : ($isPendingComplete ? '#3b82f6' : '#f59e0b') ?>"><?= $persenBayar ?>%</span>
            </div>
            <div class="cicil-progress-wrap">
                <div class="cicil-progress-bar <?= $isPendingComplete ? 'pending' : ($isNyicil ? 'partial' : '') ?>" style="width: <?= $persenBayar ?>%;"></div>
            </div>
        </div>

        <!-- Stat Boxes -->
        <div class="cicil-stats">
            <div class="cicil-stat">
                <div class="lbl">Total Tagihan</div>
                <div class="val"><?= formatRupiah($nominalWajib) ?></div>
            </div>
            <?php if ($totalVerified > 0): ?>
            <div class="cicil-stat">
                <div class="lbl">Sudah Lunas</div>
                <div class="val" style="color:var(--success);"><?= formatRupiah($totalVerified) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($totalPending > 0): ?>
            <div class="cicil-stat">
                <div class="lbl">Dalam Proses</div>
                <div class="val" style="color:#60a5fa;"><?= formatRupiah($totalPending) ?></div>
            </div>
            <?php endif; ?>
            <div class="cicil-stat">
                <div class="lbl"><?= $lunasVerified ? '✅ Status' : 'Sisa Tagihan' ?></div>
                <div class="val" style="color:<?= $lunasVerified ? 'var(--success)' : ($isNyicil ? '#f59e0b' : ($isPendingComplete ? '#3b82f6' : '#ef4444')) ?>;">
                    <?= $lunasVerified ? 'LUNAS' : formatRupiah($sisaTagihan) ?>
                </div>
            </div>
        </div>
        
        <!-- Action Badge + Button -->
        <div style="margin-top: 22px;">
            <?php if ($lunasVerified): ?>
                <span class="badge badge-success" style="font-size:15px; padding:10px 28px;"><i class="fas fa-check-circle"></i> PEMBAYARAN LUNAS</span>
            <?php elseif ($isPendingComplete): ?>
                <span class="badge" style="background:#3b82f6; color:white; font-size:15px; padding:10px 28px;"><i class="fas fa-clock"></i> MENUNGGU VERIFIKASI</span>
                <p style="margin-top:10px; color:var(--text-secondary); font-size:13px;">Pembayaran Anda sedang diperiksa oleh Admin.</p>
            <?php elseif ($isNyicil): ?>
                <span class="badge badge-warning" style="font-size:15px; padding:10px 28px;"><i class="fas fa-adjust"></i> NYICIL — SISA <?= formatRupiah($sisaTagihan) ?></span>
                <p style="margin:10px 0 12px; color:var(--text-secondary); font-size:13px;">Sudah <?= $cicilanKe ?>× cicilan. Lanjutkan untuk melunasi.</p>
                <a href="bayar.php?type=<?= urlencode($type) ?><?= $tahunFilter ? '&tahun='.$tahunFilter : '' ?>" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Lanjutkan Cicilan
                </a>
            <?php else: ?>
                <span class="badge badge-danger" style="font-size:15px; padding:10px 28px;"><i class="fas fa-exclamation-triangle"></i> BELUM LUNAS</span>
                <p style="margin:10px 0 12px; color:var(--text-secondary); font-size:13px;">Silakan bayar langsung ke Bendahara atau kirim bukti transfer.</p>
                <a href="bayar.php?type=<?= urlencode($type) ?><?= $tahunFilter ? '&tahun='.$tahunFilter : '' ?>" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Bayar / Cicil (Kirim Bukti)
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Riwayat Cicilan -->
    <div class="card glass animate-slide-up" style="animation-delay: 0.1s; margin-top: 20px;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-history"></i> Riwayat Cicilan <?= e($type) ?>
                <?php if ($cicilanKe > 0): ?>
                <span style="background:rgba(99,102,241,0.15);color:#a5b4fc;border:1px solid rgba(99,102,241,0.3);border-radius:20px;font-size:11px;padding:2px 9px;font-weight:600;margin-left:6px;"><?= $cicilanKe ?>× Cicilan</span>
                <?php endif; ?>
            </h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($riwayat)): ?>
                <div style="padding: 40px; text-align: center; color: var(--text-muted);">
                    <i class="fas fa-folder-open" style="font-size: 36px; display: block; margin-bottom: 10px;"></i>
                    Belum ada riwayat pembayaran.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table" style="color: #fff;">
                        <thead>
                            <tr>
                                <th>Cicilan</th>
                                <th>Tanggal</th>
                                <th>Jumlah</th>
                                <th style="text-align: right;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $cicNo = 0; foreach ($riwayat as $r): ?>
                            <tr>
                                <td>
                                    <?php if ($r['status'] !== 'ditolak'): $cicNo++; ?>
                                        <span class="badge-ke">Ke-<?= $cicNo ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-size:12px;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatTanggal($r['tanggal_bayar'], 'd/m/Y') ?></td>
                                <td style="font-weight: 700; color: <?= $r['status'] === 'ditolak' ? 'var(--text-muted)' : 'var(--success)' ?>;"><?= formatRupiah($r['jumlah_bayar']) ?></td>
                                <td style="text-align: right;">
                                    <?php if ($r['status'] == 'lunas'): ?>
                                        <span class="badge badge-success">Verified</span>
                                    <?php elseif ($r['status'] == 'pending'): ?>
                                        <span class="badge badge-warning">Proses</span>
                                    <?php else: ?>
                                        <div style="display:flex; flex-direction:column; align-items:flex-end; gap:3px;">
                                            <span class="badge badge-danger">Ditolak</span>
                                            <?php if (!empty($r['admin_note'])): ?>
                                            <small style="color:#ef4444; font-size:10px; font-style:italic; max-width:140px; text-align:right;"><?= e($r['admin_note']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if (count($riwayat) > 1 && $totalTerbayar > 0): ?>
                        <tfoot>
                            <tr style="border-top: 1px solid rgba(255,255,255,0.1);">
                                <td colspan="2" style="text-align:right; color:var(--text-secondary); font-size:12px; padding:10px 16px;">Total Terbayar:</td>
                                <td colspan="2" style="font-weight:800; color:#34d399; padding:10px 16px;"><?= formatRupiah($totalTerbayar) ?></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer-siswa.php'; ?>
