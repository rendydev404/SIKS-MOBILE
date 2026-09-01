<?php
/**
 * Diagnostik tarif pembayaran.
 *
 * Jalankan di server: php tmp/cek_tarif.php  (atau buka lewat browser)
 * Menampilkan tarif efektif tiap jenis pembayaran dan menandai yang masih 0,
 * karena tarif 0 membuat item terlihat "sudah lunas/ceklis" di portal siswa.
 * Script ini HANYA MEMBACA, tidak mengubah data.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) header('Content-Type: text/plain; charset=utf-8');

echo "=== Isi tabel setting_pembayaran ===\n";
foreach ($pdo->query("SELECT jenis, nominal, tahun_masuk FROM setting_pembayaran ORDER BY jenis, tahun_masuk") as $r) {
    $tag = ((float)$r['nominal'] <= 0) ? '  <-- NOL (bikin status jadi lunas palsu)' : '';
    printf("- %-35s angkatan %-5s = %s%s\n", $r['jenis'], $r['tahun_masuk'], number_format($r['nominal'], 0, ',', '.'), $tag);
}

echo "\n=== Tarif efektif per kategori aktif ===\n";
foreach (getPaymentCategories() as $group => $items) {
    echo "[$group]\n";
    foreach ($items as $name => $meta) {
        $nominal = (float)getNominalPembayaran($pdo, $name);
        $tag = ($nominal <= 0) ? '  <-- PERLU DIISI di menu Keuangan' : '';
        printf("  %-35s = %s%s\n", $name, number_format($nominal, 0, ',', '.'), $tag);
    }
}
