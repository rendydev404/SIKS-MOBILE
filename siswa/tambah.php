<?php
/**
 * Tambah Siswa
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();
checkRole(['admin']);

$pageTitle = 'Tambah Siswa';

// Get data untuk form
$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, jurusan")->fetchAll();
$tahunAjaran = getTahunAjaranAktif($pdo);
$bulanList = getBulanIndonesia();

// Get Jenis Pembayaran dan Nominalnya (Per Tahun)
$settings = $pdo->query("SELECT jenis, nominal, tahun_masuk FROM setting_pembayaran")->fetchAll();
$nominalMap = []; // Default (tahun_masuk=0)
$nominalMapByYear = []; // Per tahun
foreach ($settings as $s) {
    $nominalMapByYear[$s['tahun_masuk']][$s['jenis']] = (int)$s['nominal'];
    if ($s['tahun_masuk'] == 0) {
        $nominalMap[$s['jenis']] = (int)$s['nominal'];
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nis = trim($_POST['nis'] ?? '');
    $nisn = trim($_POST['nisn'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $jenisKelamin = $_POST['jenis_kelamin'] ?? '';
    $noWhatsapp = formatNomorWA(trim($_POST['no_whatsapp'] ?? ''));
    $kelasId = $_POST['kelas_id'] ?? '';
    
    if (empty($nis) || empty($nama) || empty($jenisKelamin) || empty($kelasId)) {
        $error = 'NIS, Nama, Jenis Kelamin, dan Kelas wajib diisi!';
    } else {
        try {
            $pdo->beginTransaction();
            // Cek NIS sudah ada
            $cek = $pdo->prepare("SELECT id FROM siswa WHERE nis = ?");
            $cek->execute([$nis]);
            if ($cek->fetch()) {
                $error = 'NIS sudah terdaftar!';
            } else {
                $tahunMasuk = (int)($_POST['tahun_masuk'] ?? date('Y'));
                $stmt = $pdo->prepare("INSERT INTO siswa (nis, nisn, nama, jenis_kelamin, no_whatsapp, kelas_id, tahun_masuk, tahun_ajaran_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $nis, $nisn ?: null, $nama, $jenisKelamin, 
                    $noWhatsapp ?: null,
                    $kelasId, $tahunMasuk, $tahunAjaran['id'] ?? null
                ]);
                $siswaId = $pdo->lastInsertId();

                // Handle Historical Payments (History Pembayaran Multi-Tahun)
                $historyData = $_POST['history'] ?? [];
                if (!empty($historyData)) {
                    $nominalSpp = getNominalPembayaran($pdo, 'SPP', $tahunMasuk);
                    $stmtPay = $pdo->prepare("INSERT INTO pembayaran (siswa_id, jenis_pembayaran, bulan, tahun, jumlah_bayar, tanggal_bayar, metode_bayar, keterangan, status, user_id) VALUES (?, 'SPP', ?, ?, ?, ?, ?, ?, 'lunas', ?)");
                    foreach ($historyData as $thn => $bulanArr) {
                        foreach ($bulanArr as $blnIdx) {
                            $namaBln = $bulanList[$blnIdx];
                            $stmtPay->execute([
                                $siswaId, $namaBln, $thn, $nominalSpp, 
                                date('Y-m-d'), 'Tunai', 'Migrasi Data Manual', $_SESSION['user_id']
                            ]);
                        }
                    }
                }

                // Handle Administrative Migration (DSP, PKL, etc.) with Installment Support
                $adminHistory = $_POST['admin_migrate'] ?? [];
                $adminAmounts = $_POST['admin_amount'] ?? [];
                
                if (!empty($adminHistory)) {
                    $stmtAdminPay = $pdo->prepare("INSERT INTO pembayaran (siswa_id, jenis_pembayaran, bulan, tahun, jumlah_bayar, tanggal_bayar, metode_bayar, keterangan, status, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'lunas', ?)");
                    foreach ($adminHistory as $jenis) {
                        // Handle possible multi-year Daftar Ulang (format: "Daftar Ulang|Year")
                        $displayJenis = $jenis;
                        $customYear = $tahunMasuk; // Gunakan tahun masuk siswa sebagai tahun dasar
                        if (strpos($jenis, '|') !== false) {
                            list($displayJenis, $customYear) = explode('|', $jenis);
                        }

                        $rawAmount = $adminAmounts[$jenis] ?? '0';
                        $amount = (int)str_replace(['.', ','], '', $rawAmount);
                        
                        if ($amount > 0) {
                            $stmtAdminPay->execute([
                                $siswaId, $displayJenis, getBulanIndonesia()[(int)date('n')-1], (int)$customYear, 
                                $amount, date('Y-m-d'), 'Tunai', 'Migrasi Data Manual (Awal/Cicilan)', $_SESSION['user_id']
                            ]);
                        }
                    }
                }

                $pdo->commit();
                
                setAlert('success', 'Siswa berhasil ditambahkan!');
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
        <h2 class="card-title">Form Tambah Siswa</h2>
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
                           value="<?= e($_POST['nis'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>NISN</label>
                    <input type="text" name="nisn" class="form-control form-control-simple" 
                           value="<?= e($_POST['nisn'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Nama Lengkap <span class="text-danger">*</span></label>
                <input type="text" name="nama" class="form-control form-control-simple" 
                       value="<?= e($_POST['nama'] ?? '') ?>" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Jenis Kelamin <span class="text-danger">*</span></label>
                    <select name="jenis_kelamin" class="form-control form-control-simple" required>
                        <option value="">-- Pilih --</option>
                        <option value="L" <?= ($_POST['jenis_kelamin'] ?? '') == 'L' ? 'selected' : '' ?>>Laki-laki</option>
                        <option value="P" <?= ($_POST['jenis_kelamin'] ?? '') == 'P' ? 'selected' : '' ?>>Perempuan</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Kelas <span class="text-danger">*</span></label>
                    <select name="kelas_id" id="kelas_id" class="form-control form-control-simple" required onchange="updateMigrationVisibility()">
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach ($kelasList as $kelas): ?>
                            <option value="<?= $kelas['id'] ?>" 
                                    data-tingkat="<?= $kelas['tingkat'] ?>" 
                                    data-jurusan="<?= $kelas['jurusan'] ?>"
                                    <?= ($_POST['kelas_id'] ?? '') == $kelas['id'] ? 'selected' : '' ?>>
                                <?= e($kelas['nama_kelas']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tahun Masuk <span class="text-danger">*</span></label>
                    <select name="tahun_masuk" id="tahun_masuk" class="form-control form-control-simple" required>
                        <?php for($y = 2023; $y <= date('Y') + 1; $y++): ?>
                            <option value="<?= $y ?>" <?= ($_POST['tahun_masuk'] ?? date('Y')) == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>No. WhatsApp</label>
                <input type="text" name="no_whatsapp" class="form-control form-control-simple" 
                       value="<?= e($_POST['no_whatsapp'] ?? '') ?>" placeholder="08xxxxxxxxxx">
            </div>

            <div class="form-group" style="margin-top: 20px; padding: 15px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                <label style="font-weight: 600; display: block; margin-bottom: 10px;">
                    <i class="fas fa-history"></i> Riwayat Pembayaran (Opsional - untuk migrasi data manual)
                </label>
                <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 15px;">
                    Centang bulan yang <strong>SUDAH DIBAYAR</strong> sebelum dimasukkan ke sistem. Tahun akan muncul berdasarkan Tahun Masuk yang dipilih.
                </p>
                
                <div id="historyContainer">
                    <!-- Akan diisi oleh JavaScript -->
                </div>

                <!-- Administrative Migration Section -->
                <div style="border-top: 2px dashed #e2e8f0; margin-top: 30px; padding-top: 25px;">
                    <label style="font-weight: 800; display: block; margin-bottom: 15px; color: #334155; font-size: 1.1rem;">
                        <i class="fas fa-file-invoice-dollar" style="color: var(--primary);"></i> Migrasi Administrasi & Lainnya
                    </label>
                    <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 20px;">
                        Centang iuran yang sudah dicicil/lunas. Sistem akan otomatis mengisi harga standar, tapi Anda bisa <strong>mengubah angkanya</strong> jika murid baru membayar sebagian.
                    </p>
                    


                    <label style="font-weight: 800; display: block; margin-bottom: 15px; color: #334155; font-size: 1.1rem;">
                        <i class="fas fa-tags" style="color: var(--primary);"></i> Biaya Sekali Lulus (Satu Kali Bayar)
                    </label>

                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
                        <?php 
                        $allCats = getPaymentCategories();
                        foreach ($allCats as $catName => $items):
                            foreach ($items as $name => $config):
                                // Skip yang disetting tahunan
                                if (isYearlyPayment($name)) continue;

                                $nomDefault = getNominalPembayaran($pdo, $name, 0); // Biaya sekali bayar, default global
                                // Syarat tampil item (harus cocok dengan aturan di hitungTunggakan)
                                $req = '';
                                if (isPembayaranKhususTKJ($name)) $req = 'tkj';
                                elseif (str_contains($name, 'Kelas 11') || str_contains($name, 'PKL')) $req = 'XI';
                                elseif (str_contains($name, 'Kelas 12') || str_contains($name, 'DAT')) $req = 'XII';
                        ?>
                        <div style="background: #fff; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: all 0.3s; <?= $req ? 'display: none;' : '' ?>" 
                             class="migrate-box" data-req="<?= $req ?>">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                                <input type="checkbox" name="admin_migrate[]" value="<?= e($name) ?>" id="mig_<?= md5($name) ?>" 
                                       style="width: 20px; height: 20px; cursor: pointer;"
                                       onchange="toggleMigrateInput(this, '<?= md5($name) ?>', '<?= $nomDefault ?>')">
                                <label for="mig_<?= md5($name) ?>" style="cursor: pointer; margin: 0; font-size: 0.95rem; font-weight: 700; color: #1e293b;"><?= e($name) ?></label>
                            </div>
                            <div id="wrap_<?= md5($name) ?>" style="display: none; position: relative; animation: fadeIn 0.3s ease;">
                                <div style="position: relative; margin-bottom: 5px;">
                                    <span style="position: absolute; left: 12px; top: 12px; font-size: 14px; color: #64748b; font-weight: 600;">Rp</span>
                                    <input type="text" name="admin_amount[<?= e($name) ?>]" id="amnt_<?= md5($name) ?>"
                                           class="form-control currency-input" 
                                           style="padding-left: 40px; font-weight: 800; color: var(--primary); background: #f8fafc;"
                                           placeholder="0">
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0 5px;">
                                    <small style="color: #94a3b8; font-size: 11px;">Tagihan: <?= formatRupiah($nomDefault) ?></small>
                                    <span style="font-size: 10px; color: var(--primary); font-weight: 600; cursor: pointer; text-decoration: underline;" onclick="document.getElementById('amnt_<?= md5($name) ?>').value = '<?= number_format($nomDefault, 0, ',', '.') ?>'">Set Lunas</span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; endforeach; ?>
                    </div>
                </div>
            </div>

            
            <div class="form-actions">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Simpan
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
const nominalMap = <?= json_encode($nominalMap) ?>;
const nominalMapByYear = <?= json_encode($nominalMapByYear) ?>;
const bulanList = <?= json_encode($bulanList) ?>;
const currentYear = <?= date('Y') ?>;
const currentMonth = <?= date('n') ?>;

function generateHistoryUI() {
    const tahunMasuk = parseInt(document.getElementById('tahun_masuk').value);
    const container = document.getElementById('historyContainer');
    container.innerHTML = '';
    
    // Generate dari Juli tahun masuk sampai Juni tahun lulus (3 tahun ajaran)
    // Angkatan 2023: 2023(Jul-Des), 2024(Jan-Des), 2025(Jan-Des), 2026(Jan-Jun)
    const tahunLulus = tahunMasuk + 3; // Tahun kalender terakhir (semester genap kelas 12)
    const maxYear = Math.min(tahunLulus, currentYear + 1); // Tidak melebihi tahun depan
    
    for (let year = tahunMasuk; year <= maxYear; year++) {
        const yearDiv = document.createElement('div');
        yearDiv.style.cssText = 'margin-bottom: 20px; padding: 10px; background: #fff; border-radius: 6px; border: 1px solid #ddd;';
        
        const header = document.createElement('div');
        header.style.cssText = 'display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;';
        
        // Label kelas berdasarkan tahun
        let kelasLabel = '';
        if (year === tahunMasuk) kelasLabel = ' — Kelas 10 (Smt 1)';
        else if (year === tahunMasuk + 1) kelasLabel = ' — Kelas 10→11';
        else if (year === tahunMasuk + 2) kelasLabel = ' — Kelas 11→12';
        else if (year === tahunMasuk + 3) kelasLabel = ' — Kelas 12 (Smt 2)';
        
        header.innerHTML = `
            <strong style="font-size: 1rem; color: #333;">📅 Tahun ${year}${kelasLabel}</strong>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleAll(${year})" style="font-size: 0.75rem; padding: 2px 8px;">
                Pilih Semua
            </button>
        `;
        yearDiv.appendChild(header);
        
        const grid = document.createElement('div');
        grid.className = 'history-grid';
        grid.style.cssText = 'display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 8px;';
        
        // Tentukan bulan mulai (0 = Januari, 6 = Juli)
        // Tahun masuk dimulai dari Juli, tahun-tahun berikutnya dari Januari
        let startMonth = (year === tahunMasuk) ? 6 : 0;
        
        // Tentukan bulan akhir
        // - Tahun terakhir (tahunMasuk+3): hanya sampai Juni (akhir semester genap kelas 12)
        // - Tahun sekarang+1: juga sampai Juni
        // - Tahun lainnya: sampai Desember
        let endMonth;
        if (year === tahunLulus || year === currentYear + 1) {
            endMonth = 5; // Sampai Juni
        } else {
            endMonth = 11; // Sampai Desember
        }
        
        // Jika startMonth > endMonth, skip tahun ini dengan pesan
        if (startMonth > endMonth) {
            yearDiv.innerHTML = `<p class="text-muted" style="margin: 0; font-size: 0.85rem;">Tahun ajaran ${year}/${year+1} belum dimulai (dimulai Juli ${year})</p>`;
            container.appendChild(yearDiv);
            continue;
        }
        
        for (let m = startMonth; m <= endMonth; m++) {
            const id = `history_${year}_${m}`;
            const div = document.createElement('div');
            div.style.cssText = 'display: flex; align-items: center; gap: 6px;';
            div.innerHTML = `
                <input type="checkbox" name="history[${year}][]" value="${m}" id="${id}" 
                       style="width: 14px; height: 14px; cursor: pointer;" class="history-cb-${year}">
                <label for="${id}" style="cursor: pointer; margin: 0; font-size: 0.9rem;">${bulanList[m].substring(0, 3)}</label>
            `;
            grid.appendChild(div);
        }
        
        yearDiv.appendChild(grid);
        container.appendChild(yearDiv);
    }
}

function toggleAll(year) {
    const checkboxes = document.querySelectorAll(`.history-cb-${year}`);
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);
}

function toggleMigrateInput(cb, idHash, defaultValue) {
    const wrap = document.getElementById('wrap_' + idHash);
    const input = document.getElementById('amnt_' + idHash);
    if (cb.checked) {
        wrap.style.display = 'block';
        if (!input.value || input.value === '0') {
            input.value = new Intl.NumberFormat('id-ID').format(defaultValue);
        }
    } else {
        wrap.style.display = 'none';
        input.value = '';
    }
}


// Format mata uang (Extended for all current & future inputs)
function formatAllCurrency() {
    document.querySelectorAll('.currency-input').forEach(input => {
        input.removeEventListener('input', currencyHandler); // Avoid dupes
        input.addEventListener('input', currencyHandler);
    });
}

function currencyHandler(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value) {
        e.target.value = new Intl.NumberFormat('id-ID').format(value);
    } else {
        e.target.value = '';
    }
}

function updateMigrationVisibility() {

    const kelasSelect = document.getElementById('kelas_id');
    if (!kelasSelect) return;
    const selected = kelasSelect.options[kelasSelect.selectedIndex];
    
    if (!selected || !selected.value) return;
    
    const tingkat = selected.getAttribute('data-tingkat');
    const jurusan = selected.getAttribute('data-jurusan');
    
    // Tampilkan item sesuai jurusan/tingkat kelas yang dipilih.
    document.querySelectorAll('.migrate-box').forEach(function(box) {
        var req = box.getAttribute('data-req') || '';
        var show = true;
        if (req === 'tkj') {
            show = (jurusan || '').toUpperCase().indexOf('TKJ') !== -1;
        } else if (req === 'XI') {
            show = ['XI', 'XII', 'Alumni'].indexOf(tingkat) !== -1;
        } else if (req === 'XII') {
            show = ['XII', 'Alumni'].indexOf(tingkat) !== -1;
        }
        box.style.display = show ? 'block' : 'none';
    });

}



document.getElementById('tahun_masuk').addEventListener('change', () => {
    generateHistoryUI();
});
document.addEventListener('DOMContentLoaded', () => {
    generateHistoryUI();
    formatAllCurrency();
    updateMigrationVisibility();
});
</script>
