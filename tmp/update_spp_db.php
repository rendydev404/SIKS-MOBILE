<?php
require 'config/database.php';

try {
    $pdo->exec("INSERT INTO setting_pembayaran (jenis, nominal, tahun_masuk) VALUES ('SPP', 75000, 2026) ON DUPLICATE KEY UPDATE nominal=75000");
    $pdo->exec("INSERT INTO setting_pembayaran (jenis, nominal, tahun_masuk) VALUES ('SPP', 50000, 0) ON DUPLICATE KEY UPDATE nominal=50000");
    echo "Database updated successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
