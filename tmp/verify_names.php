<?php
require_once __DIR__ . '/../config/database.php';

echo "--- setting_pembayaran ---\n";
$stmt = $pdo->query("SELECT jenis, nominal FROM setting_pembayaran WHERE jenis LIKE 'UTS%' OR jenis LIKE 'UAS%'");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "{$row['jenis']}: {$row['nominal']}\n";
}

echo "\n--- pembayaran ---\n";
$stmt = $pdo->query("SELECT DISTINCT jenis_pembayaran FROM pembayaran WHERE jenis_pembayaran LIKE 'UTS%' OR jenis_pembayaran LIKE 'UAS%'");
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $row) {
    echo "{$row}\n";
}
