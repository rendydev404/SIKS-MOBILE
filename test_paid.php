<?php
require_once 'config/database.php';
$stmtPaid = $pdo->prepare("SELECT jenis_pembayaran, bulan, tahun, jumlah_bayar FROM pembayaran WHERE siswa_id = ? AND status = 'lunas'");
$stmtPaid->execute([36]);
print_r($stmtPaid->fetchAll(PDO::FETCH_ASSOC));
