import express from 'express';
import cors from 'cors';
import dotenv from 'dotenv';
import { SessionManager } from './services/sessionManager.js';
import { InvoiceRenderer } from './services/invoiceRenderer.js';
import { QueueEngine } from './services/queueEngine.js';

dotenv.config();

const app = express();
const port = process.env.PORT || 3050;
const expectedApiKey = process.env.API_KEY || 'siks_secret_wa_key_2026_alamin';

app.use(cors({ origin: '*' }));
app.use(express.json({ limit: '10mb' }));

// Inisialisasi Service Utama
const sessionManager = new SessionManager({
  sessionsDir: process.env.SESSIONS_DIR || './data/sessions',
  logLevel: process.env.NODE_ENV === 'production' ? 'warn' : 'info'
});

const invoiceRenderer = new InvoiceRenderer({
  executablePath: process.env.PUPPETEER_EXECUTABLE_PATH
});

const queueEngine = new QueueEngine({
  sessionManager,
  invoiceRenderer,
  minDelaySeconds: process.env.MIN_DELAY_SECONDS,
  maxDelaySeconds: process.env.MAX_DELAY_SECONDS,
  cooldownEvery: process.env.BATCH_COOLDOWN_EVERY,
  cooldownSeconds: process.env.BATCH_COOLDOWN_SECONDS
});

// Middleware Autentikasi API Key
export function apiKeyMiddleware(req, res, next) {
  // Allow health check without API key
  if (req.path === '/health' || req.path === '/') {
    return next();
  }

  const apiKey = req.headers?.['x-api-key'] || req.query?.api_key;
  if (!apiKey || apiKey !== expectedApiKey) {
    return res.status(401).json({
      success: false,
      error: 'Unauthorized: API Key tidak valid atau tidak disertakan.'
    });
  }
  next();
}

app.use(apiKeyMiddleware);

// Health check endpoint
app.get('/health', (req, res) => {
  res.json({ status: 'ok', service: 'siks-wa-gateway', timestamp: new Date().toISOString() });
});

// 1. Status Gateway & Device
app.get('/api/status', (req, res) => {
  const devices = sessionManager.getAllStatus();
  const queue = queueEngine.getProgress();

  res.json({
    success: true,
    gateway: {
      uptime: process.uptime(),
      memory: process.memoryUsage()
    },
    devices,
    queue
  });
});

// 2. Ambil QR Code Perangkat
app.get('/api/devices/:id/qr', (req, res) => {
  const deviceId = req.params.id;
  const status = sessionManager.getStatus(deviceId);

  if (!status) {
    return res.status(404).json({ success: false, error: 'Device tidak ditemukan' });
  }

  const qr = sessionManager.getQR(deviceId);
  res.json({
    success: true,
    deviceId,
    status: status.status,
    phone: status.phone,
    name: status.name,
    qr: qr || null
  });
});

// 3. Logout Perangkat
app.post('/api/devices/:id/logout', async (req, res) => {
  const deviceId = req.params.id;
  try {
    const result = await sessionManager.logout(deviceId);
    res.json(result);
  } catch (error) {
    res.status(500).json({ success: false, error: error.message });
  }
});

// 4. Mulai Broadcast Antrean
app.post('/api/blast/start', async (req, res) => {
  const { batchId, items } = req.body;
  try {
    const result = await queueEngine.startBatch(batchId, items);
    res.json(result);
  } catch (error) {
    res.status(400).json({ success: false, error: error.message });
  }
});

// 5. Cek Progres Realtime Broadcast
app.get('/api/blast/progress', (req, res) => {
  res.json({
    success: true,
    progress: queueEngine.getProgress()
  });
});

// 6. Kontrol Antrean (Pause, Resume, Stop)
app.post('/api/blast/control', (req, res) => {
  const { action } = req.body;

  let result;
  if (action === 'pause') {
    result = queueEngine.pause();
  } else if (action === 'resume') {
    result = queueEngine.resume();
  } else if (action === 'stop') {
    result = queueEngine.stop();
  } else {
    return res.status(400).json({ success: false, error: 'Aksi tidak valid (gunakan: pause, resume, stop)' });
  }

  res.json(result);
});

