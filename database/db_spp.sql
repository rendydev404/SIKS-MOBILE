-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: May 21, 2026 at 04:17 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_spp`
--

-- --------------------------------------------------------

--
-- Table structure for table `kelas`
--

CREATE TABLE `kelas` (
  `id` int(11) NOT NULL,
  `nama_kelas` varchar(20) NOT NULL,
  `jurusan` enum('MLPB','BDP','TKJ') NOT NULL,
  `tingkat` enum('X','XI','XII') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kelas`
--

INSERT INTO `kelas` (`id`, `nama_kelas`, `jurusan`, `tingkat`, `created_at`) VALUES
(1, '10 MPLB 1', 'MLPB', 'X', '2026-05-21 13:30:44'),
(2, '10 MPLB 2', 'MLPB', 'X', '2026-05-21 13:30:44'),
(3, '11 MPLB 1', 'MLPB', 'XI', '2026-05-21 13:30:44'),
(4, '11 MPLB 2', 'MLPB', 'XI', '2026-05-21 13:30:44'),
(5, '12 MPLB 1', 'MLPB', 'XII', '2026-05-21 13:30:44'),
(6, '12 MPLB 2', 'MLPB', 'XII', '2026-05-21 13:30:44'),
(7, '10 TKJ', 'TKJ', 'X', '2026-05-21 13:30:44'),
(8, '11 TKJ', 'TKJ', 'XI', '2026-05-21 13:30:44'),
(9, '12 TKJ', 'TKJ', 'XII', '2026-05-21 13:30:44'),
(10, '10 BDP 1', 'BDP', 'X', '2026-05-21 13:30:44'),
(11, '10 BDP 2', 'BDP', 'X', '2026-05-21 13:30:44'),
(12, '11 BDP 1', 'BDP', 'XI', '2026-05-21 13:30:44'),
(13, '11 BDP 2', 'BDP', 'XI', '2026-05-21 13:30:44'),
(14, '12 BDP 1', 'BDP', 'XII', '2026-05-21 13:30:44'),
(15, '12 BDP 2', 'BDP', 'XII', '2026-05-21 13:30:44');

-- --------------------------------------------------------

--
-- Table structure for table `pembayaran`
--

