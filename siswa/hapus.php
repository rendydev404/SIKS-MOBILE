<?php
/**
 * Hapus Siswa
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();
checkRole(['admin']);

$id = $_GET['id'] ?? 0;

try {
    $stmt = $pdo->prepare("DELETE FROM siswa WHERE id = ?");
    $stmt->execute([$id]);
    setAlert('success', 'Data siswa berhasil dihapus!');
} catch (PDOException $e) {
    setAlert('error', 'Gagal menghapus data. Mungkin masih ada pembayaran terkait.');
}

header('Location: index.php');
exit;
