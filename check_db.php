<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$stmt = $pdo->query("SELECT id, nama, tahun_masuk FROM siswa WHERE nama LIKE '%dilla%'");
$siswa = $stmt->fetch(PDO::FETCH_ASSOC);

if ($siswa) {
    echo "Siswa: " . $siswa['nama'] . "\n";
    $tunggakan = hitungTunggakan($pdo, $siswa['id'], true);
    echo "Total Tunggakan: " . $tunggakan['total'] . "\n";
    echo "SPP Tunggakan:\n";
    print_r($tunggakan['spp']);
} else {
    echo "Not found\n";
}
