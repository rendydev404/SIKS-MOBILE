<?php
/**
 * Konfigurasi Firebase Cloud Messaging untuk hosting ini.
 *
 * File ini ikut ter-deploy lewat push ke main, jadi TIDAK BOLEH berisi rahasia.
 * Project ID bukan rahasia (sudah ada di google-services.json aplikasi), dan
 * path file juga bukan rahasia. Yang rahasia hanya dua, dan keduanya sengaja
 * tidak ada di sini:
 *
 *   1. Private key Firebase, yang tinggal di service.json di luar public_html.
 *   2. cron_secret, yang harus ditulis langsung di server lewat File Manager.
 *      Lihat catatan di bawah.
 */

// Aplikasi ini bisa berada langsung di public_html, atau di dalam folder
// subdomain seperti public_html/sikssmkalamin. Jumlah tingkat menuju home
// direktori jadi berbeda, jadi jangan dipatok: telusuri ke atas beberapa
// tingkat dan kumpulkan semua lokasi yang masuk akal.
$naik = dirname(__DIR__);
$folderNaik = [];
for ($i = 0; $i < 4; $i++) {
    $naik = dirname($naik);
    $folderNaik[] = $naik;
}
// Dibalik supaya yang terjauh dari web root dicoba lebih dulu. Kalau ada dua
// salinan, yang di luar public_html yang menang - bukan yang bisa diunduh
// publik.
$folderNaik = array_reverse($folderNaik);

$kandidatServiceAccount = [];
foreach ($folderNaik as $folder) {
    $kandidatServiceAccount[] = $folder . '/service.json';
    $kandidatServiceAccount[] = $folder . '/firebase/service.json';
    $kandidatServiceAccount[] = $folder . '/private/service.json';
}

return [
    // Sama dengan project_id pada google-services.json aplikasi Android.
    'project_id' => 'siks-3819e',

    // Dicoba berurutan, yang pertama terbaca dipakai.
    'service_account_path' => $kandidatServiceAccount,

    // Dibiarkan kosong dengan sengaja. Repository ini publik, jadi menuliskan
    // secret di sini sama dengan mengumumkannya. Cron pengumuman akan menolak
    // dijalankan selama nilainya belum diisi.
    //
    // Pengumuman tetap terkirim realtime tanpa cron, karena dikirim langsung
    // dari halaman admin. Cron hanya jaring pengaman untuk percobaan ulang dan
    // sisa perangkat kalau siswanya banyak.
    //
    // Untuk mengaktifkannya nanti: buat config/firebase.secret.php di server
    // lewat File Manager, isinya
    //     <?php return ['cron_secret' => 'teks-acak-panjang'];
    // File itu tidak ikut Git dan akan terbaca otomatis di bawah ini.
];
