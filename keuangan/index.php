<?php
/**
 * Pusat Biaya Lain-lain (Non-SPP)
 * Sistem Informasi Keuangan Sekolah - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();

$pageTitle = 'Pusat Biaya Lain-lain';

$categories = getPaymentCategories();

include '../includes/header.php';
?>

<div class="alert alert-info" style="margin-bottom: 30px; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 12px; padding: 16px 20px;">
    <div style="display: flex; align-items: center; gap: 12px;">
        <div style="width: 32px; height: 32px; background: rgba(59, 130, 246, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #60a5fa;">
            <i class="fas fa-lightbulb"></i>
        </div>
        <div style="flex: 1; font-size: 14px; color: var(--text-primary);">
            Pilih salah satu modul di bawah ini untuk mengelola pembayaran, menentukan tarif, dan melihat riwayat secara khusus.
        </div>
    </div>
</div>

<style>
.premium-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}
.premium-card {
    background: rgba(30, 41, 59, 0.6);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none;
    position: relative;
    overflow: hidden;
}
.premium-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; height: 4px;
    background: var(--card-color, var(--primary));
    opacity: 0;
    transition: opacity 0.3s ease;
}
.premium-card:hover {
    transform: translateY(-5px);
    background: rgba(30, 41, 59, 0.8);
    border-color: rgba(255, 255, 255, 0.1);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
}
.premium-card:hover::before {
    opacity: 1;
}
.card-header-flex {
    display: flex;
    align-items: center;
    gap: 16px;
}
.icon-box {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
    box-shadow: inset 0 2px 4px rgba(255,255,255,0.1);
}
.card-title-box h4 {
    margin: 0 0 4px 0;
    font-size: 16px;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.2;
}
.card-title-box .price {
    font-size: 13px;
    color: var(--text-secondary);
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
}
.card-stats {
    display: flex;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 12px;
    padding: 12px;
    margin-top: auto;
}
.stat-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.stat-item:first-child {
    border-right: 1px solid rgba(255,255,255,0.05);
}
.stat-item:last-child {
    padding-left: 12px;
}
.stat-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.stat-val {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-primary);
}
.stat-val.success {
    color: var(--success);
}
</style>

<?php foreach ($categories as $catName => $items): ?>
<div style="margin-bottom: 40px; animation: slideUp 0.5s ease backwards;">
    <h3 style="margin-bottom: 24px; font-size: 16px; font-weight: 700; color: var(--text-primary); text-transform: uppercase; letter-spacing: 1.5px; display: flex; align-items: center; gap: 12px;">
        <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--primary); box-shadow: 0 0 10px var(--primary);"></span>
        <?= $catName ?>
        <div style="flex: 1; height: 1px; background: linear-gradient(90deg, rgba(255,255,255,0.1), transparent);"></div>
    </h3>
    
    <div class="premium-grid">
        <?php foreach ($items as $name => $config): 
            // Fetch Nominal & Stats
            $stmtSet = $pdo->prepare("SELECT nominal FROM setting_pembayaran WHERE jenis = ?");
            $stmtSet->execute([$name]);
            $nominal = $stmtSet->fetch()['nominal'] ?? 0;

            $stmtStats = $pdo->prepare("SELECT SUM(jumlah_bayar) as total, COUNT(DISTINCT siswa_id) as jml_siswa FROM pembayaran WHERE jenis_pembayaran = ? AND status != 'ditolak'");
            $stmtStats->execute([$name]);
            $stats = $stmtStats->fetch();
            $totalTerkumpul = $stats['total'] ?? 0;
            $siswaLunas = $stats['jml_siswa'] ?? 0;
        ?>
        <a href="fitur.php?type=<?= urlencode($name) ?>" class="premium-card" style="--card-color: <?= $config['color'] ?>;">
            <div class="card-header-flex">
                <div class="icon-box" style="background: <?= $config['color'] ?>22; color: <?= $config['color'] ?>;">
                    <i class="fas <?= $config['icon'] ?>"></i>
                </div>
                <div class="card-title-box">
                    <h4><?= $name ?></h4>
                    <div class="price">
                        <i class="fas fa-tag" style="font-size: 10px; opacity: 0.7;"></i> 
                        <?= $nominal > 0 ? formatRupiah($nominal) : 'Belum diset' ?>
                    </div>
                </div>
            </div>
            
            <div class="card-stats">
                <div class="stat-item">
                    <span class="stat-label">Terkumpul</span>
                    <span class="stat-val success"><?= formatRupiah($totalTerkumpul) ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Siswa Bayar</span>
                    <span class="stat-val"><?= $siswaLunas ?> <span style="font-size: 11px; color: var(--text-muted); font-weight: 400;">org</span></span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<?php include '../includes/footer.php'; ?>
