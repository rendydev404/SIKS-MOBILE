<?php
/**
 * Portal Siswa - Ganti Password
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

// Cek login siswa
if (!isset($_SESSION['siswa_id'])) {
    header('Location: index.php');
    exit;
}

$siswaId = $_SESSION['siswa_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $passwordLama = $_POST['password_lama'] ?? '';
    $passwordBaru = $_POST['password_baru'] ?? '';
    $konfirmasi = $_POST['konfirmasi'] ?? '';
    
    // Get current password
    $stmt = $pdo->prepare("SELECT password FROM siswa WHERE id = ?");
    $stmt->execute([$siswaId]);
    $siswa = $stmt->fetch();
    
    if (empty($passwordLama) || empty($passwordBaru) || empty($konfirmasi)) {
        $error = 'Semua field harus diisi!';
    } elseif (!password_verify($passwordLama, $siswa['password'])) {
        $error = 'Password lama salah!';
    } elseif (strlen($passwordBaru) < 4) {
        $error = 'Password baru minimal 4 karakter!';
    } elseif ($passwordBaru !== $konfirmasi) {
        $error = 'Konfirmasi password tidak cocok!';
    } else {
        try {
            $hash = password_hash($passwordBaru, PASSWORD_DEFAULT);
            $stmtUpdate = $pdo->prepare("UPDATE siswa SET password = ? WHERE id = ?");
            $stmtUpdate->execute([$hash, $siswaId]);
            $success = 'Password berhasil diubah! Silakan ingat password baru Anda.';
        } catch (PDOException $e) {
            $error = 'Gagal mengubah password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password - Portal Siswa</title>
    <script>
        if (navigator.userAgent.indexOf('SIKSApp/') !== -1) {
            document.documentElement.classList.add('native-app-mode');
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <style>
        body {
            background: radial-gradient(circle at top left, #1e293b, #0f172a);
            min-height: 100vh;
        }
        .container { max-width: 500px; margin: 80px auto; padding: 0 20px; }
        .form-control-simple { 
            background: rgba(15, 23, 42, 0.6); border: 1px solid var(--border-color);
            color: white; border-radius: 12px; padding: 12px 18px;
        }
        .form-control-simple:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2); }
    </style>
</head>
<body>
    <div class="container animate-slide-up">
        <div class="card glass">
            <div class="card-header">
                <h2 class="card-title"><i class="fas fa-key" style="color: var(--primary);"></i> Ganti Password</h2>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger animate-fade"><?= e($error) ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success animate-fade"><?= e($success) ?></div>
                    <div class="mt-2">
                        <a href="dashboard.php" class="btn btn-primary btn-block">Kembali ke Dashboard</a>
                    </div>
                <?php else: ?>
                
                <div class="alert alert-info" style="font-size: 13px; background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.2);">
                    <i class="fas fa-shield-alt"></i>
                    Pastikan password baru Anda aman dan mudah diingat.
                </div>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Password Saat Ini</label>
                        <input type="password" name="password_lama" class="form-control form-control-simple" required placeholder="Masukkan password lama">
                    </div>
                    
                    <div class="form-group">
                        <label>Password Baru</label>
                        <input type="password" name="password_baru" class="form-control form-control-simple" required minlength="4" placeholder="Minimal 4 karakter">
                    </div>
                    
                    <div class="form-group">
                        <label>Ulangi Password Baru</label>
                        <input type="password" name="konfirmasi" class="form-control form-control-simple" required placeholder="Konfirmasi password baru">
                    </div>
                    
                    <div style="margin-top: 30px; display: flex; gap: 15px;">
                        <a href="dashboard.php" class="btn btn-secondary" style="flex:1;">Batal</a>
                        <button type="submit" class="btn btn-primary" style="flex:2;">
                            <i class="fas fa-save" style="margin-right: 8px;"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
