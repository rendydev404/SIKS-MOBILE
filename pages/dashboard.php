<?php
/**
 * Dashboard
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();

$pageTitle = 'Dashboard';

// Get statistics
$totalSiswa = $pdo->query("SELECT COUNT(*) FROM siswa WHERE status = 'aktif'")->fetchColumn();
$totalKelas = $pdo->query("SELECT COUNT(*) FROM kelas")->fetchColumn();

// Get SPP aktif
$spp = getSppAktif($pdo);
$nominalSpp = $spp ? $spp['nominal'] : 0;

// Get total pembayaran bulan ini
$bulanIni = date('F');
$bulanIndo = [
    'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
    'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
    'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
    'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
];
$namaBulanIndo = $bulanIndo[$bulanIni];
$tahunReal = (int)date('Y');
// Database menggunakan tahun aktual/fisik untuk bulan pembayaran
$tahunSpp = $tahunReal;

$stmtPembayaran = $pdo->prepare("SELECT COALESCE(SUM(jumlah_bayar), 0) as total FROM pembayaran WHERE bulan = ? AND tahun = ? AND status = 'lunas' AND jenis_pembayaran = 'SPP'");
$stmtPembayaran->execute([$namaBulanIndo, $tahunSpp]);
$totalPembayaranBulanIni = $stmtPembayaran->fetchColumn();

// Hitung siswa yang sudah bayar (lunas) bulan ini khusus SPP
$stmtSudahBayar = $pdo->prepare("SELECT COUNT(DISTINCT siswa_id) FROM pembayaran WHERE bulan = ? AND tahun = ? AND status = 'lunas' AND jenis_pembayaran = 'SPP'");
$stmtSudahBayar->execute([$namaBulanIndo, $tahunSpp]);
$siswaSudahBayar = $stmtSudahBayar->fetchColumn();

// Ambil total siswa aktif agar akurasi Belum Bayar terjaga
$totalSiswa = $pdo->query("SELECT COUNT(*) FROM siswa WHERE status = 'aktif'")->fetchColumn();
$siswaBelumBayar = max(0, $totalSiswa - $siswaSudahBayar);

$bulanIniDisplay = $namaBulanIndo; // Untuk label UI

// Hitung pembayaran yang menunggu verifikasi (semua periode)
$totalPending = $pdo->query("SELECT COUNT(*) FROM pembayaran WHERE status = 'pending'")->fetchColumn();

// Get 5 pembayaran terakhir
$pembayaranTerakhir = $pdo->query("
    SELECT p.*, s.nama as nama_siswa, s.nis, k.nama_kelas
    FROM pembayaran p
    JOIN siswa s ON p.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    ORDER BY p.created_at DESC
    LIMIT 5
")->fetchAll();

include '../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="fas fa-user-graduate"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format($totalSiswa) ?></div>
            <div class="stat-label">Total Siswa Aktif</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon success">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= formatRupiah($totalPembayaranBulanIni) ?></div>
            <div class="stat-label">Pembayaran <?= $bulanIniDisplay ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon info">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format($siswaSudahBayar) ?></div>
            <div class="stat-label">Sudah Bayar <?= $bulanIniDisplay ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon danger">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format($siswaBelumBayar) ?></div>
            <div class="stat-label">Belum Bayar <?= $bulanIniDisplay ?></div>
        </div>
    </div>

    <div class="stat-card" onclick="location.href='../pembayaran/index.php?status=pending'" style="cursor: pointer; border: 1px solid var(--warning);">
        <div class="stat-icon warning">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format($totalPending) ?></div>
            <div class="stat-label">Menunggu Verifikasi</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-history"></i> Transaksi Terakhir
        </h2>
        <a href="<?= BASE_URL ?>pembayaran/" class="btn btn-primary btn-sm">
            Lihat Semua
        </a>
    </div>
    <div class="card-body">
        <?php if (empty($pembayaranTerakhir)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Belum ada pembayaran</h3>
                <p>Belum ada transaksi pembayaran SPP yang tercatat</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                            <th>Bulan</th>
                            <th>Jumlah</th>
                            <th>Bukti</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pembayaranTerakhir as $row): 
                            // Parse bukti using central function
                            $buktiFile = getBuktiTransfer($row['keterangan']);
                        ?>
                        <tr>
                            <td><?= formatTanggal($row['tanggal_bayar'], 'd M Y') ?></td>
                            <td><?= e($row['nis']) ?></td>
                            <td><?= e($row['nama_siswa']) ?></td>
                            <td><?= e($row['nama_kelas'] ?? '-') ?></td>
                            <td><?= e($row['bulan']) ?> <?= $row['tahun'] ?></td>
                            <td class="text-success"><?= formatRupiah($row['jumlah_bayar']) ?></td>
                            <td>
                                <?php if (!empty($buktiFile)): ?>
                                    <a href="../uploads/bukti/<?= trim($buktiFile) ?>" target="_blank" class="btn btn-sm btn-outline-info" title="Lihat Bukti">
                                        <i class="fas fa-image"></i> Lihat
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
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

<?php include '../includes/footer.php'; ?>
