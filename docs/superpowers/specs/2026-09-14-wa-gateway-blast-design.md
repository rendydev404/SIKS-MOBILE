# Spesifikasi Desain: WhatsApp Automated Invoice Gateway & Blast System

**Tanggal:** 2026-09-14  
**Status:** Disetujui (Approved)  
**Tujuan:** Otomatisasi pengiriman invoice gambar dan caption tagihan SPP ke WhatsApp orang tua siswa SMK Al Amin menggunakan bot unofficial multi-device (Baileys) dengan mekanisme anti-ban yang aman.

---

## 1. Latar Belakang & Masalah
Saat ini pada sistem **SIKS-MOBILE** (Sistem Informasi Keuangan Sekolah - SMK Al Amin):
- Terdapat ~500 siswa yang terdaftar dalam sistem.
- Di halaman `pembayaran/kirim-invoice.php`, admin harus membuka halaman `invoice-view.php` satu per satu untuk setiap siswa.
- Admin harus menyalin gambar secara manual (via `html2canvas`) dan menekan Ctrl+V serta tombol Kirim di WhatsApp Web/Desktop secara berulang-ulang untuk ratusan siswa.
- Proses manual ini sangat memakan waktu, rawan terlewat, dan melelahkan bagi admin sekolah.

## 2. Tujuan Solusi
1. Membangun **Service Docker Gateway Mandiri** (`siks-wa-gateway`) di VPS berbasis Node.js.
2. Mendukung **Dual WhatsApp Account (2 Nomor)** yang dapat dihubungkan langsung dari dashboard web SIKS dengan memindai (scan) QR code.
3. Otomatisasi pembuatan **Gambar Invoice Asli** (persis sama dengan tampilan visual kartu invoice saat ini) menggunakan headless browser (Puppeteer) di dalam container secara instan.
4. Menghasilkan **Caption Pesan Dinamis** yang mencakup rincian spesifik siswa, daftar tunggakan per bulan, tunggakan lainnya, total biaya, dan info rekening sekolah resmi (SeaBank).
5. Menerapkan **Protokol Anti-Ban Humanis**:
   - Rotasi bergantian antara 2 nomor (*round-robin load balancing*).
   - Jeda waktu acak (*randomized jitter*) 20 s/d 45 detik per pesan.
   - Jeda istirahat (*cooldown*) 3–5 menit setiap 25 pengiriman.
   - Sinyal simulasi mengetik (*presence composing*) 2–3 detik sebelum pengiriman.
   - Validasi nomor WhatsApp aktif via Baileys `onWhatsApp` sebelum proses pengiriman.
6. Menyediakan antarmuka **Web Admin SIKS yang User-Friendly**:
   - Pemilihan fleksibel (Kirim Semua 500 siswa sekaligus atau pilih sebagian siswa/per kelas).
   - Monitor progres realtime (*live progress bar*, status terkirim, tombol Pause, Resume, dan Stop).
   - Pengiriman berjalan di background VPS, sehingga laptop/browser admin dapat ditutup kapan saja tanpa memutus antrean.

---

## 3. Arsitektur Sistem

```
+-------------------------------------------------------------+
|                     SMK Al Amin SIKS Web                    |
|                (PHP / MySQL di Web Hosting)                 |
|                                                             |
|  [Halaman Tagihan]       [Device Manager]   [Live Monitor]  |
|  - Checkbox Siswa        - Scan QR Device 1 - Progress Bar  |
|  - Filter Kelas          - Scan QR Device 2 - Pause/Resume  |
+------------------------------+------------------------------+
                               |
                   HTTPS / REST API (API Key)
                               |
+------------------------------v------------------------------+
|             VPS Linux (76.13.193.138) - Docker              |
|               Container: siks-wa-gateway                    |
|                                                             |
|  +---------------------+      +--------------------------+  |
|  |   Express REST API  | <--> |   Anti-Ban Queue Engine  |  |
|  |  (/devices, /blast) |      | (Round-robin, Jitter)    |  |
|  +---------------------+      +-------------+------------+  |
|                                             |               |
|         +-----------------------------------+               |
|         |                                   |               |
|  +------v---------------+            +------v------------+  |
|  |  Puppeteer Renderer  |            |   Baileys Engine  |  |
|  | (Screenshot Card PNG)|            |  (Device 1 & 2)   |  |
|  +----------------------+            +--------+----------+  |
+-----------------------------------------------|-------------+
                                                |
                                   WebSocket / TLS Encrypted
                                                |
                                  +-------------v-------------+
                                  |     WhatsApp Network      |
                                  | (500 Orang Tua / Siswa)   |
                                  +---------------------------+
```

---

## 4. Komponen & Detail Implementasi

### A. Service Docker: `siks-wa-gateway` (Node.js)
Ditempatkan pada folder `/opt/siks-wa-gateway` di VPS atau subdirektori proyek:
- **Dependensi Utama:**
  - `@whiskeysockets/baileys`: Koneksi socket WhatsApp Web Multi-Device.
  - `puppeteer-core` / `puppeteer`: Render screenshot kartu invoice.
  - `express` / `cors`: REST API server.
  - `qrcode`: Konversi raw QR code Baileys menjadi Base64 DataURL agar langsung tampil di browser.
- **Manajemen Sesi:**
  - Sesi disimpan di volume persisten `/data/sessions/device_1` dan `/data/sessions/device_2` menggunakan `useMultiFileAuthState`. Sesi tetap utuh saat container restart.
