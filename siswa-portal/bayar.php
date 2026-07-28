<?php
/**
 * Portal Siswa - Bayar SPP
 * Pembayaran bulanan include SPP, Infak, Komputer
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

// Cek login siswa
if (!isset($_SESSION['siswa_id'])) {
    header('Location: index.php');
    exit;
}

$siswaId = $_SESSION['siswa_id'];

// Get data siswa
$stmt = $pdo->prepare("SELECT s.*, k.nama_kelas, k.tingkat FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
$stmt->execute([$siswaId]);
$siswa = $stmt->fetch();

if (!$siswa) {
    header('Location: index.php');
    exit;
}

// Get Payment Type from URL
$type         = $_GET['type'] ?? 'SPP';
$tahunFromUrl = isset($_GET['tahun']) ? (int)$_GET['tahun'] : null;

if (strpos($type, 'Werpak TKJ') !== false && strtoupper($siswa['jurusan'] ?? '') !== 'TKJ') {
    header('Location: dashboard.php');
    exit;
}


// Determine if this is a monthly payment
$isMonthly = in_array($type, ['SPP', 'Infak', 'Komputer']);
$isYearlyT = isYearlyPayment($type);

// Untuk biaya tahunan: nominal berdasarkan tahun pelaksanaan. Untuk lainnya: angkatan siswa.
$startYearCalc = (int)($siswa['tahun_masuk'] ?? date('Y'));
$lookupTahun = $isYearlyT ? ($tahunFromUrl ?? (int)date('Y')) : $startYearCalc;
$nominal      = getNominalPembayaran($pdo, $type, $lookupTahun);

// Untuk pembayaran tahunan, hitung sisa tagihan dari DB agar otomatis cicil
$sisaTagihanAwal  = $nominal; // default: belum bayar sama sekali
$totalSudahBayar  = 0;
if (!$isMonthly) {
    $cekAwal = cekPembayaran($pdo, $siswaId, null, $tahunFromUrl, $type);
    $sisaTagihanAwal = $cekAwal['sisa'];
    $totalSudahBayar  = $cekAwal['total_dibayar'] + $cekAwal['total_pending'];
}

$bulanList = getBulanIndonesia();
$error = '';
$success = false;

// Proses pembayaran
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if bulan is an array (multi-month) or fallback to single string
    $bulanInput = $isMonthly ? ($_POST['bulan'] ?? []) : [getBulanIndonesia()[(int)date('n')-1]];
    if (!is_array($bulanInput)) {
        $bulanInput = [$bulanInput]; // Convert to array if someone bypasses UI
    }
    
    // Monthly: ambil dari select; Non-monthly: ambil dari hidden input tahun (dikirim fitur-lain.php)
    $tahun = $_POST['tahun'] ?? ($isMonthly ? date('Y') : ($tahunFromUrl ?? date('Y')));
    $metodeBayar = $_POST['metode_bayar'] ?? 'Tunai';
    $jumlahBayarInput = $_POST['jumlah_bayar'] ?? '';
    // Hapus titik/koma format ribuan
    $jumlahBayarTotal = floatval(str_replace(['.', ','], '', $jumlahBayarInput));
    
    if ($isMonthly && empty($bulanInput)) {
        $error = 'Pilih minimal satu bulan pembayaran!';
    } elseif ($jumlahBayarTotal <= 0) {
        $error = 'Jumlah bayar tidak valid!';
    } else {
        // Validasi dan hitung total tagihan dari semua bulan yang dipilih
        $totalSisaDibayar = 0;
        $invalidBulan = [];
        $validasiLolos = true;
        
        foreach ($bulanInput as $bln) {
            $cekTahun = (int)$tahun;
            if ($isMonthly && in_array($bln, ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni'])) {
                $cekTahun++;
            }
            $cekStatus = cekPembayaran($pdo, $siswaId, $bln, $cekTahun, $type);
            if ($cekStatus['lunas']) {
                $invalidBulan[] = $bln;
                $validasiLolos = false;
            } else {
                $totalSisaDibayar += $cekStatus['sisa'];
            }
        }
        
        if (!$validasiLolos) {
            $error = 'Tagihan ' . e($type) . ' untuk periode ' . implode(', ', $invalidBulan) . ' ' . e($tahun) . ' sudah LUNAS. Silakan hilangkan centang bulan tersebut.';
        } elseif ($jumlahBayarTotal > $totalSisaDibayar) {
            $error = 'Jumlah bayar (Rp ' . number_format($jumlahBayarTotal, 0, ',', '.') . ') melebihi total sisa tagihan dari bulan yang dipilih (Rp ' . number_format($totalSisaDibayar, 0, ',', '.') . ')!';
        } else {
            // Validasi upload bukti
            if (!isset($_FILES['bukti_bayar']) || $_FILES['bukti_bayar']['error'] != 0) {
                $error = 'Upload bukti pembayaran wajib!';
            } else {
                // Handle upload bukti
                $uploadDir = '../uploads/bukti/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                
                $ext = strtolower(pathinfo($_FILES['bukti_bayar']['name'], PATHINFO_EXTENSION));
                $allowedExt = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (!in_array($ext, $allowedExt)) {
                    $error = 'Format file harus JPG, PNG, atau GIF!';
                } elseif ($_FILES['bukti_bayar']['size'] > 2 * 1024 * 1024) {
                    $error = 'Ukuran file maksimal 2MB!';
                } else {
                    $filename = 'bukti_' . $siswaId . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['bukti_bayar']['tmp_name'], $uploadDir . $filename)) {
                        $keteranganUtama = ($metodeBayar == 'Transfer') ? 'Bukti Transfer: ' . $filename : 'Bukti Administrasi Bendahara: ' . $filename;
                        $status = 'pending'; // Semua pembayaran dari portal siswa butuh verifikasi
                        
                        try {
                            $pdo->beginTransaction();
                            $stmt = $pdo->prepare("INSERT INTO pembayaran (siswa_id, jenis_pembayaran, bulan, tahun, jumlah_bayar, tanggal_bayar, metode_bayar, keterangan, status, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                            
                            // Distribusikan uang ke bulan-bulan yang dipilih
                            $sisaUangDistribusi = $jumlahBayarTotal;
                            
                            foreach ($bulanInput as $bln) {
                                if ($sisaUangDistribusi <= 0) break;
                                
                                // Koreksi tahun untuk bulan Januari - Juni jika bulanan (karena sistem berjalan di tahun fisik)
                                $saveTahun = (int)$tahun;
                                if ($isMonthly && in_array($bln, ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni'])) {
                                    $saveTahun++;
                                }
                                
                                $cekStatus = cekPembayaran($pdo, $siswaId, $bln, $saveTahun, $type, (int)($siswa['tahun_masuk'] ?? 0));
                                $dibutuhkan = $cekStatus['sisa'];
                                
                                // Berapa banyak yang bisa dialokasikan untuk bulan ini
                                $alokasiBulanIni = min($sisaUangDistribusi, $dibutuhkan);
                                
                                if ($alokasiBulanIni > 0) {
                                    $keterangan = $keteranganUtama;
                                    if (count($bulanInput) > 1) {
                                        $keterangan .= ' (Multi-Bulan)';
                                    }
                                    
                                    $stmt->execute([
                                        $siswaId, $type, $bln, $saveTahun, $alokasiBulanIni,
                                        date('Y-m-d'), $metodeBayar, $keterangan, $status
                                    ]);
                                    
                                    $sisaUangDistribusi -= $alokasiBulanIni;
                                }
                            }
                            
                            $pdo->commit();
                            $success = true;
                        } catch (PDOException $e) {
                            $pdo->rollBack();
                            $error = 'Gagal menyimpan pembayaran: ' . $e->getMessage();
                        }
                    } else {
                        $error = 'Gagal mengupload file!';
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bayar SPP - Portal Siswa</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background: radial-gradient(circle at top right, #1e293b, #0f172a);
            min-height: 100vh;
        }
        .container { max-width: 600px; margin: 60px auto; padding: 0 20px; }
        
        .payment-option { display: flex; gap: 15px; margin-bottom: 25px; }
        .payment-option label { 
            flex: 1; padding: 20px 15px; border: 1px solid var(--border-color); 
            border-radius: 16px; cursor: pointer; text-align: center; 
            transition: all 0.3s; background: rgba(255,255,255,0.02);
            display: flex; flex-direction: column; align-items: center; gap: 8px;
        }
        .payment-option input:checked + label { 
            border-color: var(--primary); 
            background: rgba(99, 102, 241, 0.1);
            box-shadow: 0 0 15px rgba(99, 102, 241, 0.1);
        }
        .payment-option input { display: none; }
        .payment-option i { font-size: 28px; }
        
        .nominal-box { 
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2)); 
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 25px; border-radius: 20px; text-align: center; margin-bottom: 30px; 
            backdrop-filter: blur(10px);
        }
        .nominal-box .amount { font-size: 36px; font-weight: 800; color: var(--success); }
        .nominal-box .label { color: var(--text-secondary); font-size: 13px; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 1px; }
        
        .form-control-simple { 
            background: rgba(15, 23, 42, 0.6); border: 1px solid var(--border-color);
            color: white; border-radius: 12px; padding: 12px 18px;
        }
        .form-control-simple:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2); }
        
        /* Success Overlay */
        .success-overlay { 
            position: fixed; top: 0; left: 0; right: 0; bottom: 0; 
            background: rgba(15, 23, 42, 0.9); backdrop-filter: blur(8px);
            display: flex; align-items: center; justify-content: center; z-index: 9999; 
        }
        .success-modal { 
            background: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: 24px; padding: 40px; text-align: center; max-width: 400px; 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: slideInUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
        }
        .success-icon { 
            width: 80px; height: 80px; background: var(--gradient-success); 
            border-radius: 50%; display: flex; align-items: center; justify-content: center; 
            margin: 0 auto 25px; box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
        }
        .success-icon i { font-size: 36px; color: white; }
        .success-title { font-size: 26px; font-weight: 800; color: #fff; margin-bottom: 12px; }
        .success-message { color: var(--text-secondary); margin-bottom: 25px; line-height: 1.6; }
    </style>
</head>
<body>
    
    <?php if ($success): ?>
    <div class="success-overlay" id="successModal">
        <div class="success-modal">
            <div class="success-icon" style="background: var(--warning); box-shadow: 0 10px 20px rgba(245, 158, 11, 0.3);">
                <i class="fas fa-clock"></i>
            </div>
            <h2 class="success-title"><?= ($metodeBayar == 'Tunai') ? 'Laporan Diterima' : 'Pembayaran Diproses' ?></h2>
            <p class="success-message">
                <?= ($metodeBayar == 'Tunai') 
                    ? 'Terima kasih. Laporan pembayaran tunai Anda sebesar <strong>' . formatRupiah($jumlahBayarTotal) . '</strong> telah kami terima. Status akan diperbarui setelah <strong>diverifikasi oleh Bendahara</strong>.'
                    : 'Terima kasih. Pembayaran Anda telah kami terima dan sedang dalam <strong>proses verifikasi</strong> oleh admin.' ?>
            </p>
            <a href="dashboard.php" class="btn btn-primary" style="width: 100%;">
                Ke Dashboard Sekarang
            </a>
        </div>
    </div>
    <script>
        setTimeout(function() { window.location.href = 'dashboard.php'; }, 4000);
    </script>
    <?php endif; ?>
    
    <div class="container animate-slide-up">
        <div class="card glass">
            <div class="card-header" style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                <h2 class="card-title"><i class="fas fa-credit-card" style="color: var(--primary);"></i> Bayar <?= e($type) ?></h2>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <?php if ($isMonthly): ?>
                <div id="rejectionAlert" class="alert alert-warning animate-fade" style="display: none; border-left: 4px solid var(--danger);">
                    <div style="display: flex; gap: 10px;">
                        <i class="fas fa-exclamation-triangle" style="margin-top: 3px; color: var(--danger);"></i>
                        <div>
                            <strong>Pernah Ditolak Sebelumnya:</strong><br>
                            <span id="rejectionReasonText"></span>
                            <br><small style="margin-top: 5px; display: block; opacity: 0.8;">Silakan perbaiki bukti pembayaran sesuai alasan di atas.</small>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="nominal-box">
                    <?php if (!$isMonthly && ($totalSudahBayar > 0 || $sisaTagihanAwal < $nominal)): ?>
                    <!-- Info breakdown cicilan untuk pembayaran tahunan -->
                    <div style="display:flex; justify-content:center; gap:24px; margin-bottom:14px; flex-wrap:wrap;">
                        <div style="text-align:center;">
                            <div style="font-size:10px; color:var(--text-secondary); text-transform:uppercase; margin-bottom:3px;">Total Tagihan</div>
                            <div style="font-size:15px; font-weight:700; color:#fff;"><?= formatRupiah($nominal) ?></div>
                        </div>
                        <div style="text-align:center;">
                            <div style="font-size:10px; color:var(--text-secondary); text-transform:uppercase; margin-bottom:3px;">Sudah Dibayar</div>
                            <div style="font-size:15px; font-weight:700; color:var(--success);"><?= formatRupiah($totalSudahBayar) ?></div>
                        </div>
                    </div>
                    <div class="label">Sisa yang Harus Dibayar</div>
                    <div class="amount" id="displaySisa" style="color: <?= $sisaTagihanAwal <= 0 ? 'var(--success)' : 'var(--warning)' ?>;"><?= formatRupiah($sisaTagihanAwal) ?></div>
                    <?php elseif ($isMonthly): ?>
                    <?php
                        $dataTunggakan = hitungTunggakan($pdo, $siswaId, true);
                        $totalLainnya = 0;
                        foreach (($dataTunggakan['lainnya'] ?? []) as $l) {
                            $totalLainnya += $l['sisa'] ?? 0;
                        }
                        // Total tunggakan spesifik kategori bulanan ini, jika SPP maka yang dihitung SPP
                        $tunggakanBulanIni = max(0, $dataTunggakan['total'] - $totalLainnya);
                        $tunggakanSppList = [];
                        
                        if ($type != 'SPP') {
                            // Untuk Infak/Komputer, lebih repot. Untuk sementara gunakan pendekatan sederhana
                            $tunggakanBulanIni = 0; // fallback jika bukan SPP bisa disesuaikan
                        } else {
                            $tunggakanSppList = $dataTunggakan['spp'] ?? [];
                        }
                    ?>
                    <div class="label">Total Tunggakan <?= e($type) ?> (Keseluruhan)</div>
                    <div class="amount" style="color: var(--danger); font-size: 24px; margin-bottom: 10px;"><?= ($type == 'SPP') ? formatRupiah($tunggakanBulanIni) : formatRupiah($nominal) ?></div>
                    
                    <?php if (!empty($tunggakanSppList)): ?>
                    <div style="background: rgba(239, 68, 68, 0.1); border-radius: 12px; border-left: 4px solid var(--danger); padding: 15px; margin-top: 15px; text-align: left; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <strong style="color: #f87171; font-size: 14px;"><i class="fas fa-exclamation-circle"></i> Rincian Belum Bayar</strong>
                            <span class="badge badge-danger" style="background: rgba(239,68,68,0.2);"><?= count($tunggakanSppList) ?> Bulan</span>
                        </div>
                        <ul style="margin: 0; padding-left: 20px; line-height: 1.6; color: #fca5a5; font-size: 13px;">
                            <?php foreach($tunggakanSppList as $tg): ?>
                                <li><?= e($tg) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <div id="displaySisa" style="display:none;"></div>
                    <?php else: ?>
                    <div class="label">Sisa Tagihan <?= e($type) ?></div>
                    <div class="amount" id="displaySisa"><?= formatRupiah($nominal) ?></div>
                    <?php endif; ?>
                    <small id="keteranganLunas" style="color: var(--success); font-weight: bold; display: none; margin-top: 10px;">Tagihan ini sudah lunas!</small>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="formBayarSiswa">
                    <?php if ($tahunFromUrl && !$isMonthly): ?>
                    <input type="hidden" name="tahun" value="<?= $tahunFromUrl ?>">
                    <?php endif; ?>
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label>Jumlah yang Ingin Dibayar / Dicicil (Rp)</label>
                        <input type="text" name="jumlah_bayar" id="inputJumlahBayar" class="form-control form-control-simple" 
                               value="<?= number_format($isMonthly ? $nominal : $sisaTagihanAwal, 0, ',', '.') ?>" required>
                        <small style="color: var(--text-muted); display: block; margin-top: 5px;">
                            <?php if (!$isMonthly && $totalSudahBayar > 0): ?>
                            <i class="fas fa-info-circle" style="color: #f59e0b;"></i> <strong style="color:#f59e0b;">Nyicil:</strong> Nominal di atas adalah sisa tagihan Anda. Anda boleh membayar sebagian.
                            <?php else: ?>
                            <i class="fas fa-info-circle"></i> Anda dapat mengubah nominal ini jika ingin menyicil/mengangsur pembayaran.
                            <?php endif; ?>
                        </small>
                    </div>
                    <?php if ($isMonthly): ?>
                    <div class="form-row" style="margin-bottom: 20px;">
                        <div class="form-group" style="width: 100%;">
                            <label>Pilih Tahun Tagihan</label>
                            <select name="tahun" id="pilihTahun" class="form-control form-control-simple" style="margin-bottom: 15px;">
                                <?php 
                                $tahunReal = date('Y');
                                $bulanReal = (int)date('n');
                                $currentAcademicYear = ($bulanReal < 7) ? $tahunReal - 1 : $tahunReal;
                                
                                $tingkatSiswaInfo = $siswa['tingkat'] ?? '';
                                $maxGradeIndex = 0; // Kelas 10
                                if (in_array($tingkatSiswaInfo, ['XI'])) $maxGradeIndex = 1;
                                elseif (in_array($tingkatSiswaInfo, ['XII', 'Alumni'])) $maxGradeIndex = 2;

                                $startYear = (int)($siswa['tahun_masuk'] ?? 0);
                                if ($startYear <= 0) {
                                    $startYear = date('Y');
                                    $statusSiswa = $siswa['status'] ?? 'aktif';
                                    // Logika tahun akademik default berdasarkan tingkat saat ini
                                    if ($statusSiswa === 'aktif') {
                                        if ($tingkatSiswaInfo === 'X') {
                                            $startYear = $currentAcademicYear;
                                        } elseif ($tingkatSiswaInfo === 'XI') {
                                            $startYear = $currentAcademicYear - 1;
                                        } elseif ($tingkatSiswaInfo === 'XII' || $tingkatSiswaInfo === 'Alumni') {
                                            $startYear = $currentAcademicYear - 2;
                                        }
                                    }
                                }
                                
                                $tahunMax = $startYear + $maxGradeIndex;
                                $tahunDefault = max($startYear, min($tahunMax, $currentAcademicYear));
                                
                                $grades = [10, 11, 12];
                                for ($i = 0; $i < 3; $i++): 
                                    $y = $startYear + $i;
                                    $label = "Kelas " . $grades[$i];
                                    $isLocked = ($i > $maxGradeIndex);
                                ?>
                                    <?php if ($isLocked): ?>
                                        <option value="<?= $y ?>" disabled><?= $label ?> (<?= $y ?>) - Belum Tersedia</option>
                                    <?php else: ?>
                                        <option value="<?= $y ?>" <?= ($tahunDefault == $y) ? 'selected' : '' ?>><?= $label ?> (<?= $y ?>)</option>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </select>
                            
                            <label>Pilih Bulan (Bisa lebih dari 1)</label>
                            <div class="month-grid" id="monthStatusGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px;">
                                <?php 
                                // Generate bulan Juli - Juni
                                $bulanSekolah = [
                                    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
                                    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni'
                                ];
                                foreach ($bulanSekolah as $bulan): 
                                ?>
                                    <!-- Options will be populated and styled via JS -->
                                    <label class="month-item" style="display: flex; align-items: center; gap: 8px; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer; background: rgba(255,255,255,0.02); transition: all 0.2s;">
                                        <input type="checkbox" name="bulan[]" value="<?= $bulan ?>" class="month-checkbox" style="width: 16px; height: 16px;">
                                        <span class="month-name" style="font-size: 14px; font-weight: 500;"><?= $bulan ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <small style="color: var(--text-muted); display: block; margin-top: 10px;"><i class="fas fa-info-circle"></i> Sistem otomatis menghitung total tagihan dari semua bulan yang Anda centang.</small>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Metode Pembayaran</label>
                        <div class="payment-option">
                            <input type="radio" name="metode_bayar" value="Tunai" id="tunai" checked>
                            <label for="tunai">
                                <i class="fas fa-money-bill-wave" style="color: var(--success);"></i>
                                <span>Bayar Tunai</span>
                                <small style="display:block; font-size:10px; opacity:0.6;">Melalui Bendahara</small>
                            </label>
                            
                            <input type="radio" name="metode_bayar" value="Transfer" id="transfer">
                            <label for="transfer">
                                <i class="fas fa-university" style="color: var(--info);"></i>
                                <span>Transfer</span>
                                <small style="display:block; font-size:10px; opacity:0.6;">Via Bank/Apps</small>
                            </label>
                        </div>
                    </div>
                    
                    <div class="alert alert-info" id="transferInfo" style="display: none; font-size: 13px; background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.2);">
                        <p style="margin-bottom: 5px;"><strong>Rekening Tujuan:</strong></p>
                        <div style="background: rgba(0,0,0,0.2); padding: 10px; border-radius: 8px; font-family: monospace; display: flex; justify-content: space-between; align-items: center;">
                            <span>SeaBank: 901612378561</span>
                            <i class="fas fa-copy" style="cursor:pointer;" title="Salin"></i>
                        </div>
                        <p style="margin-top: 5px; font-size: 11px;">a.n. Mira Humairoh</p>
                    </div>
                    
                    <div class="form-group" id="uploadSection" style="margin-top:20px;">
                        <label id="uploadLabel">Upload Bukti <span class="text-danger">*</span></label>
                        <div style="position:relative;">
                            <input type="file" name="bukti_bayar" class="form-control form-control-simple" accept="image/*" required style="padding-left: 45px;">
                            <i class="fas fa-cloud-upload-alt" style="position:absolute; left:15px; top:15px; color:var(--primary);"></i>
                        </div>
                        <p style="font-size: 11px; color: var(--text-muted); margin-top: 8px;" id="uploadHint">
                            <i class="fas fa-info-circle"></i> Pastikan foto bukti/nota terlihat jelas (Maks 2MB)
                        </p>
                    </div>
                    
                    <div style="margin-top: 30px; display: flex; gap: 15px;">
                        <a href="dashboard.php" class="btn btn-secondary" style="flex:1;">Batal</a>
                        <button type="submit" class="btn btn-primary" id="submitBtnBayar" style="flex:2;">
                            Kirim Pembayaran <i class="fas fa-paper-plane" style="margin-left: 8px;"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    document.querySelectorAll('input[name="metode_bayar"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            const isTunai = this.value === 'Tunai';
            document.getElementById('transferInfo').style.display = isTunai ? 'none' : 'block';
            document.getElementById('uploadHint').innerHTML = isTunai 
                ? '<i class="fas fa-info-circle"></i> Upload foto catatan administrasi dari Bendahara'
                : '<i class="fas fa-info-circle"></i> Upload foto bukti transfer/struk ATM';
        });
    });

    // Check multiple months status and load Sisa Tagihan
    const tahunSelect = document.getElementById('pilihTahun');
    const monthCheckboxes = document.querySelectorAll('.month-checkbox');
    const typeJenis = '<?= e($type) ?>';
    const inputJumlahBayar = document.getElementById('inputJumlahBayar');
    const displaySisa = document.getElementById('displaySisa');
    const submitBtn = document.getElementById('submitBtnBayar');

    function formatRibuan(angka) {
        return new Intl.NumberFormat('id-ID').format(angka);
    }

    // Format auto thousands on input jumlah bayar
    if (inputJumlahBayar) {
        inputJumlahBayar.addEventListener('input', function() {
            let val = this.value.replace(/\D/g, '');
            if(val) {
                this.value = formatRibuan(val);
            } else {
                this.value = '0';
            }
        });
    }
    
    // Simpan semua data sisa bulan yang diambil dari server
    let sisaPerBulan = {};

    function checkBulanStatus() {
        if (!tahunSelect) return;
        
        let tahun = tahunSelect.value;
        const allMonths = Array.from(monthCheckboxes).map(cb => cb.value).join(',');

        fetch(`check_rejection.php?bulan=multi&blnlist=${encodeURIComponent(allMonths)}&tahun=${tahun}&jenis=${encodeURIComponent(typeJenis)}`)
            .then(response => response.json())
            .then(data => {
                // Reset akumulasi
                sisaPerBulan = data.detail_multi;
                updateTotalChecked();
                
                // Update styling tiap checkbox based on status lunas
                monthCheckboxes.forEach(cb => {
                    const blnName = cb.value;
                    const stat = sisaPerBulan[blnName];
                    const parentLabel = cb.closest('label');
                    
                    if (stat && stat.lunas) {
                        cb.disabled = true;
                        cb.checked = false;
                        parentLabel.style.opacity = '0.5';
                        parentLabel.style.background = 'rgba(16, 185, 129, 0.1)';
                        parentLabel.style.borderColor = 'var(--success)';
                        const span = parentLabel.querySelector('.month-name');
                        span.innerHTML = `${blnName} <i class="fas fa-check-circle" style="color:var(--success); font-size:12px; margin-left:3px;"></i>`;
                        span.style.color = 'var(--success)';
                    } else {
                        cb.disabled = false;
                        parentLabel.style.opacity = '1';
                        parentLabel.style.background = 'rgba(255,255,255,0.02)';
                        parentLabel.style.borderColor = 'var(--border-color)';
                        const span = parentLabel.querySelector('.month-name');
                        span.textContent = blnName;
                        span.style.color = '#fff';
                        
                        // Default to unchecked initially, user must click
                    }
                });
            })
            .catch(err => console.error('Error checking backend:', err));
    }
    
    function updateTotalChecked() {
        let totalSisa = 0;
        let checkedCount = 0;
        
        monthCheckboxes.forEach(cb => {
            if (cb.checked && !cb.disabled) {
                const blnData = sisaPerBulan[cb.value];
                if (blnData && typeof blnData.sisa !== 'undefined') {
                    totalSisa += parseFloat(blnData.sisa);
                    checkedCount++;
                }
            }
        });
        
        // Update display but don't force disable the button to avoid UX confusion
        if (checkedCount === 0) {
            displaySisa.innerHTML = "Rp 0";
            if (inputJumlahBayar) inputJumlahBayar.value = "0";
        } else {
            displaySisa.innerHTML = "Rp " + formatRibuan(totalSisa);
            if (inputJumlahBayar) inputJumlahBayar.value = formatRibuan(totalSisa);
        }
        
        // Re-enable in case it was stuck, but let PHP handle the final validation if empty
        if (submitBtn) submitBtn.disabled = false;
    }

    if (tahunSelect) {
        tahunSelect.addEventListener('change', checkBulanStatus);
        
        monthCheckboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                const parentLabel = this.closest('label');
                if (this.checked) {
                    parentLabel.style.borderColor = 'var(--primary)';
                    parentLabel.style.boxShadow = '0 0 10px rgba(99, 102, 241, 0.2)';
                } else {
                    parentLabel.style.borderColor = 'var(--border-color)';
                    parentLabel.style.boxShadow = 'none';
                }
                updateTotalChecked();
            });
        });
    }
    
    // Call once on load
    checkBulanStatus();
    </script>
</body>
</html>
