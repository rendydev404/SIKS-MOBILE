<?php
require 'config/database.php';
$stmt = $pdo->query('SELECT s.id, s.nama, s.tahun_masuk, k.nama_kelas, k.tingkat FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
