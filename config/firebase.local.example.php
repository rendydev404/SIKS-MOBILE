<?php
/**
 * Contoh konfigurasi Firebase Cloud Messaging.
 *
 * Salin file ini menjadi config/firebase.local.php lalu isi nilainya.
 * firebase.local.php sengaja tidak ikut ke Git karena berisi rahasia.
 *
 * Cara mendapatkan service account:
 *   Firebase Console > Project settings > Service accounts >
 *   Generate new private key. Sebuah file .json akan terunduh.
 *
 * PENTING: taruh file .json itu DI LUAR public_html. Kalau diletakkan di dalam
 * web root, siapa pun bisa mengunduhnya lewat browser dan mengambil alih
 * pengiriman notifikasi atas nama sekolah.
 */

return [
    // Sama dengan project_id di google-services.json aplikasi Android.
    'project_id' => 'siks-3819e',

    // Path absolut ke service-account JSON, di luar public_html.
    // Contoh di Hostinger: /home/u288761698/firebase/siks-service-account.json
    'service_account_path' => '/home/u288761698/firebase/siks-service-account.json',

    // Nilai acak panjang, dipakai sebagai parameter ?key= pada URL cron
    // /fcm/process_queue.php. Buat sendiri, jangan pakai contoh ini.
    'cron_secret' => 'ganti-dengan-teks-acak-panjang',
];
