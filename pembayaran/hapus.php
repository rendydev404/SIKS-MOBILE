<?php
/**
 * Hapus Data Pembayaran
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();

$id = $_GET['id'] ?? 0;

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM pembayaran WHERE id = ?");
        $stmt->execute([$id]);
        
        setAlert('success', 'Data pembayaran berhasil dihapus!');
    } catch (PDOException $e) {
        setAlert('danger', 'Gagal menghapus data: ' . $e->getMessage());
    }
}

header('Location: index.php');
exit;
