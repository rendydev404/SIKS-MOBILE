<?php
require 'config/database.php';
$stmt = $pdo->query('SELECT * FROM setting_pembayaran');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt2 = $pdo->query('SELECT id, nama, tahun_masuk FROM siswa LIMIT 5');
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
