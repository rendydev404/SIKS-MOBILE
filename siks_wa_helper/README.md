# SIKS WhatsApp Helper (Windows)

Companion ini dipakai **khusus admin yang membuka SIKS Web dari Windows**. Ia menerima PNG dan caption yang baru saja ditulis halaman invoice ke clipboard, membuka WhatsApp Desktop ke nomor siswa, menempelkan gambar, dan mengisi caption. Helper **tidak pernah menekan Kirim**; admin tetap memeriksa draft lalu menekan tombol Kirim di WhatsApp.

## Instalasi admin

1. Pastikan WhatsApp Desktop untuk Windows sudah terpasang dan sudah login.
2. Download lalu double-click `downloads\SIKSWhatsAppHelper.exe` sekali saja.
3. Kembali ke SIKS Web dan gunakan tombol WhatsApp pada invoice.

Helper menyalin dirinya ke `%LOCALAPPDATA%\SIKS WhatsApp Helper` dan mendaftarkan protokol `sikswa://` pada profil Windows admin saat ini. Tidak perlu akses administrator.

## Keamanan dan perilaku

- Hanya menerima nomor WhatsApp Indonesia dalam format `62` + 9–13 digit.
- Hanya memakai gambar dan caption yang sedang berada di clipboard; tidak menulis invoice ke disk maupun mengirim data ke jaringan.
- Tidak menekan tombol Kirim, tombol Enter, atau melakukan pengiriman otomatis.
- Bila WhatsApp tidak dapat memunculkan kotak caption, helper berhenti dan memberi tahu admin. Gambar tetap berada di draft agar dapat ditinjau.

## Build ulang

Jalankan dari root repository:

```powershell
.\siks_wa_helper\build.ps1
```

Output berada di `downloads\SIKSWhatsAppHelper.exe`. Windows 10/11 sudah menyediakan .NET Framework yang dipakai helper ini.

## Test

```powershell
.\siks_wa_helper\run-tests.ps1
node .\tests\whatsapp-web.test.js
```

## Lepas helper

Jalankan `SIKSWhatsAppHelper.exe --uninstall` dari PowerShell atau Command Prompt.
