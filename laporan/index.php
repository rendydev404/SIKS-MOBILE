<?php
/**
 * Laporan Pembayaran
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();

$pageTitle = 'Laporan Pembayaran';

// Filter
$filterBulan = $_GET['bulan'] ?? date('m');
$filterTahun = $_GET['tahun'] ?? date('Y');
$filterKelas = $_GET['kelas'] ?? '';

$bulanList = getBulanIndonesia();
$bulanName = $bulanList[(int)$filterBulan - 1] ?? '';

// Get rekapitulasi data
// Logic: Siswa dianggap ada jika tahun_masuk < filterTahun ATAU (tahun_masuk = filterTahun DAN bulan >= 7)
$fTahun = (int)$filterTahun;
$fBulan = (int)$filterBulan;

$sql = "SELECT 
            k.id as kelas_id, k.nama_kelas, k.jurusan,
            (SELECT COUNT(*) FROM siswa s 
             WHERE s.kelas_id = k.id 
             AND s.status = 'aktif' 
             AND (s.tahun_masuk < ? OR (s.tahun_masuk = ? AND ? >= 7))
            ) as total_siswa,
            (SELECT COUNT(DISTINCT p.siswa_id) FROM pembayaran p 
             JOIN siswa s ON p.siswa_id = s.id 
             WHERE s.kelas_id = k.id AND p.bulan = ? AND p.tahun = ?) as sudah_bayar,
            (SELECT COALESCE(SUM(p.jumlah_bayar), 0) FROM pembayaran p 
             JOIN siswa s ON p.siswa_id = s.id 
             WHERE s.kelas_id = k.id AND p.bulan = ? AND p.tahun = ?) as total_nominal
        FROM kelas k";

$params = [$fTahun, $fTahun, $fBulan, $bulanName, $filterTahun, $bulanName, $filterTahun];

if ($filterKelas) {
    $sql .= " WHERE k.id = ?";
    $params[] = $filterKelas;
}

$sql .= " ORDER BY k.tingkat, k.jurusan";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rekapList = $stmt->fetchAll();

// Hitung total
$totalSiswa = 0;
$totalSudahBayar = 0;
$totalNominal = 0;
foreach ($rekapList as $row) {
    $totalSiswa += $row['total_siswa'];
    $totalSudahBayar += $row['sudah_bayar'];
    $totalNominal += $row['total_nominal'];
}

$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, jurusan")->fetchAll();

include '../includes/header.php';
?>

<div class="toolbar">
    <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap;">
        <select name="bulan" class="form-control form-control-simple" style="width: auto;">
            <?php foreach ($bulanList as $i => $bulan): ?>
                <option value="<?= $i + 1 ?>" <?= $filterBulan == ($i + 1) ? 'selected' : '' ?>>
                    <?= $bulan ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="tahun" class="form-control form-control-simple" style="width: auto;">
            <?php 
            $startYear = 2024;
            $endYear = date('Y') + 2;
            for ($y = $startYear; $y <= $endYear; $y++): 
            ?>
                <option value="<?= $y ?>" <?= $filterTahun == $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
        <select name="kelas" class="form-control form-control-simple" style="width: auto;">
            <option value="">Semua Kelas</option>
            <?php foreach ($kelasList as $kelas): ?>
                <option value="<?= $kelas['id'] ?>" <?= $filterKelas == $kelas['id'] ? 'selected' : '' ?>>
                    <?= e($kelas['nama_kelas']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-filter"></i> Filter
        </button>
    </form>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format($totalSiswa) ?></div>
            <div class="stat-label">Total Siswa</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon success">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format($totalSudahBayar) ?></div>
            <div class="stat-label">Sudah Bayar</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon danger">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format($totalSiswa - $totalSudahBayar) ?></div>
            <div class="stat-label">Belum Bayar</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon info">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= formatRupiah($totalNominal) ?></div>
            <div class="stat-label">Total Pendapatan</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Rekap Per Kelas - <?= $bulanName ?> <?= $filterTahun ?></h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kelas</th>
                        <th>Jurusan</th>
                        <th>Total Siswa</th>
                        <th>Sudah Bayar</th>
                        <th>Belum Bayar</th>
                        <th>Total Nominal</th>
                        <th>Persentase</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rekapList as $row): 
                        $belumBayar = $row['total_siswa'] - $row['sudah_bayar'];
                        $persen = $row['total_siswa'] > 0 ? round(($row['sudah_bayar'] / $row['total_siswa']) * 100) : 0;
                    ?>
                    <tr>
                        <td><?= e($row['nama_kelas']) ?></td>
                        <td><?= e($row['jurusan']) ?></td>
                        <td><?= $row['total_siswa'] ?></td>
                        <td class="text-success"><?= $row['sudah_bayar'] ?></td>
                        <td class="text-danger"><?= $belumBayar ?></td>
                        <td><?= formatRupiah($row['total_nominal']) ?></td>
                        <td>
                            <span class="badge badge-<?= $persen >= 80 ? 'success' : ($persen >= 50 ? 'warning' : 'danger') ?>">
                                <?= $persen ?>%
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot style="background: var(--bg-dark); font-weight: 600;">
                    <tr>
                        <td colspan="2">TOTAL</td>
                        <td><?= $totalSiswa ?></td>
                        <td class="text-success"><?= $totalSudahBayar ?></td>
                        <td class="text-danger"><?= $totalSiswa - $totalSudahBayar ?></td>
                        <td><?= formatRupiah($totalNominal) ?></td>
                        <td>
                            <?php $totalPersen = $totalSiswa > 0 ? round(($totalSudahBayar / $totalSiswa) * 100) : 0; ?>
                            <span class="badge badge-<?= $totalPersen >= 80 ? 'success' : ($totalPersen >= 50 ? 'warning' : 'danger') ?>">
                                <?= $totalPersen ?>%
                            </span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
