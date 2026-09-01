<?php
/**
 * Portal Siswa - Dashboard (Modular Hub)
 * Sistem Informasi Keuangan Sekolah - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['siswa_id'])) {
    header('Location: index.php');
    exit;
}

$siswaId = $_SESSION['siswa_id'];
$siswa = $pdo->prepare("SELECT s.*, k.nama_kelas, k.tingkat, k.jurusan FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
$siswa->execute([$siswaId]);
$siswa = $siswa->fetch();

if (!$siswa) {
    header('Location: logout.php');
    exit;
}

$pageTitle = 'Pusat Keuangan Siswa';

// Get Categories
$categories = getPaymentCategories();

// Tentukan tahun dashboard berdasarkan tahun_masuk dan tingkat agar sesuai dengan logika tab di fitur-lain.php
$tingkatSiswaInfo = $siswa['tingkat'] ?? '';
$bulanReal = (int)date('n');
$tahunReal = (int)date('Y');
$currentAcademicYear = ($bulanReal < 7) ? $tahunReal - 1 : $tahunReal;

$statusSiswa = $siswa['status'] ?? 'aktif';
$startYear = (int)($siswa['tahun_masuk'] ?? 0);
if ($startYear <= 0) {
    $startYear = date('Y');
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

$tahunDashboard = $startYear;
if ($tingkatSiswaInfo === 'XI') $tahunDashboard = $startYear + 1;
elseif ($tingkatSiswaInfo === 'XII' || $tingkatSiswaInfo === 'Alumni') $tahunDashboard = $startYear + 2;

// Special category for SPP
$sppConfig = ['icon' => 'fa-calendar-alt', 'color' => '#6366f1'];

// Summary stats
$dataTunggakanSpp = hitungTunggakan($pdo, $siswaId, true);
$pengumuman = $pdo->query("SELECT * FROM pengumuman WHERE is_active = 1 ORDER BY created_at DESC")->fetchAll();

// Get Rejected Payments - Hanya tampilkan jika belum ada transaksi pending/lunas yang lebih baru/menggantikan
$rejectedPayments = $pdo->prepare("
    SELECT p1.* 
    FROM pembayaran p1 
    WHERE p1.siswa_id = ? AND p1.status = 'ditolak'
    AND NOT EXISTS (
        SELECT 1 FROM pembayaran p2 
        WHERE p2.siswa_id = p1.siswa_id 
        AND p2.jenis_pembayaran = p1.jenis_pembayaran
        AND (p2.bulan = p1.bulan OR (p1.bulan IS NULL AND p2.bulan IS NULL))
        AND (p2.tahun = p1.tahun OR (p1.tahun IS NULL AND p2.tahun IS NULL))
        AND p2.status IN ('pending', 'lunas')
    )
    ORDER BY p1.created_at DESC LIMIT 5
");
$rejectedPayments->execute([$siswaId]);
$rejectedList = $rejectedPayments->fetchAll();

include '../includes/header-siswa.php';
?>

<style>
/* ── Minimalist Clean Dashboard Styles ── */
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

:root {
    --dash-surface: var(--bg-card);
    --dash-border: var(--border-color);
    --dash-text: var(--text-primary);
    --dash-muted: var(--text-muted);
    --dash-shadow: var(--shadow-sm);
    --dash-hover: var(--shadow-md);
}

body.light-mode {
    --dash-surface: #ffffff;
    --dash-border: #e2e8f0;
    --dash-text: #0f172a;
    --dash-muted: #64748b;
    --dash-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -2px rgba(0,0,0,0.05);
    --dash-hover: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.05);
}

.dash-wrap { 
    padding: 32px 24px; 
    max-width: 1200px; 
    margin: 0 auto; 
    font-family: 'Plus Jakarta Sans', sans-serif;
    color: var(--dash-text);
}

