<?php
// Script untuk test Push Notification ketika Wali Murid bayar SPP
require_once __DIR__ . '/includes/fcm_sender.php';
require_once __DIR__ . '/config/database.php';

echo "<h2>Test Push Notification (Simulasi Wali Murid Bayar SPP)</h2>";

// Cek apakah server key masih default
$serverKey = 'ISI_DENGAN_SERVER_KEY_FIREBASE_ANDA';
if (file_exists(__DIR__ . '/config/firebase.php')) {
    require __DIR__ . '/config/firebase.php';
    if (defined('FCM_SERVER_KEY')) {
        $serverKey = FCM_SERVER_KEY;
    }
}

if ($serverKey === 'ISI_DENGAN_SERVER_KEY_FIREBASE_ANDA') {
    echo "<p style='color:red;'><b>ERROR:</b> Firebase Server Key belum diatur!</p>";
    echo "<p>Silakan buat file <code>config/firebase.php</code> dan masukkan Server Key Anda.</p>";
    exit;
}

try {
    // Ambil token admin
    $adminTokens = $pdo->query("SELECT id, username, fcm_token FROM users WHERE role = 'admin' AND fcm_token IS NOT NULL AND fcm_token != ''")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($adminTokens)) {
        echo "<p style='color:orange;'>Tidak ada Admin yang memiliki FCM Token. Pastikan Anda sudah login sebagai Admin di aplikasi HP fisik.</p>";
        exit;
    }

    echo "<ul>";
    $title = "Pembayaran Baru";
    $body = "Pembayaran SPP baru dari Budi (Siswa Test) (Rp 150.000) menunggu verifikasi.";

    foreach ($adminTokens as $admin) {
        $token = $admin['fcm_token'];
        echo "<li>Mencoba mengirim ke Admin <b>" . htmlspecialchars($admin['username']) . "</b> (Token: " . substr($token, 0, 15) . "...)...<br>";
        
        $result = sendFCMNotification($token, $title, $body);
        
        if ($result) {
            echo "<i>Response: " . htmlspecialchars($result) . "</i></li>";
        } else {
            echo "<i style='color:red;'>Gagal dikirim!</i></li>";
        }
    }
    echo "</ul>";
    echo "<p style='color:green;'><b>Selesai!</b> Silakan cek HP Anda (Pastikan aplikasi berjalan di latar belakang/tertutup untuk melihat pop-up Notifikasi).</p>";

} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
?>
