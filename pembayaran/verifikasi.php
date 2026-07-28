<?php
/**
 * Verifikasi Pembayaran Pending
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();

$pageTitle = 'Verifikasi Pembayaran';

// Query pembayaran pending
$sql = "SELECT p.*, s.nama as nama_siswa, s.nis, k.nama_kelas
        FROM pembayaran p
        JOIN siswa s ON p.siswa_id = s.id
        LEFT JOIN kelas k ON s.kelas_id = k.id
        WHERE p.status = 'pending'
        ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$pendingList = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2 class="card-title"><i class="fas fa-check-shield" style="color: var(--warning);"></i> Menunggu Verifikasi</h2>
        <span class="badge" style="background: var(--warning); color: #000; font-size: 1rem; padding: 5px 15px;">
            <?= count($pendingList) ?> Transaksi
        </span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($pendingList)): ?>
            <div class="empty-state" style="padding: 40px;">
                <i class="fas fa-check-circle" style="color: var(--success); font-size: 48px; opacity: 0.5;"></i>
                <h3>Semua Terverifikasi</h3>
                <p>Tidak ada pembayaran yang menunggu verifikasi saat ini.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tanggal Masuk</th>
                            <th>Siswa / Kelas</th>
                            <th>Jenis Pembayaran</th>
                            <th>Jumlah</th>
                            <th>Metode</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingList as $row): ?>
                        <tr>
                            <td>
                                <?= formatTanggal($row['created_at'], 'd M Y') ?><br>
                                <small style="color: var(--text-muted);"><?= formatTanggal($row['created_at'], 'H:i') ?> WIB</small>
                            </td>
                            <td>
                                <strong><?= e($row['nama_siswa']) ?></strong><br>
                                <small><?= e($row['nis']) ?> - <?= e($row['nama_kelas'] ?? '-') ?></small>
                            </td>
                            <td>
                                <span class="badge" style="background: rgba(99,102,241,0.1); color: var(--primary-light);">
                                    <?= e($row['jenis_pembayaran']) ?>
                                </span><br>
                                <small><?= $row['bulan'] ?> <?= $row['tahun'] ?></small>
                            </td>
                            <td class="text-success" style="font-weight: 700;">
                                <?= formatRupiah($row['jumlah_bayar']) ?>
                            </td>
                            <td>
                                <span class="badge" style="background: <?= ($row['metode_bayar'] == 'Transfer' ? 'var(--info)' : 'var(--success)') ?>;">
                                    <?= $row['metode_bayar'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php $bukti = getBuktiTransfer($row['keterangan']); if ($bukti): ?>
                                        <button class="btn btn-info btn-sm btn-view-bukti" data-image="<?= BASE_URL ?>uploads/bukti/<?= $bukti ?>" title="Lihat Bukti">
                                            <i class="fas fa-image"></i> Bukti
                                        </button>
                                    <?php endif; ?>
                                    
                                    <a href="update_status.php?id=<?= $row['id'] ?>&status=lunas" class="btn btn-success btn-sm" 
                                       onclick="return confirm('Sahkan pembayaran <?= formatRupiah($row['jumlah_bayar']) ?> sebagai Lunas?')">
                                        <i class="fas fa-check"></i> Setujui
                                    </a>
                                    
                                    <button class="btn btn-danger btn-sm btn-reject" data-id="<?= $row['id'] ?>" title="Tolak">
                                        <i class="fas fa-times"></i> Rejection
                                    </button>
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

<!-- Modal Bukti (Reusing existing JS from index.php if possible, but defining local for safety) -->
<div id="buktiModal" class="modal-custom">
    <div class="modal-content-custom">
        <div class="modal-header-custom">
            <h3>Bukti Pembayaran</h3>
            <span class="close-modal">&times;</span>
        </div>
        <div class="modal-body-custom">
            <img id="buktiImage" src="" alt="Bukti Pembayaran" style="width: 100%; border-radius: 8px;">
        </div>
    </div>
</div>

<!-- Modal Reject -->
<div id="rejectModal" class="modal-custom">
    <div class="modal-content-custom" style="max-width: 400px;">
        <div class="modal-header-custom">
            <h3>Tolak Pembayaran</h3>
            <span class="close-modal" id="closeReject">&times;</span>
        </div>
        <div class="modal-body-custom">
            <form action="update_status.php" method="GET">
                <input type="hidden" name="id" id="rejectId">
                <input type="hidden" name="status" value="ditolak">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display:block; margin-bottom:8px;">Alasan Penolakan:</label>
                    <textarea name="admin_note" class="form-control" rows="3" required placeholder="Contoh: Bukti tidak terbaca / Nominal tidak sesuai"></textarea>
                </div>
                <div style="text-align: right;">
                    <button type="submit" class="btn btn-danger">Tolak & Beritahu Siswa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.modal-custom { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); }
.modal-content-custom { background: var(--bg-card); margin: 5% auto; padding: 20px; border: 1px solid var(--border-color); width: 80%; max-width: 600px; border-radius: 16px; position: relative; }
.modal-header-custom { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color); }
.close-modal { color: var(--text-secondary); font-size: 28px; font-weight: bold; cursor: pointer; }
.close-modal:hover { color: #fff; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Bukti Modal
    const modal = document.getElementById('buktiModal');
    const modalImg = document.getElementById('buktiImage');
    const closeBtn = document.querySelector('.close-modal');
    
    document.querySelectorAll('.btn-view-bukti').forEach(btn => {
        btn.addEventListener('click', function() {
            modal.style.display = "block";
            modalImg.src = this.getAttribute('data-image');
        });
    });
    
    closeBtn.onclick = function() { modal.style.display = "none"; }

    // Reject Modal
    const rejectModal = document.getElementById('rejectModal');
    const closeReject = document.getElementById('closeReject');
    const rejectIdInput = document.getElementById('rejectId');

    document.querySelectorAll('.btn-reject').forEach(btn => {
        btn.addEventListener('click', function() {
            rejectIdInput.value = this.getAttribute('data-id');
            rejectModal.style.display = "block";
        });
    });

    closeReject.onclick = function() { rejectModal.style.display = "none"; }
    
    window.onclick = function(event) {
        if (event.target == modal) modal.style.display = "none";
        if (event.target == rejectModal) rejectModal.style.display = "none";
    }
});
</script>

<?php include '../includes/footer.php'; ?>
