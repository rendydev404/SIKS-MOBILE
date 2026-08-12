<?php
/**
 * Manajemen Pengumuman
 * Admin bisa membuat pengumuman untuk siswa
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/announcement_notifications.php';
checkLogin();
checkRole(['admin']);
ensureAnnouncementNotificationSchema($pdo);

$pageTitle = 'Pengumuman';

// Proses tambah/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? '';
    $judul = trim($_POST['judul'] ?? '');
    $isi = trim($_POST['isi'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    if (!empty($judul) && !empty($isi)) {
        try {
            $pdo->beginTransaction();
            $announcementId = null;
            if ($action === 'edit' && $id) {
                $previous = $pdo->prepare('SELECT is_active FROM pengumuman WHERE id = ? FOR UPDATE');
                $previous->execute([$id]);
                $oldAnnouncement = $previous->fetch();
                if (!$oldAnnouncement) throw new PDOException('Pengumuman tidak ditemukan');
                $stmt = $pdo->prepare("UPDATE pengumuman SET judul = ?, isi = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$judul, $isi, $isActive, $id]);
                $announcementId = (int) $id;
                $isNewlyPublished = !$oldAnnouncement['is_active'] && $isActive;
                $message = 'Pengumuman berhasil diperbarui!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO pengumuman (judul, isi, is_active, created_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$judul, $isi, $isActive, $_SESSION['user_id']]);
                $announcementId = (int) $pdo->lastInsertId();
                $isNewlyPublished = (bool) $isActive;
                $message = 'Pengumuman berhasil ditambahkan!';
            }
            if ($isNewlyPublished) {
                $queued = queueAnnouncementNotification($pdo, $announcementId);
                $message .= ' Notifikasi untuk ' . $queued . ' perangkat siswa telah masuk antrean.';
            }
            $pdo->commit();
            setAlert('success', $message);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            setAlert('error', 'Gagal menyimpan: ' . $e->getMessage());
        }
    } else {
        setAlert('error', 'Judul dan isi wajib diisi!');
    }
    header('Location: index.php');
    exit;
}

// Hapus
if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM pengumuman WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        setAlert('success', 'Pengumuman berhasil dihapus!');
    } catch (PDOException $e) {
        setAlert('error', 'Gagal menghapus!');
    }
    header('Location: index.php');
    exit;
}

// Get data
$pengumumanList = $pdo->query("SELECT p.*, u.nama_lengkap as author FROM pengumuman p LEFT JOIN users u ON p.created_by = u.id ORDER BY p.created_at DESC")->fetchAll();

include '../includes/header.php';
?>

<div class="toolbar">
    <button class="btn btn-success" onclick="showModal()">
        <i class="fas fa-plus"></i> Tambah Pengumuman
    </button>
</div>

<div class="alert alert-info">
    <i class="fas fa-info-circle"></i>
    Pengumuman akan ditampilkan di <strong>Portal Siswa</strong>. 
    <a href="<?= BASE_URL ?>siswa-portal/" target="_blank" style="color: white; text-decoration: underline;">Lihat Portal Siswa</a>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (empty($pengumumanList)): ?>
            <div class="empty-state">
                <i class="fas fa-bullhorn"></i>
                <h3>Belum ada pengumuman</h3>
                <p>Buat pengumuman untuk memberitahu siswa tentang batas waktu pembayaran</p>
            </div>
        <?php else: ?>
            <div class="announcement-list">
                <?php foreach ($pengumumanList as $p): ?>
                <div class="announcement-item" style="padding: 24px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; gap: 24px; align-items: flex-start; transition: all 0.3s ease;">
                    <div style="width: 52px; height: 52px; border-radius: 14px; background: <?= $p['is_active'] ? 'rgba(16, 185, 129, 0.1)' : 'rgba(245, 158, 11, 0.1)' ?>; display: flex; align-items: center; justify-content: center; color: <?= $p['is_active'] ? 'var(--success)' : 'var(--warning)' ?>; font-size: 22px; flex-shrink: 0; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    
                    <div style="flex: 1; min-width: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; flex-wrap: wrap; gap: 12px;">
                            <div>
                                <h4 style="margin: 0 0 6px 0; font-size: 17px; color: var(--text-primary); font-weight: 600; letter-spacing: 0.2px;"><?= e($p['judul']) ?></h4>
                                <div style="font-size: 12px; color: var(--text-muted); display: flex; align-items: center; gap: 15px;">
                                    <span style="display: flex; align-items: center; gap: 6px;"><i class="far fa-calendar-alt"></i> <?= formatTanggal($p['created_at'], 'd M Y') ?></span>
                                    <span style="display: flex; align-items: center; gap: 6px;"><i class="far fa-user"></i> <?= e($p['author'] ?? 'Administrator') ?></span>
                                </div>
                            </div>
                            <div>
                                <?php if ($p['is_active']): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(16, 185, 129, 0.15); color: var(--success); padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; border: 1px solid rgba(16, 185, 129, 0.2);">
                                        <div style="width: 6px; height: 6px; border-radius: 50%; background: var(--success);"></div> Aktif
                                    </span>
                                <?php else: ?>
                                    <span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(245, 158, 11, 0.15); color: var(--warning); padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; border: 1px solid rgba(245, 158, 11, 0.2);">
                                        <div style="width: 6px; height: 6px; border-radius: 50%; background: var(--warning);"></div> Non-Aktif
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <p style="margin: 0 0 20px 0; font-size: 14px; color: var(--text-secondary); line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?= e($p['isi']) ?></p>
                        
                        <div class="action-buttons" style="display: flex; gap: 10px;">
                            <button class="btn btn-sm" onclick="editPengumuman(<?= htmlspecialchars(json_encode($p)) ?>)" style="background: rgba(99, 102, 241, 0.1); color: var(--primary-light); border: 1px solid rgba(99, 102, 241, 0.2); box-shadow: none; display: flex; align-items: center; gap: 6px;">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <a href="index.php?delete=<?= $p['id'] ?>" class="btn btn-sm btn-delete" style="background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); box-shadow: none; display: flex; align-items: center; gap: 6px;">
                                <i class="fas fa-trash"></i> Hapus
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <style>
                .announcement-item:hover {
                    background: rgba(255, 255, 255, 0.03);
                }
                .announcement-item:hover h4 {
                    color: var(--primary-light) !important;
                }
                .announcement-item:last-child {
                    border-bottom: none !important;
                }
            </style>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Form -->
<div id="modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
    <div style="background: var(--bg-card); border-radius: var(--radius-lg); padding: 24px; width: 100%; max-width: 550px; margin: auto; border: 1px solid var(--border-color);">
        <h3 style="margin-bottom: 20px;" id="modalTitle">Tambah Pengumuman</h3>
        <form method="POST">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="formId">
            
            <div class="form-group">
                <label>Judul Pengumuman</label>
                <input type="text" name="judul" id="judul" class="form-control form-control-simple" required placeholder="Contoh: Batas Waktu Pembayaran SPP">
            </div>
            
            <div class="form-group">
                <label>Isi Pengumuman</label>
                <textarea name="isi" id="isi" class="form-control form-control-simple" rows="5" required placeholder="Contoh: Pembayaran SPP bulan Desember paling lambat tanggal 10. Terima kasih."></textarea>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="is_active" id="isActive" value="1" checked>
                    <span>Aktif (tampilkan di Portal Siswa)</span>
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
    document.getElementById('modalTitle').textContent = 'Tambah Pengumuman';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value = '';
    document.getElementById('judul').value = '';
    document.getElementById('isi').value = '';
    document.getElementById('isActive').checked = true;
}

function hideModal() {
    document.getElementById('modal').style.display = 'none';
}

function editPengumuman(data) {
    document.getElementById('modal').style.display = 'flex';
    document.getElementById('modalTitle').textContent = 'Edit Pengumuman';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = data.id;
    document.getElementById('judul').value = data.judul;
    document.getElementById('isi').value = data.isi;
    document.getElementById('isActive').checked = data.is_active == 1;
}

document.getElementById('modal').addEventListener('click', function(e) {
    if (e.target === this) hideModal();
});
</script>

<?php include '../includes/footer.php'; ?>
