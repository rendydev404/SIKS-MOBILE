<?php
/**
 * Setting Pembayaran Dinamis
 * Sistem Informasi Keuangan Sekolah - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();
checkRole(['admin']);

$pageTitle = 'Setting Pembayaran';

// Proses simpan pengaturan khusus (Per Angkatan)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_specific'])) {
    $jenis = $_POST['jenis_specific'] ?? 'SPP';
    $nominal = str_replace(['.', ','], '', $_POST['nominal_specific'] ?? '0');
    $tahunMasuk = (int)($_POST['tahun_masuk_specific'] ?? 0);
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO setting_pembayaran (jenis, nominal, tahun_masuk) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE nominal = VALUES(nominal)
        ");
        $stmt->execute([$jenis, $nominal, $tahunMasuk]);
        
        // Sync with legacy SPP table if it's SPP AND it's the global default (tahun_masuk = 0)
        if ($jenis === 'SPP' && $tahunMasuk == 0) {
            $tahunAjaranAktif = getTahunAjaranAktif($pdo);
            if ($tahunAjaranAktif) {
                $pdo->prepare("UPDATE spp SET nominal = ? WHERE tahun_ajaran_id = ?")
                    ->execute([$nominal, $tahunAjaranAktif['id']]);
            }
        }
        
        $targetMsg = ($tahunMasuk == 0) ? "Standar (Semua Angkatan)" : "Angkatan $tahunMasuk";
        setAlert('success', "Harga $jenis untuk $targetMsg berhasil diperbarui!");
    } catch (PDOException $e) {
        setAlert('error', 'Gagal menyimpan: ' . $e->getMessage());
    }
    header('Location: index.php');
    exit;
}

// Proses simpan pengaturan global
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        $pdo->beginTransaction();
        foreach ($_POST['nominal'] as $jenis => $nominal) {
            $cleanedNominal = str_replace(['.', ','], '', $nominal);
            // Hanya update yang default (tahun_masuk = 0) untuk menghindari overwrite setingan spesifik
            $stmt = $pdo->prepare("UPDATE setting_pembayaran SET nominal = ? WHERE jenis = ? AND tahun_masuk = 0");
            $stmt->execute([$cleanedNominal, $jenis]);
            
            // Sync with legacy SPP table if it's SPP
            if ($jenis === 'SPP') {
                $tahunAjaranAktif = getTahunAjaranAktif($pdo);
                if ($tahunAjaranAktif) {
                    $pdo->prepare("UPDATE spp SET nominal = ? WHERE tahun_ajaran_id = ?")
                        ->execute([$cleanedNominal, $tahunAjaranAktif['id']]);
                }
            }
        }
        $pdo->commit();
        setAlert('success', 'Semua pengaturan pembayaran default berhasil disimpan!');
    } catch (PDOException $e) {
        $pdo->rollBack();
        setAlert('error', 'Gagal menyimpan: ' . $e->getMessage());
    }
    header('Location: index.php');
    exit;
}

// Get all default settings for the bottom table
$settingList = $pdo->query("SELECT * FROM setting_pembayaran WHERE tahun_masuk = 0 ORDER BY (jenis = 'SPP') DESC, jenis ASC")->fetchAll();

// Get specific SPP settings
$sppSettings = $pdo->query("SELECT * FROM setting_pembayaran WHERE jenis = 'SPP' ORDER BY tahun_masuk ASC")->fetchAll();

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-cog"></i> Pengaturan Nominal Pembayaran</h2>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Atur nominal SPP secara spesifik per Angkatan (Tahun Masuk) atau set nominal standar untuk semua angkatan secara global.
        </div>
        
        <!-- PENGATURAN SPP PER ANGKATAN -->
        <div style="background: rgba(99, 102, 241, 0.05); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 12px; padding: 20px; margin-bottom: 30px;">
            <h4 style="margin-top: 0; margin-bottom: 15px; color: var(--primary-light);"><i class="fas fa-sliders-h"></i> Tarif SPP Per Angkatan</h4>
            
            <form method="POST" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 20px;">
                <input type="hidden" name="jenis_specific" value="SPP">
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 13px;">Pilih Angkatan (Tahun Masuk)</label>
                    <select name="tahun_masuk_specific" class="form-control form-control-simple" style="min-width: 200px;">
                        <option value="0">Default (Semua Angkatan)</option>
                        <?php for($y = 2023; $y <= date('Y')+1; $y++): ?>
                            <option value="<?= $y ?>">Angkatan <?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 13px;">Nominal (Rp)</label>
                    <input type="text" name="nominal_specific" class="form-control form-control-simple currency-input" 
                           placeholder="Contoh: 150.000" required style="min-width: 200px;">
                </div>
                
                <button type="submit" name="save_specific" class="btn btn-primary" style="height: 48px;">
                    <i class="fas fa-save"></i> Simpan Tarif
                </button>
            </form>
            
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php 
                $defaultSpp = 0;
                foreach($sppSettings as $s) {
                    if ($s['tahun_masuk'] == 0) $defaultSpp = $s['nominal'];
                }
                ?>
                <span class="badge" style="background: var(--bg-card); border: 1px solid var(--border-color); font-size: 13px; padding: 8px 15px;">
                    <i class="fas fa-star" style="color: var(--warning);"></i> <b>Default:</b> <?= formatRupiah($defaultSpp) ?>
                </span>
                
                <?php foreach($sppSettings as $s): if($s['tahun_masuk'] > 0): ?>
                    <span class="badge" style="background: rgba(99, 102, 241, 0.1); border: 1px solid var(--primary); color: var(--primary-light); font-size: 13px; padding: 8px 15px; display: flex; align-items: center; gap: 8px;">
                        Angkatan <?= $s['tahun_masuk'] ?>: <?= formatRupiah($s['nominal']) ?>
                    </span>
                <?php endif; endforeach; ?>
            </div>
        </div>

        <h4 style="margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;"><i class="fas fa-list"></i> Tarif Standar (Global) Modul Lainnya</h4>


        <form method="POST">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Jenis Pembayaran</th>
                            <th style="width: 300px;">Nominal Standar (Rp)</th>
                            <th>Terakhir Diperbarui</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($settingList as $s): ?>
                        <?php 
                        // Skip Infak and Komputer
                        if (in_array($s['jenis'], ['Infak', 'Komputer'])) {
                            continue;
                        }
                        ?>
                        <tr>
                            <td style="font-weight: 600; vertical-align: middle;">
                                <?= e($s['jenis']) ?>
                                <?php if ($s['jenis'] === 'SPP'): ?>
                                    (Sudah termasuk Infak & Komputer)
                                    <span class="badge badge-success" style="margin-left: 10px;">Bulanan</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span style="font-weight: 600; color: var(--text-muted);">Rp</span>
                                    <input type="text" name="nominal[<?= e($s['jenis']) ?>]" 
                                           class="form-control form-control-simple currency-input" 
                                           value="<?= number_format($s['nominal'], 0, ',', '.') ?>" required>
                                </div>
                            </td>
                            <td style="color: var(--text-muted); font-size: 12px; vertical-align: middle;">
                                <?= formatTanggal($s['updated_at'], 'd/m/Y H:i') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-actions" style="margin-top: 30px;">
                <button type="submit" name="save_settings" class="btn btn-success btn-lg">
                    <i class="fas fa-save"></i> Simpan Semua Pengaturan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.currency-input').forEach(function(input) {
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
