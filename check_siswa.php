<?php
require 'c:/xampp/htdocs/sppsmkalamin/config/database.php';
$stmt = $pdo->query('SELECT s.id, s.nama, s.tahun_masuk, k.tingkat, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id LIMIT 10');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
