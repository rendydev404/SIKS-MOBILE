<?php
require_once __DIR__ . '/config/database.php';

echo "<h2>Migrasi Database FCM Token</h2>";

try {
    // Tambah fcm_token ke tabel users
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'fcm_token'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN fcm_token VARCHAR(255) NULL");
        echo "✅ Berhasil menambahkan kolom fcm_token ke tabel users.<br>";
    } else {
        echo "ℹ️ Kolom fcm_token sudah ada di tabel users.<br>";
    }
} catch (PDOException $e) {
    echo "❌ Error pada tabel users: " . $e->getMessage() . "<br>";
}

try {
    // Tambah fcm_token ke tabel siswa
    $stmt = $pdo->query("SHOW COLUMNS FROM siswa LIKE 'fcm_token'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE siswa ADD COLUMN fcm_token VARCHAR(255) NULL");
        echo "✅ Berhasil menambahkan kolom fcm_token ke tabel siswa.<br>";
    } else {
        echo "ℹ️ Kolom fcm_token sudah ada di tabel siswa.<br>";
    }
} catch (PDOException $e) {
    echo "❌ Error pada tabel siswa: " . $e->getMessage() . "<br>";
}

echo "<br><b>Selesai!</b> File ini bisa dihapus setelah migrasi berhasil.";
?>
