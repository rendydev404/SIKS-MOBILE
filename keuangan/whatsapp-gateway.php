<?php
/**
 * WhatsApp Multi-Device Gateway Manager
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();

// Tangani AJAX request dari frontend
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $action = $_GET['ajax'];

    if ($action === 'status') {
        $res = getWaGatewayStatus();
        echo json_encode($res);
        exit;
    }

    if ($action === 'qr') {
        $devId = $_GET['device_id'] ?? 'device_1';
        $res = getWaDeviceQR($devId);
        echo json_encode($res);
        exit;
    }

    if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $devId = $_POST['device_id'] ?? 'device_1';
        $res = logoutWaDevice($devId);
        echo json_encode($res);
        exit;
    }

    if ($action === 'send_test' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $phone = $_POST['phone'] ?? '';
        $nama = $_POST['nama'] ?? 'Siswa Uji Coba';
        $res = sendWaTestMessage([
            'phone' => $phone,
            'nama' => $nama
        ]);
        echo json_encode($res);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Aksi AJAX tidak valid']);
    exit;
}

$pageTitle = 'WhatsApp Gateway (Multi-Device)';
include '../includes/header.php';
?>

<style>
    .wa-card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }
    .wa-device-card {
        background: var(--bg-card);
        border: 1.5px solid var(--border-color);
        border-radius: 16px;
        padding: 24px;
        box-shadow: var(--shadow-sm);
        transition: all 0.2s ease;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .wa-device-card.is-connected {
        border-color: #22c55e;
        background: linear-gradient(180deg, rgba(34, 197, 94, 0.04) 0%, var(--bg-card) 100%);
    }
    .wa-device-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
    }
    .wa-device-title {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .wa-device-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: #25d366;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }
    .wa-device-name {
        font-size: 16px;
        font-weight: 700;
        margin: 0;
        color: var(--text-primary);
    }
    .wa-device-subtitle {
        font-size: 12px;
        color: var(--text-muted);
        margin: 0;
    }
    .wa-qr-container {
        min-height: 240px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: var(--bg-body);
        border-radius: 14px;
        padding: 20px;
        margin-bottom: 18px;
        text-align: center;
    }
    .wa-qr-image {
        width: 200px;
        height: 200px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        background: white;
        padding: 6px;
    }
    .wa-connected-info {
        text-align: center;
        padding: 25px 15px;
    }
    .wa-connected-avatar {
        width: 68px;
        height: 68px;
        border-radius: 50%;
        background: #22c55e;
        color: white;
        font-size: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 12px;
        box-shadow: 0 4px 15px rgba(34, 197, 94, 0.35);
    }
    .wa-connected-phone {
        font-size: 18px;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 4px;
    }
    .wa-connected-desc {
        font-size: 13px;
        color: #16a34a;
        font-weight: 500;
    }
    .wa-banner-antiban {
        background: linear-gradient(135deg, #1e293b, #0f172a);
        color: white;
        border-radius: 16px;
        padding: 20px 24px;
        margin-bottom: 25px;
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        align-items: center;
        justify-content: space-between;
    }
    .wa-banner-item {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .wa-banner-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        color: #38bdf8;
    }
</style>

<div class="wa-banner-antiban">
    <div class="wa-banner-item">
        <div class="wa-banner-icon"><i class="fas fa-shield-alt"></i></div>
        <div>
            <div style="font-size: 14px; font-weight: 700;">Proteksi Anti-Ban Aktif</div>
            <div style="font-size: 12px; opacity: 0.8;">Jeda acak 20-45 detik + rotasi otomatis 2 nomor</div>
        </div>
    </div>
    <div class="wa-banner-item">
        <div class="wa-banner-icon" style="color: #4ade80;"><i class="fas fa-coffee"></i></div>
        <div>
            <div style="font-size: 14px; font-weight: 700;">Batch Cooldown</div>
            <div style="font-size: 12px; opacity: 0.8;">Istirahat 3-5 menit setiap 25 pengiriman</div>
        </div>
    </div>
    <div class="wa-banner-item">
        <div class="wa-banner-icon" style="color: #facc15;"><i class="fas fa-server"></i></div>
        <div>
            <div style="font-size: 14px; font-weight: 700;">Server Gateway Mandiri</div>
            <div style="font-size: 12px; opacity: 0.8;">Proses otomatis berjalan di Latar Belakang (Background)</div>
        </div>
    </div>
    <div>
        <a href="../pembayaran/kirim-invoice.php" class="btn btn-primary btn-sm">
            <i class="fas fa-arrow-right"></i> Buka Kirim Tagihan
        </a>
    </div>
</div>

<div class="wa-card-grid">
    <!-- Perangkat 1 -->
    <div class="wa-device-card" id="card-device_1">
        <div>
            <div class="wa-device-header">
                <div class="wa-device-title">
                    <div class="wa-device-icon"><i class="fab fa-whatsapp"></i></div>
                    <div>
                        <h3 class="wa-device-name">Nomor 1 (Utama)</h3>
                        <p class="wa-device-subtitle">Slot Pengirim Ganjil</p>
                    </div>
                </div>
                <span id="badge-device_1" class="badge" style="background: var(--warning); color: #000;">Memeriksa...</span>
            </div>

            <div class="wa-qr-container" id="container-device_1">
                <i class="fas fa-spinner fa-spin fa-2x" style="color: var(--primary);"></i>
                <p style="margin-top: 12px; font-size: 13px; color: var(--text-muted);">Menghubungkan ke Gateway...</p>
            </div>
        </div>

        <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 15px;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="fetchDeviceStatus('device_1')">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button type="button" class="btn btn-danger btn-sm" id="btn-logout-device_1" style="display: none;" onclick="logoutDevice('device_1')">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </div>
    </div>

    <!-- Perangkat 2 -->
    <div class="wa-device-card" id="card-device_2">
        <div>
            <div class="wa-device-header">
                <div class="wa-device-title">
                    <div class="wa-device-icon" style="background: #0ea5e9;"><i class="fab fa-whatsapp"></i></div>
                    <div>
                        <h3 class="wa-device-name">Nomor 2 (Pendamping)</h3>
                        <p class="wa-device-subtitle">Slot Pengirim Genap</p>
                    </div>
                </div>
                <span id="badge-device_2" class="badge" style="background: var(--warning); color: #000;">Memeriksa...</span>
            </div>

            <div class="wa-qr-container" id="container-device_2">
                <i class="fas fa-spinner fa-spin fa-2x" style="color: var(--primary);"></i>
                <p style="margin-top: 12px; font-size: 13px; color: var(--text-muted);">Menghubungkan ke Gateway...</p>
            </div>
        </div>

        <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 15px;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="fetchDeviceStatus('device_2')">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button type="button" class="btn btn-danger btn-sm" id="btn-logout-device_2" style="display: none;" onclick="logoutDevice('device_2')">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </div>
    </div>
</div>

<!-- Kartu Uji Coba Pengiriman Langsung -->
<div class="card" style="margin-bottom: 25px; border-radius: 16px; border: 1.5px solid var(--border-color); box-shadow: var(--shadow-sm);">
    <div class="card-header" style="display: flex; align-items: center; justify-content: space-between; padding: 18px 24px; border-bottom: 1px solid var(--border-color); background: var(--bg-card);">
        <h4 style="margin: 0; font-size: 15px; font-weight: 700; color: var(--text-primary);">
            <i class="fas fa-paper-plane" style="color: #22c55e; margin-right: 8px;"></i>
            Uji Coba Pengiriman Langsung (Test Kirim WA)
        </h4>
        <span class="badge badge-info" style="font-size: 11px; padding: 4px 10px;">Tes Kartu Tagihan + Teks Otomatis</span>
    </div>
    <div class="card-body" style="padding: 20px 24px;">
        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">
            Gunakan formulir ini untuk menguji pengiriman tagihan (kartu invoice HD + format pesan resmi) ke nomor WhatsApp dummy atau nomor admin sebelum melakukan broadcast massal.
        </p>
        <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
            <div style="flex: 1; min-width: 240px;">
                <label style="font-size: 13px; font-weight: 600; margin-bottom: 6px; display: block; color: var(--text-primary);">Nomor WhatsApp Penerima:</label>
                <input type="text" id="test-wa-phone" class="form-control" placeholder="Contoh: 085885497377" value="085885497377" style="height: 42px; border-radius: 10px;">
            </div>
            <div style="flex: 1; min-width: 200px;">
                <label style="font-size: 13px; font-weight: 600; margin-bottom: 6px; display: block; color: var(--text-primary);">Nama Siswa Dummy:</label>
                <input type="text" id="test-wa-nama" class="form-control" placeholder="Contoh: Siswa Uji Coba" value="Siswa Uji Coba" style="height: 42px; border-radius: 10px;">
            </div>
            <div>
                <button type="button" class="btn btn-success" id="btn-send-test" onclick="sendTestMessage()" style="height: 42px; padding: 0 20px; border-radius: 10px; font-weight: 600;">
                    <i class="fas fa-paper-plane"></i> Kirim Uji Coba Sekarang
                </button>
            </div>
        </div>
        <div id="test-result-box" style="margin-top: 15px; display: none; padding: 12px 16px; border-radius: 10px; font-size: 13px;"></div>
    </div>
</div>


<script>
async function fetchDeviceStatus(deviceId) {
    try {
        const res = await fetch(`whatsapp-gateway.php?ajax=qr&device_id=${deviceId}`);
        const data = await res.json();

        const card = document.getElementById(`card-${deviceId}`);
        const badge = document.getElementById(`badge-${deviceId}`);
        const container = document.getElementById(`container-${deviceId}`);
        const btnLogout = document.getElementById(`btn-logout-${deviceId}`);

        if (!data || !data.success) {
            badge.className = 'badge badge-danger';
            badge.textContent = 'Gateway Offline';
            container.innerHTML = `
                <i class="fas fa-exclamation-triangle fa-2x" style="color: var(--danger);"></i>
                <p style="margin-top: 10px; font-size: 13px; color: var(--danger); font-weight: 600;">
                    ${data?.error || 'Tidak dapat terhubung ke Server Gateway'}
                </p>
                <small style="color: var(--text-muted);">Pastikan layanan server gateway sedang aktif.</small>
            `;
            btnLogout.style.display = 'none';
            card.classList.remove('is-connected');
            return;
        }

        if (data.status === 'connected') {
            card.classList.add('is-connected');
            badge.className = 'badge badge-success';
            badge.textContent = 'Terhubung';
            container.innerHTML = `
                <div class="wa-connected-info">
                    <div class="wa-connected-avatar"><i class="fas fa-check"></i></div>
                    <div class="wa-connected-phone">${data.phone || 'Nomor WhatsApp'}</div>
                    <div class="wa-connected-desc"><i class="fas fa-circle" style="font-size: 9px;"></i> Siap Kirim Tagihan (${data.name || 'SMK Al Amin'})</div>
                </div>
            `;
            btnLogout.style.display = 'inline-block';
        } else if (data.qr) {
            card.classList.remove('is-connected');
            badge.className = 'badge';
            badge.style.background = '#f59e0b';
            badge.style.color = '#000';
            badge.textContent = 'Scan QR Diperlukan';
            container.innerHTML = `
                <img src="${data.qr}" alt="Scan QR WhatsApp" class="wa-qr-image">
                <p style="margin: 12px 0 4px; font-size: 13px; font-weight: 600;">Buka WhatsApp di HP &rarr; Perangkat Tertaut &rarr; Tautkan Perangkat</p>
                <small style="color: var(--text-muted);">QR Code otomatis diperbarui secara berkala</small>
            `;
            btnLogout.style.display = 'none';
        } else {
            card.classList.remove('is-connected');
            badge.className = 'badge';
            badge.style.background = '#64748b';
            badge.style.color = '#fff';
            badge.textContent = 'Menyiapkan Sesi...';
            container.innerHTML = `
                <i class="fas fa-spinner fa-spin fa-2x" style="color: var(--primary);"></i>
                <p style="margin-top: 10px; font-size: 13px; color: var(--text-muted);">Sedang membuat sesi baru...</p>
            `;
            btnLogout.style.display = 'none';
        }
    } catch (e) {
        console.error(`Error fetching device ${deviceId}:`, e);
    }
}

async function logoutDevice(deviceId) {
    if (!confirm(`Apakah Anda yakin ingin memutus koneksi WhatsApp pada ${deviceId === 'device_1' ? 'Nomor 1' : 'Nomor 2'}?`)) {
        return;
    }

    const formData = new FormData();
    formData.append('device_id', deviceId);

    try {
        const res = await fetch('whatsapp-gateway.php?ajax=logout', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            fetchDeviceStatus(deviceId);
        } else {
            alert('Gagal logout: ' + (data.error || 'Terjadi kesalahan'));
        }
    } catch (e) {
        alert('Gagal menghubungi server.');
    }
}

async function sendTestMessage() {
    const phoneInput = document.getElementById('test-wa-phone');
    const namaInput = document.getElementById('test-wa-nama');
    const btn = document.getElementById('btn-send-test');
    const resBox = document.getElementById('test-result-box');

    const phone = phoneInput.value.trim();
    const nama = namaInput.value.trim() || 'Siswa Test';

    if (!phone) {
        alert('Masukkan nomor WhatsApp tujuan!');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Merender & Mengirim...';
    resBox.style.display = 'block';
    resBox.style.background = 'rgba(14, 165, 233, 0.1)';
    resBox.style.color = '#0284c7';
    resBox.style.border = '1px solid #38bdf8';
    resBox.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sedang merender gambar invoice HD dan mengirim ke WhatsApp...';

    const formData = new FormData();
    formData.append('phone', phone);
    formData.append('nama', nama);

    try {
        const res = await fetch('whatsapp-gateway.php?ajax=send_test', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            resBox.style.background = 'rgba(34, 197, 94, 0.1)';
            resBox.style.color = '#16a34a';
            resBox.style.border = '1px solid #4ade80';
            resBox.innerHTML = `<strong><i class="fas fa-check-circle"></i> Berhasil Terkirim!</strong> ${data.message || 'Pesan terkirim.'} (Device: ${data.deviceId || 'device_1'})`;
        } else {
            resBox.style.background = 'rgba(239, 68, 68, 0.1)';
            resBox.style.color = '#dc2626';
            resBox.style.border = '1px solid #f87171';
            resBox.innerHTML = `<strong><i class="fas fa-times-circle"></i> Gagal Mengirim:</strong> ${data.error || 'Terjadi kesalahan'}`;
        }
    } catch (err) {
        resBox.style.background = 'rgba(239, 68, 68, 0.1)';
        resBox.style.color = '#dc2626';
        resBox.style.border = '1px solid #f87171';
        resBox.innerHTML = `<strong><i class="fas fa-times-circle"></i> Error:</strong> Gagal terhubung ke server atau timeout.`;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Kirim Uji Coba Sekarang';
    }
}

// Inisialisasi status awal dan polling berkala
function pollAllDevices() {
    fetchDeviceStatus('device_1');
    fetchDeviceStatus('device_2');
}

pollAllDevices();
// Refresh status otomatis setiap 6 detik
setInterval(pollAllDevices, 6000);
</script>

<?php include '../includes/footer.php'; ?>
