<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

checkLogin();

// Logika update status
$id = $_GET['id'] ?? '';
$status = $_GET['status'] ?? '';
$adminNote = $_GET['admin_note'] ?? null;
$redirect = $_GET['redirect'] ?? 'verifikasi';

if ($id && in_array($status, ['lunas', 'ditolak'])) {
    try {
        if ($status == 'ditolak' && $adminNote) {
            $stmt = $pdo->prepare("UPDATE pembayaran SET status = ?, admin_note = ? WHERE id = ?");
            $stmt->execute([$status, $adminNote, $id]);
        } elseif ($status == 'lunas') {
            $stmt = $pdo->prepare("UPDATE pembayaran SET status = ?, verifikasi_admin_id = ?, tanggal_verifikasi = NOW() WHERE id = ?");
            $stmt->execute([$status, $_SESSION['user_id'], $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE pembayaran SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
        }
        
        $msg = ($status == 'lunas') ? 'Pembayaran berhasil diverifikasi (Lunas).' : 'Pembayaran telah ditolak.';
        setAlert('success', $msg);

        // Redirect ke halaman kirim WA verifikasi beserta gambar kwitansi
        header('Location: verifikasi-wa.php?id=' . urlencode($id) . '&redirect=' . urlencode($redirect));
        exit;
    } catch (PDOException $e) {
        setAlert('danger', 'Gagal memproses pembayaran.');
    }
} else {
    setAlert('danger', 'Permintaan tidak valid.');
}

header('Location: index.php');
exit;
?>
