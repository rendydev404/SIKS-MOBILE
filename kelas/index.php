<?php
/**
 * Daftar Kelas
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();
checkRole(['admin']);

$pageTitle = 'Data Kelas';

// Proses tambah/edit kelas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? '';
    $namaKelas = trim($_POST['nama_kelas'] ?? '');
    $jurusan = $_POST['jurusan'] ?? '';
    $tingkat = $_POST['tingkat'] ?? '';
    
    if (!empty($namaKelas) && !empty($jurusan) && !empty($tingkat)) {
        try {
            if ($action === 'edit' && $id) {
                $stmt = $pdo->prepare("UPDATE kelas SET nama_kelas = ?, jurusan = ?, tingkat = ? WHERE id = ?");
                $stmt->execute([$namaKelas, $jurusan, $tingkat, $id]);
                setAlert('success', 'Data kelas berhasil diperbarui!');
            } else {
                $stmt = $pdo->prepare("INSERT INTO kelas (nama_kelas, jurusan, tingkat) VALUES (?, ?, ?)");
                $stmt->execute([$namaKelas, $jurusan, $tingkat]);
                setAlert('success', 'Kelas berhasil ditambahkan!');
            }
        } catch (PDOException $e) {
            setAlert('error', 'Gagal menyimpan data: ' . $e->getMessage());
        }
    } else {
        setAlert('error', 'Semua field wajib diisi!');
    }
    header('Location: index.php');
    exit;
}

// Get daftar kelas
$kelasList = $pdo->query("
    SELECT k.*, 
           (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id = k.id AND s.status = 'aktif') as jumlah_siswa
    FROM kelas k 
    ORDER BY k.tingkat, k.jurusan, k.nama_kelas
")->fetchAll();

include '../includes/header.php';
?>

<div class="toolbar">
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Cari kelas...">
    </div>
    <button class="btn btn-success" onclick="showModal()">
        <i class="fas fa-plus"></i> Tambah Kelas
    </button>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (empty($kelasList)): ?>
            <div class="empty-state">
                <i class="fas fa-school"></i>
                <h3>Belum ada data kelas</h3>
                <p>Silakan tambahkan kelas baru</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Kelas</th>
                            <th>Jurusan</th>
                            <th>Tingkat</th>
                            <th>Jumlah Siswa</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kelasList as $i => $kelas): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= e($kelas['nama_kelas']) ?></td>
                            <td><?= e($kelas['jurusan']) ?></td>
                            <td><?= $kelas['tingkat'] ?></td>
                            <td><?= $kelas['jumlah_siswa'] ?> siswa</td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-primary btn-sm" onclick="editKelas(<?= htmlspecialchars(json_encode($kelas)) ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="proses.php?action=delete&id=<?= $kelas['id'] ?>" class="btn btn-danger btn-sm btn-delete">
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
        <h3 style="margin-bottom: 20px;" id="modalTitle">Tambah Kelas</h3>
        <form method="POST">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="formId">
            
            <div class="form-group">
                <label>Nama Kelas</label>
                <input type="text" name="nama_kelas" id="namaKelas" class="form-control form-control-simple" required placeholder="Contoh: X MLPB">
            </div>
            
            <div class="form-group">
                <label>Jurusan</label>
                <select name="jurusan" id="jurusan" class="form-control form-control-simple" required>
                    <option value="">-- Pilih --</option>
                    <option value="MLPB">MLPB</option>
                    <option value="BDP">BDP</option>
                    <option value="TKJ">TKJ</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Tingkat</label>
                <select name="tingkat" id="tingkat" class="form-control form-control-simple" required>
                    <option value="">-- Pilih --</option>
                    <option value="X">X</option>
                    <option value="XI">XI</option>
                    <option value="XII">XII</option>
                </select>
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
    document.getElementById('modalTitle').textContent = 'Tambah Kelas';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value = '';
    document.getElementById('namaKelas').value = '';
    document.getElementById('jurusan').value = '';
    document.getElementById('tingkat').value = '';
}

function hideModal() {
    document.getElementById('modal').style.display = 'none';
}

function editKelas(data) {
    document.getElementById('modal').style.display = 'flex';
    document.getElementById('modalTitle').textContent = 'Edit Kelas';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = data.id;
    document.getElementById('namaKelas').value = data.nama_kelas;
    document.getElementById('jurusan').value = data.jurusan;
    document.getElementById('tingkat').value = data.tingkat;
}

document.getElementById('modal').addEventListener('click', function(e) {
    if (e.target === this) hideModal();
});
</script>

<?php include '../includes/footer.php'; ?>
