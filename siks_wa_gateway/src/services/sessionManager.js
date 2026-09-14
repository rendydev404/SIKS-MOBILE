import { default as makeWASocket, useMultiFileAuthState, DisconnectReason } from '@whiskeysockets/baileys';
import pino from 'pino';
import qrcode from 'qrcode';
import fs from 'fs';
import path from 'path';

export class SessionManager {
  constructor(options = {}) {
    this.sessionsDir = options.sessionsDir || process.env.SESSIONS_DIR || './data/sessions';
    this.devices = {
      device_1: { id: 'device_1', label: 'Nomor 1 (Utama)', sock: null, status: 'disconnected', qr: null, phone: null, name: null, reconnectAttempts: 0 },
      device_2: { id: 'device_2', label: 'Nomor 2 (Pendamping)', sock: null, status: 'disconnected', qr: null, phone: null, name: null, reconnectAttempts: 0 }
    };
    this.logger = pino({ level: options.logLevel || 'warn' });
    this.ensureDirectoryExists(this.sessionsDir);
  }

  ensureDirectoryExists(dir) {
    if (!fs.existsSync(dir)) {
      fs.mkdirSync(dir, { recursive: true });
    }
  }

  async initAll() {
    await this.initDevice('device_1');
    await this.initDevice('device_2');
  }

  async initDevice(deviceId) {
    const dev = this.devices[deviceId];
    if (!dev) throw new Error(`Device ID tidak valid: ${deviceId}`);

    const sessionPath = path.join(this.sessionsDir, deviceId);
    this.ensureDirectoryExists(sessionPath);

    try {
      dev.status = 'connecting';
      const { state, saveCreds } = await useMultiFileAuthState(sessionPath);

      const sock = makeWASocket({
        auth: state,
        logger: this.logger,
        printQRInTerminal: false,
        browser: ['SIKS SMK Al Amin', 'Desktop', '1.0.0'],
        syncFullHistory: false,
        generateHighQualityLinkPreview: false
      });

      dev.sock = sock;

      sock.ev.on('creds.update', saveCreds);

      sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
          try {
            dev.qr = await qrcode.toDataURL(qr, { width: 300, margin: 2 });
            dev.status = 'scan_needed';
          } catch (qrErr) {
            this.logger.error({ err: qrErr }, 'Gagal mengonversi QR ke DataURL');
          }
        }

        if (connection === 'open') {
          dev.status = 'connected';
          dev.qr = null;
          dev.reconnectAttempts = 0;
          const userJid = sock.user?.id || '';
          dev.phone = userJid.split(':')[0] || userJid.split('@')[0] || 'Unknown';
          dev.name = sock.user?.name || 'SMK Al Amin';
          this.logger.info(`[${deviceId}] WhatsApp Connected: ${dev.phone} (${dev.name})`);
        }

        if (connection === 'close') {
          const statusCode = lastDisconnect?.error?.output?.statusCode;
          const isLoggedOut = statusCode === DisconnectReason.loggedOut;

          dev.status = 'disconnected';
          dev.phone = null;
          dev.name = null;
          this.logger.warn(`[${deviceId}] WhatsApp Disconnected. Code: ${statusCode}, LoggedOut: ${isLoggedOut}`);

          if (isLoggedOut) {
            dev.qr = null;
            // Hapus folder sesi jika user memilih logout dari aplikasi WA HP
            fs.rmSync(sessionPath, { recursive: true, force: true });
            this.ensureDirectoryExists(sessionPath);
            // Buka sesi baru untuk memunculkan QR baru
            setTimeout(() => this.initDevice(deviceId), 2000);
          } else {
            // Reconnect otomatis dengan backoff
            const delay = Math.min(3000 * Math.pow(1.5, dev.reconnectAttempts++), 30000);
            setTimeout(() => this.initDevice(deviceId), delay);
          }
        }
      });

      return dev;
    } catch (error) {
      dev.status = 'error';
      dev.qr = null;
      this.logger.error({ err: error }, `Gagal menginisialisasi ${deviceId}`);
      return dev;
    }
  }

  getStatus(deviceId) {
    const dev = this.devices[deviceId];
    if (!dev) return null;
    return {
      id: dev.id,
      label: dev.label,
      status: dev.status,
      phone: dev.phone,
      name: dev.name,
      hasQr: !!dev.qr
    };
  }

  getAllStatus() {
    return {
      device_1: this.getStatus('device_1'),
      device_2: this.getStatus('device_2')
    };
  }

  getQR(deviceId) {
    const dev = this.devices[deviceId];
    if (!dev) return null;
    return dev.qr;
  }

  getSocket(deviceId) {
    const dev = this.devices[deviceId];
    if (dev && dev.status === 'connected') {
      return dev.sock;
    }
    return null;
  }

  getAvailableDeviceIds() {
    return Object.keys(this.devices).filter(
      (id) => this.devices[id].status === 'connected' && this.devices[id].sock
    );
  }

  async logout(deviceId) {
    const dev = this.devices[deviceId];
    if (!dev) throw new Error(`Device ID tidak valid: ${deviceId}`);

    if (dev.sock) {
      try {
        await dev.sock.logout();
      } catch (err) {
        this.logger.warn(`Logout error ignored: ${err.message}`);
      }
    }

    const sessionPath = path.join(this.sessionsDir, deviceId);
    fs.rmSync(sessionPath, { recursive: true, force: true });
    this.ensureDirectoryExists(sessionPath);

    dev.status = 'disconnected';
    dev.phone = null;
    dev.name = null;
    dev.qr = null;

    // Restart inisialisasi untuk memicu QR code baru
    setTimeout(() => this.initDevice(deviceId), 1500);

    return { success: true, message: `Device ${deviceId} berhasil di-logout.` };
  }
}
