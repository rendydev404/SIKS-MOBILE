<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$cek = cekPembayaran($pdo, 36, null, 2023, 'UTS 1', 2023);
print_r($cek);
$nom = getNominalPembayaran($pdo, 'UTS 1', 2023);
echo "Nominal: $nom\n";
