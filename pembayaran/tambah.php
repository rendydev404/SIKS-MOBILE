<?php
/**
 * Input Pembayaran Dinamis
 * Sistem Informasi Keuangan Sekolah - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();

$pageTitle = 'Input Pembayaran';

// Get siswa aktif beserta tingkat/tahun_masuk
$siswaList = $pdo->query("SELECT s.*, k.nama_kelas, k.tingkat FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.status IN ('aktif', 'lulus') ORDER BY k.tingkat, k.nama_kelas, s.nama")->fetchAll();

$siswaAngkatanMap = [];
foreach($siswaList as $s) {
    $y = (int)$s['tahun_masuk'];
    if (!$y) {
        $curYear = date('Y') - (date('n') < 7 ? 1 : 0);
        if ($s['tingkat'] === 'X') $y = $curYear;
        elseif ($s['tingkat'] === 'XI') $y = $curYear - 1;
        elseif ($s['tingkat'] === 'XII') $y = $curYear - 2;
    }
    $siswaAngkatanMap[$s['id']] = $y;
}

$settings = $pdo->query("SELECT jenis, nominal, tahun_masuk FROM setting_pembayaran")->fetchAll();
$nominalMapByAngkatan = [];
foreach ($settings as $s) {
    $nominalMapByAngkatan[$s['tahun_masuk']][$s['jenis']] = (int)$s['nominal'];
}

// Daftar jenis pembayaran yang aktif. Harus mengikuti getPaymentCategories(),
// bukan daftar nama lama, supaya transaksi yang dicatat admin memakai nama yang
// sama dengan yang dibaca portal siswa (mis. "PKL (Praktik Kerja Lapangan)",
// bukan "PSG / PKL").
$jenisAktif = ['SPP', 'Infak', 'Komputer'];
foreach (getPaymentCategories() as $items) {
    foreach ($items as $namaJenis => $meta) {
        if (!in_array($namaJenis, $jenisAktif, true)) $jenisAktif[] = $namaJenis;
    }
}

foreach ($jenisAktif as $k) {
    if (!isset($nominalMapByAngkatan[0][$k])) {
        $nominalMapByAngkatan[0][$k] = (int)getNominalPembayaran($pdo, $k);
    }
}

$bulanList = getBulanIndonesia();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siswaId = $_POST['siswa_id'] ?? '';
    $jenisPembayaran = $_POST['jenis_pembayaran'] ?? 'SPP';
    $bulan = $_POST['bulan'] ?? '';
    $tahun = $_POST['tahun'] ?? date('Y');
    $jumlahBayar = str_replace(['.', ','], '', $_POST['jumlah_bayar'] ?? '');
    $tanggalBayar = $_POST['tanggal_bayar'] ?? date('Y-m-d');
    $metodeBayar = $_POST['metode_bayar'] ?? 'Tunai';
    $keterangan = trim($_POST['keterangan'] ?? '');
    
    if (empty($siswaId) || empty($bulan) || empty($jumlahBayar)) {
        $error = 'Siswa, Bulan, dan Jumlah Bayar wajib diisi!';
    } else {
        // Cek total pembayaran yang sudah masuk untuk periode & jenis ini
        $isMonthly = in_array($jenisPembayaran, ['SPP', 'Infak', 'Komputer']);
        $isYearly = isYearlyPayment($jenisPembayaran);
        if ($isMonthly) {
            $cekStmt = $pdo->prepare("SELECT SUM(jumlah_bayar) as total_dibayar FROM pembayaran WHERE siswa_id = ? AND jenis_pembayaran = ? AND bulan = ? AND tahun = ? AND status != 'ditolak'");
            $cekStmt->execute([$siswaId, $jenisPembayaran, $bulan, $tahun]);
        } elseif ($isYearly) {
            // Filter by tahun untung mendukung multi-tahun pada pembayaran non-bulanan
            $cekStmt = $pdo->prepare("SELECT SUM(jumlah_bayar) as total_dibayar FROM pembayaran WHERE siswa_id = ? AND jenis_pembayaran = ? AND tahun = ? AND status != 'ditolak'");
            $cekStmt->execute([$siswaId, $jenisPembayaran, $tahun]);
        } else {
            $cekStmt = $pdo->prepare("SELECT SUM(jumlah_bayar) as total_dibayar FROM pembayaran WHERE siswa_id = ? AND jenis_pembayaran = ? AND status != 'ditolak'");
            $cekStmt->execute([$siswaId, $jenisPembayaran]);
        }
        $dataCek = $cekStmt->fetch();
        $totalDibayar = (float)($dataCek['total_dibayar'] ?? 0);
        
        $siswaAngkatanVal = $siswaAngkatanMap[$siswaId] ?? 0;
        // Biaya tahunan (UTS, UAS, dll) = harga berdasarkan tahun pelaksanaan
        // Biaya lainnya (DSP, Pendaftaran, dll) = harga berdasarkan angkatan siswa
        $lookupTahun = $isYearly ? (int)$tahun : $siswaAngkatanVal;
        $nominalTagihan = (float)getNominalPembayaran($pdo, $jenisPembayaran, $lookupTahun);
        $sisaTagihan = $nominalTagihan - $totalDibayar;

        // Validasi: Apakah sudah lunas?
        if ($totalDibayar >= $nominalTagihan) {
            $error = 'Siswa sudah LUNAS untuk pembayaran ' . $jenisPembayaran . ' periode ' . $bulan . ' ' . $tahun;
        } elseif ($jumlahBayar > $sisaTagihan && $sisaTagihan > 0) {
            // Validasi: Apakah bayar lebih dari sisa yang dibutuhkan?
            $error = 'Jumlah bayar (Rp ' . number_format($jumlahBayar, 0, ',', '.') . ') melebihi sisa tagihan (Rp ' . number_format($sisaTagihan, 0, ',', '.') . ')!';
        } else {
            try {
                $status = 'pending'; // Semua pembayaran, termasuk admin-side, butuh verifikasi
                
                // Upload Bukti Required
                if (!isset($_FILES['bukti_transfer']) || $_FILES['bukti_transfer']['error'] != 0) {
                    throw new Exception('Upload bukti pembayaran wajib disertakan!');
                }
                
                $file = $_FILES['bukti_transfer'];
                $filename = 'bukti_admin_' . time() . '_' . rand(100,999) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
                $target = '../uploads/bukti/' . $filename;
                
                if (!is_dir('../uploads/bukti/')) mkdir('../uploads/bukti/', 0777, true);
                
                if (move_uploaded_file($file['tmp_name'], $target)) {
                    $keterangan .= ' | Bukti: ' . $filename;
                } else {
                    throw new Exception('Gagal mengupload bukti pembayaran.');
                }

                $stmt = $pdo->prepare("INSERT INTO pembayaran (siswa_id, jenis_pembayaran, bulan, tahun, jumlah_bayar, tanggal_bayar, metode_bayar, keterangan, user_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $siswaId, $jenisPembayaran, $bulan, $tahun, $jumlahBayar, 
                    $tanggalBayar, $metodeBayar, $keterangan ?: null, $_SESSION['user_id'], $status
                ]);
                
                $pembayaranId = $pdo->lastInsertId();
                setAlert('success', 'Pembayaran berhasil disimpan!');
                header('Location: cetak.php?id=' . $pembayaranId);
                exit;
            } catch (PDOException $e) {
                $error = 'Gagal menyimpan pembayaran: ' . $e->getMessage();
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-plus-circle"></i> Form Input Pembayaran</h2>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        
        <div class="alert alert-info" style="border-radius: 8px; font-size: 0.95rem;">
            <i class="fas fa-info-circle"></i> <strong>Catatan:</strong> Pembayaran <strong>SPP sudah termasuk dengan Infak dan Komputer</strong>.
        </div>
        
        <form method="POST" id="formPembayaran" enctype="multipart/form-data">
            <div class="form-group">
                <label>Pilih Siswa <span class="text-danger">*</span></label>
                <select name="siswa_id" class="form-control form-control-simple" required id="selectSiswa">
                    <option value="">-- Pilih Siswa --</option>
                    <?php 
                    $currentClass = '';
                    foreach ($siswaList as $siswa): 
                        if ($currentClass != ($siswa['nama_kelas'] ?? 'Tanpa Kelas')): 
                            if ($currentClass != '') echo '</optgroup>';
                            $currentClass = ($siswa['nama_kelas'] ?? 'Tanpa Kelas');
                            echo '<optgroup label="' . e($currentClass) . '">';
                        endif;
                    ?>
                        <option value="<?= $siswa['id'] ?>" <?= ($_POST['siswa_id'] ?? '') == $siswa['id'] ? 'selected' : '' ?>>
                            <?= e($siswa['nis']) ?> - <?= e($siswa['nama']) ?> <?= ($siswa['status'] == 'lulus') ? '(LULUS)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if ($currentClass != '') echo '</optgroup>'; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Jenis Pembayaran <span class="text-danger">*</span></label>
                <select name="jenis_pembayaran" id="jenisPembayaran" class="form-control form-control-simple" required>
                    <?php foreach ($jenisAktif as $jenis): ?>
                        <option value="<?= e($jenis) ?>"><?= e($jenis) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Bulan <span class="text-danger">*</span></label>
                    <select name="bulan" class="form-control form-control-simple" required>
                        <?php foreach ($bulanList as $bulan): ?>
                            <option value="<?= $bulan ?>" <?= $bulan == (getBulanIndonesia()[(int)date('n')-1]) ? 'selected' : '' ?>>
                                <?= $bulan ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="yearGroup">
                    <label>Tahun <span class="text-danger">*</span></label>
                    <select name="tahun" id="tahunSelect" class="form-control form-control-simple" required>
                        <?php 
                        $curMonthNum = (int)date('n');
                        $defaultYear = ($curMonthNum < 7) ? (int)date('Y') - 1 : (int)date('Y');
                        
                        $startYear = 2023; 
                        $endYear = date('Y') + 1;
                        
                        for ($y = $startYear; $y <= $endYear; $y++): 
                        ?>
                            <option value="<?= $y ?>" <?= $y == $defaultYear ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                    <small class="text-muted"><i class="fas fa-info-circle"></i> Gunakan tahun awal ajaran (misal: Jan 2026 masuk tahun 2025).</small>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Jumlah Bayar (Rp) <span class="text-danger">*</span></label>
                    <input type="text" name="jumlah_bayar" id="jumlahBayar" class="form-control form-control-simple currency-input" required>
                    <small style="color: var(--text-muted); display: block; margin-top: 5px;"><i class="fas fa-info-circle"></i> <strong>Fitur Cicilan:</strong> Ubah nominal ini jika siswa membayar kurang dari tagihan penuh. Sistem otomatis akan mencatatnya sebagai cicilan (Nyicil).</small>
                </div>
                <div class="form-group">
                    <label>Tanggal Bayar <span class="text-danger">*</span></label>
                    <input type="date" name="tanggal_bayar" class="form-control form-control-simple" 
                           value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Metode Pembayaran</label>
                <select name="metode_bayar" id="metodeBayar" class="form-control form-control-simple">
                    <option value="Tunai">Tunai</option>
                    <option value="Transfer">Transfer</option>
                </select>
            </div>

            <div class="form-group" id="buktiField">
                <label>Bukti Pembayaran / Transfer <span class="text-danger">*</span></label>
                <input type="file" name="bukti_transfer" class="form-control form-control-simple" accept="image/*" required>
                <small class="text-white">Admin wajib mengupload bukti pembayaran (foto struk / uang cash) yang nantinya akan diverifikasi.</small>
            </div>
            
            <div class="form-group">
                <label>Keterangan</label>
                <textarea name="keterangan" class="form-control form-control-simple" rows="2" placeholder="Contoh: Titipan orang tua / Potongan beasiswa"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Simpan Pembayaran
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </form>
    </div>
</div>

<script>
const nominalMapByAngkatan = <?= json_encode($nominalMapByAngkatan) ?>;
const siswaAngkatan = <?= json_encode($siswaAngkatanMap) ?>;
const yearlyTypes = ['UTS 1', 'UAS 1', 'UTS 2', 'UAS 2', 'Kenaikan Kelas', 'Daftar Ulang'];
const selectSiswa = document.getElementById('selectSiswa');
const selectJenis = document.getElementById('jenisPembayaran');
const selectTahun = document.querySelector('select[name="tahun"]');
const inputJumlah = document.getElementById('jumlahBayar');

function updateNominal() {
    const sId = selectSiswa.value;
    const j = selectJenis.value;
    const tahunSelect = document.getElementById('tahunSelect');
    const bulanSelect = document.querySelector('select[name="bulan"]');

    const tahunForm = tahunSelect ? tahunSelect.value : new Date().getFullYear();
    
    // Biaya tahunan → pakai tahun form, biaya lainnya → pakai angkatan siswa
    let lookupKey;
    if (yearlyTypes.includes(j)) {
        lookupKey = tahunForm;
    } else {
        lookupKey = sId ? (siswaAngkatan[sId] || 0) : 0;
    }
    
    let nom = 150000;
    if (nominalMapByAngkatan[lookupKey] && nominalMapByAngkatan[lookupKey][j]) {
        nom = nominalMapByAngkatan[lookupKey][j];
    } else if (nominalMapByAngkatan[0] && nominalMapByAngkatan[0][j]) {
        nom = nominalMapByAngkatan[0][j];
    }
    inputJumlah.value = new Intl.NumberFormat('id-ID').format(nom);
}

selectSiswa.addEventListener('change', updateNominal);
selectJenis.addEventListener('change', updateNominal);
if (selectTahun) selectTahun.addEventListener('change', updateNominal);
const selectBulan = document.querySelector('select[name="bulan"]');
if (selectBulan) selectBulan.addEventListener('change', updateNominal);
updateNominal(); // initial call

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
