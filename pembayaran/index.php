<?php
/**
 * Daftar Pembayaran SPP, Infak, Komputer
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();

$pageTitle = 'Riwayat Transaksi';

// Filter (Jenis & Kelas tetap ada sebagai filter global)
$filterJenis = $_GET['jenis'] ?? '';
$filterKelas = $_GET['kelas_id'] ?? '';
$filterStatus = $_GET['status'] ?? '';

// Get daftar kelas untuk filter
$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();

// Query pembayaran - Kita ambil semua lalu kelompokkan di PHP untuk Tab
$sql = "SELECT p.*, s.nama as nama_siswa, s.nis, s.tahun_masuk, k.nama_kelas, u.nama_lengkap as petugas, v.nama_lengkap as verifikator
        FROM pembayaran p
        JOIN siswa s ON p.siswa_id = s.id
        LEFT JOIN kelas k ON s.kelas_id = k.id
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN users v ON p.verifikasi_admin_id = v.id
        WHERE 1=1";
$params = [];

if ($filterKelas) {
    $sql .= " AND s.kelas_id = ?";
    $params[] = $filterKelas;
}

if ($filterJenis) {
    $sql .= " AND p.jenis_pembayaran = ?";
    $params[] = $filterJenis;
}

if ($filterStatus) {
    $sql .= " AND p.status = ?";
    $params[] = $filterStatus;
}

$sql .= " ORDER BY p.tahun DESC, FIELD(p.bulan, 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember') ASC, p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pembayaranList = $stmt->fetchAll();

// Kelompokkan pembayaran per tahun dan per bulan
$grouped = [];
foreach ($pembayaranList as $row) {
    $tahun = $row['tahun'];
    $bulan = $row['bulan'];
    $grouped[$tahun][$bulan][] = $row;
}
// Tambahkan tab untuk tahun ini & tahun depan meskipun belum ada datanya (misal: 2026, 2027)
$curYear = (int)date('Y');
$nextYear = $curYear + 1;
if (!isset($grouped[$curYear])) $grouped[$curYear] = [];
if (!isset($grouped[$nextYear])) $grouped[$nextYear] = [];
krsort($grouped); // Urutkan dari tahun terbesar (terbaru) turun ke yang terlama

// Tentukan Tab Aktif
$tahunKeys = array_keys($grouped);
$activeTahun = $_GET['tab_tahun'] ?? ($tahunKeys[0] ?? '');
if (!isset($grouped[$activeTahun])) $activeTahun = $tahunKeys[0] ?? '';

$bulanKeys = $activeTahun ? array_keys($grouped[$activeTahun]) : [];
$activeBulan = $_GET['tab_bulan'] ?? ($bulanKeys[0] ?? '');
if (!in_array($activeBulan, $bulanKeys)) $activeBulan = $bulanKeys[0] ?? '';

$stmtJenis = $pdo->query("SELECT jenis FROM setting_pembayaran ORDER BY (jenis = 'SPP') DESC, jenis ASC");
$jenisList = $stmtJenis->fetchAll(PDO::FETCH_COLUMN);

$bulanList = getBulanIndonesia();

include '../includes/header.php';
?>

<style>
/* ===== TOOLBAR ===== */
.riwayat-toolbar {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}
.riwayat-toolbar select {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    padding: 7px 12px;
    border-radius: 8px;
    font-size: 0.875rem;
    cursor: pointer;
}
.riwayat-toolbar .spacer { flex: 1; }

/* ===== TAB TAHUN ===== */
.tab-tahun-wrap {
    display: flex;
    gap: 8px;
    flex-wrap: nowrap;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    margin-bottom: 0;
    padding: 16px 16px 0;
    border-bottom: 2px solid var(--border-color);
}
.tab-tahun-wrap::-webkit-scrollbar {
    display: none;
}
.tab-tahun-btn {
    padding: 8px 22px;
    border-radius: 8px 8px 0 0;
    border: 2px solid transparent;
    border-bottom: none;
    background: transparent;
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 7px;
    position: relative;
    bottom: -2px;
    white-space: nowrap;
    flex-shrink: 0;
}
.tab-tahun-btn:hover {
    color: var(--primary-light);
    background: rgba(99,102,241,0.08);
}
.tab-tahun-btn.active {
    color: var(--primary-light);
    border-color: var(--primary-light);
    background: var(--bg-card);
    border-bottom: 2px solid var(--bg-card);
}

