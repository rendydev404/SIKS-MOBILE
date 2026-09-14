# WhatsApp Automated Invoice Gateway & Blast Implementation Plan

> **For Claude / Antigravity:** REQUIRED SUB-SKILL: Use superpowers:executing-plans or subagent-driven-development to implement this plan task-by-task.

**Goal:** Membangun microservice Docker Gateway WhatsApp mandiri berbasis Node.js (Baileys + Puppeteer) di VPS untuk mengirim tagihan invoice gambar + caption dinamis secara otomatis ke ~500 orang tua siswa dengan rotasi 2 nomor, timer jeda anti-ban, dan kontrol dashboard interaktif di SIKS Web.

**Architecture:** Service `siks-wa-gateway` (Node.js) berjalan di Docker container VPS (76.13.193.138), mengelola 2 sesi WhatsApp Web (Baileys) dengan auth persisten, merender gambar kartu invoice beresolusi tinggi via Puppeteer headless internal, dan memproses antrean pengiriman dengan jeda acak 20-45 detik serta cooldown berkala. Web SIKS (PHP di hosting) berkomunikasi ke gateway melalui REST API yang diamankan oleh API Key.

**Tech Stack:** Node.js (v20), `@whiskeysockets/baileys`, `puppeteer`, `express`, `qrcode`, Docker, Docker Compose, PHP 8+, MySQL (PDO), JavaScript (Vanilla, Fetch API).

---

### Task 1: Scaffolding Microservice `siks_wa_gateway` & Docker Configuration

**Files:**
- Create: `siks_wa_gateway/package.json`
- Create: `siks_wa_gateway/.env.example`
- Create: `siks_wa_gateway/Dockerfile`
- Create: `siks_wa_gateway/docker-compose.yml`
- Create: `siks_wa_gateway/.gitignore`

**Step 1: Buat file `siks_wa_gateway/package.json`**
Definisikan dependensi `@whiskeysockets/baileys`, `express`, `cors`, `puppeteer`, `qrcode`, `dotenv`, dan script start.

**Step 2: Buat file `Dockerfile` & `docker-compose.yml`**
Konfigurasikan image `node:20-bullseye-slim` dengan dependensi OS Chromium (libnss3, libatk, font liberation, dll), volume mount untuk `/data/sessions`, dan port binding (default port `3050`).

**Step 3: Verifikasi build lokal package**
Jalankan verifikasi sintaks package.json.

**Step 4: Commit**
```bash
git add siks_wa_gateway/
git commit -m "feat(gateway): scaffold siks-wa-gateway project and docker setup"
```

---

### Task 2: Baileys Dual-Device Session Manager

**Files:**
- Create: `siks_wa_gateway/src/services/sessionManager.js`
- Test: `siks_wa_gateway/tests/sessionManager.test.js`

**Step 1: Tulis unit test untuk session manager**
Test state awal device 1 & 2 (status: `disconnected`), pembuatan instance socket, dan penyimpanan QR event.

**Step 2: Implementasikan `sessionManager.js`**
- Inisialisasi 2 slot device: `device_1` dan `device_2`.
- Gunakan `useMultiFileAuthState` dengan path `./data/sessions/device_{id}`.
- Event listener `connection.update`: tangkap QR code, generate DataURL base64 via `qrcode`, deteksi status `open`, `connecting`, dan `close`.
- Tangani auto-reconnect jika disconnected bukan karena logout sengaja.
- Sediakan fungsi `getStatus(deviceId)`, `getQR(deviceId)`, `logout(deviceId)`, dan `getSocket(deviceId)`.

**Step 3: Jalankan test dan verifikasi pass**

**Step 4: Commit**
```bash
git add siks_wa_gateway/src/services/sessionManager.js siks_wa_gateway/tests/sessionManager.test.js
git commit -m "feat(gateway): implement Baileys dual-device session manager"
```

---

### Task 3: Puppeteer In-Memory Invoice Card Renderer

**Files:**
- Create: `siks_wa_gateway/src/services/invoiceRenderer.js`
- Create: `siks_wa_gateway/src/templates/invoiceTemplate.html`
- Test: `siks_wa_gateway/tests/invoiceRenderer.test.js`

**Step 1: Tulis test verifikasi render invoice**
Test fungsi `renderInvoiceImage(studentData)` mengembalikan Buffer gambar PNG yang valid dengan ukuran byte > 0.

**Step 2: Buat template `invoiceTemplate.html`**
Adaptasi kode HTML & CSS dari `pembayaran/invoice-view.php` (kartu invoice modern, gradien biru, rincian SPP, rincian biaya lainnya, kotak total, info transfer SeaBank a.n Mira Humairoh).

