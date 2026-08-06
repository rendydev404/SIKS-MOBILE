<?php
require_once 'config/database.php';

// Revert monthly payments back to their original physical year
$stmt = $pdo->prepare("
    UPDATE pembayaran 
    SET tahun = tahun + 1 
    WHERE jenis_pembayaran IN ('SPP', 'Infak', 'Komputer') 
      AND bulan IN ('Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni')
      AND tahun >= 2024
");
$stmt->execute();
echo "Reverted rows: " . $stmt->rowCount() . "\n";
?>
