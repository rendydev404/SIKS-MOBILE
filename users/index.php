<?php
/**
 * Manajemen User
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();
checkRole(['admin']);

$pageTitle = 'Data User';

// Proses tambah/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $namaLengkap = trim($_POST['nama_lengkap'] ?? '');
    $role = $_POST['role'] ?? 'bendahara';
    $status = $_POST['status'] ?? 'aktif';
    
    if (!empty($username) && !empty($namaLengkap)) {
        try {
            if ($action === 'edit' && $id) {
                // Cek username duplikat
                $cek = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $cek->execute([$username, $id]);
                if ($cek->fetch()) {
                    setAlert('error', 'Username sudah digunakan!');
                } else {
                    if (!empty($password)) {
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, nama_lengkap = ?, role = ?, status = ? WHERE id = ?");
                        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $namaLengkap, $role, $status, $id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, nama_lengkap = ?, role = ?, status = ? WHERE id = ?");
                        $stmt->execute([$username, $namaLengkap, $role, $status, $id]);
                    }
                    setAlert('success', 'Data user berhasil diperbarui!');
                }
            } else {
                if (empty($password)) {
                    setAlert('error', 'Password wajib diisi untuk user baru!');
                } else {
                    // Cek username
                    $cek = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                    $cek->execute([$username]);
                    if ($cek->fetch()) {
                        setAlert('error', 'Username sudah digunakan!');
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role, status) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $namaLengkap, $role, $status]);
                        setAlert('success', 'User berhasil ditambahkan!');
                    }
                }
            }
        } catch (PDOException $e) {
            setAlert('error', 'Gagal menyimpan data: ' . $e->getMessage());
        }
    } else {
        setAlert('error', 'Username dan Nama Lengkap wajib diisi!');
    }
    header('Location: index.php');
    exit;
}

// Hapus user
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    if ($id != $_SESSION['user_id']) {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            setAlert('success', 'User berhasil dihapus!');
        } catch (PDOException $e) {
            setAlert('error', 'Gagal menghapus user!');
        }
    } else {
        setAlert('error', 'Tidak dapat menghapus akun sendiri!');
    }
    header('Location: index.php');
    exit;
}

// Get daftar user
$userList = $pdo->query("SELECT * FROM users ORDER BY role, nama_lengkap")->fetchAll();

include '../includes/header.php';
?>

<div class="toolbar">
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Cari user...">
    </div>
    <button class="btn btn-success" onclick="showModal()">
        <i class="fas fa-plus"></i> Tambah User
    </button>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Username</th>
                        <th>Nama Lengkap</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($userList as $i => $user): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($user['username']) ?></td>
                        <td><?= e($user['nama_lengkap']) ?></td>
                        <td>
                            <span class="badge badge-<?= $user['role'] ?>">
                                <?= ucfirst($user['role']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-<?= $user['status'] == 'aktif' ? 'success' : 'danger' ?>">
                                <?= ucfirst($user['status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-primary btn-sm" onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <a href="index.php?delete=<?= $user['id'] ?>" class="btn btn-danger btn-sm btn-delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Form -->
<div id="modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: var(--bg-card); border-radius: var(--radius-lg); padding: 24px; width: 100%; max-width: 450px; margin: 20px; border: 1px solid var(--border-color);">
        <h3 style="margin-bottom: 20px;" id="modalTitle">Tambah User</h3>
        <form method="POST">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="formId">
            
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" id="username" class="form-control form-control-simple" required>
            </div>
            
            <div class="form-group">
                <label>Password <span id="pwdNote" style="color: var(--text-muted); font-size: 12px;"></span></label>
                <input type="password" name="password" id="password" class="form-control form-control-simple">
            </div>
            
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="nama_lengkap" id="namaLengkap" class="form-control form-control-simple" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="role" class="form-control form-control-simple">
                        <option value="admin">Admin</option>
                        <option value="bendahara">Bendahara</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="status" class="form-control form-control-simple">
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Non-Aktif</option>
                    </select>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Simpan
                </button>
                <button type="button" class="btn btn-secondary" onclick="hideModal()">
                    Batal
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showModal() {
    document.getElementById('modal').style.display = 'flex';
    document.getElementById('modalTitle').textContent = 'Tambah User';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value = '';
    document.getElementById('username').value = '';
    document.getElementById('password').value = '';
    document.getElementById('password').required = true;
    document.getElementById('pwdNote').textContent = '';
    document.getElementById('namaLengkap').value = '';
    document.getElementById('role').value = 'bendahara';
    document.getElementById('status').value = 'aktif';
}

function hideModal() {
    document.getElementById('modal').style.display = 'none';
}

function editUser(data) {
    document.getElementById('modal').style.display = 'flex';
    document.getElementById('modalTitle').textContent = 'Edit User';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = data.id;
    document.getElementById('username').value = data.username;
    document.getElementById('password').value = '';
    document.getElementById('password').required = false;
    document.getElementById('pwdNote').textContent = '(Kosongkan jika tidak ingin mengubah)';
    document.getElementById('namaLengkap').value = data.nama_lengkap;
    document.getElementById('role').value = data.role;
    document.getElementById('status').value = data.status;
}

document.getElementById('modal').addEventListener('click', function(e) {
    if (e.target === this) hideModal();
});
</script>

<?php include '../includes/footer.php'; ?>