/* ===== TAB BULAN ===== */
.tab-bulan-wrap {
    display: flex;
    gap: 6px;
    flex-wrap: nowrap;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    padding: 14px 16px;
    background: rgba(99,102,241,0.04);
    border-bottom: 1px solid var(--border-color);
}
.tab-bulan-wrap::-webkit-scrollbar {
    display: none;
}
.tab-bulan-btn {
    padding: 5px 16px;
    border-radius: 999px;
    border: 1px solid var(--border-color);
    background: transparent;
    color: var(--text-secondary);
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.18s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
    flex-shrink: 0;
}
.tab-bulan-btn:hover {
    border-color: var(--primary-light);
    color: var(--primary-light);
}
.tab-bulan-btn.active {
    background: var(--primary-light);
    color: #fff;
    border-color: var(--primary-light);
}
.tab-bulan-btn .cnt {
    background: rgba(255,255,255,0.25);
    padding: 1px 6px;
    border-radius: 999px;
    font-size: 0.75rem;
}
.tab-bulan-btn:not(.active) .cnt {
    background: rgba(99,102,241,0.12);
    color: var(--primary-light);
}
</style>

<!-- TOOLBAR -->
<div class="riwayat-toolbar">
    <form method="GET" id="filterForm" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <?php if ($activeTahun): ?>
        <input type="hidden" name="tab_tahun" value="<?= e($activeTahun) ?>">
        <input type="hidden" name="tab_bulan" value="<?= e($activeBulan) ?>">
        <?php endif; ?>
        <?php if ($filterStatus): ?>
        <input type="hidden" name="status" value="<?= e($filterStatus) ?>">
        <?php endif; ?>
        <select name="jenis" onchange="this.form.submit()">
            <option value="">Semua Jenis</option>
            <?php foreach ($jenisList as $jenis): ?>
                <option value="<?= e($jenis) ?>" <?= $filterJenis == $jenis ? 'selected' : '' ?>><?= e($jenis) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="kelas_id" onchange="this.form.submit()">
            <option value="">Semua Kelas</option>
            <?php foreach ($kelasList as $kelas): ?>
                <option value="<?= $kelas['id'] ?>" <?= $filterKelas == $kelas['id'] ? 'selected' : '' ?>><?= e($kelas['nama_kelas']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <div class="spacer"></div>
    <a href="import.php" class="btn btn-secondary"><i class="fas fa-file-import"></i> Upload Data</a>
    <?php
    $exportUrl = 'export.php?jenis=' . urlencode($filterJenis) . '&kelas_id=' . urlencode($filterKelas) . '&status=' . urlencode($filterStatus);
    if ($activeBulan) $exportUrl .= '&bulan=' . urlencode($activeBulan);
    if ($activeTahun) $exportUrl .= '&tahun=' . urlencode($activeTahun);
    ?>
    <a href="<?= $exportUrl ?>" class="btn btn-primary" style="background:var(--success);border-color:var(--success);"><i class="fas fa-file-excel"></i> Export Excel</a>
    <a href="tambah.php" class="btn btn-success"><i class="fas fa-plus"></i> Input Pembayaran</a>
</div>

<?php if (empty($grouped)): ?>
<div class="card">
    <div class="card-body">
        <div class="empty-state">
            <i class="fas fa-money-bill-wave"></i>
            <h3>Belum ada pembayaran</h3>
            <p>Belum ada transaksi pembayaran yang tercatat</p>
            <a href="tambah.php" class="btn btn-primary">Input Pembayaran</a>
        </div>
    </div>
</div>
<?php else: ?>

<div class="card" style="overflow:visible;">
    <!-- Tab Tahun -->
    <div class="tab-tahun-wrap">
        <?php foreach ($tahunKeys as $thn): ?>
        <a href="?tab_tahun=<?= $thn ?>&jenis=<?= urlencode($filterJenis) ?>&kelas_id=<?= urlencode($filterKelas) ?>&status=<?= urlencode($filterStatus) ?>"
           class="tab-tahun-btn <?= $thn == $activeTahun ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> Tahun <?= $thn ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Tab Bulan -->
    <?php if ($activeTahun && !empty($grouped[$activeTahun])): ?>
    <div class="tab-bulan-wrap">
        <?php foreach ($grouped[$activeTahun] as $bln => $bRows): ?>
        <a href="?tab_tahun=<?= $activeTahun ?>&tab_bulan=<?= urlencode($bln) ?>&jenis=<?= urlencode($filterJenis) ?>&kelas_id=<?= urlencode($filterKelas) ?>&status=<?= urlencode($filterStatus) ?>"
           class="tab-bulan-btn <?= $bln == $activeBulan ? 'active' : '' ?>">
            <?= $bln ?>
            <span class="cnt"><?= count($bRows) ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Tabel data bulan aktif -->
    <div class="card-body" style="padding:0;">
    <?php
    $activeRows = $grouped[$activeTahun][$activeBulan] ?? [];
    if (empty($activeRows)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>Tidak ada data</h3>
            <p>Belum ada pembayaran pada periode ini</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Tahun</th>
                    <th>Tanggal</th>
                    <th>NIS</th>
                    <th>Nama Siswa</th>
                    <th>Kelas</th>
                    <th>Jenis</th>
                    <th>Jumlah</th>
                    <th>Metode</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activeRows as $row): ?>
                <tr>
                    <td><?= e($row['tahun']) ?></td>
                    <td><?= formatTanggal($row['tanggal_bayar'], 'd M Y') ?></td>
                    <td><?= e($row['nis']) ?></td>
                    <td><?= e($row['nama_siswa']) ?></td>
                    <td>
                        <?php 
                        $tingkatStr = '';
                        if (!empty($row['bulan']) && !empty($row['tahun'])) {
                            // Coba dapatkan tahun masuk dari DB (query index.php tidak join tahun_masuk saat ini, kita perlu fetch atau hitung manual jika bisa, tapi querynya cuma SELECT p.*, s.nama, s.nis, k.nama_kelas)
                            // Jika kita butuh tahun_masuk, kita ubah querynya atau ambil dari $row kalau sudah ada.
                            // Kita ubah query di line 22 dulu.
                        }
                        ?>
                        <?= e($row['nama_kelas'] ?? '-') ?>
                        <?php if (isset($row['tahun_masuk']) && $row['tahun_masuk'] > 0): ?>
                            <?php
                            $bIdx = array_search($row['bulan'], getBulanIndonesia());
                            $isJanJun = ($bIdx !== false && $bIdx < 6);
                            $acadYearStart = $isJanJun ? ((int)$row['tahun'] - 1) : (int)$row['tahun'];
                            $diff = $acadYearStart - (int)$row['tahun_masuk'];
                            
                            $histKelas = '';
                            if ($diff == 0) $histKelas = 'Kls 10';
                            elseif ($diff == 1) $histKelas = 'Kls 11';
                            elseif ($diff == 2) $histKelas = 'Kls 12';
                            elseif ($diff > 2) $histKelas = 'Alumni';
                            
                            if ($histKelas) {
                                echo '<br><small class="text-muted">(Riwayat: ' . $histKelas . ')</small>';
                            }
                            ?>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge" style="background:rgba(99,102,241,0.1);color:var(--primary-light);"><?= e($row['jenis_pembayaran']) ?></span></td>
                    <td class="text-success"><?= formatRupiah($row['jumlah_bayar']) ?></td>
                    <td><?= $row['metode_bayar'] ?></td>
                    <td>
                        <?php if (($row['status'] ?? 'lunas') == 'pending'): ?>
                            <span class="badge" style="background:var(--warning);color:#000;">Menunggu</span>
                        <?php elseif (($row['status'] ?? 'lunas') == 'ditolak'): ?>
                            <span class="badge" style="background:var(--danger);">Ditolak</span>
                        <?php else: ?>
                            <span class="badge badge-success">Lunas</span>
                            <?php if ($row['verifikator']): ?>
                                <div style="font-size: 10px; color: var(--text-muted); margin-top: 4px;">
                                    Verif: <?= e($row['verifikator']) ?><br>
                                    <?= formatTanggal($row['tanggal_verifikasi'], 'd/m/y H:i') ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <?php if (($row['status'] ?? 'lunas') == 'pending'): ?>
                                <a href="update_status.php?id=<?= $row['id'] ?>&status=lunas&redirect=index" class="btn btn-success btn-sm" title="Setujui" onclick="return confirm('Verifikasi pembayaran ini sebagai LUNAS?')"><i class="fas fa-check"></i></a>
                                <button class="btn btn-danger btn-sm btn-reject" data-id="<?= $row['id'] ?>" title="Tolak"><i class="fas fa-times"></i></button>
                            <?php else: ?>
                                <a href="verifikasi-wa.php?id=<?= $row['id'] ?>&redirect=index" class="btn btn-success btn-sm" title="Kirim WA (Gambar Kwitansi)" style="background: #25d366; border-color: #25d366;"><i class="fab fa-whatsapp"></i></a>
                            <?php endif; ?>
                            <a href="cetak.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm" title="Cetak" target="_blank"><i class="fas fa-print"></i></a>
                            <a href="detail.php?siswa_id=<?= $row['siswa_id'] ?>" class="btn btn-secondary btn-sm" title="Detail"><i class="fas fa-eye"></i></a>
                            <?php $bukti = getBuktiTransfer($row['keterangan']); if ($bukti): ?>
                            <button class="btn btn-info btn-sm btn-view-bukti" data-image="<?= BASE_URL ?>uploads/bukti/<?= $bukti ?>" title="Lihat Bukti"><i class="fas fa-image"></i></button>
                            <?php endif; ?>
                            <a href="hapus.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-sm" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus data pembayaran ini?')"><i class="fas fa-trash"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    </div><!-- end card-body -->
</div><!-- end card -->
<?php endif; ?>

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
    
    // Close modal proof
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
        if (event.target == rejectModal) {
            rejectModal.style.display = "none";
        }
    }

    // Modal Reject
    const rejectModal = document.getElementById('rejectModal');
    const closeReject = document.getElementById('closeReject');
    const rejectForm = document.getElementById('rejectForm');
    const rejectIdInput = document.getElementById('rejectId');

    document.querySelectorAll('.btn-reject').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            rejectIdInput.value = this.getAttribute('data-id');
            rejectModal.style.display = "block";
        });
    });

    if(closeReject) {
        closeReject.onclick = function() {
            rejectModal.style.display = "none";
        }
    }
});
</script>

<!-- Modal Reject -->
<div id="rejectModal" class="modal-custom">
    <div class="modal-content-custom" style="max-width: 400px;">
        <div class="modal-header-custom">
            <h3>Tolak Pembayaran</h3>
            <span class="close-modal" id="closeReject">&times;</span>
        </div>
        <div class="modal-body-custom">
            <form id="rejectForm" action="update_status.php" method="GET">
                <input type="hidden" name="id" id="rejectId">
                <input type="hidden" name="status" value="ditolak">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display:block; margin-bottom:8px;">Alasan Penolakan:</label>
                    <textarea name="admin_note" class="form-control" rows="3" required placeholder="Contoh: Bukti transfer tidak jelas / salah upload"></textarea>
                </div>
                <div style="text-align: right;">
                    <button type="submit" class="btn btn-danger">Tolak Pembayaran</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
