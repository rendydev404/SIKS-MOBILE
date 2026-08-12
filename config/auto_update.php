<?php
/**
 * Auto Database Setup & Update
 * Script ini akan otomatis memperbarui struktur database
 * Dijalankan setiap kali halaman diakses
 */

require_once 'database.php';

// Fungsi untuk cek dan update database
function autoUpdateDatabase($pdo) {
    try {
        // 1. Cek dan tambah kolom password di siswa
        $columns = $pdo->query("SHOW COLUMNS FROM siswa LIKE 'password'")->fetch();
        if (!$columns) {
            $pdo->exec("ALTER TABLE siswa ADD COLUMN password VARCHAR(255) DEFAULT NULL AFTER nisn");
        }
        
        // 2. Cek dan tambah kolom no_whatsapp di siswa
        $columns = $pdo->query("SHOW COLUMNS FROM siswa LIKE 'no_whatsapp'")->fetch();
        if (!$columns) {
            $pdo->exec("ALTER TABLE siswa ADD COLUMN no_whatsapp VARCHAR(15) AFTER no_telp");
        }
        
        // 3. Cek dan tambah kolom jenis_pembayaran di pembayaran (Ubah ke VARCHAR agar dinamis)
        $columns = $pdo->query("SHOW COLUMNS FROM pembayaran LIKE 'jenis_pembayaran'")->fetch();
        if (!$columns) {
            $pdo->exec("ALTER TABLE pembayaran ADD COLUMN jenis_pembayaran VARCHAR(100) NOT NULL DEFAULT 'SPP' AFTER spp_id");
        } else {
            // Selalu pastikan tipe data VARCHAR agar tidak terbatas ENUM
            $pdo->exec("ALTER TABLE pembayaran MODIFY COLUMN jenis_pembayaran VARCHAR(100) NOT NULL DEFAULT 'SPP'");
        }
        
        // 4. Cek dan buat tabel pengumuman
        $tables = $pdo->query("SHOW TABLES LIKE 'pengumuman'")->fetch();
        if (!$tables) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS pengumuman (
                id INT PRIMARY KEY AUTO_INCREMENT,
                judul VARCHAR(200) NOT NULL,
                isi TEXT NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB");
        }
        
        // 5. Update tahun ajaran ke 2025/2026 jika belum ada
        $tahun = $pdo->query("SELECT * FROM tahun_ajaran WHERE tahun = '2025/2026'")->fetch();
        if (!$tahun) {
            $pdo->exec("INSERT INTO tahun_ajaran (tahun, semester, is_active) VALUES ('2025/2026', 'Ganjil', TRUE) ON DUPLICATE KEY UPDATE tahun=tahun");
            $pdo->exec("UPDATE tahun_ajaran SET is_active = FALSE WHERE tahun != '2025/2026'");
        }
        
        // 6. Cek dan tambah kolom tahun_masuk di siswa
        $columns = $pdo->query("SHOW COLUMNS FROM siswa LIKE 'tahun_masuk'")->fetch();
        if (!$columns) {
            $pdo->exec("ALTER TABLE siswa ADD COLUMN tahun_masuk INT DEFAULT NULL AFTER kelas_id");
            // Set default tahun_masuk berdasarkan tingkat kelas (10=2025, 11=2024, 12=2023 untuk tahun 2026)
            $pdo->exec("UPDATE siswa s 
                        JOIN kelas k ON s.kelas_id = k.id 
                        SET s.tahun_masuk = CASE 
                            WHEN k.tingkat = 10 THEN 2025
                            WHEN k.tingkat = 11 THEN 2024
                            WHEN k.tingkat = 12 THEN 2023
                            ELSE 2024 
                        END 
                        WHERE s.tahun_masuk IS NULL");
        }

        // 7. Cek dan buat tabel setting_pembayaran
        $tables = $pdo->query("SHOW TABLES LIKE 'setting_pembayaran'")->fetch();
        if (!$tables) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS setting_pembayaran (
                id INT PRIMARY KEY AUTO_INCREMENT,
                jenis VARCHAR(100) UNIQUE NOT NULL,
                nominal DECIMAL(12, 2) NOT NULL DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB");
            
            // Default values
            $pdo->exec("INSERT INTO setting_pembayaran (jenis, nominal) VALUES 
                ('SPP', 50000), 
                ('Pendaftaran', 0),
                ('MPLS', 0),
                ('Seragam Olahraga', 0),
                ('Baju Werpack (TKJ)', 0),
                ('Jas Almamater', 0),
                ('Atribut', 0),
                ('Rapot', 0),
                ('UTS', 0),
                ('UAS', 0),
                ('Ujian Semester 1', 0),
                ('Ujian Semester 2', 0),
                ('PSG / PKL', 0),
                ('Ujian Akhir (Kelas 12)', 0),
                ('Kenaikan Kelas', 0),
                ('Daftar Ulang', 0),
                ('Uang Bangunan', 0)
            ");
        } else {
            // Ensure all types exist
            $pembayaran_types = [
                'SPP', 'Pendaftaran', 'MPLS', 'Seragam Olahraga', 'Baju Werpack (TKJ)', 
                'Jas Almamater', 'Atribut', 'Rapot', 'UTS', 'UAS', 'Ujian Semester 1', 
                'Ujian Semester 2', 'PSG / PKL', 'Ujian Akhir (Kelas 12)', 'Kenaikan Kelas', 
                'Daftar Ulang', 'Uang Bangunan'
            ];
            foreach ($pembayaran_types as $type) {
                $pdo->prepare("INSERT IGNORE INTO setting_pembayaran (jenis, nominal) VALUES (?, 0)")->execute([$type]);
            }
        }
        
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Jalankan auto update sekali per session (Versioning untuk memaksa update)
if (!isset($_SESSION['db_updated_v2'])) {
    autoUpdateDatabase($pdo);
    $_SESSION['db_updated_v2'] = true;
}
