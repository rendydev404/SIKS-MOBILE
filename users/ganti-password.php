<?php
/**
 * Ganti Password Admin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();
checkRole(['admin']);

$pageTitle = 'Ganti Password';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $passwordLama = $_POST['password_lama'] ?? '';
    $passwordBaru = $_POST['password_baru'] ?? '';
    $konfirmasi = $_POST['konfirmasi_password'] ?? '';

    if ($passwordLama === '' || $passwordBaru === '' || $konfirmasi === '') {
        $error = 'Semua kolom password wajib diisi.';
    } elseif (strlen($passwordBaru) < 8) {
        $error = 'Password baru minimal 8 karakter.';
    } elseif ($passwordBaru !== $konfirmasi) {
        $error = 'Konfirmasi password baru tidak sama.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? AND status = \'aktif\' LIMIT 1');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($passwordLama, $user['password'])) {
                $error = 'Password lama tidak sesuai.';
            } elseif (password_verify($passwordBaru, $user['password'])) {
                $error = 'Password baru harus berbeda dari password lama.';
            } else {
                $update = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                $update->execute([
                    password_hash($passwordBaru, PASSWORD_DEFAULT),
                    $_SESSION['user_id'],
                ]);
                setAlert('success', 'Password berhasil diubah. Gunakan password baru saat login berikutnya.');
                header('Location: ganti-password.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Password tidak dapat diubah. Silakan coba lagi.';
        }
    }
}

include '../includes/header.php';
?>

<div class="card" style="max-width: 560px; margin: 0 auto;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-key"></i> Ganti Password Admin</h2>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= e($error) ?></div>
        <?php endif; ?>

        <p style="color: var(--text-secondary); margin: 0 0 24px;">
            Gunakan password yang kuat dan tidak sama dengan password sebelumnya.
        </p>

        <form method="POST" autocomplete="on">
            <div class="form-group">
                <label for="password_lama">Password Lama</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password_lama" name="password_lama" class="form-control" autocomplete="current-password" required>
                </div>
            </div>
            <div class="form-group">
                <label for="password_baru">Password Baru</label>
                <div class="input-wrapper">
                    <i class="fas fa-key"></i>
                    <input type="password" id="password_baru" name="password_baru" class="form-control" autocomplete="new-password" minlength="8" required>
                </div>
            </div>
            <div class="form-group">
                <label for="konfirmasi_password">Konfirmasi Password Baru</label>
                <div class="input-wrapper">
                    <i class="fas fa-check-circle"></i>
                    <input type="password" id="konfirmasi_password" name="konfirmasi_password" class="form-control" autocomplete="new-password" minlength="8" required>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Password Baru
                </button>
                <a href="index.php" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
