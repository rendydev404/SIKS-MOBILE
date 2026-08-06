<?php
/**
 * Daftar Siswa
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();

$pageTitle = 'Data Siswa';

// Filter
$filterKelas = $_GET['kelas'] ?? '';
$filterStatus = $_GET['status'] ?? 'aktif';

// Query siswa
$sql = "SELECT s.*, k.nama_kelas, k.jurusan 
        FROM siswa s 
        LEFT JOIN kelas k ON s.kelas_id = k.id 
        WHERE 1=1";
$params = [];

if ($filterKelas) {
    $sql .= " AND s.kelas_id = ?";
    $params[] = $filterKelas;
}

if ($filterStatus) {
    $sql .= " AND s.status = ?";
    $params[] = $filterStatus;
}

$sql .= " ORDER BY k.tingkat, k.nama_kelas, s.nama";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$siswaList = $stmt->fetchAll();

// Get daftar kelas untuk filter
$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();

include '../includes/header.php';
?>

<div class="toolbar">
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Cari nama atau NIS...">
    </div>
    <div class="filter-group">
        <form method="GET" style="display: flex; gap: 12px;">
            <select name="kelas" onchange="this.form.submit()">
                <option value="">Semua Kelas</option>
                <?php foreach ($kelasList as $kelas): ?>
                    <option value="<?= $kelas['id'] ?>" <?= $filterKelas == $kelas['id'] ? 'selected' : '' ?>>
                        <?= e($kelas['nama_kelas']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="status" onchange="this.form.submit()">
                <option value="aktif" <?= $filterStatus == 'aktif' ? 'selected' : '' ?>>Aktif</option>
                <option value="lulus" <?= $filterStatus == 'lulus' ? 'selected' : '' ?>>Lulus</option>
                <option value="pindah" <?= $filterStatus == 'pindah' ? 'selected' : '' ?>>Pindah</option>
                <option value="" <?= $filterStatus == '' ? 'selected' : '' ?>>Semua Status</option>
            </select>
        </form>
    </div>
    <?php if (isAdmin()): ?>
    <a href="tambah.php" class="btn btn-success">
        <i class="fas fa-plus"></i> Tambah Siswa
    </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (empty($siswaList)): ?>
            <div class="empty-state">
                <i class="fas fa-user-graduate"></i>
                <h3>Tidak ada data siswa</h3>
                <p>Belum ada data siswa yang terdaftar</p>
                <?php if (isAdmin()): ?>
                <a href="tambah.php" class="btn btn-primary">Tambah Siswa</a>
                <?php endif; ?>
            </div>
        <?php else: 
            // Group students by grade
            $groupedSiswa = [];
            foreach ($siswaList as $s) {
                $t = $s['tingkat'] ?? 'Lainnya';
                $groupedSiswa[$t][] = $s;
            }
            
            $icons = [
                10 => 'fa-book',
                11 => 'fa-award',
                12 => 'fa-user-graduate'
            ];
            
            foreach ($groupedSiswa as $tingkat => $list): 
                $icon = $icons[$tingkat] ?? 'fa-users';
            ?>
                <div class="grade-section" style="margin-bottom: 30px;">
                    <div class="section-header" style="padding: 15px 20px; background: rgba(var(--primary-rgb), 0.05); border-left: 4px solid var(--primary); display: flex; align-items: center; gap: 15px; margin-bottom: 0; border-radius: 4px 4px 0 0;">
                        <div class="grade-icon" style="width: 40px; height: 40px; border-radius: 10px; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                            <i class="fas <?= $icon ?>"></i>
                        </div>
                        <div>
                            <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: var(--text-dark);">Kelas <?= $tingkat ?></h3>
                            <p style="margin: 0; font-size: 13px; color: var(--text-muted);"><?= count($list) ?> Siswa Terdaftar</p>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th width="50">No</th>
                                    <th width="100">NIS</th>
                                    <th>Nama</th>
                                    <th width="50">L/P</th>
                                    <th>Kelas/Jurusan</th>
                                    <th width="100">Status</th>
                                    <?php if (isAdmin()): ?>
                                    <th width="120">Aksi</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($list as $i => $siswa): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td class="font-medium"><?= e($siswa['nis']) ?></td>
                                    <td class="font-semibold text-dark"><?= e($siswa['nama']) ?></td>
                                    <td><span class="badge badge-light"><?= $siswa['jenis_kelamin'] ?></span></td>
                                    <td>
                                        <div style="font-weight: 500;"><?= e($siswa['nama_kelas'] ?? '-') ?></div>
                                        <div style="font-size: 11px; opacity: 0.7; color: var(--text-muted);"><?= e($siswa['jurusan'] ?? '') ?></div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= $siswa['status'] == 'aktif' ? 'status-active' : 'status-pending' ?>">
                                            <?= ucfirst($siswa['status']) ?>
                                        </span>
                                        <?php if ($siswa['status'] == 'lulus'): 
                                            // Hitung tunggakan dengan detail
                                            $dataTunggakan = hitungTunggakan($pdo, $siswa['id'], true);
                                            $tunggakanTotal = $dataTunggakan['total'];
                                            if ($tunggakanTotal > 0):
                                        ?>
                                            <div style="margin-top: 8px; text-align: left; background: rgba(239, 68, 68, 0.1); border-left: 3px solid #ef4444; padding: 6px 10px; border-radius: 4px; min-width: 150px;">
                                                <div style="font-size: 11px; font-weight: 700; color: #b91c1c; margin-bottom: 4px;">
                                                    <i class="fas fa-exclamation-triangle"></i> Tunggakan: <?= formatRupiah($tunggakanTotal) ?>
                                                </div>
                                                <div style="font-size: 10px; color: #991b1b; line-height: 1.4;">
                                                    <?php 
                                                    $sppItems = array_map(function($v) { return "SPP " . $v; }, $dataTunggakan['spp']);
                                                    $lainItems = array_column($dataTunggakan['lainnya'], 'nama');
                                                    $allItems = array_merge($sppItems, $lainItems);
                                                    echo e(implode(', ', $allItems));
                                                    ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div style="font-size: 11px; color: #10b981; margin-top: 5px; font-weight: 600; text-align: center;">
                                                <i class="fas fa-check-circle"></i> Lunas
                                            </div>
                                        <?php 
                                            endif;
                                        endif; ?>
                                    </td>
                                    <?php if (isAdmin()): ?>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="<?= BASE_URL ?>pembayaran/detail.php?siswa_id=<?= $siswa['id'] ?>" class="btn-action btn-info" title="Riwayat">
                                                <i class="fas fa-history"></i>
                                            </a>
                                            <a href="edit.php?id=<?= $siswa['id'] ?>" class="btn-action btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="hapus.php?id=<?= $siswa['id'] ?>" class="btn-action btn-danger btn-delete" title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