- **Docker Compose & Dockerfile:**
  - Base image: `node:20-bullseye-slim` yang sudah terkonfigurasi dependensi library Chromium Linux (`libnss3`, `libatk1.0-0`, `fonts-liberation`, dll).

### B. Reproduksi Gambar Invoice & Caption Dinamis
1. **Invoice Card Image:**
   - Template HTML/CSS kartu invoice diadaptasi langsung dari `pembayaran/invoice-view.php` (header biru gradien, logo sekolah, tabel rincian SPP, rincian biaya lainnya, kotak total merah/hijau, instruksi transfer SeaBank).
   - Puppeteer merender template ini secara *in-memory* dengan viewport beresolusi 2x (retina) agar teks terlihat tajam di WhatsApp.
2. **Format Caption Pesan:**
   - Mengikuti struktur resmi saat ini:
   ```text
   Assalamu'alaikum Warahmatullahi Wabarakatuh.

   Yth. Orang Tua/Wali dari:
   Nama Siswa : *[Nama Siswa]*
   Kelas : *[Kelas]*

   Berikut rincian tagihan sampai *[Bulan] [Tahun]*:

   *1. Tagihan SPP (Rincian lihat pada Gambar)*

   *2. Tunggakan Lainnya (Keterangan):*
   - [Nama Biaya]: Rp [Nominal]

   *TOTAL TAGIHAN KESELURUHAN: Rp [Total]*

   Pembayaran dapat dilakukan melalui transfer ke rekening sekolah:
   *SeaBank: 901612378561 a.n Mira Humairoh*

   Mohon kirimkan bukti transfer setelah melakukan pembayaran. Atas perhatiannya kami ucapkan terima kasih.
   Wassalamu'alaikum Warahmatullahi Wabarakatuh.

   *Keuangan SMK Al Amin*
   ```

### C. Antarmuka Web SIKS (PHP)
1. **Menu Baru: `keuangan/whatsapp-gateway.php`:**
   - Halaman khusus untuk menghubungkan 2 Nomor WhatsApp.
   - Menampilkan status langsung (Terhubung / Butuh Scan / Terputus).
   - Menampilkan gambar QR Code secara live dengan tombol "Segarkan QR" dan "Logout Perangkat".
2. **Peningkatan Halaman `pembayaran/kirim-invoice.php`:**
   - Header tabel dilengkapi checkbox **"Pilih Semua"**.
   - Setiap baris siswa memiliki checkbox seleksi.
   - Panel aksi atas memiliki tombol:
     - **"🚀 Mulai Kirim Otomatis (WA Blast)"**
     - Indikator kesiapan 2 nomor (misal: `Nomor 1: Siap | Nomor 2: Siap`).
3. **Modal Monitor Pengiriman Realtime:**
   - Menampilkan progress bar persentase `(Terkirim / Total)`.
   - Menampilkan identitas siswa yang sedang diproses dan timer hitung mundur jeda aman.
   - Tombol kontrol: **Jeda (Pause)**, **Lanjutkan (Resume)**, dan **Batalkan (Stop)**.
   - Riwayat log ringkas hasil pengiriman siswa.

---

## 5. Spesifikasi REST API Gateway

| Method | Endpoint | Fungsi | Payload / Response |
|---|---|---|---|
| `GET` | `/api/status` | Cek status kesehatan gateway & 2 device | `{ device1: { status, phone, name }, device2: { status, phone, name } }` |
| `GET` | `/api/devices/:id/qr` | Mengambil QR code terkini perangkat 1 atau 2 | `{ qr: "data:image/png;base64,..." }` |
| `POST` | `/api/devices/:id/logout` | Memutuskan koneksi salah satu perangkat | `{ success: true, message: "Logged out" }` |
| `POST` | `/api/blast/start` | Memulai pengiriman massal baru | Payload: `{ batchId, items: [ { id, nama, phone, invoiceData, caption } ] }` |
| `GET` | `/api/blast/progress` | Mengambil progres pengiriman terkini | `{ status: "running"|"paused"|"idle", total, sent, failed, currentItem, remainingSeconds }` |
| `POST` | `/api/blast/control` | Mengatur jalannya antrean | Payload: `{ action: "pause"|"resume"|"stop" }` |

Semua request wajib menyertakan header keamanan:
`X-API-KEY: <SECRET_KEY>`

---

## 6. Rencana Pengujian & Verifikasi
1. **Verifikasi Koneksi WhatsApp:**
   - Hubungkan Nomor 1 dan Nomor 2 via scan QR code di dashboard SIKS.
   - Pastikan sesi tersimpan dan otomatis tersambung kembali saat container di-restart.
2. **Verifikasi Rendering Gambar:**
   - Uji render satu invoice siswa, pastikan file PNG beresolusi tajam, font terbaca jelas, dan data rincian akurat.
3. **Uji Coba Pengiriman Terbatas (Smoke Test):**
   - Kirim ke 2–3 nomor uji coba internal (nomor admin/pengembang).
   - Pastikan gambar invoice dan caption masuk sempurna ke chat WhatsApp.
4. **Verifikasi Antrean & Anti-Ban:**
   - Uji jeda dinamis (20-45 detik) dan pembagian gilir antara Nomor 1 dan Nomor 2.
   - Uji tombol Pause, Resume, dan Stop dari dashboard web SIKS.
5. **Uji Simulasi Beban 500 Siswa:**
   - Pastikan antrean terus berjalan di VPS saat browser admin ditutup.
