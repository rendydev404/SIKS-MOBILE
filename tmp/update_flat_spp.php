<?php
require 'config/database.php';
require 'includes/functions.php';

try {
    $pdo->beginTransaction();

    // 1. Update SPP Settings
    // Hapus setting spesifik karena sekarang semuanya sama (global 75.000)
    $pdo->exec("DELETE FROM setting_pembayaran WHERE jenis = 'SPP' AND tahun_masuk > 0");
    
    // Set default SPP (tahun_masuk = 0) ke 75.000
    $pdo->exec("UPDATE setting_pembayaran SET nominal = 75000 WHERE jenis = 'SPP' AND tahun_masuk = 0");
    
    // Update juga tabel spp legacy
    $tahunAjaranAktif = getTahunAjaranAktif($pdo);
    if ($tahunAjaranAktif) {
        $pdo->prepare("UPDATE spp SET nominal = 75000 WHERE tahun_ajaran_id = ?")
            ->execute([$tahunAjaranAktif['id']]);
    }

    // 2. Update Pengumuman
    $judulLama = "Pemberitahuan Penyesuaian Administrasi SPP Tahun Ajaran Baru";
    $isiBaru = "Assalamu'alaikum Warahmatullahi Wabarakatuh.\n\nYth. Bapak/Ibu Orang Tua/Wali Murid beserta seluruh Siswa/i SMK Al Amin,\n\nSehubungan dengan adanya peningkatan kebutuhan biaya operasional dan administrasi sekolah guna menunjang mutu pendidikan, dengan ini kami sampaikan bahwa terdapat penyesuaian tarif Sumbangan Pembinaan Pendidikan (SPP).\n\nTerhitung mulai Tahun Ajaran Baru ini, tarif SPP bagi **seluruh siswa/i SMK Al Amin (semua angkatan)** disesuaikan menjadi sebesar **Rp 75.000 per bulan**.\n\nPenyesuaian ini bertujuan agar kami dapat terus memberikan pelayanan dan fasilitas pendidikan yang maksimal untuk putra-putri Bapak/Ibu. \n\nDemikian informasi ini kami sampaikan. Atas perhatian, pengertian, dan kerja samanya, kami ucapkan terima kasih.\n\nWassalamu'alaikum Warahmatullahi Wabarakatuh.";
    
    $stmt = $pdo->prepare("UPDATE pengumuman SET isi = ? WHERE judul = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$isiBaru, $judulLama]);
    
    $pdo->commit();
    echo "Update sukses!";
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage();
}
