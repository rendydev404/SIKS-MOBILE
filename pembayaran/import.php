<?php
/**
 * Bulk Upload Pembayaran SPP
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();

$pageTitle = 'Upload Data Pembayaran';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    if ($file['error'] !== 0) {
        $error = 'Gagal mengupload file!';
    } else {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if ($ext !== 'csv') {
            $error = 'Format file harus CSV!';
        } else {
            $handle = fopen($file['tmp_name'], 'r');
            $header = fgetcsv($handle, 1000, ','); // Skip header
            
            $imported = 0;
            $failed = 0;
            $errors = [];
            
            $pdo->beginTransaction();
            
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                // Format: nis, bulan, tahun, jumlah, tanggal, metode, keterangan
                if (count($data) < 3) continue;
                
                $nis = trim($data[0]);
                $bulan = trim($data[1]);
                $tahun = trim($data[2]);
                $jumlah = isset($data[3]) && $data[3] !== '' ? str_replace(['.', ','], '', $data[3]) : getNominalPembayaran($pdo, 'SPP');
                $tanggal = isset($data[4]) ? $data[4] : date('Y-m-d');
                $metode = isset($data[5]) ? $data[5] : 'Tunai';
                $keterangan = isset($data[6]) ? $data[6] : 'Imported';
                
                // Get siswa_id from nis
                $stmtSiswa = $pdo->prepare("SELECT id FROM siswa WHERE nis = ?");
                $stmtSiswa->execute([$nis]);
                $siswa = $stmtSiswa->fetch();
                
                if (!$siswa) {
                    $failed++;
                    $errors[] = "NIS $nis tidak ditemukan.";
                    continue;
                }
                
                $siswaId = $siswa['id'];
                
                // Cek apakah sudah bayar
                $cekStmt = $pdo->prepare("SELECT id FROM pembayaran WHERE siswa_id = ? AND bulan = ? AND tahun = ?");
                $cekStmt->execute([$siswaId, $bulan, $tahun]);
                
                if ($cekStmt->fetch()) {
                    $failed++;
                    $errors[] = "NIS $nis sudah membayar bulan $bulan $tahun.";
                    continue;
                }
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO pembayaran (siswa_id, jenis_pembayaran, bulan, tahun, jumlah_bayar, tanggal_bayar, metode_bayar, keterangan, user_id) VALUES (?, 'SPP', ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $siswaId, $bulan, $tahun, $jumlah, 
                        $tanggal, $metode, $keterangan, $_SESSION['user_id']
                    ]);
                    $imported++;
                } catch (PDOException $e) {
                    $failed++;
                    $errors[] = "Gagal mengimport NIS $nis: " . $e->getMessage();
                }
            }
            
            fclose($handle);
            $pdo->commit();
            
            $success = "Berhasil mengimport $imported data.";
            if ($failed > 0) {
                $success .= " Gagal $failed data.";
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Upload Data Pembayaran (CSV)</h2>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-warning" style="max-height: 200px; overflow-y: auto;">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="alert alert-info">
            <p><strong>Petunjuk Upload:</strong></p>
            <ol>
                <li>Gunakan file CSV dengan pemisah koma (,).</li>
                <li>Format kolom: <code>nis, bulan, tahun, jumlah, tanggal, metode, keterangan</code></li>
                <li>Contoh: <code>12345, Januari, 2025, 50000, 2025-01-01, Tunai, Bayar SPP</code></li>
                <li>NIS wajib ada di sistem dan aktif.</li>
            </ol>
            <a href="template_import.csv" class="btn btn-sm btn-secondary" style="margin-top: 10px;">
                <i class="fas fa-download"></i> Download Template CSV
            </a>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Pilih File CSV</label>
                <input type="file" name="csv_file" class="form-control form-control-simple" accept=".csv" required>
            </div>
            
            <div class="form-actions" style="margin-top: 20px;">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-upload"></i> Jalankan Import
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
