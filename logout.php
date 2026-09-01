<?php
/**
 * Logout
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

session_start();

// Kosongkan data sesi, lalu buang cookie-nya. Tanpa langkah ini browser
// mengirim ulang PHPSESSID yang sudah dihapus di server, sehingga login
// berikutnya bisa menempel ke sesi yang tidak valid.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]
    );
}
session_destroy();

header('Location: index.php');
exit;