// 7. Kirim Pesan Uji Coba Langsung ke 1 Nomor (Test Dummy)
app.post('/api/send-test', async (req, res) => {
  const { phone, nama, nis, kelas, caption, invoiceData, deviceId } = req.body;

  if (!phone) {
    return res.status(400).json({ success: false, error: 'Nomor telepon (phone) wajib diisi.' });
  }

  const availableDevices = sessionManager.getAvailableDeviceIds();
  if (availableDevices.length === 0) {
    return res.status(400).json({ success: false, error: 'Tidak ada nomor WhatsApp yang terhubung.' });
  }

  const targetDeviceId = (deviceId && availableDevices.includes(deviceId)) ? deviceId : availableDevices[0];
  const sock = sessionManager.getSocket(targetDeviceId);

  try {
    let cleanPhone = String(phone).replace(/[^0-9]/g, '');
    if (cleanPhone.startsWith('0')) cleanPhone = '62' + cleanPhone.slice(1);
    if (cleanPhone.startsWith('8')) cleanPhone = '62' + cleanPhone;

    const jid = `${cleanPhone}@s.whatsapp.net`;

    // Render invoice card jika ada invoiceData
    let imageBuffer = null;
    const invData = invoiceData || {
      nama: nama || 'Siswa Uji Coba',
      nis: nis || '12345',
      kelas: kelas || 'XII RPL',
      bulanName: 'September',
      tahun: 2026,
      totalSppHanya: 150000,
      totalKenaikan: 0,
      tunggakanTotal: 150000,
      tunggakanLainnya: []
    };

    try {
      const raw = await invoiceRenderer.renderToImageBuffer(invData);
      if (raw) imageBuffer = Buffer.isBuffer(raw) ? raw : Buffer.from(raw);
    } catch (e) {
      console.error('[SendTest] Render image error:', e.message);
    }

    const payload = {};
    if (imageBuffer && Buffer.isBuffer(imageBuffer)) {
      payload.image = imageBuffer;
      payload.caption = caption || `Assalamu'alaikum Warahmatullahi Wabarakatuh.\n\n*Uji Coba Pengiriman Tagihan SPP SMK Al Amin*\nNama Siswa: *${invData.nama}*\nKelas: *${invData.kelas}*\nTotal: *Rp 150.000*\n\nPesan dan kartu invoice ini dikirim otomatis via WhatsApp Gateway SIKS.`;
      payload.mimetype = 'image/png';
    } else {
      payload.text = caption || `Assalamu'alaikum Warahmatullahi Wabarakatuh.\n\n*Uji Coba Pengiriman Tagihan SPP SMK Al Amin*\nNama Siswa: *${invData.nama}*\nSistem WhatsApp Gateway SIKS berhasil terhubung.`;
    }

    const sentResult = await sock.sendMessage(jid, payload);

    res.json({
      success: true,
      message: `Pesan uji coba berhasil dikirim ke ${cleanPhone} melalui ${targetDeviceId}.`,
      deviceId: targetDeviceId,
      phone: cleanPhone,
      messageId: sentResult?.key?.id || null
    });
  } catch (error) {
    console.error('[SendTest] Gagal mengirim:', error);
    res.status(500).json({ success: false, error: error.message });
  }
});

import path from 'path';
import { fileURLToPath } from 'url';

const isDirectRun = process.argv[1] && fileURLToPath(import.meta.url) === path.resolve(process.argv[1]);

if (isDirectRun) {
  sessionManager.initAll().catch((err) => {
    console.error('Inisialisasi WhatsApp Session gagal:', err);
  });

  app.listen(port, () => {
    console.log(`🚀 SIKS WhatsApp Gateway running on port ${port}`);
  });
}

export default app;
