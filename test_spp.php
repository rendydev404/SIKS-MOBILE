<?php
require 'config/database.php';
$stmt = $pdo->query("SELECT * FROM setting_pembayaran WHERE jenis = 'SPP'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
