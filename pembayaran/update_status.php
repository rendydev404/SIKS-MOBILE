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

        // --- FCM Notification to Siswa ---
        require_once '../includes/fcm_sender.php';
        $stmtSiswa = $pdo->prepare("SELECT s.fcm_token, p.jenis_pembayaran, p.jumlah_bayar, p.bulan, p.tahun FROM pembayaran p JOIN siswa s ON p.siswa_id = s.id WHERE p.id = ?");
        $stmtSiswa->execute([$id]);
        $dataSiswa = $stmtSiswa->fetch();
        
        if ($dataSiswa && !empty($dataSiswa['fcm_token'])) {
            $jns = $dataSiswa['jenis_pembayaran'];
            $nom = number_format($dataSiswa['jumlah_bayar'], 0, ',', '.');
            $periode = $dataSiswa['bulan'] ? "{$dataSiswa['bulan']} {$dataSiswa['tahun']}" : $dataSiswa['tahun'];
            
            if ($status == 'lunas') {
                $title = "Pembayaran Berhasil!";
                $body = "Pembayaran $jns ($periode) sebesar Rp $nom telah DIVERIFIKASI Lunas.";
            } else {
                $title = "Pembayaran Ditolak";
                $body = "Pembayaran $jns sebesar Rp $nom DITOLAK. " . ($adminNote ? "Alasan: $adminNote" : "Silakan cek kembali.");
            }
            sendFCMNotification($dataSiswa['fcm_token'], $title, $body);
        }
        // ---------------------------------

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
