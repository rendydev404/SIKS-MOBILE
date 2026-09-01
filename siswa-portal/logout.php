<?php
/**
 * Portal Siswa - Logout
 */
session_start();

// Buang seluruh sesi, bukan hanya key siswa: menyisakan sesi lama membuat
// alert flash dan sisa data ikut terbawa ke login berikutnya.
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
