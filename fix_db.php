<?php
require_once 'config/database.php';

try {
    // 1. Alter table
    echo "Altering table...\n";
    $pdo->exec("ALTER TABLE setting_pembayaran MODIFY COLUMN jenis VARCHAR(50) NOT NULL");
    echo "Table altered successfully.\n";

    // 2. Update data
    echo "Updating data...\n";
    $stmt = $pdo->prepare("UPDATE setting_pembayaran SET jenis = 'Ujian Akhir (Kelas 12)' WHERE jenis LIKE 'Ujian Akhir (Kelas 1%'");
    $stmt->execute();
    echo "Data updated successfully. Rows affected: " . $stmt->rowCount() . "\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
