<?php
/**
 * AJAX Endpoint - Check Rejection Reason
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
// Redundant session_start removed as it is already in functions.php
// session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['siswa_id'])) {
    echo json_encode(['rejected' => false, 'sisa' => 0, 'lunas' => false]);
    exit;
}

$siswaId = $_SESSION['siswa_id'];
$bulan = $_GET['bulan'] ?? '';
$tahun = $_GET['tahun'] ?? '';
$jenis = $_GET['jenis'] ?? 'SPP';

if (!$bulan || !$tahun) {
    echo json_encode(['rejected' => false, 'sisa' => 0, 'lunas' => false]);
    exit;
}

// Handler untuk pengecekan multi-bulan
if ($bulan === 'multi' && isset($_GET['blnlist'])) {
    $blnList = explode(',', $_GET['blnlist']);
    $resultMulti = [];
    
    // Ambil data siswa untuk lookup angkatan
    $stmtSiswa = $pdo->prepare("SELECT tahun_masuk FROM siswa WHERE id = ?");
    $stmtSiswa->execute([$siswaId]);
    $dataSiswa = $stmtSiswa->fetch();
    $angkatanSiswa = (int)($dataSiswa['tahun_masuk'] ?? 0);

    foreach ($blnList as $b) {
        $b = trim($b);
        if (empty($b)) continue;
        
        $cekTahun = (int)$tahun;
        if (in_array($b, ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni'])) {
            $cekTahun++;
        }
        
        $cek = cekPembayaran($pdo, $siswaId, $b, $cekTahun, $jenis, $angkatanSiswa);
        $resultMulti[$b] = [
            'sisa' => $cek['sisa'],
            'lunas' => $cek['lunas'],
            'nominal' => $cek['nominal_tagihan']
        ];
    }
    
    echo json_encode([
        'rejected' => false,
        'detail_multi' => $resultMulti
    ]);
    exit;
}

$cekTahun = (int)$tahun;
if (in_array($bulan, ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni'])) {
    $cekTahun++;
}

$cek = cekPembayaran($pdo, $siswaId, $bulan, $cekTahun, $jenis, $angkatanSiswa ?? $dataSiswa['tahun_masuk'] ?? 0);

try {
    $stmt = $pdo->prepare("SELECT admin_note FROM pembayaran WHERE siswa_id = ? AND bulan = ? AND tahun = ? AND jenis_pembayaran = ? AND status = 'ditolak' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$siswaId, $bulan, $cekTahun, $jenis]);
    $result = $stmt->fetch();

    echo json_encode([
        'rejected' => ($result !== false),
        'admin_note' => $result ? $result['admin_note'] : null,
        'sisa' => $cek['sisa'],
        'lunas' => $cek['lunas'],
        'nominal' => $cek['nominal_tagihan']
    ]);
} catch (PDOException $e) {
    echo json_encode(['rejected' => false, 'sisa' => 0, 'lunas' => false]);
}
