<?php
/**
 * Detail Pembayaran Siswa
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();

$siswaId = $_GET['siswa_id'] ?? 0;

// Get data siswa
$stmt = $pdo->prepare("SELECT s.*, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
$stmt->execute([$siswaId]);
$siswa = $stmt->fetch();

if (!$siswa) {
    setAlert('error', 'Data siswa tidak ditemukan!');
    header('Location: index.php');
    exit;
}

$pageTitle = 'Detail Pembayaran: ' . $siswa['nama'];

// Get riwayat pembayaran
$stmtPembayaran = $pdo->prepare("SELECT * FROM pembayaran WHERE siswa_id = ? ORDER BY tahun DESC, FIELD(bulan, 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember')");
$stmtPembayaran->execute([$siswaId]);
$pembayaranList = $stmtPembayaran->fetchAll();

// Get SPP aktif
$spp = getSppAktif($pdo);
$nominal = $spp ? $spp['nominal'] : 0;

$bulanList = getBulanIndonesia();
$tahunReal = date('Y');
$tahunFilter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : $tahunReal;

// Cek status pembayaran per bulan
$statusBayar = [];
foreach ($pembayaranList as $p) {
    $statusBayar[$p['bulan'] . '_' . $p['tahun']] = $p;
}

include '../includes/header.php';
?>

<div class="card mb-3">
    <div class="card-header">
        <h2 class="card-title">Informasi Siswa</h2>
    </div>
    <div class="card-body">
        <div class="form-row">
            <div>
                <p><strong>NIS:</strong> <?= e($siswa['nis']) ?></p>
                <p><strong>Nama:</strong> <?= e($siswa['nama']) ?></p>
            </div>
            <div>
                <p><strong>Kelas:</strong> <?= e($siswa['nama_kelas'] ?? '-') ?></p>
                <p><strong>Status:</strong> <span class="badge badge-<?= $siswa['status'] == 'aktif' ? 'success' : 'warning' ?>"><?= ucfirst($siswa['status']) ?></span></p>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <h2 class="card-title" style="margin:0;">Status Pembayaran Tahun <?= $tahunFilter ?></h2>
        <form method="GET" style="display: flex; gap: 8px;">
            <input type="hidden" name="siswa_id" value="<?= $siswaId ?>">
            <select name="tahun" class="form-control form-control-simple" style="width: auto;" onchange="this.form.submit()">
                <?php for($y = 2024; $y <= 2028; $y++): ?>
                    <option value="<?= $y ?>" <?= $y == $tahunFilter ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <?php foreach ($bulanList as $bulan): ?>
                            <th class="text-center"><?= substr($bulan, 0, 3) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php foreach ($bulanList as $bulan): 
                            $key = $bulan . '_' . $tahunFilter;
                            $pembayaran = $statusBayar[$key] ?? null;
                            $sudahBayar = $pembayaran !== null;
                        ?>
                            <td class="text-center">
                                <?php if ($sudahBayar): ?>
                                    <span class="payment-status lunas" title="<?= formatRupiah($pembayaran['jumlah_bayar']) ?> (<?= formatTanggal($pembayaran['tanggal_bayar'], 'd/m/y') ?>)">
                                        <i class="fas fa-check"></i>
                                    </span>
                                <?php else: ?>
                                    <?php 
                                    $isFuture = ($tahunFilter > $tahunReal) || ($tahunFilter == $tahunReal && (array_search($bulan, $bulanList) + 1) > (int)date('n'));
                                    ?>
                                    <span class="payment-status <?= $isFuture ? 'nanti' : 'belum' ?>">
                                        <i class="fas <?= $isFuture ? 'fa-clock' : 'fa-times' ?>"></i>
                                    </span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.payment-status {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}
.payment-status.lunas { background: rgba(16, 185, 129, 0.1); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2); }
.payment-status.belum { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); }
.payment-status.nanti { background: rgba(255, 255, 255, 0.05); color: var(--text-muted); border: 1px solid var(--border-color); }
</style>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Riwayat Pembayaran</h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($pembayaranList)): ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <h3>Belum ada pembayaran</h3>
                <p>Siswa ini belum memiliki riwayat pembayaran</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Bulan/Tahun</th>
                            <th>Jumlah</th>
                            <th>Metode</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pembayaranList as $row): ?>
                        <tr>
                            <td><?= formatTanggal($row['tanggal_bayar'], 'd M Y') ?></td>
                            <td>
                                <?php 
                                $tingkatStr = '';
                                if (!empty($siswa['tahun_masuk']) && !empty($row['tahun'])) {
                                    $bIdx = array_search($row['bulan'], getBulanIndonesia());
                                    $isJanJun = ($bIdx !== false && $bIdx < 6);
                                    $acadYearStart = $isJanJun ? ((int)$row['tahun'] - 1) : (int)$row['tahun'];
                                    $diff = $acadYearStart - (int)$siswa['tahun_masuk'];
                                    
                                    if ($diff == 0) $tingkatStr = ' <small class="text-muted">(Kelas 10)</small>';
                                    elseif ($diff == 1) $tingkatStr = ' <small class="text-muted">(Kelas 11)</small>';
                                    elseif ($diff == 2) $tingkatStr = ' <small class="text-muted">(Kelas 12)</small>';
                                    elseif ($diff > 2) $tingkatStr = ' <small class="text-muted">(Alumni)</small>';
                                }
                                ?>
                                <?= $row['bulan'] ?> <?= $row['tahun'] ?><?= $tingkatStr ?>
                            </td>
                            <td class="text-success"><?= formatRupiah($row['jumlah_bayar']) ?></td>
                            <td><?= $row['metode_bayar'] ?></td>
                            <td>
                                <a href="cetak.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm" target="_blank">
                                    <i class="fas fa-print"></i> Cetak
                                </a>
                                <?php 
                                $bukti = getBuktiTransfer($row['keterangan']);
                                if ($bukti): 
                                ?>
                                <button class="btn btn-info btn-sm btn-view-bukti" data-image="<?= BASE_URL ?>uploads/bukti/<?= $bukti ?>" title="Lihat Bukti">
                                    <i class="fas fa-image"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-3" style="display: flex; gap: 10px; flex-wrap: wrap;">
    <a href="index.php" class="btn btn-secondary" style="flex: 1; text-align: center; min-width: 120px;">
        <i class="fas fa-arrow-left"></i> Kembali
    </a>
    <a href="tambah.php?siswa_id=<?= $siswaId ?>" class="btn btn-success" style="flex: 2; text-align: center; min-width: 180px;">
        <i class="fas fa-plus"></i> Input Pembayaran
    </a>
</div>

<!-- Modal Bukti Transfer -->
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

<style>
.modal-custom {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.8);
    backdrop-filter: blur(5px);
}
.modal-content-custom {
    background-color: var(--bg-card);
    margin: 5% auto;
    padding: 20px;
    border: 1px solid var(--border-color);
    width: 80%;
    max-width: 600px;
    border-radius: 16px;
    position: relative;
}
.modal-header-custom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border-color);
}
.close-modal {
    color: var(--text-secondary);
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}
.close-modal:hover {
    color: #fff;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('buktiModal');
    const modalImg = document.getElementById('buktiImage');
    const closeBtn = document.querySelector('.close-modal');
    
    document.querySelectorAll('.btn-view-bukti').forEach(btn => {
        btn.addEventListener('click', function() {
            modal.style.display = "block";
            modalImg.src = this.getAttribute('data-image');
        });
    });
    
    closeBtn.onclick = function() {
        modal.style.display = "none";
    }
    
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
