<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$cek = cekPembayaran($pdo, 36, null, null, 'DSP', 2023);
print_r($cek);
