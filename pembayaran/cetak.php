<?php
/**
 * Cetak Bukti Pembayaran
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();

$id = $_GET['id'] ?? 0;

// Get data pembayaran
$stmt = $pdo->prepare("
    SELECT p.*, s.nama as nama_siswa, s.nis, s.nisn, k.nama_kelas, u.nama_lengkap as petugas
    FROM pembayaran p
    JOIN siswa s ON p.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$pembayaran = $stmt->fetch();

if (!$pembayaran) {
    die('Data pembayaran tidak ditemukan!');
}

function terbilang($angka) {
    $angka = abs($angka);
    $huruf = ["", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas"];
    $temp = "";
    if ($angka < 12) {
        $temp = " " . $huruf[$angka];
    } else if ($angka < 20) {
        $temp = terbilang($angka - 10) . " belas";
    } else if ($angka < 100) {
        $temp = terbilang($angka / 10) . " puluh" . terbilang($angka % 10);
    } else if ($angka < 200) {
        $temp = " seratus" . terbilang($angka - 100);
    } else if ($angka < 1000) {
        $temp = terbilang($angka / 100) . " ratus" . terbilang($angka % 100);
    } else if ($angka < 2000) {
        $temp = " seribu" . terbilang($angka - 1000);
    } else if ($angka < 1000000) {
        $temp = terbilang($angka / 1000) . " ribu" . terbilang($angka % 1000);
    } else if ($angka < 1000000000) {
        $temp = terbilang($angka / 1000000) . " juta" . terbilang($angka % 1000000);
    }
    return $temp;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bukti Pembayaran Siswa - <?= e($pembayaran['nama_siswa']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f5f5; padding: 20px; }
        .print-container { background: white; max-width: 800px; margin: 0 auto; padding: 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .print-header { text-align: center; border-bottom: 3px double #333; padding-bottom: 20px; margin-bottom: 30px; }
        .print-header h1 { font-size: 20px; margin-bottom: 4px; }
        .print-header h2 { font-size: 16px; font-weight: 500; color: #666; }
        .print-header p { font-size: 13px; color: #888; margin-top: 8px; }
        .info-row { display: flex; margin-bottom: 10px; }
        .info-label { width: 150px; font-weight: 500; color: #666; }
        .info-value { flex: 1; }
        .info-section { margin-bottom: 30px; }
        .info-section h3 { font-size: 14px; font-weight: 600; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 1px solid #eee; }
        .amount-box { background: #f0fdf4; border: 1px solid #86efac; padding: 20px; border-radius: 8px; text-align: center; margin: 30px 0; }
        .amount-box .label { font-size: 13px; color: #666; margin-bottom: 8px; }
        .amount-box .value { font-size: 28px; font-weight: 700; color: #16a34a; }
        .amount-box .terbilang { font-size: 12px; color: #666; margin-top: 8px; font-style: italic; }
        .footer { margin-top: 40px; display: flex; justify-content: space-between; }
        .footer-col { text-align: center; }
        .footer-col .date { font-size: 13px; margin-bottom: 60px; }
        .footer-col .name { font-weight: 600; border-top: 1px solid #333; padding-top: 5px; }
        .btn-print { background: #6366f1; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; margin-top: 20px; }
        .btn-print:hover { background: #4f46e5; }
        .no-print { text-align: center; margin-top: 20px; }
        @media print {
            body { background: white; padding: 0; }
            .print-container { box-shadow: none; max-width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="print-container">
        <div class="print-header">
            <h1>SMK AL AMIN</h1>
            <h2>BUKTI PEMBAYARAN SISWA</h2>
            <p>Jl. Pendidikan No. 123, Kota</p>
        </div>
        
        <div class="info-section">
            <h3>Informasi Siswa</h3>
            <div class="info-row">
                <span class="info-label">NIS</span>
                <span class="info-value">: <?= e($pembayaran['nis']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Nama Siswa</span>
                <span class="info-value">: <?= e($pembayaran['nama_siswa']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Kelas</span>
                <span class="info-value">: <?= e($pembayaran['nama_kelas'] ?? '-') ?></span>
            </div>
        </div>
        
        <div class="info-section">
            <h3>Detail Pembayaran</h3>
            <div class="info-row">
                <span class="info-label">No. Transaksi</span>
                <span class="info-value">: TRX-<?= str_pad($pembayaran['id'], 6, '0', STR_PAD_LEFT) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Tanggal Bayar</span>
                <span class="info-value">: <?= formatTanggal($pembayaran['tanggal_bayar'], 'd F Y') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Periode</span>
                <span class="info-value">: <?= $pembayaran['bulan'] ?> 
                    <?php 
                    if (in_array($pembayaran['jenis_pembayaran'], ['SPP', 'Infak', 'Komputer'])) {
                        $bln = $pembayaran['bulan'];
                        $thn = (int)$pembayaran['tahun'];
                        if (in_array($bln, ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni'])) {
                            echo ($thn - 1) . '/' . $thn;
                        } else {
                            echo $thn . '/' . ($thn + 1);
                        }
                    } else {
                        echo $pembayaran['tahun'];
                    }
                    ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Metode Bayar</span>
                <span class="info-value">: <?= $pembayaran['metode_bayar'] ?></span>
            </div>
        </div>
        
        <div class="amount-box">
            <div class="label">JUMLAH PEMBAYARAN</div>
            <div class="value"><?= formatRupiah($pembayaran['jumlah_bayar']) ?></div>
            <div class="terbilang"><?= ucwords(trim(terbilang($pembayaran['jumlah_bayar']))) ?> Rupiah</div>
        </div>
        
        <div class="footer">
            <div class="footer-col">
                <div class="date">Mengetahui,</div>
                <div class="name">Kepala Sekolah</div>
            </div>
            <div class="footer-col">
                <div class="date"><?= formatTanggal(date('Y-m-d'), 'd F Y') ?></div>
                <div class="name"><?= e($pembayaran['petugas'] ?? 'Petugas') ?></div>
            </div>
        </div>
    </div>
    
    <div class="no-print">
        <button onclick="window.print()" class="btn-print">
            <i class="fas fa-print"></i> Cetak Bukti Pembayaran
        </button>
        <br><br>
        <a href="index.php" style="color: #666;">← Kembali ke Daftar Pembayaran</a>
    </div>
</body>
</html>
