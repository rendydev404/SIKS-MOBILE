<?php
/**
 * Export Data Pembayaran ke Excel (CSV format)
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();

// Filter
$filterBulan = $_GET['bulan'] ?? '';
$filterTahun = $_GET['tahun'] ?? date('Y');
$filterJenis = $_GET['jenis'] ?? '';
$filterKelas = $_GET['kelas_id'] ?? '';

// Query pembayaran
$sql = "SELECT p.*, s.nama as nama_siswa, s.nis, k.nama_kelas, u.nama_lengkap as petugas
        FROM pembayaran p
        JOIN siswa s ON p.siswa_id = s.id
        LEFT JOIN kelas k ON s.kelas_id = k.id
        LEFT JOIN users u ON p.user_id = u.id
        WHERE 1=1";
$params = [];

if ($filterKelas) {
    $sql .= " AND s.kelas_id = ?";
    $params[] = $filterKelas;
}

if ($filterBulan) {
    $sql .= " AND p.bulan = ?";
    $params[] = $filterBulan;
}

if ($filterTahun) {
    $sql .= " AND p.tahun = ?";
    $params[] = $filterTahun;
}

if ($filterJenis) {
    $sql .= " AND p.jenis_pembayaran = ?";
    $params[] = $filterJenis;
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pembayaranList = $stmt->fetchAll();

// Jika filter kelas ada, ambil nama kelasnya
$namaKelas = 'Semua Kelas';
if ($filterKelas) {
    $stmtKelas = $pdo->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
    $stmtKelas->execute([$filterKelas]);
    $k = $stmtKelas->fetch();
    if ($k) $namaKelas = $k['nama_kelas'];
}

// Set Header untuk Download Excel (Open as XML Excel)
$filename = "Laporan_Pembayaran_" . ($filterBulan ?: "Tahunan") . "_" . $filterTahun . ".xls";

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename=' . $filename);
header('Pragma: no-cache');
header('Expires: 0');

?>
<style>
    .table-export { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; }
    .table-export th { background-color: #4f46e5; color: #ffffff; font-weight: bold; border: 1px solid #000; padding: 10px; }
    .table-export td { border: 1px solid #000; padding: 8px; }
    .title { font-size: 18px; font-weight: bold; margin-bottom: 5px; text-align: center; }
    .subtitle { font-size: 14px; margin-bottom: 20px; text-align: center; color: #666; }
</style>

<div class="title">LAPORAN KEUANGAN SISWA SMK AL AMIN</div>
<div class="subtitle">
    Periode: <?= $filterBulan ?: 'Semua Bulan' ?> <?= $filterTahun ?> | Kelas: <?= $namaKelas ?>
</div>

<table class="table-export border="1">
    <thead>
        <tr>
            <th>No</th>
            <th>Tanggal</th>
            <th>NIS</th>
            <th>Nama Siswa</th>
            <th>Kelas</th>
            <th>Jenis</th>
            <th>Bulan/Tahun</th>
            <th>Jumlah</th>
            <th>Metode</th>
            <th>Petugas</th>
            <th>Keterangan</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        $total = 0;
        foreach ($pembayaranList as $row): 
            $total += $row['jumlah_bayar'];
        ?>
        <tr>
            <td align="center"><?= $no++ ?></td>
            <td align="center"><?= date('d/m/Y', strtotime($row['tanggal_bayar'])) ?></td>
            <td>'<?= $row['nis'] ?></td> <!-- Single quote to force string in Excel -->
            <td><?= $row['nama_siswa'] ?></td>
            <td align="center"><?= $row['nama_kelas'] ?? '-' ?></td>
            <td align="center">SPP</td>
            <td align="center"><?= $row['bulan'] ?> <?= $row['tahun'] ?></td>
            <td align="right"><?= number_format($row['jumlah_bayar'], 0, ',', '.') ?></td>
            <td align="center"><?= $row['metode_bayar'] ?></td>
            <td><?= $row['petugas'] ?? '-' ?></td>
            <td><?= $row['keterangan'] ?? '-' ?></td>
        </tr>
        <?php endforeach; ?>
        <tr style="background-color: #f3f4f6; font-weight: bold;">
            <td colspan="7" align="right">TOTAL PEMBAYARAN:</td>
            <td align="right"><?= number_format($total, 0, ',', '.') ?></td>
            <td colspan="3"></td>
        </tr>
    </tbody>
</table>
<?php exit; ?>
