<?php
/**
 * Portal Siswa - Login
 * Login dengan NISN dan Password
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

// Jika sudah login sebagai siswa
if (isset($_SESSION['siswa_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nisn = trim($_POST['nisn'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($nisn) || empty($password)) {
        $error = 'NISN dan Password harus diisi!';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM siswa WHERE nisn = ? AND status IN ('aktif', 'lulus')");
            $stmt->execute([$nisn]);
            $siswa = $stmt->fetch();
            
            if ($siswa) {
                // Cek apakah password sudah diset
                if (empty($siswa['password'])) {
                    // First time login - set password
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmtUpdate = $pdo->prepare("UPDATE siswa SET password = ? WHERE id = ?");
                    $stmtUpdate->execute([$hash, $siswa['id']]);
                    
                    $_SESSION['siswa_id'] = $siswa['id'];
                    $_SESSION['siswa_nis'] = $siswa['nis'];
                    $_SESSION['siswa_nama'] = $siswa['nama'];
                    $_SESSION['is_siswa'] = true;
                    
                    setAlert('success', 'Password berhasil diset! Silakan ingat password Anda: ' . $password);
                    header('Location: dashboard.php');
                    exit;
                } else {
                    // Verify password
                    if (password_verify($password, $siswa['password'])) {
                        $_SESSION['siswa_id'] = $siswa['id'];
                        $_SESSION['siswa_nis'] = $siswa['nis'];
                        $_SESSION['siswa_nama'] = $siswa['nama'];
                        $_SESSION['is_siswa'] = true;
                        
                        header('Location: dashboard.php');
                        exit;
                    } else {
                        $error = 'Password salah!';
                    }
                }
            } else {
                $error = 'NISN tidak ditemukan atau status siswa tidak mendapatkan akses!';
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan sistem.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Siswa - SIKS SMK Al Amin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <style>
        body {
            background: radial-gradient(circle at top right, #1e293b, #0f172a);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 28px;
            padding: 45px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: slideInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .login-header .logo-icon {
            width: 70px; height: 70px; margin: 0 auto 20px;
            background: linear-gradient(135deg, #10b981, #34d399);
            border-radius: 20px; display: flex; align-items: center; justify-content: center;
            font-size: 32px; color: white; box-shadow: 0 10px 15px rgba(16, 185, 129, 0.2);
        }
        .login-header h1 { font-size: 28px; font-weight: 800; margin-bottom: 8px; color: #fff; }
        .login-header p { color: var(--text-secondary); font-size: 14px; margin-bottom: 30px; }
        
        .alert-info {
            background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.2);
            font-size: 13px; margin-bottom: 25px; padding: 15px; border-radius: 12px;
        }
        
        .form-control {
            background: rgba(15, 23, 42, 0.6) !important;
            border: 1px solid var(--border-color) !important;
            color: #fff !important;
            padding-left: 48px !important;
            height: 54px;
            border-radius: 14px;
        }
        .form-control:focus { border-color: var(--primary) !important; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15) !important; }
        .input-wrapper i { font-size: 18px; left: 18px; color: var(--text-muted); }
        
        .btn-green {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white; border: none; font-weight: 700; height: 54px;
            border-radius: 14px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .btn-green:hover { transform: translateY(-2px); box-shadow: 0 8px 15px rgba(16, 185, 129, 0.3); }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="login-header text-center">
            <div class="logo-icon" style="background: transparent; box-shadow: none; width: 120px; height: 120px; margin: 0 auto 20px;">
                <img src="../assets/img/logo_sekolah.png" alt="Logo SMK" style="width: 100%; height: 100%; object-fit: contain;">
            </div>
            <h1>Portal Siswa</h1>
            <p>Sistem Informasi Keuangan Sekolah</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger animate-fade">
                <i class="fas fa-exclamation-circle"></i>
                <?= e($error) ?>
            </div>
        <?php endif; ?>
        
        <div class="alert alert-info d-flex gap-2">
            <i class="fas fa-info-circle" style="margin-top: 3px; color: var(--info);"></i>
            <div>
                <strong>Login Pertama?</strong><br>
                Gunakan NISN Anda dan buat password baru saat masuk pertama kali.
            </div>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label>NISN</label>
                <div class="input-wrapper">
                    <i class="fas fa-id-card"></i>
                    <input type="text" name="nisn" class="form-control" placeholder="Nomor Induk Siswa Nasional" value="<?= e($_POST['nisn'] ?? '') ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" class="form-control" placeholder="Masukkan password Anda" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-green btn-block btn-lg mt-3">
                Masuk ke Portal <i class="fas fa-arrow-right" style="margin-left: 8px; font-size: 14px;"></i>
            </button>
        </form>
    </div>
</body>
</html>
</body>
</html>
