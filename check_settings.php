<?php
require_once 'config/database.php';
$stmt = $pdo->query("SELECT jenis, nominal FROM setting_pembayaran");
$f = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Existing Payment Types:\n";
foreach($f as $r) {
    echo "- [" . $r['jenis'] . "] = " . $r['nominal'] . "\n";
}
