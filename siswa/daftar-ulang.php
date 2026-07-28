<?php
/**
 * Manajemen Daftar Ulang & Kenaikan Kelas
 * Sistem Informasi Keuangan Sekolah - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();
checkRole(['admin']);

$pageTitle = 'Daftar Ulang';

// Get Tahun Ajaran Aktif
$taAktif = getTahunAjaranAktif($pdo);

// Proses Update Nominal Default
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_nominal_default'])) {
    $nominalBaru = str_replace(['.', ','], '', $_POST['nominal_default'] ?? '0');
    $stmtNom = $pdo->prepare("INSERT INTO setting_pembayaran (jenis, nominal, tahun_masuk) VALUES ('Daftar Ulang', ?, 0) ON DUPLICATE KEY UPDATE nominal = ?");
    $stmtNom->execute([$nominalBaru, $nominalBaru]);
    setAlert('success', 'Nominal default daftar ulang berhasil diperbarui!');
    header('Location: daftar-ulang.php');
    exit;
}

$nominalSeksarang = getNominalPembayaran($pdo, 'Daftar Ulang', 0);

// Proses Kenaikan Kelas & Daftar Ulang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_daftar_ulang'])) {
    $siswaIds = $_POST['siswa_ids'] ?? [];
    $targetKelasId = $_POST['target_kelas_id'] ?? '';
    $nominal = $_POST['nominal'] ?? 0;
    $metodeBayar = $_POST['metode_bayar'] ?? 'Tunai';
    
    if (empty($siswaIds)) {
        setAlert('error', 'Pilih minimal satu siswa!');
    } elseif (empty($targetKelasId)) {
        setAlert('error', 'Pilih kelas tujuan!');
    } else {
        try {
            $pdo->beginTransaction();
            
            $stmtUpdate = $pdo->prepare("UPDATE siswa SET kelas_id = ? WHERE id = ?");
            $stmtBayar = $pdo->prepare("
                INSERT INTO pembayaran (siswa_id, jenis_pembayaran, bulan, tahun, jumlah_bayar, tanggal_bayar, metode_bayar, keterangan, status, user_id)
                VALUES (?, 'Daftar Ulang', ?, ?, ?, CURDATE(), ?, 'Pembayaran Daftar Ulang Kenaikan Kelas', 'pending', ?)
            ");
            
            $currentMonth = date('F'); // English month name for enum if needed, but DB uses Indonesian
            $bulanIndo = getBulanIndonesia();
            $currentMonthIndo = $bulanIndo[(int)date('n') - 1];
            $currentYear = date('Y');
            
            foreach ($siswaIds as $id) {
                // 1. Update Kelas
                $stmtUpdate->execute([$targetKelasId, $id]);
                
                // 2. Catat Pembayaran (jika nominal > 0)
                if ($nominal > 0) {
                    $stmtBayar->execute([
                        $id,
                        $currentMonthIndo,
                        $currentYear,
                        $nominal,
                        $metodeBayar,
                        $_SESSION['user_id']
                    ]);
                }
            }
            
            $pdo->commit();
            setAlert('success', count($siswaIds) . ' siswa berhasil diproses daftar ulang/kenaikan kelas.');
            header('Location: daftar-ulang.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            setAlert('error', 'Gagal memproses: ' . $e->getMessage());
        }
    }
}

// Filter
$filterKelas = $_GET['kelas'] ?? '';

// Query siswa yang butuh daftar ulang (Tingkat X dan XI)
$sql = "SELECT s.*, k.nama_kelas, k.tingkat, k.jurusan 
        FROM siswa s 
        JOIN kelas k ON s.kelas_id = k.id 
        WHERE s.status = 'aktif' AND k.tingkat IN ('X', 'XI')";
$params = [];

if ($filterKelas) {
    $sql .= " AND s.kelas_id = ?";
    $params[] = $filterKelas;
}

$sql .= " ORDER BY k.tingkat, k.jurusan, s.nama";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$siswaList = $stmt->fetchAll();

// Get daftar kelas untuk filter
$kelasListSource = $pdo->query("SELECT * FROM kelas WHERE tingkat IN ('X', 'XI') ORDER BY tingkat, jurusan")->fetchAll();
$kelasListTarget = $pdo->query("SELECT * FROM kelas WHERE tingkat IN ('XI', 'XII') ORDER BY tingkat, jurusan")->fetchAll();

include '../includes/header.php';
?>

<div class="toolbar">
    <div class="filter-group">
        <form method="POST" style="display: flex; gap: 12px; align-items: center; background: rgba(99, 102, 241, 0.05); padding: 10px 15px; border-radius: 12px; border: 1px dashed var(--primary);">
            <label style="font-weight: 600; font-size: 13px;">Nominal Default Daftar Ulang:</label>
            <input type="text" name="nominal_default" class="form-control currency-input" value="<?= number_format($nominalSeksarang, 0, ',', '.') ?>" style="width: 150px; padding: 8px 12px;">
            <button type="submit" name="update_nominal_default" class="btn btn-primary btn-sm">
                <i class="fas fa-save"></i> Simpan
            </button>
        </form>
        
        <form method="GET" style="display: flex; gap: 12px; align-items: center; margin-left: auto;">
            <label>Filter Kelas Asal:</label>
            <select name="kelas" onchange="this.form.submit()">
                <option value="">Semua Kelas (Asal 10 & 11)</option>
                <?php foreach ($kelasListSource as $kelas): ?>
                    <option value="<?= $kelas['id'] ?>" <?= $filterKelas == $kelas['id'] ? 'selected' : '' ?>>
                        <?= e($kelas['nama_kelas']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<form method="POST" id="formDaftarUlang">
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header" style="padding: 16px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <h3 class="card-title">Pengaturan Kenaikan Kelas</h3>
            <div style="display: flex; gap: 12px; align-items: center;">
                <div class="form-group" style="margin-bottom: 0;">
                    <select name="target_kelas_id" class="form-control" required style="min-width: 200px;">
                        <option value="">-- Pilih Kelas Tujuan --</option>
                        <?php foreach ($kelasListTarget as $kelas): ?>
                            <option value="<?= $kelas['id'] ?>"><?= e($kelas['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <input type="text" name="nominal" id="nominalInput" class="form-control form-control-simple currency-input" placeholder="Biaya Daftar Ulang (Rp)" style="width: 200px;" value="<?= number_format($nominalSeksarang, 0, ',', '.') ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <select name="metode_bayar" class="form-control">
                        <option value="Tunai">Tunai</option>
                        <option value="Transfer">Transfer</option>
                    </select>
                </div>
                <button type="submit" name="proses_daftar_ulang" class="btn btn-primary" onclick="return confirm('Proses kenaikan kelas dan daftar ulang siswa yang dipilih?')">
                    <i class="fas fa-check-circle"></i> Proses Kenaikan
                </button>
            </div>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">
                                <input type="checkbox" id="checkAll">
                            </th>
                            <th>NIS</th>
                            <th>Nama</th>
                            <th>Kelas Saat Ini</th>
                            <th>Proses Kenaikan</th>
                            <th>Status</th>
                            <th>WhatsApp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($siswaList)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px;">
                                    <p style="color: #666;">Pilih filter kelas untuk menampilkan siswa.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($siswaList as $siswa): 
                                $noWa = formatNomorWA($siswa['no_whatsapp'] ?? '');
                                $pesanWA = "Halo *" . e($siswa['nama']) . "*,\n\nKami informasikan bahwa saat ini adalah periode *Daftar Ulang* untuk kenaikan kelas di SMK Al Amin.\n\nBesar biaya daftar ulang adalah: *RP " . number_format($nominalSeksarang, 0, ',', '.') . "*.\n\n*Harap membawa berkas:*\n- KK\n- KTP Orang Tua\n- Ijazah SMP\n\n*Ketentuan Map:*\n- MPLB: Merah\n- BDP: Kuning\n- TKJ: Biru\n\n*NOTE : APABILA SUDAH MELAKUKAN PEMBAYARAN BAIK TUNAI ATAU TF TOLONG SEGARA UPLOAD TANDA BUKTINYA.*\n\nMohon segera melakukan pembayaran di bagian Bendahara Sekolah. Terima kasih.\n\n_SIKS SMK AL Amin_";
                                $waUrl = "https://wa.me/" . $noWa . "?text=" . urlencode($pesanWA);
                            ?>
                            <tr>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="siswa_ids[]" value="<?= $siswa['id'] ?>" class="siswa-check">
                                </td>
                                <td><?= e($siswa['nis']) ?></td>
                                <td><?= e($siswa['nama']) ?></td>
                                <td><?= e($siswa['nama_kelas']) ?></td>
                                <td>
                                    <?php 
                                        $targetTingkat = ($siswa['tingkat'] == 'X') ? 'XI (11)' : 'XII (12)';
                                    ?>
                                    <span class="badge badge-info" style="opacity: 0.7;"><?= $siswa['tingkat'] ?></span>
                                    <i class="fas fa-arrow-right" style="font-size: 10px; margin: 0 5px; color: var(--primary);"></i>
                                    <span class="badge badge-success"><?= $targetTingkat ?></span>
                                </td>
                                <td><span class="badge badge-success">Aktif</span></td>
                                <td>
                                    <?php if ($noWa): ?>
                                    <a href="<?= $waUrl ?>" target="_blank" class="btn btn-sm" style="background: #25D366; color: white; padding: 5px 10px;">
                                        <i class="fab fa-whatsapp"></i> Tagih
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</form>

<script>
document.getElementById('checkAll').addEventListener('change', function() {
    const checks = document.querySelectorAll('.siswa-check');
    checks.forEach(c => c.checked = this.checked);
});

// Format mata uang
document.querySelectorAll('.currency-input').forEach(input => {
    input.addEventListener('input', function() {
        let value = this.value.replace(/\D/g, '');
        if (value) {
            this.value = new Intl.NumberFormat('id-ID').format(value);
        } else {
            this.value = '';
        }
    });
});

// Bersihkan format saat submit
document.getElementById('formDaftarUlang').addEventListener('submit', function() {
    const nominalInput = document.getElementById('nominalInput');
    nominalInput.value = nominalInput.value.replace(/\D/g, '');
});
</script>

<?php include '../includes/footer.php'; ?>
