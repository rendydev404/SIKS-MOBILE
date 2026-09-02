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

// Satu tingkat di atas public_html. __DIR__ adalah public_html/config, jadi dua
// tingkat naik dari sini. Dipakai supaya path tetap benar baik saat diakses
// lewat web maupun saat cron dijalankan dari CLI.
$diLuarWebRoot = dirname(dirname(__DIR__));

return [
    // Sama dengan project_id pada google-services.json aplikasi Android.
    'project_id' => 'siks-3819e',

    // Dicoba berurutan, yang pertama terbaca dipakai. Daftar ini menutupi
    // tempat-tempat yang wajar untuk menaruh service.json di luar public_html,
    // sehingga tidak perlu ditebak persis satu folder.
    'service_account_path' => [
        $diLuarWebRoot . '/service.json',
        $diLuarWebRoot . '/firebase/service.json',
        $diLuarWebRoot . '/private/service.json',
        $diLuarWebRoot . '/config/service.json',
    ],

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
