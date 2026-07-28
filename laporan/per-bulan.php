<?php
/**
 * Laporan Per Bulan
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();

$pageTitle = 'Laporan Per Bulan';

$filterTahun = $_GET['tahun'] ?? date('Y');
$bulanList = getBulanIndonesia();

// Get data pembayaran per bulan
$rekapBulan = [];
foreach ($bulanList as $bulan) {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT siswa_id) as jumlah_siswa, COALESCE(SUM(jumlah_bayar), 0) as total_bayar
        FROM pembayaran WHERE bulan = ? AND tahun = ? AND status = 'lunas'
    ");
    $stmt->execute([$bulan, $filterTahun]);
    $data = $stmt->fetch();
    $rekapBulan[$bulan] = $data;
}

// Get total siswa aktif
$totalSiswa = $pdo->query("SELECT COUNT(*) FROM siswa WHERE status = 'aktif'")->fetchColumn();

// Get SPP
$spp = getSppAktif($pdo);
$nominalSpp = $spp ? $spp['nominal'] : 0;
$targetPerBulan = $totalSiswa * $nominalSpp;

include '../includes/header.php';
?>

<div class="toolbar">
    <form method="GET" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <select name="tahun" class="form-control form-control-simple" style="width: auto;" onchange="this.form.submit()">
            <?php 
            for ($y = 2024; $y <= 2028; $y++): 
            ?>
                <option value="<?= $y ?>" <?= $filterTahun == $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
        <button type="button" class="btn btn-secondary no-print" onclick="window.print()">
            <i class="fas fa-print"></i> Cetak Laporan
        </button>
    </form>
</div>

<style>
@media print {
    /* Override global hidden visibility */
    body * { visibility: hidden !important; }
    .print-container, .print-container * { visibility: visible !important; }
    
    .print-container { 
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        color: black !important;
    }

    .no-print, .sidebar, .sidebar-overlay, .header, .toolbar, .top-header, .screen-only { display: none !important; }
    .print-only { display: block !important; }
    
    .card { border: none !important; box-shadow: none !important; background: white !important; width: 100% !important; }
    .card-header { padding: 0 0 10px 0 !important; border-bottom: 2px solid #333 !important; margin-bottom: 20px !important; color: black !important; }
    .card-title { color: black !important; font-size: 20px !important; }
    
    .table { width: 100% !important; border-collapse: collapse !important; border: 1px solid #000 !important; }
    .table th, .table td { border: 1px solid #000 !important; padding: 8px !important; color: black !important; background: white !important; }
    .table th { background: #f0f0f0 !important; }
    
    .text-success { color: black !important; font-weight: bold !important; }
    .badge { border: 1px solid #000 !important; color: black !important; background: none !important; padding: 2px 5px !important; }
    
    /* Ensure no dark background from theme */
    body { background: white !important; }
}
@media screen {
    .print-only { display: none !important; }
}
.month-row:hover {
    background: rgba(255, 255, 255, 0.03);
    transform: translateX(4px);
}
</style>

<div class="print-container">
    <div class="card glass">
        <div class="card-header" style="border-bottom: 1px solid rgba(255,255,255,0.05); padding: 24px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: var(--gradient-primary); display: flex; align-items: center; justify-content: center; color: white; box-shadow: var(--shadow-sm);">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <h2 class="card-title" style="margin: 0; font-size: 18px;">Rekap Pembayaran</h2>
                    <span style="font-size: 13px; color: var(--text-secondary);">Tahun <?= $filterTahun ?></span>
                </div>
            </div>
        </div>
        
        <div class="card-body" style="padding: 0;">
            <!-- SCREEN VIEW -->
            <div class="screen-only">
                <?php 
                $grandTotal = 0;
                $grandSiswa = 0;
                foreach ($bulanList as $bulan): 
                    $data = $rekapBulan[$bulan];
                    $grandTotal += $data['total_bayar'];
                    $grandSiswa += $data['jumlah_siswa'];
                    $persen = $targetPerBulan > 0 ? round(($data['total_bayar'] / $targetPerBulan) * 100) : 0;
                    
                    $colorVar = 'var(--danger)';
                    $iconClass = 'fa-times-circle';
                    $bgSoft = 'rgba(239, 68, 68, 0.1)';
                    if ($persen >= 80) {
                        $colorVar = 'var(--success)';
                        $iconClass = 'fa-check-circle';
                        $bgSoft = 'rgba(16, 185, 129, 0.1)';
                    } elseif ($persen >= 50) {
                        $colorVar = 'var(--warning)';
                        $iconClass = 'fa-exclamation-circle';
                        $bgSoft = 'rgba(245, 158, 11, 0.1)';
                    }
                ?>
                <div class="month-row" style="display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; border-bottom: 1px solid rgba(255,255,255,0.05); transition: all 0.3s ease; flex-wrap: wrap; gap: 15px;">
                    
                    <div style="flex: 1; min-width: 200px; display: flex; align-items: center; gap: 15px;">
                        <div style="width: 48px; height: 48px; border-radius: 12px; background: <?= $bgSoft ?>; display: flex; align-items: center; justify-content: center; color: <?= $colorVar ?>; font-size: 20px;">
                            <i class="fas <?= $iconClass ?>"></i>
                        </div>
                        <div>
                            <h4 style="margin: 0 0 4px 0; font-size: 16px; color: var(--text-primary); font-weight: 600; letter-spacing: 0.5px;"><?= strtoupper($bulan) ?></h4>
                            <span style="font-size: 13px; color: var(--text-secondary); display: flex; align-items: center; gap: 6px;">
                                <i class="fas fa-users" style="font-size: 10px; opacity: 0.7;"></i> 
                                <?= $data['jumlah_siswa'] ?> dari <?= $totalSiswa ?> siswa
                            </span>
                        </div>
                    </div>
                    
                    <div style="flex: 1; min-width: 150px;">
                        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">Penerimaan</div>
                        <div style="font-size: 18px; font-weight: 700; color: var(--text-primary); display: flex; align-items: baseline; gap: 4px;">
                            <span style="font-size: 14px; color: <?= $colorVar ?>;">Rp</span>
                            <?= number_format($data['total_bayar'], 0, ',', '.') ?>
                        </div>
                    </div>
                    
                    <div style="flex: 1; min-width: 150px;">
                        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">Target</div>
                        <div style="font-size: 16px; font-weight: 500; color: var(--text-secondary);">
                            <?= formatRupiah($targetPerBulan) ?>
                        </div>
                    </div>
                    
                    <div style="flex: 1; min-width: 200px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; margin-bottom: 8px;">
                            <span style="color: var(--text-secondary); font-weight: 500;">Pencapaian</span>
                            <span style="background: <?= $bgSoft ?>; color: <?= $colorVar ?>; padding: 2px 8px; border-radius: 6px; font-weight: 700; font-size: 12px;">
                                <?= $persen ?>%
                            </span>
                        </div>
                        <div style="background: rgba(0,0,0,0.2); height: 8px; border-radius: 8px; overflow: hidden; box-shadow: inset 0 1px 2px rgba(0,0,0,0.2);">
                            <div style="width: <?= min($persen, 100) ?>%; height: 100%; background: <?= $colorVar ?>; border-radius: 8px; transition: width 1s ease; position: relative;">
                                <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.3) 50%, rgba(255,255,255,0) 100%); animation: shimmer 2s infinite;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div style="padding: 24px; background: rgba(0,0,0,0.2); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
                    <div>
                        <h3 style="margin: 0 0 4px 0; color: var(--text-primary); font-size: 15px; text-transform: uppercase; letter-spacing: 1px;">Total Keseluruhan</h3>
                        <span style="color: var(--text-secondary); font-size: 13px;">Tahun Ajaran <?= $filterTahun ?></span>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 24px; font-weight: 800; color: var(--success); text-shadow: 0 2px 10px rgba(16, 185, 129, 0.2);">
                            <?= formatRupiah($grandTotal) ?>
                        </div>
                        <div style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
                            Target: <?= formatRupiah($targetPerBulan * 12) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PRINT ONLY VIEW -->
            <div class="print-only">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Bulan</th>
                            <th>Jumlah Siswa Bayar</th>
                            <th>Total Penerimaan</th>
                            <th>Target</th>
                            <th>Pencapaian</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bulanList as $bulan): 
                            $data = $rekapBulan[$bulan];
                            $persen = $targetPerBulan > 0 ? round(($data['total_bayar'] / $targetPerBulan) * 100) : 0;
                        ?>
                        <tr>
                            <td><strong><?= $bulan ?></strong></td>
                            <td><?= $data['jumlah_siswa'] ?> dari <?= $totalSiswa ?> siswa</td>
                            <td class="text-success"><?= formatRupiah($data['total_bayar']) ?></td>
                            <td><?= formatRupiah($targetPerBulan) ?></td>
                            <td><?= $persen ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td><strong>TOTAL TAHUN <?= $filterTahun ?></strong></td>
                            <td>-</td>
                            <td class="text-success"><strong><?= formatRupiah($grandTotal) ?></strong></td>
                            <td><strong><?= formatRupiah($targetPerBulan * 12) ?></strong></td>
                            <td>
                                <?php $totalPersen = ($targetPerBulan * 12) > 0 ? round(($grandTotal / ($targetPerBulan * 12)) * 100) : 0; ?>
                                <strong><?= $totalPersen ?>%</strong>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        </div>
    </div>
</div>
<style>
@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
</style>

<?php include '../includes/footer.php'; ?>