/* Animations */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(15px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Hero Card (Minimalist) */
.hero-card {
    display: flex; align-items: center; justify-content: space-between;
    background: var(--dash-surface);
    border: 1px solid var(--dash-border);
    border-radius: 24px; 
    padding: 40px 48px;
    margin-bottom: 32px;
    box-shadow: var(--dash-shadow);
    animation: fadeInUp 0.5s ease forwards;
}
.hero-avatar {
    width: 80px; height: 80px; border-radius: 50%;
    background: #f1f5f9; color: #475569;
    display: flex; align-items: center; justify-content: center;
    font-size: 32px; font-weight: 700; font-family: 'Outfit', sans-serif;
    flex-shrink: 0; border: 1px solid #e2e8f0;
}
body:not(.light-mode) .hero-avatar {
    background: #1e293b; color: #f1f5f9; border-color: #334155;
}
.hero-info { flex: 1; padding: 0 32px; }
.hero-info .greeting { 
    font-size: 14px; color: var(--dash-muted); font-weight: 600; text-transform: uppercase; 
    letter-spacing: 1px; margin-bottom: 8px; font-family: 'Outfit', sans-serif;
}
.hero-info .name { 
    font-size: 28px; font-weight: 800; color: var(--dash-text); margin-bottom: 16px; line-height: 1.2; 
    font-family: 'Outfit', sans-serif; letter-spacing: -0.5px;
}
.hero-info .badges { display: flex; gap: 12px; flex-wrap: wrap; }
.hero-badge { 
    padding: 6px 16px; border-radius: 8px; font-size: 13px; font-weight: 600;
    display: flex; align-items: center; gap: 8px;
    background: rgba(99,102,241,0.08); color: #6366f1;
}
.hero-badge.kelas { background: rgba(16,185,129,0.08); color: #10b981; }
.hero-badge.lulus { background: rgba(245,158,11,0.08); color: #f59e0b; }

/* Announcement Strip (Redesigned) */
.announcement-container {
    display: flex; flex-direction: column; gap: 16px;
    margin-bottom: 32px;
}
.ann-card {
    background: var(--dash-surface); 
    border: 1px solid var(--dash-border);
    border-radius: 16px; padding: 24px;
    display: flex; gap: 20px; align-items: flex-start;
    box-shadow: var(--dash-shadow);
    animation: fadeInUp 0.5s ease forwards; opacity: 0;
    position: relative; overflow: hidden;
}
.ann-card::before {
    content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%;
    background: #f59e0b;
}
.ann-card-icon { 
    width: 44px; height: 44px; border-radius: 12px; 
    background: rgba(245,158,11,0.1); color: #f59e0b; 
    display: flex; align-items: center; justify-content: center; 
    font-size: 18px; flex-shrink: 0;
}
.ann-card-body { flex: 1; }
.ann-card-label { 
    font-size: 11px; font-weight: 700; color: #f59e0b; 
    text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px; 
    display: flex; align-items: center; gap: 6px;
}
.ann-card-title { 
    color: var(--dash-text); font-weight: 700; font-size: 18px; 
    margin-bottom: 12px; font-family: 'Outfit', sans-serif;
}
.ann-card-text { 
    color: var(--dash-muted); font-size: 14px; line-height: 1.7; 
}
.ann-card-text strong {
    color: var(--dash-text);
    font-weight: 600;
}

/* Rejection Alert */
.reject-card {
    display: flex; gap: 20px; align-items: center;
    background: #fef2f2; border: 1px solid #fecaca;
    border-radius: 16px; padding: 24px; margin-bottom: 24px;
}
body:not(.light-mode) .reject-card {
    background: rgba(239,68,68,0.05); border-color: rgba(239,68,68,0.2);
}
.reject-icon { 
    width: 48px; height: 48px; border-radius: 12px; background: #fee2e2; 
    color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0;
}
body:not(.light-mode) .reject-icon { background: rgba(239,68,68,0.1); }
.reject-body { flex: 1; }
.reject-body h5 { color: #991b1b; margin: 0 0 8px; font-size: 16px; font-weight: 700; }
body:not(.light-mode) .reject-body h5 { color: #fca5a5; }
.reject-body p { margin: 0; font-size: 14px; color: #b91c1c; }
body:not(.light-mode) .reject-body p { color: #f87171; }
.reject-note { margin-top: 12px; padding: 12px 16px; background: #fff; border-radius: 8px; border: 1px solid #fecaca; font-size: 14px;}
body:not(.light-mode) .reject-note { background: rgba(0,0,0,0.2); border-color: rgba(239,68,68,0.2); }
.reject-btn { 
    padding: 12px 24px; background: #ef4444; color: #fff; 
    border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; white-space: nowrap; 
    transition: background 0.3s;
}
.reject-btn:hover { background: #dc2626; color: #fff; }

/* Main SPP Action Banner */
.spp-card {
    display: flex; align-items: center; gap: 24px;
    background: var(--dash-surface);
    border: 1px solid var(--dash-border); border-radius: 20px;
    padding: 32px 40px; text-decoration: none; margin-bottom: 48px;
    transition: all 0.3s ease;
    animation: fadeInUp 0.5s ease forwards; animation-delay: 0.2s; opacity: 0;
    box-shadow: var(--dash-shadow);
}
.spp-card:hover { 
    transform: translateY(-4px); 
    box-shadow: var(--dash-hover); 
    border-color: rgba(99,102,241,0.3);
}
.spp-card-icon { 
    width: 64px; height: 64px; border-radius: 16px; 
    background: rgba(99,102,241,0.1); 
    color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 28px; flex-shrink: 0;
}
.spp-card-info { flex: 1; }
.spp-card-info h3 { margin: 0 0 8px; color: var(--dash-text); font-size: 22px; font-weight: 700; font-family: 'Outfit', sans-serif;}
.spp-card-info p { margin: 0; color: var(--dash-muted); font-size: 15px; }
.spp-badge { 
    padding: 8px 20px; border-radius: 99px; font-size: 13px; font-weight: 600; 
    display: flex; align-items: center; gap: 8px;
}
.spp-badge.warn { background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; }
body:not(.light-mode) .spp-badge.warn { background: rgba(239,68,68,0.1); border-color: rgba(239,68,68,0.3); }

/* Category Sections */
.cat-section { margin-bottom: 48px; animation: fadeInUp 0.5s ease forwards; opacity: 0; }
.cat-section:nth-child(2) { animation-delay: 0.3s; }
.cat-section:nth-child(3) { animation-delay: 0.4s; }
.cat-section:nth-child(4) { animation-delay: 0.5s; }

.cat-header {
    display: flex; align-items: center; margin-bottom: 24px;
}
.cat-label { font-size: 16px; font-weight: 700; color: var(--dash-text); font-family: 'Outfit', sans-serif;}

/* Bento Grid System for Payments */
.pay-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 20px;
}
.pay-card {
    background: var(--dash-surface);
    border: 1px solid var(--dash-border); border-radius: 16px; padding: 24px;
    text-decoration: none; transition: all 0.3s ease;
    display: flex; flex-direction: column; gap: 20px;
    box-shadow: var(--dash-shadow);
}
.pay-card:hover { 
    transform: translateY(-4px); 
    box-shadow: var(--dash-hover); 
}
.pay-card-top { display: flex; align-items: center; justify-content: space-between; }
.pay-card-icon { 
    width: 48px; height: 48px; border-radius: 12px; 
    display: flex; align-items: center; justify-content: center; font-size: 20px;
}
.pay-card-arrow { 
    color: var(--dash-muted); font-size: 14px; transition: transform 0.3s; 
}
.pay-card:hover .pay-card-arrow { transform: translateX(4px); color: var(--dash-text); }
.pay-card-content {}
.pay-card-name { font-size: 16px; font-weight: 700; color: var(--dash-text); font-family: 'Outfit', sans-serif; margin-bottom: 12px;}

.status-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600;
}
.pill-lunas { background: rgba(16,185,129,0.1); color: #10b981; }
.pill-pending { background: rgba(239,68,68,0.1); color: #ef4444; }
.pill-verif { background: rgba(59,130,246,0.1); color: #3b82f6; }
.pill-nyicil { background: rgba(245,158,11,0.1); color: #f59e0b; }

/* Responsive adjustments */
@media(max-width: 768px) {
    .dash-wrap { padding: 20px 16px; }
    .hero-card { padding: 32px 24px; flex-direction: column; text-align: center; gap: 24px; }
    .hero-info { padding: 0; }
    .hero-info .badges { justify-content: center; }
    .spp-card { padding: 24px 20px; flex-direction: column; text-align: center; gap: 16px; }
    .pay-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
}
</style>

<div class="dash-wrap">

    <?php if (!empty($rejectedList)): ?>
    <div style="margin-bottom:24px;">
        <div style="font-size:11px;font-weight:800;color:#ef4444;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-exclamation-triangle"></i> Perlu Perbaikan
        </div>
        <?php foreach ($rejectedList as $r): ?>
        <div class="reject-card">
            <div class="reject-icon"><i class="fas fa-times-circle"></i></div>
            <div class="reject-body">
                <h5>Pembayaran <?= e($r['jenis_pembayaran']) ?> Ditolak</h5>
                <p><?= $r['bulan'] ? 'Periode: '.e($r['bulan']).' '.e($r['tahun']) : 'Tahun Ajaran: '.e($r['tahun']) ?> &bull; <strong style="color:#fff"><?= formatRupiah($r['jumlah_bayar']) ?></strong></p>
                <div class="reject-note">
                    <small>Alasan:</small>
                    <span><?= e($r['admin_note'] ?: 'Bukti tidak valid atau tidak terbaca.') ?></span>
                </div>
            </div>
            <a href="bayar.php?type=<?= urlencode($r['jenis_pembayaran']) ?>" class="reject-btn">Perbaiki <i class="fas fa-arrow-right"></i></a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Hero Welcome Card -->
    <div class="hero-card" style="margin-bottom:20px;">
        <div class="hero-avatar"><?= strtoupper(substr($siswa['nama'],0,1)) ?></div>
        <div class="hero-info">
            <div class="greeting">Selamat Datang Kembali</div>
            <div class="name"><?= e($siswa['nama']) ?></div>
            <div class="badges">
                <span class="hero-badge nis"><i class="fas fa-id-card"></i> <?= e($siswa['nis']) ?></span>
                <span class="hero-badge kelas"><i class="fas fa-graduation-cap"></i> <?= $statusSiswa==='lulus' ? 'ALUMNI / LULUS' : e($siswa['nama_kelas']??'-') ?></span>
                <?php if($statusSiswa==='lulus'): ?><span class="hero-badge lulus"><i class="fas fa-star"></i> Lulusan</span><?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($statusSiswa !== 'lulus' && !empty($pengumuman)): ?>
    <div class="announcement-container">
        <?php foreach(array_slice($pengumuman,0,3) as $index => $ann): 
            $parsedIsi = e($ann['isi']);
            $parsedIsi = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $parsedIsi);
            $parsedIsi = nl2br($parsedIsi);
        ?>
        <div class="ann-card" style="animation-delay: <?= 0.1 + ($index * 0.1) ?>s;">
            <div class="ann-card-icon"><i class="fas fa-bullhorn"></i></div>
            <div class="ann-card-body">
                <div class="ann-card-label">Pengumuman Penting</div>
                <div class="ann-card-title"><?= e($ann['judul']) ?></div>
                <div class="ann-card-text"><?= $parsedIsi ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php 
    $hasSppTunggakan = !empty($dataTunggakanSpp['spp']);
    ?>
    <!-- SPP Feature Banner -->
    <a href="fitur-spp.php" class="spp-card">
        <div class="spp-card-icon"><i class="fas fa-calendar-check"></i></div>
        <div class="spp-card-info">
            <h3>SPP Bulanan</h3>
            <p>Kelola & bayar tagihan SPP, Infak, dan Komputer</p>
        </div>
        <?php if ($hasSppTunggakan): ?>
        <span class="spp-badge warn">
            <i class="fas fa-exclamation-circle"></i> Ada Tagihan
        </span>
        <?php endif; ?>
        <i class="fas fa-chevron-right" style="color:rgba(255,255,255,0.25);margin-left:8px;"></i>
    </a>

    <!-- Payment Categories -->
    <?php
    $catColors = [
        'Pembayaran Umum' => ['dot'=>'#6366f1','label'=>'#818cf8'],
        'Kelas 10'        => ['dot'=>'#10b981','label'=>'#34d399'],
        'Kelas 11'        => ['dot'=>'#f59e0b','label'=>'#fbbf24'],
        'Kelas 12'        => ['dot'=>'#ef4444','label'=>'#f87171'],
    ];
    
    foreach ($categories as $catName => $items):
        // Sembunyikan kategori khusus karena SPP sudah memiliki banner utamanya sendiri di atas
        if ($catName === 'Pembayaran Rutin') continue;
        
        // Sembunyikan kategori kelas yang belum dicapai siswa
        if ($catName === 'Kelas 11' && $tingkatSiswaInfo === 'X') continue;
        if ($catName === 'Kelas 12' && in_array($tingkatSiswaInfo, ['X', 'XI'])) continue;
        
        $cc = $catColors[$catName] ?? ['dot'=>'#6366f1','label'=>'#818cf8'];
    ?>
    <div class="cat-section">
        <div class="cat-header">
            <span class="cat-label"><?= $catName ?></span>
        </div>
        <div class="pay-grid">
        <?php foreach ($items as $name => $config):
            // Sembunyikan Werpak TKJ jika jurusan bukan TKJ
            if (isPembayaranKhususTKJ($name) && !isJurusanTKJ($siswa['jurusan'] ?? '')) continue;
            
            $sk = $dataTunggakanSpp['categories'][$name] ?? 'belum';
            if ($sk==='lunas') { $pillClass='pill-lunas'; $pillIcon='fa-check-circle'; $pillTxt='Lunas'; }
            elseif ($sk==='pending') { $pillClass='pill-verif'; $pillIcon='fa-clock'; $pillTxt='Verifikasi'; }
            elseif ($sk==='nyicil') { $pillClass='pill-nyicil'; $pillIcon='fa-adjust'; $pillTxt='Nyicil'; }
            else { $pillClass='pill-pending'; $pillIcon='fa-times-circle'; $pillTxt='Belum Lunas'; }
        ?>
        <a href="fitur-lain.php?type=<?= urlencode($name) ?>" class="pay-card" style="--card-accent:<?= $config['color'] ?>;">
            <style>.pay-card[style*="--card-accent:<?= $config['color'] ?>;"]:hover::after { background:<?= $config['color'] ?>; }</style>
            <div class="pay-card-top">
                <div class="pay-card-icon" style="background:<?= $config['color'] ?>18;color:<?= $config['color'] ?>;">
                    <i class="fas <?= $config['icon'] ?>"></i>
                </div>
                <i class="fas fa-chevron-right pay-card-arrow"></i>
            </div>
            <div class="pay-card-content">
                <div class="pay-card-name"><?= e($name) ?></div>
                <div class="pay-card-status">
                    <?php if ($sk !== 'lunas'): ?>
                    <span class="status-pill <?= $pillClass ?>">
                        <i class="fas <?= $pillIcon ?>"></i> <?= $pillTxt ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

</div>

<?php include '../includes/footer-siswa.php'; ?>


