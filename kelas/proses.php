<?php
/**
 * Proses Kelas (Hapus)
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();
checkRole(['admin']);

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if ($action === 'delete' && $id) {
    try {
        // Cek apakah ada siswa di kelas ini
        $cek = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE kelas_id = ?");
        $cek->execute([$id]);
        if ($cek->fetchColumn() > 0) {
            setAlert('error', 'Tidak dapat menghapus kelas yang masih memiliki siswa!');
        } else {
            $stmt = $pdo->prepare("DELETE FROM kelas WHERE id = ?");
            $stmt->execute([$id]);
            setAlert('success', 'Kelas berhasil dihapus!');
        }
    } catch (PDOException $e) {
        setAlert('error', 'Gagal menghapus kelas: ' . $e->getMessage());
    }
}

header('Location: index.php');
exit;
