<?php
/**
 * Konfigurasi Database
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

// Konfigurasi database
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'u288761698_dilla');
define('DB_PASS', 'Dilla@123#');
define('DB_NAME', 'u288761698_siks');
define('DB_PORT', 3306);

// Membuat koneksi dengan PDO
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Koneksi MySQLi (alternative)
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Base URL
define('BASE_URL', 'https://sikssmkalamin.absensismkalamin.my.id/');

// Timezone
date_default_timezone_set('Asia/Jakarta');
