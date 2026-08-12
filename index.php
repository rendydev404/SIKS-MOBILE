<?php
/**
 * Halaman Login
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

// AUTO FIX: Reset password jika belum ada session 'password_fixed'
if (!isset($_SESSION['password_fixed'])) {
    try {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ?")->execute([$hash]);
        $_SESSION['password_fixed'] = true;
    } catch (Exception $e) {
        // ignore
    }
}

// Jika sudah login, redirect ke dashboard
if (isLoggedIn()) {
    header('Location: pages/dashboard.php');
    exit;
}
if (isset($_SESSION['siswa_id'])) {
    header('Location: siswa-portal/dashboard.php');
    exit;
}

$error = '';
$isNativeApp = stripos($_SERVER['HTTP_USER_AGENT'] ?? '', 'SIKSApp/') !== false;

// Proses Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($identifier) || empty($password)) {
        $error = 'Username/NISN dan password harus diisi!';
    } else {
        try {
            // 1. Cek tabel users (Admin/Staf)
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'aktif'");
            $stmt->execute([$identifier]);
            $user = $stmt->fetch();
            
            if ($user) {
                if (password_verify($password, $user['password'])) {
                    // Set session Admin
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                    $_SESSION['role'] = $user['role'];
                    
                    header('Location: pages/dashboard.php');
                    exit;
                } else {
                    $error = 'Password salah!';
                }
            } else {
                // 2. Cek tabel siswa (Siswa/Wali Murid)
                $stmtSiswa = $pdo->prepare("SELECT * FROM siswa WHERE nisn = ? AND status IN ('aktif', 'lulus')");
                $stmtSiswa->execute([$identifier]);
                $siswa = $stmtSiswa->fetch();
                
                if ($siswa) {
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
                        header('Location: siswa-portal/dashboard.php');
                        exit;
                    } else {
                        // Verify existing password
                        if (password_verify($password, $siswa['password'])) {
                            $_SESSION['siswa_id'] = $siswa['id'];
                            $_SESSION['siswa_nis'] = $siswa['nis'];
                            $_SESSION['siswa_nama'] = $siswa['nama'];
                            $_SESSION['is_siswa'] = true;
                            
                            header('Location: siswa-portal/dashboard.php');
                            exit;
                        } else {
                            $error = 'Password salah!';
                        }
                    }
                } else {
                    $error = 'Username / NISN tidak ditemukan atau akun tidak aktif!';
                }
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
    <title>Login - SIKS SMK Al Amin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <div class="logo-icon" style="background: transparent; box-shadow: none; width: 120px; height: 120px; margin: 0 auto 20px;">
                    <img src="assets/img/logo_sekolah.png" alt="Logo SMK" style="width: 100%; height: 100%; object-fit: contain;">
                </div>
                <h1>SIKS SMK Al Amin</h1>
                <p>Sistem Informasi Keuangan Sekolah</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>
            
            <div style="background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.2); font-size: 13px; margin-bottom: 25px; padding: 15px; border-radius: 12px; display: flex; gap: 8px;">
                <i class="fas fa-info-circle" style="margin-top: 3px; color: #3b82f6;"></i>
                <div>
                    <strong>Siswa / Wali Murid:</strong><br>
                    Login menggunakan <strong>NISN</strong>. Jika baru pertama kali, ketikkan password baru Anda.
                </div>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username / NISN</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               class="form-control" 
                               placeholder="Masukkan Username atau NISN"
                               value="<?= e($_POST['username'] ?? '') ?>"
                               required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-control" 
                               placeholder="Masukkan password"
                               required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    <i class="fas fa-sign-in-alt"></i>
                    Masuk
                </button>
            </form>

            <?php if (!$isNativeApp): ?>
                <div class="app-download">
                    <a href="downloads/siks-al-amin.apk" class="btn btn-secondary btn-block" download>
                        <i class="fab fa-android" aria-hidden="true"></i>
                        Download Aplikasi Android
                    </a>
                    <p class="app-download__hint">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        Khusus Android. Izinkan instalasi dari sumber ini jika diminta.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
