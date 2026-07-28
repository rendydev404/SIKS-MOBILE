<?php
/**
 * Preview Invoice HTML (bisa di-screenshot)
 * Menghasilkan invoice visual yang bagus
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

$siswaId = $_GET['siswa_id'] ?? 0;
$filterBulan = $_GET['bulan'] ?? date('n');
$tahun = $_GET['tahun'] ?? date('Y');

$bulanList = getBulanIndonesia();
$bulanName = $bulanList[(int)$filterBulan - 1] ?? '';

// Get siswa
$stmt = $pdo->prepare("SELECT s.*, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
$stmt->execute([$siswaId]);
$siswa = $stmt->fetch();

if (!$siswa) {
    die('Siswa tidak ditemukan');
}

// Nominal bulanan
$nominalBulanan = getNominalPembayaran($pdo, 'SPP');

// Hitung tunggakan
$tunggakanList = [];
$totalTunggakan = 0;

// Mulai dari Juli tahun masuk siswa
$startYear = (int)($siswa['tahun_masuk'] ?? 2024);
$startMonth = 7; // Juli

$curY = (int)date('Y');
$curM = (int)date('n');

for ($y = $startYear; $y <= (int)$tahun; $y++) {
    if ($y > $curY) break;

    $monthStart = ($y == $startYear) ? $startMonth : 1;
    $limitMonth = ($y == (int)$tahun) ? (int)$filterBulan : 12;
    if ($y == $curY) {
        $limitMonth = min($limitMonth, $curM);
    }

    for ($m = $monthStart; $m <= $limitMonth; $m++) {
        $bln = $bulanList[$m - 1];
        
        // Cek apakah sudah bayar
        $cek = $pdo->prepare("SELECT id FROM pembayaran WHERE siswa_id = ? AND bulan = ? AND tahun = ?");
        $cek->execute([$siswaId, $bln, $y]);
        $sudahBayar = $cek->fetch();

        if (!$sudahBayar) {
            $tunggakanList[] = $bln . ($y != $curY ? " $y" : "");
            $totalTunggakan += $nominalBulanan;
        }
    }
}

$totalTagihan = $totalTunggakan;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?= e($siswa['nama']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #1a1a2e; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .invoice-container { width: 400px; }
        .invoice { background: linear-gradient(145deg, #16213e 0%, #1a1a2e 100%); border-radius: 20px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.5); }
        .invoice-header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 25px; text-align: center; color: white; }
        .invoice-header h1 { font-size: 20px; font-weight: 700; margin-bottom: 5px; }
        .invoice-header p { font-size: 14px; opacity: 0.9; }
        .invoice-body { padding: 25px; }
        .student-info { background: rgba(255,255,255,0.05); border-radius: 12px; padding: 15px; margin-bottom: 20px; }
        .student-info h3 { color: #fff; font-size: 18px; margin-bottom: 8px; }
        .student-info p { color: #9ca3af; font-size: 13px; line-height: 1.6; }
        .invoice-items { margin-bottom: 20px; }
        .invoice-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .invoice-item:last-child { border-bottom: none; }
        .invoice-item .label { color: #9ca3af; font-size: 14px; }
        .invoice-item .amount { color: #fff; font-weight: 600; }
        .invoice-item.paid .label { color: #10b981; }
        .invoice-item.paid .amount { color: #10b981; }
        .tunggakan-section { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 12px; padding: 15px; margin-bottom: 20px; }
        .tunggakan-section h4 { color: #ef4444; font-size: 14px; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
        .tunggakan-item { display: flex; justify-content: space-between; padding: 8px 0; font-size: 13px; }
        .tunggakan-item .label { color: #fca5a5; }
        .tunggakan-item .amount { color: #ef4444; font-weight: 600; }
        .total-section { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); border-radius: 12px; padding: 20px; text-align: center; }
        .total-section .label { color: rgba(255,255,255,0.8); font-size: 14px; margin-bottom: 5px; }
        .total-section .amount { color: #fff; font-size: 28px; font-weight: 700; }
        .total-section.lunas { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .invoice-footer { padding: 20px 25px; background: rgba(0,0,0,0.2); }
        .bank-info { background: rgba(255,255,255,0.05); border-radius: 10px; padding: 12px; font-size: 12px; color: #9ca3af; text-align: center; line-height: 1.6; }
        .bank-info strong { color: #fff; }
        .deadline { text-align: center; color: #f59e0b; font-size: 12px; margin-top: 15px; }
        .btn-download { display: block; width: 100%; padding: 15px; background: #10b981; color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; margin-top: 20px; text-decoration: none; text-align: center; }
        .btn-download:hover { background: #059669; }
        @media print { .btn-download { display: none; } body { background: white; } }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="invoice" id="invoiceCard">
            <div class="invoice-header">
                <h1>📋 INVOICE PEMBAYARAN</h1>
                <?php
                $curY = (int)date('Y');
                $curM = (int)date('n');
                $isFuture = ($tahun > $curY || ($tahun == $curY && $filterBulan > $curM));
                $hdrBulan = $isFuture ? $bulanList[$curM-1] : $bulanName;
                $hdrTahun = $isFuture ? $curY : $tahun;
                ?>
                <p>SMK Al Amin - <?= $hdrBulan ?> <?= $hdrTahun ?></p>
            </div>
            
            <div class="invoice-body">
                <div class="student-info">
                    <h3><?= e($siswa['nama']) ?></h3>
                    <p>
                        NIS: <?= e($siswa['nis']) ?><br>
                        Kelas: <?= e($siswa['nama_kelas'] ?? '-') ?>
                    </p>
                </div>
                
                <div class="invoice-items">
                    <?php if ($tagihanBulanIni > 0): ?>
                    <div class="invoice-item">
                        <span class="label">SPP <?= $bulanName ?> <?= $tahun ?></span>
                        <span class="amount">Rp <?= number_format($nominalBulanan, 0, ',', '.') ?></span>
                    </div>
                    <?php else: ?>
                    <div class="invoice-item paid">
                        <span class="label">✓ SPP <?= $bulanName ?> <?= $tahun ?></span>
                        <span class="amount">LUNAS</span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($totalTunggakan > 0): ?>
                <div class="tunggakan-section">
                    <h4>⚠️ TUNGGAKAN</h4>
                    <?php foreach ($tunggakanList as $t): ?>
                    <div class="tunggakan-item">
                        <span class="label">SPP <?= $t ?></span>
                        <span class="amount">Rp <?= number_format($nominalBulanan, 0, ',', '.') ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="total-section <?= $totalTagihan == 0 ? 'lunas' : '' ?>">
                    <div class="label">TOTAL TAGIHAN</div>
                    <div class="amount">
                        <?php if ($totalTagihan > 0): ?>
                            Rp <?= number_format($totalTagihan, 0, ',', '.') ?>
                        <?php else: ?>
                            ✓ LUNAS
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="invoice-footer">
                <div class="bank-info">
                    <strong>Transfer ke:</strong><br>
                    Bank BRI: 1234-5678-9012-3456<br>
                    a.n. Bendahara SMK Al Amin
                </div>
                <div class="deadline">
                    ⏰ Batas Pembayaran: Tanggal 10 setiap bulan
                </div>
            </div>
        </div>
        
        <button class="btn-download" onclick="window.print()">
            📥 Simpan / Screenshot Invoice
        </button>
    </div>
</body>
</html>
