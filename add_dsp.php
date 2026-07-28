<?php
require_once 'config/database.php';

try {
    // Check if DSP exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM setting_pembayaran WHERE jenis = ?");
    $stmt->execute(['DSP']);
    $exists = $stmt->fetchColumn();

    if (!$exists) {
        $stmt = $pdo->prepare("INSERT INTO setting_pembayaran (jenis, nominal) VALUES (?, ?)");
        $stmt->execute(['DSP', 0]);
        echo "Added 'DSP' to setting_pembayaran.\n";
    } else {
        echo "'DSP' already exists.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
