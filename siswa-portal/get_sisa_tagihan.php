<?php
/**
 * Endpoint AJAX - Get Sisa Tagihan
 * Mengembalikan JSON berisi info tagihan & cicilan untuk pembayaran tahunan
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['siswa_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$siswaId = $_SESSION['siswa_id'];
$type    = $_GET['type']  ?? '';
$tahun   = $_GET['tahun'] ?? '';

if (!$type) {
    echo json_encode(['error' => 'Parameter type diperlukan']);
    exit;
}

$isYearly  = isYearlyPayment($type);
$isMonthly = in_array($type, ['SPP', 'Infak', 'Komputer']);

// Ambil data siswa untuk lookup angkatan
$stmtSiswa = $pdo->prepare("SELECT tahun_masuk FROM siswa WHERE id = ?");
$stmtSiswa->execute([$siswaId]);
$dataSiswa = $stmtSiswa->fetch();
$angkatanSiswa = (int)($dataSiswa['tahun_masuk'] ?? 0);

// Ambil nominal tagihan: tahunan = tahun pelaksanaan, lainnya = angkatan
$lookupTahun = ($isYearly && $tahun) ? (int)$tahun : $angkatanSiswa;
$nominalTagihan = (float)getNominalPembayaran($pdo, $type, $lookupTahun);

// Hitung yang sudah dibayar
if ($isMonthly) {
    // Untuk bulanan, butuh bulan juga (tidak dihandle di sini)
    echo json_encode(['error' => 'Gunakan endpoint check_rejection untuk SPP bulanan']);
    exit;
}

$bulan = null; // tahunan tidak pakai bulan

if ($isYearly && $tahun) {
    $cek = cekPembayaran($pdo, $siswaId, $bulan, $tahun, $type);
} else {
    $cek = cekPembayaran($pdo, $siswaId, $bulan, null, $type);
}

$totalLunas   = $cek['total_dibayar'];
$totalPending = $cek['total_pending'];
$totalSemua   = $totalLunas + $totalPending;
$sisa         = $cek['sisa'];
$status       = $cek['status'];

// Hitung persentase
$persen = ($nominalTagihan > 0) ? min(100, round(($totalSemua / $nominalTagihan) * 100)) : 0;

// Ambil riwayat transaksi cicilan
if ($isYearly && $tahun) {
    $stmtRiwayat = $pdo->prepare("SELECT id, jumlah_bayar, tanggal_bayar, status, keterangan, metode_bayar FROM pembayaran WHERE siswa_id = ? AND jenis_pembayaran = ? AND tahun = ? ORDER BY tanggal_bayar ASC, id ASC");
    $stmtRiwayat->execute([$siswaId, $type, $tahun]);
} else {
    $stmtRiwayat = $pdo->prepare("SELECT id, jumlah_bayar, tanggal_bayar, status, keterangan, metode_bayar FROM pembayaran WHERE siswa_id = ? AND jenis_pembayaran = ? ORDER BY tanggal_bayar ASC, id ASC");
    $stmtRiwayat->execute([$siswaId, $type]);
}
$riwayat = $stmtRiwayat->fetchAll(PDO::FETCH_ASSOC);

// Beri label cicilan ke-N
$cicilanKe = 0;
foreach ($riwayat as &$r) {
    if ($r['status'] !== 'ditolak') {
        $cicilanKe++;
        $r['cicilan_ke'] = $cicilanKe;
    } else {
        $r['cicilan_ke'] = null;
    }
}
unset($r);

echo json_encode([
    'nominal_tagihan' => $nominalTagihan,
    'total_lunas'     => $totalLunas,
    'total_pending'   => $totalPending,
    'total_terbayar'  => $totalSemua,
    'sisa'            => $sisa,
    'status'          => $status,
    'persen'          => $persen,
    'lunas'           => $cek['lunas'],
    'riwayat'         => $riwayat,
]);
