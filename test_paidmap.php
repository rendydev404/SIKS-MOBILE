<?php
require_once 'config/database.php';
$stmtPaid = $pdo->prepare("SELECT jenis_pembayaran, bulan, tahun, jumlah_bayar FROM pembayaran WHERE siswa_id = ? AND status = 'lunas'");
$stmtPaid->execute([36]);
$paidHistory = $stmtPaid->fetchAll(PDO::FETCH_ASSOC);

$paidMap = [];
foreach ($paidHistory as $ph) {
    $j = $ph['jenis_pembayaran'];
    $t = $ph['tahun'];
    $b = $ph['bulan'] ?: 'YEARLY';
    $paidMap[$j][$t][$b] = true;
}
echo json_encode($paidMap);
