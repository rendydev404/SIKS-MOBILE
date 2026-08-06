<?php
require 'config/database.php';

$judul = "Pemberitahuan Penyesuaian Administrasi SPP Tahun Ajaran Baru";
$isi = "Assalamu'alaikum Warahmatullahi Wabarakatuh.\n\nYth. Bapak/Ibu Orang Tua/Wali Murid beserta seluruh Siswa/i SMK Al Amin,\n\nSehubungan dengan adanya peningkatan kebutuhan biaya operasional dan administrasi sekolah guna menunjang mutu pendidikan, dengan ini kami sampaikan bahwa terdapat penyesuaian tarif Sumbangan Pembinaan Pendidikan (SPP).\n\n1. Bagi siswa Tahun Ajaran Baru (Angkatan 2026), tarif SPP ditetapkan sebesar Rp 75.000 per bulan.\n2. Bagi siswa angkatan sebelumnya, tarif SPP tetap berlaku normal sebesar Rp 50.000 per bulan.\n\nPenyesuaian ini bertujuan agar kami dapat terus memberikan pelayanan dan fasilitas pendidikan yang maksimal. Demikian informasi ini kami sampaikan. Atas perhatian, pengertian, dan kerja samanya, kami ucapkan terima kasih.\n\nWassalamu'alaikum Warahmatullahi Wabarakatuh.";

$stmt = $pdo->prepare("INSERT INTO pengumuman (judul, isi, is_active, created_by) VALUES (?, ?, 1, 1)");
$stmt->execute([$judul, $isi]);
echo "Pengumuman berhasil ditambahkan.";