CREATE TABLE `pembayaran` (
  `id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `spp_id` int(11) DEFAULT NULL,
  `jenis_pembayaran` varchar(100) NOT NULL DEFAULT 'SPP',
  `bulan` enum('Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember') NOT NULL,
  `tahun` int(11) NOT NULL,
  `jumlah_bayar` decimal(12,2) NOT NULL,
  `tanggal_bayar` date NOT NULL,
  `metode_bayar` enum('Tunai','Transfer') DEFAULT 'Tunai',
  `keterangan` text DEFAULT NULL,
  `status` enum('lunas','pending','ditolak') NOT NULL DEFAULT 'lunas',
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verifikasi_admin_id` int(11) DEFAULT NULL,
  `tanggal_verifikasi` timestamp NULL DEFAULT NULL,
  `admin_note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pengumuman`
--

CREATE TABLE `pengumuman` (
  `id` int(11) NOT NULL,
  `judul` varchar(200) NOT NULL,
  `isi` text NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pengumuman`
--

INSERT INTO `pengumuman` (`id`, `judul`, `isi`, `is_active`, `created_by`, `created_at`) VALUES
(1, 'Batas Waktu Pembayaran SPP', 'Pembayaran SPP, Infak, dan Komputer setiap bulan paling lambat tanggal 10. Terima kasih atas perhatiannya.', 1, 1, '2026-05-21 13:30:44'),
(2, 'Info Pembayaran', 'Pembayaran dapat dilakukan melalui Bendahara Sekolah atau Transfer ke rekening sekolah. Harap simpan bukti pembayaran.', 1, 1, '2026-05-21 13:30:44');

-- --------------------------------------------------------

--
-- Table structure for table `setting_pembayaran`
--

CREATE TABLE `setting_pembayaran` (
  `id` int(11) NOT NULL,
  `jenis` varchar(100) NOT NULL,
  `nominal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tahun_masuk` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `setting_pembayaran`
--

INSERT INTO `setting_pembayaran` (`id`, `jenis`, `nominal`, `tahun_masuk`, `updated_at`) VALUES
(1, 'SPP', 150000.00, 0, '2026-05-21 14:11:08'),
(2, 'Pendaftaran', 150000.00, 0, '2026-05-21 14:11:08'),
(3, 'MPLS', 100000.00, 0, '2026-05-21 14:11:08'),
(4, 'Seragam Olahraga', 150000.00, 0, '2026-05-21 14:11:08'),
(5, 'Baju Werpack (TKJ)', 200000.00, 0, '2026-05-21 14:11:08'),
(6, 'Jas Almamater', 150000.00, 0, '2026-05-21 14:11:08'),
(7, 'Atribut', 50000.00, 0, '2026-05-21 14:11:08'),
(8, 'Rapot', 50000.00, 0, '2026-05-21 14:11:08'),
(9, 'UTS', 0.00, 0, '2026-05-21 13:53:40'),
(10, 'UAS', 0.00, 0, '2026-05-21 13:53:40'),
(11, 'Ujian Semester 1', 0.00, 0, '2026-05-21 13:53:40'),
(12, 'Ujian Semester 2', 0.00, 0, '2026-05-21 13:53:40'),
(13, 'PSG / PKL', 300000.00, 0, '2026-05-21 14:11:08'),
(14, 'Ujian Akhir (Kelas 12)', 100000.00, 0, '2026-05-21 14:11:08'),
(15, 'Kenaikan Kelas', 50000.00, 0, '2026-05-21 14:11:08'),
(16, 'Daftar Ulang', 200000.00, 0, '2026-05-21 14:11:08'),
(17, 'Uang Bangunan', 0.00, 0, '2026-05-21 13:53:40'),
(35, 'DSP', 300000.00, 0, '2026-05-21 14:12:34'),
(36, 'Ujian Akhir (Kelas 12)', 1200000.00, 2023, '2026-05-21 14:16:30'),
(37, 'Ujian Akhir (Kelas 12)', 1000000.00, 2024, '2026-05-21 14:16:48');

-- --------------------------------------------------------

--
-- Table structure for table `siswa`
--

CREATE TABLE `siswa` (
  `id` int(11) NOT NULL,
  `nis` varchar(20) NOT NULL,
  `nisn` varchar(20) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `nama` varchar(100) NOT NULL,
  `jenis_kelamin` enum('L','P') NOT NULL,
  `tempat_lahir` varchar(50) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `no_telp` varchar(15) DEFAULT NULL,
  `no_whatsapp` varchar(15) DEFAULT NULL,
  `nama_wali` varchar(100) DEFAULT NULL,
  `no_telp_wali` varchar(15) DEFAULT NULL,
  `kelas_id` int(11) DEFAULT NULL,
  `tahun_masuk` int(11) DEFAULT NULL,
  `tahun_ajaran_id` int(11) DEFAULT NULL,
  `status` enum('aktif','lulus','pindah','keluar') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `spp`
--

CREATE TABLE `spp` (
  `id` int(11) NOT NULL,
  `tahun_ajaran_id` int(11) NOT NULL,
  `nominal` decimal(12,2) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `spp`
--

INSERT INTO `spp` (`id`, `tahun_ajaran_id`, `nominal`, `keterangan`, `created_at`) VALUES
(1, 1, 150000.00, 'SPP Semester Ganjil 2025/2026', '2026-05-21 13:30:44');

-- --------------------------------------------------------

--
-- Table structure for table `tahun_ajaran`
--

CREATE TABLE `tahun_ajaran` (
  `id` int(11) NOT NULL,
  `tahun` varchar(9) NOT NULL,
  `semester` enum('Ganjil','Genap') NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tahun_ajaran`
--

INSERT INTO `tahun_ajaran` (`id`, `tahun`, `semester`, `is_active`, `created_at`) VALUES
(1, '2025/2026', 'Ganjil', 1, '2026-05-21 13:30:44'),
(2, '2025/2026', 'Genap', 0, '2026-05-21 13:30:44');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `role` enum('admin','bendahara') NOT NULL DEFAULT 'bendahara',
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$dhPs4IG0LPPMMkdiuh5mFeQJtBav9TAQeSgUyeQc/yoiGtE4WpW6q', 'Administrator', 'admin', 'aktif', '2026-05-21 13:30:44', '2026-05-21 14:05:04'),
(2, 'bendahara', '$2y$10$dhPs4IG0LPPMMkdiuh5mFeQJtBav9TAQeSgUyeQc/yoiGtE4WpW6q', 'Bendahara Sekolah', 'bendahara', 'aktif', '2026-05-21 13:30:44', '2026-05-21 14:05:04');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `kelas`
--
ALTER TABLE `kelas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `spp_id` (`spp_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_pembayaran_siswa` (`siswa_id`),
  ADD KEY `idx_pembayaran_bulan` (`bulan`,`tahun`);

--
-- Indexes for table `pengumuman`
--
ALTER TABLE `pengumuman`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `setting_pembayaran`
--
ALTER TABLE `setting_pembayaran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_jenis_tahun` (`jenis`,`tahun_masuk`);

--
-- Indexes for table `siswa`
--
ALTER TABLE `siswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nis` (`nis`),
  ADD UNIQUE KEY `nisn` (`nisn`),
  ADD KEY `tahun_ajaran_id` (`tahun_ajaran_id`),
  ADD KEY `idx_siswa_nis` (`nis`),
  ADD KEY `idx_siswa_kelas` (`kelas_id`);

--
-- Indexes for table `spp`
--
ALTER TABLE `spp`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tahun_ajaran_id` (`tahun_ajaran_id`);

--
-- Indexes for table `tahun_ajaran`
--
ALTER TABLE `tahun_ajaran`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `kelas`
--
ALTER TABLE `kelas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `pembayaran`
--
ALTER TABLE `pembayaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pengumuman`
--
ALTER TABLE `pengumuman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `setting_pembayaran`
--
ALTER TABLE `setting_pembayaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `siswa`
--
ALTER TABLE `siswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `spp`
--
ALTER TABLE `spp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tahun_ajaran`
--
ALTER TABLE `tahun_ajaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD CONSTRAINT `pembayaran_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pembayaran_ibfk_2` FOREIGN KEY (`spp_id`) REFERENCES `spp` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `pembayaran_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pengumuman`
--
ALTER TABLE `pengumuman`
  ADD CONSTRAINT `pengumuman_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `siswa`
--
ALTER TABLE `siswa`
  ADD CONSTRAINT `siswa_ibfk_1` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `siswa_ibfk_2` FOREIGN KEY (`tahun_ajaran_id`) REFERENCES `tahun_ajaran` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `spp`
--
ALTER TABLE `spp`
  ADD CONSTRAINT `spp_ibfk_1` FOREIGN KEY (`tahun_ajaran_id`) REFERENCES `tahun_ajaran` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
