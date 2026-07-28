<?php
/**
 * Kirim Invoice WA SPP - SMK Al Aminstem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();

$pageTitle = 'Kirim Tagihan WA';

// Get data
$filterKelas = $_GET['kelas'] ?? '';
$filterBulan = $_GET['bulan'] ?? date('n');
$tahun = $_GET['tahun'] ?? date('Y');

$bulanList = getBulanIndonesia();
$bulanName = $bulanList[(int)$filterBulan - 1] ?? '';

// Get siswa
$sql = "SELECT s.*, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.status = 'aktif'";
$params = [];
if ($filterKelas) {
    $sql .= " AND s.kelas_id = ?";
    $params[] = $filterKelas;
}
$sql .= " ORDER BY k.tingkat, k.jurusan, s.nama";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$siswaList = $stmt->fetchAll();

$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, jurusan")->fetchAll();

include '../includes/header.php';
?>

<div class="toolbar">
    <div class="alert alert-info" style="margin-bottom: 25px; border-left: 5px solid #3b82f6;">
        <h5 style="margin-top: 0;"><i class="fas fa-info-circle"></i> Cara Menagih dengan Gambar (Sangat Mudah):</h5>
        <ol style="margin-bottom: 0; padding-left: 20px;">
            <li>Klik tombol biru <strong><i class="fas fa-eye"></i> Lihat</strong> untuk melihat invoice siswa.</li>
            <li>Di halaman invoice:
                <ul>
                    <li><strong>Laptop:</strong> Klik tombol <strong><i class="fas fa-copy"></i> Salin & Buka WA</strong>, lalu Tempel (Ctrl+V) di WA.</li>
                    <li><strong>HP:</strong> Klik tombol biru <strong><i class="fas fa-share-nodes"></i> Bagikan</strong> untuk langsung mengirim ke WA.</li>
                </ul>
            </li>
        </ol>
    </div>

    <div class="card mb-4">
        <i class="fas fa-search"></i>
        <input type="text" id="searchInput" placeholder="Cari nama atau NIS...">
    </div>
    <div class="filter-group">
        <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap;">
            <select name="kelas" class="form-control form-control-simple" style="width: auto;" onchange="this.form.submit()">
                <option value="">Semua Kelas</option>
                <?php foreach ($kelasList as $kelas): ?>
                    <option value="<?= $kelas['id'] ?>" <?= $filterKelas == $kelas['id'] ? 'selected' : '' ?>>
                        <?= e($kelas['nama_kelas']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="bulan" class="form-control form-control-simple" style="width: auto;" onchange="this.form.submit()">
                <?php foreach ($bulanList as $i => $bulan): ?>
                    <option value="<?= $i + 1 ?>" <?= $filterBulan == ($i + 1) ? 'selected' : '' ?>>
                        <?= $bulan ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="tahun" class="form-control form-control-simple" style="width: auto;" onchange="this.form.submit()">
                <?php 
                $startYear = 2024;
                $endYear = date('Y') + 2;
                for ($y = $startYear; $y <= $endYear; $y++): 
                ?>
                    <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>
</div>

                    <?php 
                    // Menggunakan filter bulan & tahun yang dipilih
                    $ceilBulan = (int)$filterBulan;
                    $ceilTahun = (int)$tahun;
                    $ceilBulanName = $bulanList[$ceilBulan - 1];
                    ?>
<div class="alert alert-info" style="border-left: 5px solid #8b5cf6;">
    <i class="fas fa-calendar-alt"></i> <strong>Sistem Penagihan Sesuai Filter:</strong><br>
    Sistem saat ini menampilkan tagihan SPP sampai dengan bulan <strong><?= $ceilBulanName ?> <?= $ceilTahun ?></strong> sesuai dengan filter yang Anda pilih di atas.
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Daftar Siswa - Invoice Tagihan s/d <?= $ceilBulanName ?> <?= $ceilTahun ?></h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th>NIS</th>
                        <th>Nama</th>
                        <th>Kelas</th>
                        <th>Status SPP <?= $bulanName ?></th>
                        <th>Rincian Tunggakan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1; 
                    ?>
                    <?php if (count($siswaList) > 0): ?>

                    <?php foreach ($siswaList as $siswa): 
                        // Hitung tunggakan berdasarkan filter
                        $dataTunggakan = hitungTunggakan($pdo, $siswa['id'], true, $ceilBulan, $ceilTahun);
                        $tunggakan = $dataTunggakan['total'];
                        $tunggakanBulan = $dataTunggakan['bulan'];
                        
                        // Jika tidak ada tunggakan, SKIP siswa ini (Sembunyikan yg lunas)
                        if ($tunggakan <= 0) continue;

                        $noWa = $siswa['no_whatsapp'] ?? '';
                        $noWa = preg_replace('/[^0-9]/', '', $noWa);
                        if (substr($noWa, 0, 1) == '0') $noWa = '62' . substr($noWa, 1);
                    ?>
                    <tr>
                        <td style="text-align: center;"><?= $no++ ?></td>
                        <td><?= e($siswa['nis']) ?></td>
                        <td><?= e($siswa['nama']) ?></td>
                        <td><?= e($siswa['nama_kelas'] ?? '-') ?></td>
                        <td>
                            <?php 
                            // Cek status cicilan/pembayaran khusus bulan filter
                            $cekStat = $pdo->prepare("SELECT status FROM pembayaran WHERE siswa_id = ? AND bulan = ? AND tahun = ? AND jenis_pembayaran = 'SPP' ORDER BY created_at DESC LIMIT 1");
                            $cekStat->execute([$siswa['id'], $bulanName, $tahun]);
                            $resStat = $cekStat->fetch();
                            $lastStatus = $resStat['status'] ?? null;

                            if ($lastStatus == 'pending'): ?>
                                <span class="badge" style="background: var(--warning); color: #000;">Verifikasi Admin</span>
                            <?php else: 
                                $cekBulan = cekPembayaran($pdo, $siswa['id'], $bulanName, $tahun);
                                if ($cekBulan['status'] == 'lunas'): ?>
                                    <span class="badge badge-success">Lunas</span>
                                <?php elseif ($cekBulan['status'] == 'nyicil'): ?>
                                    <span class="badge" style="background: #0ea5e9; color: #fff;">Nyicil (Kurang <?= number_format($cekBulan['sisa'], 0, ',', '.') ?>)</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Belum</span>
                                <?php endif; 
                            endif; ?>
                        </td>
                        <td>
                            <strong class="text-danger"><?= formatRupiah($tunggakan) ?></strong>
                            <br>
                            <span style="font-size: 11px; color: var(--text-muted); display: block; max-width: 250px; line-height: 1.2;">
                                <?= implode(', ', array_slice($tunggakanBulan, 0, 5)) ?>
                                <?= count($tunggakanBulan) > 5 ? '...' : '' ?>
                            </span>
                        </td>
                        <td>
                             <a href="invoice-view.php?siswa_id=<?= $siswa['id'] ?>&bulan=<?= $filterBulan ?>&tahun=<?= $tahun ?>" 
                                class="btn btn-primary btn-sm" title="Lihat & Kirim Invoice">
                                <i class="fas fa-eye"></i> Lihat Tagihan
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if ($no == 1): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                            <i class="fas fa-check-circle" style="font-size: 40px; margin-bottom: 10px; color: var(--success); display: block;"></i>
                            Semua siswa sudah lunas untuk periode ini!
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Simple search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('table tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>

<?php include '../includes/footer.php'; ?>
