<?php
/**
 * Edit Siswa
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();
checkRole(['admin']);

$pageTitle = 'Edit Siswa';

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM siswa WHERE id = ?");
$stmt->execute([$id]);
$siswa = $stmt->fetch();

if (!$siswa) {
    setAlert('error', 'Data siswa tidak ditemukan!');
    header('Location: index.php');
    exit;
}

$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, jurusan")->fetchAll();

// Get Jenis Pembayaran dan Nominalnya (Per Tahun)
$settings = $pdo->query("SELECT jenis, nominal, tahun_masuk FROM setting_pembayaran")->fetchAll();
$nominalMap = [];
$nominalMapByYear = [];
foreach ($settings as $s) {
    $nominalMapByYear[$s['tahun_masuk']][$s['jenis']] = (int)$s['nominal'];
    if ($s['tahun_masuk'] == 0) {
        $nominalMap[$s['jenis']] = (int)$s['nominal'];
    }
}
$error = '';

// Get Existing Payments for Pre-checking (Awareness)
$stmtPaid = $pdo->prepare("SELECT jenis_pembayaran, bulan, tahun, jumlah_bayar FROM pembayaran WHERE siswa_id = ? AND status = 'lunas'");
$stmtPaid->execute([$id]);
$paidHistory = $stmtPaid->fetchAll(PDO::FETCH_ASSOC);

$paidMap = []; // format: [jenis][tahun][bulan] = true
foreach ($paidHistory as $ph) {
    $j = $ph['jenis_pembayaran'];
    $t = $ph['tahun'];
    $b = $ph['bulan'] ?: 'YEARLY';
    $paidMap[$j][$t][$b] = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nis = trim($_POST['nis'] ?? '');
    $nisn = trim($_POST['nisn'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $jenisKelamin = $_POST['jenis_kelamin'] ?? '';
    $noWhatsapp = formatNomorWA(trim($_POST['no_whatsapp'] ?? ''));
    $kelasId = $_POST['kelas_id'] ?? '';
    $status = $_POST['status'] ?? 'aktif';
    
    if (empty($nis) || empty($nama) || empty($jenisKelamin) || empty($kelasId)) {
        $error = 'NIS, Nama, Jenis Kelamin, dan Kelas wajib diisi!';
    } else {
        try {
            // Cek NIS duplikat
            $cek = $pdo->prepare("SELECT id FROM siswa WHERE nis = ? AND id != ?");
            $cek->execute([$nis, $id]);
            if ($cek->fetch()) {
                $error = 'NIS sudah digunakan siswa lain!';
            } else {
                $tahunMasuk = (int)($_POST['tahun_masuk'] ?? 2024);
                $stmt = $pdo->prepare("UPDATE siswa SET nis = ?, nisn = ?, nama = ?, jenis_kelamin = ?, no_whatsapp = ?, kelas_id = ?, tahun_masuk = ?, status = ? WHERE id = ?");
                $stmt->execute([
                    $nis, $nisn ?: null, $nama, $jenisKelamin, 
                    $noWhatsapp ?: null,
                    $kelasId, (int)$tahunMasuk, $status, $id
                ]);

                // 1. Handle Bulanan (SPP, Infak, Komputer) History for Edit (NEW)
                $historyData = $_POST['history'] ?? [];
                if (!empty($historyData)) {
                    // Gunakan INSERT ... SELECT WHERE NOT EXISTS agar tidak duplikat
                    $stmtPay = $pdo->prepare("
                        INSERT INTO pembayaran (siswa_id, jenis_pembayaran, bulan, tahun, jumlah_bayar, tanggal_bayar, metode_bayar, keterangan, status, user_id) 
                        SELECT ?, ?, ?, ?, ?, ?, ?, ?, 'lunas', ?
                        FROM (SELECT 1) as tmp
                        WHERE NOT EXISTS (
                            SELECT 1 FROM pembayaran 
                            WHERE siswa_id = ? AND jenis_pembayaran = ? AND bulan = ? AND tahun = ? AND status = 'lunas'
                        )
                    ");
                    
                    foreach ($historyData as $jenisBln => $tahunData) {
                        if (!in_array($jenisBln, ['SPP', 'Infak', 'Komputer'])) continue;
                        $nominalBln = (float)getNominalPembayaran($pdo, $jenisBln, $tahunMasuk);
                        
                        foreach ($tahunData as $thn => $bulanArr) {
                            foreach ($bulanArr as $blnIdx) {
                                $namaBln = getBulanIndonesia()[$blnIdx];
                                $stmtPay->execute([
                                    $id, $jenisBln, $namaBln, $thn, $nominalBln, 
                                    date('Y-m-d'), 'Tunai', 'Migrasi Data Manual (Edit)', $_SESSION['user_id'],
                                    $id, $jenisBln, $namaBln, $thn
                                ]);
                            }
                        }
                    }
                }

                // 2. Handle Administrative Migration for Edit (Awal/Cicilan)
                $adminHistory = $_POST['admin_migrate'] ?? [];
                $adminAmounts = $_POST['admin_amount'] ?? [];
                
                if (!empty($adminHistory)) {
                    $stmtAdminPay = $pdo->prepare("INSERT INTO pembayaran (siswa_id, jenis_pembayaran, bulan, tahun, jumlah_bayar, tanggal_bayar, metode_bayar, keterangan, status, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'lunas', ?)");
                    foreach ($adminHistory as $jenis) {
                        $displayJenis = $jenis;
                        $customYear = $tahunMasuk; // Gunakan tahun masuk siswa sebagai tahun dasar untuk iuran sekali bayar
                        if (strpos($jenis, '|') !== false) {
                            list($displayJenis, $customYear) = explode('|', $jenis);
                        }

                        $rawAmount = $adminAmounts[$jenis] ?? '0';
                        $amount = (int)str_replace(['.', ','], '', $rawAmount);
                        
                        if ($amount > 0) {
                            $stmtAdminPay->execute([
                                $id, $displayJenis, getBulanIndonesia()[(int)date('n')-1], (int)$customYear, 
                                $amount, date('Y-m-d'), 'Tunai', 'Migrasi Data Manual (Awal/Cicilan) - Edit', $_SESSION['user_id']
                            ]);
                        }
                    }
                }
                
                setAlert('success', 'Data siswa berhasil diperbarui!');
                header('Location: index.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Gagal menyimpan data: ' . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Form Edit Siswa</h2>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>NIS <span class="text-danger">*</span></label>
                    <input type="text" name="nis" class="form-control form-control-simple" 
                           value="<?= e($siswa['nis']) ?>" required>
                </div>
                <div class="form-group">
                    <label>NISN</label>
                    <input type="text" name="nisn" class="form-control form-control-simple" 
                           value="<?= e($siswa['nisn'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Nama Lengkap <span class="text-danger">*</span></label>
                <input type="text" name="nama" class="form-control form-control-simple" 
                       value="<?= e($siswa['nama']) ?>" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Jenis Kelamin <span class="text-danger">*</span></label>
                    <select name="jenis_kelamin" class="form-control form-control-simple" required>
                        <option value="L" <?= $siswa['jenis_kelamin'] == 'L' ? 'selected' : '' ?>>Laki-laki</option>
                        <option value="P" <?= $siswa['jenis_kelamin'] == 'P' ? 'selected' : '' ?>>Perempuan</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Kelas <span class="text-danger">*</span></label>
                    <select name="kelas_id" id="kelas_id" class="form-control form-control-simple" required onchange="updateMigrationVisibility()">
                        <?php foreach ($kelasList as $kelas): ?>
                            <option value="<?= $kelas['id'] ?>" 
                                    data-tingkat="<?= $kelas['tingkat'] ?>"
                                    data-jurusan="<?= $kelas['jurusan'] ?>"
                                    <?= $siswa['kelas_id'] == $kelas['id'] ? 'selected' : '' ?>>
                                <?= e($kelas['nama_kelas']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tahun Masuk <span class="text-danger">*</span></label>
                    <select name="tahun_masuk" class="form-control form-control-simple" required>
                        <?php for($y = 2023; $y <= date('Y') + 1; $y++): ?>
                            <option value="<?= $y ?>" <?= ($siswa['tahun_masuk'] ?? 2024) == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-control form-control-simple" required>
                        <option value="aktif" <?= ($siswa['status'] ?? 'aktif') == 'aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="lulus" <?= ($siswa['status'] ?? '') == 'lulus' ? 'selected' : '' ?>>Lulus / Alumni</option>
                        <option value="keluar" <?= ($siswa['status'] ?? '') == 'keluar' ? 'selected' : '' ?>>Keluar / Pindah</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label>No. WhatsApp</label>
                <input type="text" name="no_whatsapp" class="form-control form-control-simple" 
                       value="<?= e($siswa['no_whatsapp'] ?? '') ?>" placeholder="08xxxxxxxxxx">
            </div>
            
<?php
function isPaidPHP($paidMap, $jenis, $tahun, $bulan = 'YEARLY') {
    return isset($paidMap[$jenis][$tahun][$bulan]);
}
?>

            <div class="form-group" style="margin-top: 20px; padding: 15px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                    <i class="fas fa-history"></i> Riwayat Pembayaran Bulanan (SPP, Infak, Komputer)
                </label>
                <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 15px; font-style: italic;">
                    <i class="fas fa-info-circle"></i> Catatan: SPP sudah termasuk dengan Infak dan Komputer.
                </div>
                <div id="historyContainer">
                    <!-- Akan diisi oleh JavaScript -->
                </div>

                <div style="border-top: 2px dashed #e2e8f0; margin-top: 30px; padding-top: 25px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 10px;">
                        <i class="fas fa-file-invoice-dollar"></i> Migrasi Administrasi & Iuran Lainnya
                    </label>
                    <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 20px;">
                        Gunakan bagian ini jika siswa baru melunasi iuran lama secara manual.
                    </p>



                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
                    <?php 
                    $allCats = getPaymentCategories();
                    foreach ($allCats as $catName => $items):
                        foreach ($items as $name => $config):
                            if (isYearlyPayment($name)) continue; // Tahunan di-handle JS (jika masih ada)
                            
                            $nomDefault = getNominalPembayaran($pdo, $name, $siswa['tahun_masuk'] ?? 0);
                            $cekStat = cekPembayaran($pdo, $id, null, null, $name, $siswa['tahun_masuk'] ?? 0);
                            $isPaidItem = $cekStat['lunas'];
                            
                            // Tentukan box ID untuk item yang perlu filter kelas/jurusan
                            $boxId = '';
                            if (str_contains($name, 'Werpack')) $boxId = 'box_werpack';
                            if ($name === 'PSG / PKL') $boxId = 'box_pkl';
                            if (str_contains($name, 'Ujian Akhir')) $boxId = 'box_ujian_akhir';
                    ?>
                    <div style="background: #fff; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02); <?= $isPaidItem ? 'border: 1px solid #10b981; background: #f0fdf4;' : '' ?>" 
                         class="migrate-box" <?= $boxId ? 'id="'.$boxId.'"' : '' ?>>
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                            <input type="checkbox" name="admin_migrate[]" value="<?= e($name) ?>" id="mig_<?= md5($name) ?>" 
                                   style="width: 20px; height: 20px; cursor: pointer;"
                                   onchange="toggleMigrateInput(this, '<?= md5($name) ?>', '<?= $nomDefault ?>')" 
                                   <?= $isPaidItem ? 'checked disabled' : '' ?>>
                            <label for="mig_<?= md5($name) ?>" style="cursor: pointer; margin: 0; font-size: 0.95rem; font-weight: 700; color: #1e293b; <?= $isPaidItem ? 'color: #10b981;' : 'color: #ef4444;' ?>">
                                <?= e($name) ?> <?= $isPaidItem ? '<span style="color:#10b981; font-weight:bold;">(Lunas ✓)</span>' : '<span style="color:#ef4444; font-weight:bold;">(Belum Lunas ✗)</span>' ?>
                            </label>
                        </div>
                        <div id="wrap_<?= md5($name) ?>" style="display: none;">
                            <input type="text" name="admin_amount[<?= e($name) ?>]" id="amnt_<?= md5($name) ?>" class="form-control currency-input" placeholder="Rp 0">
                        </div>
                    </div>
                    <?php endforeach; endforeach; ?>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
function updateMigrationVisibility() {
    const kelasSelect = document.getElementById('kelas_id');
    if (!kelasSelect) return;
    const selected = kelasSelect.options[kelasSelect.selectedIndex];
    if (!selected || !selected.value) return;
    
    const tingkat = selected.getAttribute('data-tingkat');
    const jurusan = selected.getAttribute('data-jurusan');
    
    // Werpack: hanya TKJ
    const boxWerpack = document.getElementById('box_werpack');
    if (boxWerpack) boxWerpack.style.display = (jurusan === 'TKJ') ? 'block' : 'none';
    
    // PKL: Muncul untuk kelas XI, XII, dan Alumni
    const boxPkl = document.getElementById('box_pkl');
    if (boxPkl) boxPkl.style.display = (['XI', 'XII', 'Alumni'].includes(tingkat)) ? 'block' : 'none';
    
    // Ujian Akhir: Muncul untuk kelas XII dan Alumni
    const boxUjian = document.getElementById('box_ujian_akhir');
    if (boxUjian) boxUjian.style.display = (['XII', 'Alumni'].includes(tingkat)) ? 'block' : 'none';
    

}
const nominalMap = <?= json_encode($nominalMap) ?>;
const nominalMapByYear = <?= json_encode($nominalMapByYear) ?>;
const paidMap = <?= json_encode($paidMap) ?>;
const bulanList = <?= json_encode(getBulanIndonesia()) ?>;
const currentYear = <?= date('Y') ?>;

function isAlreadyPaid(jenis, tahun, bulan = 'YEARLY') {
    const isMonthly = ['SPP', 'Infak', 'Komputer'].includes(jenis);
    
    if (!isMonthly) {
        // Untuk pembayaran tahunan/sekali bayar, anggap lunas jika ada record di tahun tersebut (apapun bulannya)
        if (paidMap[jenis] && paidMap[jenis][tahun]) {
            return Object.values(paidMap[jenis][tahun]).some(v => v === true);
        }
        // Fallback jika tahun tidak spesifik (misal pendaftaran yang tahunnya null di DB)
        if (paidMap[jenis]) {
            for (let t in paidMap[jenis]) {
                if (Object.values(paidMap[jenis][t]).some(v => v === true)) return true;
            }
        }
        return false;
    }

    // Logika bulanan standar
    let paid = paidMap[jenis] && paidMap[jenis][tahun] && paidMap[jenis][tahun][bulan];
    if (!paid && (jenis === 'Infak' || jenis === 'Komputer')) {
        // Jika SPP lunas, anggap Infak dan Komputer juga lunas sesuai data historis
        paid = paidMap['SPP'] && paidMap['SPP'][tahun] && paidMap['SPP'][tahun][bulan];
    }
    return paid;
}

function getNominal(jenis, tahun) {
    if (nominalMapByYear[tahun] && nominalMapByYear[tahun][jenis] !== undefined) {
        return nominalMapByYear[tahun][jenis];
    }
    // Cek key 0, empty string, atau null (fallback PHP)
    if (nominalMapByYear[0] && nominalMapByYear[0][jenis] !== undefined) {
        return nominalMapByYear[0][jenis];
    }
    if (nominalMapByYear[''] && nominalMapByYear[''][jenis] !== undefined) {
        return nominalMapByYear[''][jenis];
    }
    return nominalMap[jenis] || 0;
}

function toggleMigrateInput(cb, idHash, defaultValue) {
    const wrap = document.getElementById('wrap_' + idHash);
    const input = document.getElementById('amnt_' + idHash);
    if (cb.checked) {
        wrap.style.display = 'block';
        if (!input.value || input.value === '0' || input.value === '') {
            input.value = new Intl.NumberFormat('id-ID').format(defaultValue);
        }
    } else {
        wrap.style.display = 'none';
        input.value = '';
    }
}

function generateHistoryUI() {
    const tahunMasukSelect = document.getElementsByName('tahun_masuk')[0];
    const tahunMasuk = parseInt(tahunMasukSelect.value) || new Date().getFullYear();
    const container = document.getElementById('historyContainer');
    container.innerHTML = '';
    
    const tahunLulus = tahunMasuk + 3;
    const maxYear = Math.min(tahunLulus, currentYear + 1);
    
    const monthlyTypes = ['SPP', 'Infak', 'Komputer'];
    
    for (let year = tahunMasuk; year <= maxYear; year++) {
        const yearDiv = document.createElement('div');
        yearDiv.style.cssText = 'margin-bottom: 20px; padding: 15px; background: #fff; border-radius: 8px; border: 1px solid #ddd; overflow-x: auto; box-shadow: 0 1px 3px rgba(0,0,0,0.05);';
        
        const header = document.createElement('div');
        header.style.cssText = 'display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; flex-wrap: wrap; gap: 10px;';
        
        let kelasLabel = '';
        if (year === tahunMasuk) kelasLabel = ' — Kelas 10 (Smt 1)';
        else if (year === tahunMasuk + 1) kelasLabel = ' — Kelas 10→11';
        else if (year === tahunMasuk + 2) kelasLabel = ' — Kelas 11→12';
        else if (year === tahunMasuk + 3) kelasLabel = ' — Kelas 12 (Smt 2)';
        
        header.innerHTML = `
            <strong style="font-size: 1.05rem; color: #1e293b;">📅 Tahun ${year}${kelasLabel}</strong>
            <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleAllType('SPP', ${year})" style="font-size: 0.75rem; padding: 4px 10px; border-radius: 4px;">Semua SPP</button>
                <button type="button" class="btn btn-sm btn-outline-info" onclick="toggleAllType('Infak', ${year})" style="font-size: 0.75rem; padding: 4px 10px; border-radius: 4px;">Semua Infak</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllType('Komputer', ${year})" style="font-size: 0.75rem; padding: 4px 10px; border-radius: 4px;">Semua Komputer</button>
            </div>
        `;
        yearDiv.appendChild(header);
        
        let startMonth = (year === tahunMasuk) ? 6 : 0;
        let endMonth = (year === tahunLulus || year === currentYear + 1) ? 5 : 11;
        
        if (startMonth > endMonth) {
            yearDiv.innerHTML = `<p class="text-muted" style="margin: 0; font-size: 0.85rem;">Tahun Masuk ${year} (Semester Ganjil dimulai Juli)</p>`;
            container.appendChild(yearDiv);
            continue;
        }

        const table = document.createElement('table');
        table.className = 'table table-bordered table-hover';
        table.style.cssText = 'font-size: 0.85rem; margin-bottom: 0; min-width: 600px;';
        
        let thead = '<thead style="background: #f8fafc;"><tr><th style="width: 120px; font-weight: 600; color: #475569;">Bulan</th>';
        monthlyTypes.forEach(t => {
            thead += `<th class="text-center" style="font-weight: 600; color: #475569;">${t}</th>`;
        });
        thead += '</tr></thead>';
        table.innerHTML = thead;
        
        let tbody = document.createElement('tbody');
        
        for (let m = startMonth; m <= endMonth; m++) {
            const blnName = bulanList[m];
            
            let tr = document.createElement('tr');
            tr.innerHTML = `<td class="align-middle" style="font-weight: 600; color: #334155;">${blnName}</td>`;
            
            monthlyTypes.forEach(type => {
                const isPaid = isAlreadyPaid(type, year, blnName);
                const id = `history_${type}_${year}_${m}`;
                const bgColor = isPaid ? '#f0fdf4' : '#fff1f2';
                const textColor = isPaid ? '#059669' : '#e11d48';
                const labelText = isPaid ? 'Lunas ✓' : 'Belum Lunas ✗';
                
                tr.innerHTML += `
                    <td class="text-center align-middle" style="background-color: ${bgColor}; transition: background 0.2s;">
                        <div style="display: flex; justify-content: center; align-items: center; gap: 8px; width: 100%; height: 100%;">
                            <input type="checkbox" name="history[${type}][${year}][]" value="${m}" id="${id}" 
                                   style="width: 16px; height: 16px; cursor: pointer; accent-color: #10b981;" 
                                   class="history-cb-${type}-${year}" ${isPaid ? 'checked disabled' : ''}>
                            <label for="${id}" style="cursor: pointer; margin: 0; font-size: 0.85rem; color: ${textColor}; font-weight: ${isPaid ? '700' : '600'}; user-select: none; white-space: nowrap;">
                                ${labelText}
                            </label>
                        </div>
                    </td>
                `;
            });
            
            tbody.appendChild(tr);
        }
        
        table.appendChild(tbody);
        yearDiv.appendChild(table);
        container.appendChild(yearDiv);
    }
}

function toggleAllType(type, year) {
    const checkboxes = document.querySelectorAll(`.history-cb-${type}-${year}:not(:disabled)`);
    if (checkboxes.length === 0) return;
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);
}



function formatAllCurrency() {
    document.querySelectorAll('.currency-input').forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value) {
                e.target.value = new Intl.NumberFormat('id-ID').format(value);
            } else {
                e.target.value = '';
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    generateHistoryUI();
    updateMigrationVisibility(); 
    document.getElementsByName('tahun_masuk')[0].addEventListener('change', () => {
    });
});
</script>
