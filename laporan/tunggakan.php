<?php
/**
 * Laporan Tunggakan
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();

$pageTitle = 'Laporan Tunggakan';

$filterKelas = $_GET['kelas'] ?? '';
$filterBulan = $_GET['bulan'] ?? '';
$tahun = $_GET['tahun'] ?? date('Y');

$bulanList = getBulanIndonesia();
$bulanSekarang = (int)date('n');

// Get SPP
$spp = getSppAktif($pdo);
$nominalSpp = $spp ? $spp['nominal'] : 0;

// Get siswa yang belum bayar
$sql = "SELECT s.*, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.status IN ('aktif', 'lulus')";
$params = [];

if ($filterKelas) {
    $sql .= " AND s.kelas_id = ?";
    $params[] = $filterKelas;
}

$sql .= " ORDER BY k.tingkat, k.jurusan, s.nama";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$siswaList = $stmt->fetchAll();

// Tentukan batas bulan yang dicek
$currentYear = (int)date('Y');
$currentMonth = (int)date('n');

if ($tahun < $currentYear) {
    $limitBulan = 12; // Tahun lalu, cek semua bulan
} elseif ($tahun == $currentYear) {
    $limitBulan = $currentMonth; // Tahun sekarang, cek sampai bulan ini
} else {
    $limitBulan = 0; // Tahun depan, belum ada tunggakan
}

// Hitung tunggakan per siswa
$tunggakanList = [];
foreach ($siswaList as $siswa) {
    $tunggakan = [];
    $startYear = (int)($siswa['tahun_masuk'] ?? 2024);

    for ($i = 0; $i < $limitBulan; $i++) {
        // Skip jika bulan ini sebelum siswa masuk (Jan-Jun di tahun masuk)
        if ($tahun == $startYear && $i < 6) {
            continue;
        }

        $bulan = $bulanList[$i];
        $cek = cekPembayaran($pdo, $siswa['id'], $bulan, $tahun);
        if (!$cek['lunas']) {
            $tunggakan[] = $bulan;
        }
    }
    
    if (!empty($tunggakan)) {
        if (empty($filterBulan) || in_array($filterBulan, $tunggakan)) {
            $siswa['tunggakan'] = $tunggakan;
            $siswa['total_tunggakan'] = count($tunggakan) * $nominalSpp;
            $tunggakanList[] = $siswa;
        }
    }
}

$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, jurusan")->fetchAll();

include '../includes/header.php';
?>

<div class="toolbar">
    <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap;">
        <select name="kelas" class="form-control form-control-simple" style="width: auto;">
            <option value="">Semua Kelas</option>
            <?php foreach ($kelasList as $kelas): ?>
                <option value="<?= $kelas['id'] ?>" <?= $filterKelas == $kelas['id'] ? 'selected' : '' ?>>
                    <?= e($kelas['nama_kelas']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="bulan" class="form-control form-control-simple" style="width: auto;">
            <option value="">Semua Bulan</option>
            <?php for ($i = 0; $i < 12; $i++): ?>
                <option value="<?= $bulanList[$i] ?>" <?= $filterBulan == $bulanList[$i] ? 'selected' : '' ?>>
                    <?= $bulanList[$i] ?>
                </option>
            <?php endfor; ?>
        </select>
        <select name="tahun" class="form-control form-control-simple" style="width: auto;">
            <?php 
            for ($y = 2024; $y <= 2028; $y++): 
            ?>
                <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-filter"></i> Filter
        </button>
    </form>
</div>

<div class="alert alert-info">
    <i class="fas fa-info-circle"></i>
    Menampilkan siswa yang memiliki tunggakan SPP <?= ($limitBulan > 0) ? 'sampai dengan bulan <strong>' . $bulanList[$limitBulan - 1] . ' ' . $tahun . '</strong>' : 'untuk tahun <strong>' . $tahun . '</strong> (Belum ada tagihan)' ?>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Daftar Siswa dengan Tunggakan (<?= count($tunggakanList) ?> siswa)</h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($tunggakanList)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle" style="color: var(--success);"></i>
                <h3>Tidak ada tunggakan</h3>
                <p>Semua siswa sudah membayar SPP</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                            <th>Bulan Tunggakan</th>
                            <th>Total Tunggakan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tunggakanList as $i => $siswa): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= e($siswa['nis']) ?></td>
                            <td><?= e($siswa['nama']) ?></td>
                            <td><?= e($siswa['nama_kelas'] ?? '-') ?></td>
                            <td>
                                <?php foreach ($siswa['tunggakan'] as $bulan): ?>
                                    <span class="badge badge-danger" style="margin: 2px;"><?= $bulan ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td class="text-danger"><strong><?= formatRupiah($siswa['total_tunggakan']) ?></strong></td>
                            <td>
                                <a href="<?= BASE_URL ?>pembayaran/tambah.php?siswa_id=<?= $siswa['id'] ?>" class="btn btn-success btn-sm">
                                    <i class="fas fa-money-bill-wave"></i> Bayar
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot style="background: var(--bg-dark); font-weight: 600;">
                        <tr>
                            <td colspan="5">TOTAL TUNGGAKAN</td>
                            <td class="text-danger">
                                <?php 
                                $grandTotal = array_sum(array_column($tunggakanList, 'total_tunggakan'));
                                echo formatRupiah($grandTotal);
                                ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