**Step 3: Implementasikan `invoiceRenderer.js`**
- Inisialisasi Puppeteer headless browser instance dengan flag `--no-sandbox --disable-setuid-sandbox`.
- Fungsi `generateInvoicePNG(data)`: menyuntikkan data siswa ke template HTML, set viewport 480x800 deviceScaleFactor 2 (tajam), tangkap screenshot kartu `#invoiceCard` sebagai buffer PNG.

**Step 4: Jalankan test dan pastikan hasil screenshot berupa Buffer PNG valid**

**Step 5: Commit**
```bash
git add siks_wa_gateway/src/services/invoiceRenderer.js siks_wa_gateway/src/templates/ siks_wa_gateway/tests/invoiceRenderer.test.js
git commit -m "feat(gateway): implement headless puppeteer invoice card renderer"
```

---

### Task 4: Anti-Ban Queue Engine & Dual-Device Rotation

**Files:**
- Create: `siks_wa_gateway/src/services/queueEngine.js`
- Test: `siks_wa_gateway/tests/queueEngine.test.js`

**Step 1: Tulis test logika antrean**
Test rotasi nomor ganjil-genap (device 1 vs device 2), perhitungan delay acak (20-45 detik), cooldown batch setiap 25 pesan, fungsi pause, resume, dan stop.

**Step 2: Implementasikan `queueEngine.js`**
- Antrean in-memory dengan status: `IDLE`, `RUNNING`, `PAUSED`, `STOPPED`.
- Alur per siswa:
  1. Tentukan device aktif (round-robin antara `device_1` dan `device_2`).
  2. Cek apakah nomor terdaftar di WA (`sock.onWhatsApp(phone)`). Jika tidak valid -> tandai gagal, lanjut.
  3. Kirim presence typing: `sock.sendPresenceUpdate('composing', jid)` selama 2.5 detik.
  4. Render gambar invoice via `invoiceRenderer`.
  5. Kirim pesan media: `sock.sendMessage(jid, { image: buffer, caption: text })`.
  6. Catat log hasil kirim (sukses/gagal/timestamp).
  7. Hitung jeda acak: `delay = Math.floor(Math.random() * (45 - 20 + 1) + 20) * 1000`.
  8. Cek apakah item ke-kelipatan 25 -> jika ya, jeda istirahat ekstra 180-300 detik.
- Sediakan metode `pause()`, `resume()`, `stop()`, `getProgress()`.

**Step 3: Jalankan test dan verifikasi pass**

**Step 4: Commit**
```bash
git add siks_wa_gateway/src/services/queueEngine.js siks_wa_gateway/tests/queueEngine.test.js
git commit -m "feat(gateway): implement anti-ban queue engine with dual-device rotation"
```

---

### Task 5: Gateway REST API Server

**Files:**
- Create: `siks_wa_gateway/src/server.js`
- Test: `siks_wa_gateway/tests/api.test.js`

**Step 1: Tulis test endpoint API**
Test proteksi header `X-API-KEY`, endpoint `/api/status`, `/api/devices/:id/qr`, `/api/blast/start`, `/api/blast/progress`, `/api/blast/control`.

**Step 2: Implementasikan `src/server.js`**
- Express app dengan CORS dan JSON body parser.
- Middleware auth `X-API-KEY`.
- Rute:
  - `GET /api/status`: status server dan kedua perangkat.
  - `GET /api/devices/:id/qr`: ambil QR code device 1 atau 2.
  - `POST /api/devices/:id/logout`: logout device tertentu.
  - `POST /api/blast/start`: mulai batch pengiriman.
  - `GET /api/blast/progress`: ambil progress realtime, siswa aktif, hitung mundur jeda.
  - `POST /api/blast/control`: aksi `pause`, `resume`, `stop`.

**Step 3: Jalankan test API dan verifikasi respon status 200/401/400**

**Step 4: Commit**
```bash
git add siks_wa_gateway/src/server.js siks_wa_gateway/tests/api.test.js
git commit -m "feat(gateway): implement secure REST API endpoints"
```

---

### Task 6: Konfigurasi Client Gateway & Database Log di Web SIKS

**Files:**
- Create: `config/whatsapp_gateway.php`
- Modify: `config/auto_update.php`
- Modify: `includes/functions.php`

**Step 1: Buat tabel database riwayat blast**
Di `config/auto_update.php`, tambahkan auto-migration untuk tabel `wa_blast_logs`:
- `id` (int auto_increment)
- `siswa_id` (int)
- `batch_id` (varchar)
- `no_tujuan` (varchar)
- `nomor_pengirim` (varchar)
- `bulan` (varchar)
- `tahun` (int)
- `status` (enum: pending, sent, failed)
- `pesan_error` (text null)
- `sent_at` (datetime)

