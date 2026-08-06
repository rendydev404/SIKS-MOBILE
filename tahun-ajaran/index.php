<?php
/**
 * Tahun Ajaran
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();
checkRole(['admin']);

$pageTitle = 'Tahun Ajaran';

// Proses
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? '';
    $tahun = trim($_POST['tahun'] ?? '');
    $semester = $_POST['semester'] ?? '';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    if (!empty($tahun) && !empty($semester)) {
        try {
            if ($isActive) {
                // Nonaktifkan semua tahun ajaran lain
                $pdo->query("UPDATE tahun_ajaran SET is_active = 0");
            }
            
            if ($action === 'edit' && $id) {
                $stmt = $pdo->prepare("UPDATE tahun_ajaran SET tahun = ?, semester = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$tahun, $semester, $isActive, $id]);
                setAlert('success', 'Tahun ajaran berhasil diperbarui!');
            } else {
                $stmt = $pdo->prepare("INSERT INTO tahun_ajaran (tahun, semester, is_active) VALUES (?, ?, ?)");
                $stmt->execute([$tahun, $semester, $isActive]);
                setAlert('success', 'Tahun ajaran berhasil ditambahkan!');
            }
        } catch (PDOException $e) {
            setAlert('error', 'Gagal menyimpan: ' . $e->getMessage());
        }
    } else {
        setAlert('error', 'Tahun dan semester wajib diisi!');
    }
    header('Location: index.php');
    exit;
}

// Hapus
if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM tahun_ajaran WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        setAlert('success', 'Tahun ajaran berhasil dihapus!');
    } catch (PDOException $e) {
        setAlert('error', 'Gagal menghapus! Mungkin masih ada data terkait.');
    }
    header('Location: index.php');
    exit;
}

// Get data
$tahunAjaranList = $pdo->query("SELECT * FROM tahun_ajaran ORDER BY tahun DESC, semester")->fetchAll();

include '../includes/header.php';
?>

<div class="toolbar">
    <button class="btn btn-success" onclick="showModal()">
        <i class="fas fa-plus"></i> Tambah Tahun Ajaran
    </button>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (empty($tahunAjaranList)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar"></i>
                <h3>Belum ada tahun ajaran</h3>
                <p>Silakan tambahkan tahun ajaran</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tahun</th>
                            <th>Semester</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tahunAjaranList as $i => $ta): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= e($ta['tahun']) ?></td>
                            <td><?= $ta['semester'] ?></td>
                            <td>
                                <?php if ($ta['is_active']): ?>
                                    <span class="badge badge-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Non-Aktif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-primary btn-sm" onclick="editTa(<?= htmlspecialchars(json_encode($ta)) ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="index.php?delete=<?= $ta['id'] ?>" class="btn btn-danger btn-sm btn-delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Form -->
<div id="modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: var(--bg-card); border-radius: var(--radius-lg); padding: 24px; width: 100%; max-width: 450px; margin: 20px; border: 1px solid var(--border-color);">
        <h3 style="margin-bottom: 20px;" id="modalTitle">Tambah Tahun Ajaran</h3>
        <form method="POST">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="formId">
            
            <div class="form-group">
                <label>Tahun Ajaran</label>
                <input type="text" name="tahun" id="tahun" class="form-control form-control-simple" placeholder="2024/2025" required>
            </div>
            
            <div class="form-group">
                <label>Semester</label>
                <select name="semester" id="semester" class="form-control form-control-simple" required>
                    <option value="Ganjil">Ganjil</option>
                    <option value="Genap">Genap</option>
                </select>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="is_active" id="isActive" value="1">
                    <span>Set sebagai Tahun Ajaran Aktif</span>
                </label>
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
    document.getElementById('modalTitle').textContent = 'Tambah Tahun Ajaran';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value = '';
    document.getElementById('tahun').value = '';
    document.getElementById('semester').value = 'Ganjil';
    document.getElementById('isActive').checked = false;
}

function hideModal() {
    document.getElementById('modal').style.display = 'none';
}

function editTa(data) {
    document.getElementById('modal').style.display = 'flex';
    document.getElementById('modalTitle').textContent = 'Edit Tahun Ajaran';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = data.id;
    document.getElementById('tahun').value = data.tahun;
    document.getElementById('semester').value = data.semester;
    document.getElementById('isActive').checked = data.is_active == 1;
}

document.getElementById('modal').addEventListener('click', function(e) {
    if (e.target === this) hideModal();
});
</script>

<?php include '../includes/footer.php'; ?>
