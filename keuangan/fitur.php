<?php
/**
 * Modul Fitur Pembayaran Khusus (Redesign)
 * Sistem Informasi Keuangan Sekolah - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();

$type = $_GET['type'] ?? '';
if (!$type) {
    header('Location: index.php');
    exit;
}

$tab = $_GET['tab'] ?? 'input';
$pageTitle = 'Manajemen: ' . $type;

// Deteksi apakah jenis ini tahunan (UTS, UAS, dll) atau sekali bayar
$isTypeYearly = isYearlyPayment($type);
$isTypeMonthly = in_array($type, ['SPP', 'Infak', 'Komputer']);
$labelTahun = $isTypeYearly ? 'Tahun' : 'Angkatan'; // Tahunan = per tahun pelaksanaan, sekali = per angkatan

// Get Nominal Default & Angkatan dari Setting
$stmtNominal = $pdo->prepare("SELECT nominal, tahun_masuk FROM setting_pembayaran WHERE jenis = ? ORDER BY tahun_masuk ASC");
$stmtNominal->execute([$type]);
$daftarHarga = $stmtNominal->fetchAll(PDO::FETCH_ASSOC);

$nominalDefault = getNominalPembayaran($pdo, $type);
foreach($daftarHarga as $h) {
    if($h['tahun_masuk'] == 0) $nominalDefault = $h['nominal'];
}

// Filter Tahun
$tahunFilter = $_GET['tahun'] ?? date('Y');

// Get Siswa List (untuk dropdown input)
$siswaListSelect = $pdo->query("SELECT s.*, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.status = 'aktif' ORDER BY k.tingkat, k.nama_kelas, s.nama")->fetchAll();

// Get Riwayat Pembayaran (Filtered by Year)
$stmtHist = $pdo->prepare("
    SELECT p.*, s.nama as nama_siswa, s.nis, k.nama_kelas, u.nama_lengkap as petugas
    FROM pembayaran p
    JOIN siswa s ON p.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.jenis_pembayaran = ? AND p.tahun = ?
    ORDER BY p.tanggal_bayar DESC, p.created_at DESC
");
$stmtHist->execute([$type, $tahunFilter]);
$riwayat = $stmtHist->fetchAll();

// Hitung Status per Siswa (Filtered by Year)
$stmtStatus = $pdo->prepare("
    SELECT s.id, s.nama, s.nis, s.no_whatsapp, k.nama_kelas, 
           IFNULL(SUM(CASE WHEN p.status = 'lunas' THEN p.jumlah_bayar ELSE 0 END), 0) as total_bayar,
           IFNULL(SUM(CASE WHEN p.status = 'pending' THEN 1 ELSE 0 END), 0) as has_pending
    FROM siswa s 
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN pembayaran p ON s.id = p.siswa_id AND p.jenis_pembayaran = ? AND p.status != 'ditolak' AND p.tahun = ?
    WHERE s.status = 'aktif'
    GROUP BY s.id
    ORDER BY k.tingkat, k.nama_kelas, s.nama
");
$stmtStatus->execute([$type, $tahunFilter]);
$dataStatusSiswa = $stmtStatus->fetchAll();

$siswaLunas = [];
$siswaBelum = [];
$pembayaranPending = [];

// Get specific pending payments for the tab (Filtered by Year)
$stmtPending = $pdo->prepare("
    SELECT p.*, s.nama as nama_siswa, s.nis, k.nama_kelas
    FROM pembayaran p
    JOIN siswa s ON p.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    WHERE p.jenis_pembayaran = ? AND p.status = 'pending' AND p.tahun = ?
    ORDER BY p.tanggal_bayar ASC
");
$stmtPending->execute([$type, $tahunFilter]);
$pembayaranPending = $stmtPending->fetchAll();

foreach ($dataStatusSiswa as $ds) {
    // Cari nominal spesifik untuk siswa ini berdasarkan tahun masuk
    $nominalSiswa = getNominalPembayaran($pdo, $type, $ds['tahun_masuk'] ?? 0);
    
    // Untuk SPP/Bulanan, target lunas adalah 12 bulan x nominal
    if ($isTypeMonthly && $type === 'SPP') {
        if ($tahunFilter < 2026) {
            $targetNominal = 50000 * 12; // 600.000
        } elseif ($tahunFilter == 2026) {
            $targetNominal = (50000 * 6) + (75000 * 6); // 300.000 + 450.000 = 750.000
        } else {
            $targetNominal = 75000 * 12; // 900.000
        }
    } else {
        $targetNominal = $isTypeMonthly ? ($nominalSiswa * 12) : $nominalSiswa;
    }
    
    $lunas = ($ds['total_bayar'] >= $targetNominal && $targetNominal > 0);
    if ($lunas) {
        $siswaLunas[] = $ds;
    } else {
        $ds['sisa'] = max(0, $targetNominal - $ds['total_bayar']);
        $siswaBelum[] = $ds;
    }
}

// Proses Update Nominal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_nominal'])) {
    $newNominal = str_replace(['.', ','], '', $_POST['new_nominal'] ?? '0');
    $setTahun = (int)($_POST['set_tahun_masuk'] ?? 0);
    try {
        $stmt = $pdo->prepare("
            INSERT INTO setting_pembayaran (jenis, nominal, tahun_masuk) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE nominal = VALUES(nominal)
        ");
        $stmt->execute([$type, $newNominal, $setTahun]);
        $targetMsg = ($setTahun == 0) ? "Standar (Semua $labelTahun)" : "$labelTahun $setTahun";
        setAlert('success', "Harga $type untuk $targetMsg berhasil diperbarui!");
        header("Location: fitur.php?type=" . urlencode($type));
        exit;
    } catch (PDOException $e) {
        setAlert('error', 'Gagal update harga: ' . $e->getMessage());
    }
}

// Proses Simpan Pembayaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_bayar'])) {
    $siswaId = $_POST['siswa_id'] ?? '';
    $jumlah = str_replace(['.', ','], '', $_POST['jumlah_bayar'] ?? '0');
    $metode = $_POST['metode_bayar'] ?? 'Tunai';
    $ket = trim($_POST['keterangan'] ?? '');
    
    if (empty($siswaId) || $jumlah <= 0) {
        setAlert('error', 'Siswa dan Jumlah Bayar wajib diisi!');
    } else {
        try {
            $status = ($metode === 'Transfer') ? 'pending' : 'lunas';
            
            // Handle File Upload
            if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] == 0) {
                $file = $_FILES['bukti_transfer'];
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'bukti_' . time() . '_' . rand(100,999) . '.' . $ext;
                $target = '../uploads/bukti/' . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $target)) {
                    $ket .= ' | Bukti: ' . $filename;
                }
            }

            $bulanDipilih = $_POST['bulan_bayar'] ?? getBulanIndonesia()[(int)date('n')-1];
            
            $stmt = $pdo->prepare("INSERT INTO pembayaran (siswa_id, jenis_pembayaran, bulan, tahun, jumlah_bayar, tanggal_bayar, metode_bayar, keterangan, status, user_id) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?)");
            $stmt->execute([
                $siswaId, $type, $bulanDipilih, date('Y'),
                $jumlah, $metode, $ket ?: "Pembayaran $type", $status, $_SESSION['user_id']
            ]);
            
            $msg = "Pembayaran $type berhasil disimpan!";
            if ($status == 'pending') $msg .= " Status: Menunggu Verifikasi.";
            setAlert('success', $msg);
            
            header("Location: fitur.php?type=" . urlencode($type) . "&tab=" . ($status == 'pending' ? 'pending' : 'input'));
            exit;
        } catch (PDOException $e) {
            setAlert('error', 'Gagal menyimpan: ' . $e->getMessage());
        }
    }
}

// Proses Verifikasi (Approve/Reject)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $payId = $_GET['id'];
    
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE pembayaran SET status = 'lunas', user_id = ?, verifikasi_admin_id = ?, tanggal_verifikasi = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $payId]);
        setAlert('success', 'Pembayaran berhasil diverifikasi!');
        header("Location: ../pembayaran/verifikasi-wa.php?id=" . urlencode($payId) . "&redirect=fitur");
        exit;
    } elseif ($action === 'reject') {
        $note = $_GET['reason'] ?? 'Bukti tidak valid atau tidak terbaca.';
        $stmt = $pdo->prepare("UPDATE pembayaran SET status = 'ditolak', admin_note = ?, keterangan = CONCAT(keterangan, ' | Ditolak: ', ?) WHERE id = ?");
        $stmt->execute([$note, $note, $payId]);
        setAlert('warning', 'Pembayaran ditolak.');
        header("Location: ../pembayaran/verifikasi-wa.php?id=" . urlencode($payId) . "&redirect=fitur");
        exit;
    }
    header("Location: fitur.php?type=" . urlencode($type) . "&tab=pending");
    exit;
}

include '../includes/header.php';
?>

<div class="toolbar" style="margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Kembali ke Hub
    </a>

    <!-- Year Filter -->
    <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 5px 15px;">
        <form method="GET" style="display: flex; align-items: center; gap: 10px;">
            <input type="hidden" name="type" value="<?= e($type) ?>">
            <label style="font-size: 13px; color: var(--text-secondary); margin: 0;">Tahun:</label>
            <select name="tahun" class="form-control form-control-sm" onchange="this.form.submit()" style="width: auto; height: 35px; padding: 5px 10px; color: #fff;">
                <?php for($y = 2024; $y <= date('Y')+1; $y++): ?>
                    <option value="<?= $y ?>" style="color: #000;" <?= $y == $tahunFilter ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <?php if (isset($_GET['tab'])): ?>
                <input type="hidden" name="tab" value="<?= e($_GET['tab']) ?>">
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Quick Nominal Setting -->
    <div class="filter-group" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 10px 15px; margin-left: auto;">
        <form method="POST" style="display: flex; gap: 8px; flex-wrap: wrap; width: 100%;">
            <select name="set_tahun_masuk" class="form-control form-control-sm" style="flex: 1; min-width: 120px; height: 35px; padding: 5px 10px; background: rgba(0,0,0,0.5); color: #fff !important; border: 1px solid rgba(255,255,255,0.1);">
                <option value="0" style="color: #000;">Default (Semua <?= $labelTahun ?>)</option>
                <?php for($y = 2023; $y <= date('Y')+1; $y++): ?>
                    <option value="<?= $y ?>" style="color: #000;"><?= $labelTahun ?> <?= $y ?></option>
                <?php endfor; ?>
            </select>
            <input type="text" name="new_nominal" class="form-control currency-input" 
                   style="flex: 1; min-width: 100px; height: 35px; padding: 5px 10px; font-size: 14px; text-align: right;" 
                   value="<?= number_format($nominalDefault, 0, ',', '.') ?>" placeholder="Rp">
            <button type="submit" name="update_nominal" class="btn btn-primary btn-sm" style="flex: 1; min-width: 120px; height: 35px;">
                <i class="fas fa-sync-alt"></i> Update Tarif
            </button>
        </form>
        <div style="font-size: 11px; color: var(--text-muted); margin-top: 8px; display: flex; gap: 10px; flex-wrap: wrap;">
            <span><i class="fas fa-info-circle"></i> <b>Default:</b> <?= formatRupiah($nominalDefault) ?></span>
            <?php foreach($daftarHarga as $h): if($h['tahun_masuk'] > 0): ?>
                <span class="badge badge-info" style="font-size: 10px;"><?= $labelTahun ?> <?= $h['tahun_masuk'] ?>: <?= formatRupiah($h['nominal']) ?></span>
            <?php endif; endforeach; ?>
        </div>
    </div>
</div>

<div style="margin-bottom: 30px; width: 100%;">
    <div class="mobile-scroll-x" style="gap: 5px; background: rgba(0,0,0,0.2); padding: 5px; border-radius: 12px; width: 100%;">
        <a href="?type=<?= urlencode($type) ?>&tab=lunas" class="btn <?= $tab == 'lunas' ? 'btn-success' : 'btn-secondary' ?>" style="padding: 10px 20px;">
            <i class="fas fa-check-double"></i> Lunas (<?= count($siswaLunas) ?>)
        </a>
        <a href="?type=<?= urlencode($type) ?>&tab=pending" class="btn <?= $tab == 'pending' ? 'btn-warning' : 'btn-secondary' ?>" style="padding: 10px 20px; position: relative;">
            <i class="fas fa-clock"></i> Verifikasi (<?= count($pembayaranPending) ?>)
            <?php if (count($pembayaranPending) > 0): ?>
                <span style="position: absolute; top: -5px; right: -5px; width: 10px; height: 10px; background: #ff4444; border-radius: 50%; border: 2px solid var(--bg-card);"></span>
            <?php endif; ?>
        </a>
        <a href="?type=<?= urlencode($type) ?>&tab=belum" class="btn <?= $tab == 'belum' ? 'btn-danger' : 'btn-secondary' ?>" style="padding: 10px 20px;">
            <i class="fas fa-exclamation-circle"></i> Nunggak (<?= count($siswaBelum) ?>)
        </a>
    </div>
</div>

<?php if ($tab == 'input'): ?>
<div class="stats-grid" style="grid-template-columns: 1fr 2fr;">
    <!-- Form Section -->
    <div class="card glass animate-slide-right">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-pen-fancy"></i> Catat Pembayaran Baru</h3>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Nama Siswa</label>
                    <select name="siswa_id" id="selectSiswa" class="form-control form-control-simple" required>
                        <option value="">-- Cari Nama/NIS --</option>
                        <?php 
                        $curClass = '';
                        foreach ($siswaListSelect as $s): 
                            if ($curClass != ($s['nama_kelas'] ?? 'Tanpa Kelas')): 
                                if ($curClass != '') echo '</optgroup>';
                                $curClass = ($s['nama_kelas'] ?? 'Tanpa Kelas');
                                echo '<optgroup label="' . e($curClass) . '">';
                            endif;
                        ?>
                            <option value="<?= $s['id'] ?>"><?= e($s['nis']) ?> - <?= e($s['nama']) ?></option>
                        <?php endforeach; ?>
                        <?php if ($curClass != '') echo '</optgroup>'; ?>
                    </select>
                </div>
                
                <?php if ($isTypeMonthly): ?>
                <div class="form-group">
                    <label>Bulan Pembayaran</label>
                    <select name="bulan_bayar" class="form-control form-control-simple">
                        <?php 
                        $bulanSkr = getBulanIndonesia()[(int)date('n')-1];
                        foreach (getBulanIndonesia() as $b): ?>
                            <option value="<?= $b ?>" <?= $b == $bulanSkr ? 'selected' : '' ?>><?= $b ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Jumlah Bayar (Rp)</label>
                    <input type="text" name="jumlah_bayar" class="form-control form-control-simple currency-input" 
                           value="<?= number_format($nominalDefault, 0, ',', '.') ?>" required>
                </div>

                <div class="form-group">
                    <label>Metode</label>
                    <select name="metode_bayar" id="metodeBayar" class="form-control form-control-simple" onchange="toggleBukti()">
                        <option value="Tunai">Tunai</option>
                        <option value="Transfer">Transfer</option>
                    </select>
                </div>

                <div class="form-group" id="buktiField" style="display: none;">
                    <label>Bukti Transfer (Jika ada)</label>
                    <input type="file" name="bukti_transfer" class="form-control form-control-simple" accept="image/*">
                    <small class="text-muted">Wajib jika Transfer. Status akan menjadi <b>Menunggu Verifikasi</b>.</small>
                </div>

                <div class="form-group">
                    <label>Catatan (Opsional)</label>
                    <textarea name="keterangan" class="form-control form-control-simple" rows="2" placeholder="Tulis catatan jika perlu..."></textarea>
                </div>

                <button type="submit" name="proses_bayar" class="btn btn-success btn-block">
                    <i class="fas fa-check"></i> Simpan Transaksi
                </button>
            </form>
            
            <script>
            function toggleBukti() {
                const metode = document.getElementById('metodeBayar').value;
                const field = document.getElementById('buktiField');
                field.style.display = (metode === 'Transfer') ? 'block' : 'none';
            }
            </script>
        </div>
    </div>

    <!-- Quick History -->
    <div class="card glass animate-slide-up">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-file-invoice"></i> Bukti Bayar Terakhir: <?= e($type) ?></h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($riwayat)): ?>
                <div style="text-align: center; padding: 50px; color: var(--text-muted);">
                    <i class="fas fa-receipt" style="font-size: 40px; margin-bottom: 10px; display: block;"></i>
                    Belum ada bukti pembayaran.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Siswa</th>
                                <th>Jumlah</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($riwayat, 0, 10) as $row): 
                                // Extract bukti filename from keterangan if exists
                                $buktiHist = null;
                                if (strpos($row['keterangan'], 'Bukti: ') !== false) {
                                    $parts = explode('Bukti: ', $row['keterangan']);
                                    $buktiHist = trim(end($parts));
                                }
                            ?>
                            <tr>
                                <td style="font-size: 13px;"><?= formatTanggal($row['tanggal_bayar'], 'd/m/y') ?></td>
                                <td>
                                    <div style="font-weight: 600; font-size: 13px;"><?= e($row['nama_siswa']) ?></div>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= e($row['nama_kelas'] ?? '-') ?></div>
                                </td>
                                <td style="font-weight: 700; color: var(--success);"><?= formatRupiah($row['jumlah_bayar']) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($buktiHist): ?>
                                            <a href="../uploads/bukti/<?= $buktiHist ?>" target="_blank" class="btn btn-info btn-sm" title="Lihat Bukti"><i class="fas fa-eye"></i></a>
                                        <?php endif; ?>
                                        <a href="../pembayaran/cetak.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-primary btn-sm"><i class="fas fa-print"></i></a>
                                        <a href="../pembayaran/hapus.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus bukti bayar ini?')"><i class="fas fa-trash"></i></a>
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
</div>

<?php elseif ($tab == 'lunas'): ?>
<div class="card glass animate-slide-up">
    <div class="card-header">
        <h3 class="card-title">Daftar Siswa Sudah Lunas (<?= e($type) ?>)</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama Siswa</th>
                        <th>NIS</th>
                        <th>Kelas</th>
                        <th>Total Terbayar</th>
                        <th style="text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($siswaLunas)): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">Belum ada siswa yang lunas.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($siswaLunas as $s): ?>
                    <tr>
                        <td style="font-weight: 600;"><?= e($s['nama']) ?></td>
                        <td><?= e($s['nis']) ?></td>
                        <td><?= e($s['nama_kelas'] ?: '-') ?></td>
                        <td style="font-weight: 700; color: var(--success);"><?= formatRupiah($s['total_bayar']) ?></td>
                        <td style="text-align: center;"><span class="badge badge-success">Lunas</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($tab == 'belum'): ?>
<div class="card glass animate-slide-up">
    <div class="card-header">
        <h3 class="card-title">Daftar Siswa Belum Lunas (<?= e($type) ?>)</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama Siswa</th>
                        <th>Kelas</th>
                        <th>Sudah Bayar</th>
                        <th>Sisa Tagihan</th>
                        <th style="text-align: center;">Keterangan</th>
                        <th style="text-align: center;">Notifikasi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($siswaBelum)): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">Semua siswa sudah lunas!</td></tr>
                    <?php endif; ?>
                    <?php foreach ($siswaBelum as $s): ?>
                    <tr>
                        <td style="font-weight: 600;"><?= e($s['nama']) ?></td>
                        <td><?= e($s['nama_kelas'] ?: '-') ?></td>
                        <td style="color: var(--text-secondary);"><?= formatRupiah($s['total_bayar']) ?></td>
                        <td style="font-weight: 700; color: var(--danger);"><?= formatRupiah($s['sisa']) ?></td>
                        <td style="text-align: center;">
                            <?php if ($s['has_pending'] > 0): ?>
                                <span class="badge badge-info animate-pulse">Sedang Verifikasi</span>
                            <?php elseif ($s['total_bayar'] > 0): ?>
                                <span class="badge badge-warning">Mencicil</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Belum Ada</span>
                            <?php endif; ?>
                        </td>
                            <?php 
                            $wa = formatNomorWA($s['no_whatsapp'] ?? '');
                            
                            if ($wa): 
                                $waMsg = "Assalamu'alaikum Warahmatullahi Wabarakatuh.\n\n";
                                $waMsg .= "Yth. Orang Tua/Wali dari:\n";
                                $waMsg .= "Nama Siswa: *" . $s['nama'] . "*\n";
                                $waMsg .= "Kelas: *" . ($s['nama_kelas'] ?: '-') . "*\n\n";
                                $waMsg .= "Kami dari bagian Keuangan SMK Al Amin menginformasikan perihal administrasi sekolah putra/putri Bapak/Ibu untuk iuran: *" . $type . "*.\n\n";
                                $waMsg .= "📊 *Rincian Tagihan:*\n";
                                $waMsg .= "• Status: " . ($s['total_bayar'] > 0 ? "Mencicil" : "Belum Bayar") . "\n";
                                $waMsg .= "• Jumlah yang Sudah Dibayar: " . formatRupiah($s['total_bayar']) . "\n";
                                $waMsg .= "• *Sisa Tagihan yang Harus Melunasi: " . formatRupiah($s['sisa']) . "*\n\n";
                                $waMsg .= "Pembayaran dapat dilakukan melalui transfer ke rekening sekolah atau langsung ke bagian Keuangan SMK Al Amin.\n\n";
                                $waMsg .= "Demikian informasi ini kami sampaikan. Atas perhatian dan kerjasamanya, kami ucapkan terima kasih.\n\n";
                                $waMsg .= "Wassalamu'alaikum Warahmatullahi Wabarakatuh.\n";
                                $waMsg .= "*Bendahara SMK Al Amin*";
                                
                                $waUrl = "https://wa.me/" . $wa . "?text=" . urlencode($waMsg);
                            ?>
                                <a href="<?= $waUrl ?>" target="_blank" class="btn btn-success btn-sm" style="background: #25d366; border-color: #25d366;" title="Kirim Pengingat WA">
                                    Tagih
                                </a>
                            <?php else: ?>
                                <span class="text-muted" style="font-size: 10px;">No WA Kosong</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($tab == 'pending'): ?>
<div class="card glass animate-slide-up">
    <div class="card-header">
        <h3 class="card-title">Pembayaran Menunggu Verifikasi (<?= e($type) ?>)</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal Lapor</th>
                        <th>Profil Siswa</th>
                        <th>Jumlah Bayar</th>
                        <th>Metode</th>
                        <th style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pembayaranPending)): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 50px; color: var(--text-muted);">
                            <i class="fas fa-check-circle" style="font-size: 40px; margin-bottom: 10px; display: block; color: var(--success);"></i>
                            Semua pembayaran sudah diverifikasi.
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($pembayaranPending as $p): 
                        $bukti = getBuktiTransfer($p['keterangan']);
                    ?>
                    <tr>
                        <td><?= formatTanggal($p['tanggal_bayar']) ?></td>
                        <td>
                            <div style="font-weight: 600;"><?= e($p['nama_siswa']) ?></div>
                            <div style="font-size: 11px; color: var(--text-muted);"><?= e($p['nis']) ?> | <?= e($p['nama_kelas']) ?></div>
                        </td>
                        <td style="font-weight: 700; color: var(--info);"><?= formatRupiah($p['jumlah_bayar']) ?></td>
                        <td><span class="badge <?= $p['metode_bayar'] == 'Transfer' ? 'badge-info' : 'badge-secondary' ?>"><?= $p['metode_bayar'] ?></span></td>
                        <td style="text-align: center;">
                            <div class="action-buttons" style="justify-content: center;">
                                <?php if ($bukti): ?>
                                    <button onclick="lihatBukti('<?= BASE_URL ?>uploads/bukti/<?= $bukti ?>', '<?= $p['id'] ?>')" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> Cek Bukti
                                    </button>
                                <?php else: ?>
                                    <a href="?type=<?= urlencode($type) ?>&action=approve&id=<?= $p['id'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Siswa ini lapor bayar tanpa bukti gambar. Setujui?')">
                                        <i class="fas fa-check"></i> Setujui
                                    </a>
                                <?php endif; ?>
                                <button onclick="rejectPayment('<?= $p['id'] ?>')" class="btn btn-danger btn-sm">
                                    <i class="fas fa-times"></i> Tolak
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Bukti -->
<div id="modalBukti" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.85); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(5px);">
    <div style="background: var(--bg-card); border-radius: 20px; width: 90%; max-width: 500px; overflow: hidden; position: relative; border: 1px solid var(--border-color); animation: slideInUp 0.3s ease;">
        <div style="padding: 15px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h4 style="margin: 0;">Verifikasi Bukti Transfer</h4>
            <button onclick="closeModal()" style="background: none; border: none; color: #fff; font-size: 20px; cursor: pointer;"><i class="fas fa-times"></i></button>
        </div>
        <div style="padding: 20px; text-align: center; background: #000;">
            <img id="imgBukti" src="" style="max-width: 100%; border-radius: 10px; max-height: 400px; object-fit: contain;">
        </div>
        <div style="padding: 20px; border-top: 1px solid var(--border-color); display: flex; gap: 10px; background: rgba(0,0,0,0.1);">
            <a id="btnSetuju" href="" class="btn btn-success" style="flex: 1;" onclick="return confirm('Konfirmasi bahwa uang sudah masuk ke rekening?')">
                <i class="fas fa-check-circle"></i> Setujui (Lunas)
            </a>
            <button onclick="closeModal()" class="btn btn-secondary" style="flex: 1;">Batal</button>
        </div>
    </div>
</div>

<script>
function lihatBukti(src, id) {
    document.getElementById('imgBukti').src = src;
    document.getElementById('btnSetuju').href = `?type=<?= urlencode($type) ?>&action=approve&id=${id}`;
    document.getElementById('modalBukti').style.display = 'flex';
}
function closeModal() {
    document.getElementById('modalBukti').style.display = 'none';
}
function rejectPayment(id) {
    const reason = prompt('Alasan penolakan:', 'Bukti tidak valid.');
    if (reason !== null) {
        window.location.href = `?type=<?= urlencode($type) ?>&action=reject&id=${id}&reason=${encodeURIComponent(reason)}`;
    }
}
</script>
<?php endif; ?>

<script>
// Currency Formatting
document.querySelectorAll('.currency-input').forEach(input => {
    input.addEventListener('input', function() {
        let value = this.value.replace(/\D/g, '');
        if (value) {
            this.value = new Intl.NumberFormat('id-ID').format(value);
        } else {
            this.value = '0';
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