**Step 2: Buat `config/whatsapp_gateway.php`**
Menyimpan konfigurasi URL Gateway VPS (misal: `http://76.13.193.138:3050`), API Key, dan fungsi helper PHP:
- `waGatewayCall($endpoint, $method, $data)`
- `getWaGatewayStatus()`
- `getWaDeviceQR($deviceId)`
- `startWaBlast($payload)`

**Step 3: Commit**
```bash
git add config/whatsapp_gateway.php config/auto_update.php includes/functions.php
git commit -m "feat(web): add gateway client helper and blast logs migration"
```

---

### Task 7: Halaman Manajemen Perangkat WhatsApp (`keuangan/whatsapp-gateway.php`)

**Files:**
- Create: `keuangan/whatsapp-gateway.php`
- Modify: `includes/header.php`

**Step 1: Buat file `keuangan/whatsapp-gateway.php`**
- Tampilan 2 Card berdampingan:
  - **Nomor 1 (Device Utama)**
  - **Nomor 2 (Device Pendamping)**
- Badge status: Hijau (Terhubung), Kuning (Menunggu Scan), Merah (Terputus).
- Tampilan QR Code interaktif yang otomatis refresh tiap 15 detik jika belum terhubung.
- Tombol "Logout / Ganti Nomor".
- Panel pengaturan jeda (Rentang detik minimal, maksimal, jeda batch).

**Step 2: Tambahkan menu di navigasi `includes/header.php`**
Tambahkan link menu "WhatsApp Gateway" di bagian menu Keuangan / Sistem.

**Step 3: Commit**
```bash
git add keuangan/whatsapp-gateway.php includes/header.php
git commit -m "feat(web): add WhatsApp multi-device manager page"
```

---

### Task 8: Peningkatan Halaman Tagihan & Modal Monitor Broadcast

**Files:**
- Modify: `pembayaran/kirim-invoice.php`
- Create: `pembayaran/ajax-blast.php`

**Step 1: Buat endpoint AJAX `pembayaran/ajax-blast.php`**
Menerima array ID siswa yang dicentang admin, menyusun payload data invoice lengkap + caption untuk tiap siswa sesuai format resmi, lalu mengirimkannya ke VPS via `waGatewayCall()`. Sediakan juga handler untuk polling status (`action=poll`) dan kontrol (`action=pause|resume|stop`).

**Step 2: Perbarui antarmuka `pembayaran/kirim-invoice.php`**
- Tambahkan Checkbox "Pilih Semua" pada header tabel.
- Tambahkan Checkbox seleksi pada setiap baris siswa menunggak.
- Tambahkan bilah aksi atas: counter *"X siswa dipilih"* dan tombol hijau **"🚀 Kirim Tagihan Otomatis ke WA"**.
- Tambahkan Modal Floating Monitor:
  - Progress bar dinamis (persentase & jumlah siswa terkirim).
  - Status teks live (siswa yang sedang diproses, nomor pengirim yang dipakai).
  - Countdown timer jeda anti-ban sebelum siswa berikutnya.
  - Tombol kontrol: **Pause**, **Resume**, **Stop**.
  - Catatan: *"Pengiriman berjalan di server VPS. Anda dapat menutup halaman ini kapan saja."*

**Step 3: Commit**
```bash
git add pembayaran/kirim-invoice.php pembayaran/ajax-blast.php
git commit -m "feat(web): add batch student selector and live blast monitor modal"
```

---

### Task 9: Deployment VPS & End-to-End Smoke Testing

**Files:**
- Create: `siks_wa_gateway/deploy-vps.sh`
- Test: Pengujian koneksi end-to-end

**Step 1: Buat script deployment `deploy-vps.sh`**
Script rsync/git pull dan `docker compose up -d --build` di VPS `76.13.193.138`.

**Step 2: Jalankan container di VPS**
Deploy service ke VPS, pastikan port 3050 terbuka dan container berstatus `healthy`.

**Step 3: Uji Coba Scan QR 2 Nomor**
Buka `keuangan/whatsapp-gateway.php` di web SIKS, scan QR untuk Device 1 dan Device 2 menggunakan 2 nomor WA uji coba.

**Step 4: Uji Coba Pengiriman Terbatas (Smoke Test)**
Pilih 2–3 nomor uji coba di halaman `kirim-invoice.php`, klik "Kirim Tagihan Otomatis ke WA". Verifikasi:
- Gambar kartu invoice terkirim tajam dan akurat.
- Caption teks tagihan dinamis lengkap.
- Penggiliran nomor berjalan (Pesan 1 via Device 1, Pesan 2 via Device 2).
- Jeda anti-ban aktif di antara pengiriman.

**Step 5: Final commit & Walkthrough documentation**
```bash
git add .
git commit -m "chore: finalize WhatsApp blast gateway setup and deployment scripts"
```
