export class QueueEngine {
  constructor(options = {}) {
    this.sessionManager = options.sessionManager;
    this.invoiceRenderer = options.invoiceRenderer;
    this.minDelaySeconds = Number(options.minDelaySeconds || process.env.MIN_DELAY_SECONDS || 20);
    this.maxDelaySeconds = Number(options.maxDelaySeconds || process.env.MAX_DELAY_SECONDS || 45);
    this.cooldownEvery = Number(options.cooldownEvery || process.env.BATCH_COOLDOWN_EVERY || 25);
    this.cooldownSeconds = Number(options.cooldownSeconds || process.env.BATCH_COOLDOWN_SECONDS || 180);

    this.status = 'idle'; // 'idle' | 'running' | 'paused' | 'stopped'
    this.batchId = null;
    this.items = [];
    this.currentIndex = 0;
    this.successCount = 0;
    this.failedCount = 0;
    this.results = [];
    this.currentDevice = null;
    this.deviceRotationIndex = 0;
    this.delayRemaining = 0;
    this.currentStudent = null;
    this.isCoolingDown = false;
    this.abortController = null;
    this.delayTimer = null;
    this.onProgressCallback = options.onProgress || null;
  }

  getRandomDelay() {
    const min = Math.min(this.minDelaySeconds, this.maxDelaySeconds);
    const max = Math.max(this.minDelaySeconds, this.maxDelaySeconds);
    return Math.floor(Math.random() * (max - min + 1)) + min;
  }

  getProgress() {
    return {
      status: this.status,
      batchId: this.batchId,
      total: this.items.length,
      current: this.currentIndex,
      success: this.successCount,
      failed: this.failedCount,
      percentage: this.items.length > 0 ? Math.round(((this.successCount + this.failedCount) / this.items.length) * 100) : 0,
      currentStudent: this.currentStudent,
      currentDevice: this.currentDevice,
      delayRemaining: this.delayRemaining,
      isCoolingDown: this.isCoolingDown,
      results: this.results.slice(-15) // 15 log terakhir
    };
  }

  async startBatch(batchId, items = []) {
    if (this.status === 'running') {
      throw new Error('Antrean pengiriman sedang berjalan. Harap tunggu atau hentikan terlebih dahulu.');
    }

    if (!items || items.length === 0) {
      throw new Error('Daftar siswa untuk dikirim tidak boleh kosong.');
    }

    const availableDevices = this.sessionManager.getAvailableDeviceIds();
    if (availableDevices.length === 0) {
      throw new Error('Tidak ada nomor WhatsApp yang terhubung. Silakan scan QR terlebih dahulu.');
    }

    this.batchId = batchId || `BATCH-${Date.now()}`;
    this.items = items;
    this.currentIndex = 0;
    this.successCount = 0;
    this.failedCount = 0;
    this.results = [];
    this.status = 'running';
    this.isCoolingDown = false;

    // Jalankan loop pengiriman secara asynchronous di background
    this.processQueue();

    return {
      success: true,
      message: `Antrean dimulai untuk ${items.length} siswa.`,
      batchId: this.batchId
    };
  }

  pause() {
    if (this.status !== 'running') return { success: false, message: 'Antrean tidak sedang berjalan.' };
    this.status = 'paused';
    return { success: true, message: 'Antrean berhasil dijeda.' };
  }

  resume() {
    if (this.status !== 'paused') return { success: false, message: 'Antrean tidak dalam status dijeda.' };
    this.status = 'running';
    this.processQueue();
    return { success: true, message: 'Antrean berhasil dilanjutkan.' };
  }

  stop() {
    this.status = 'stopped';
    this.delayRemaining = 0;
    this.currentStudent = null;
    return { success: true, message: 'Antrean berhasil dibatalkan.' };
  }

  async sleepWithCountdown(seconds, isCooldown = false) {
    this.isCoolingDown = isCooldown;
    this.delayRemaining = seconds;

    while (this.delayRemaining > 0) {
      if (this.status !== 'running') {
        this.delayRemaining = 0;
        this.isCoolingDown = false;
        return;
      }
      await new Promise((resolve) => setTimeout(resolve, 1000));
      this.delayRemaining--;
    }
    this.isCoolingDown = false;
  }

  async processQueue() {
    while (this.currentIndex < this.items.length && this.status === 'running') {
      const item = this.items[this.currentIndex];
      this.currentStudent = { id: item.id, nama: item.nama, nis: item.nis, kelas: item.kelas };

      // 1. Pilih Device aktif dengan rotasi (Round-Robin)
      const availableDevices = this.sessionManager.getAvailableDeviceIds();
      if (availableDevices.length === 0) {
        this.status = 'paused';
        this.results.push({
          siswa_id: item.id,
          nama: item.nama,
          status: 'failed',
          error: 'Semua perangkat WhatsApp terputus. Antrean dijeda otomatis.',
          timestamp: new Date().toISOString()
        });
        break;
      }

      const assignedDevice = availableDevices[this.deviceRotationIndex % availableDevices.length];
      this.deviceRotationIndex++;
      this.currentDevice = assignedDevice;
      const sock = this.sessionManager.getSocket(assignedDevice);

      try {
        // Normalisasi nomor telepon
        let cleanPhone = String(item.phone || '').replace(/[^0-9]/g, '');
        if (cleanPhone.startsWith('0')) cleanPhone = '62' + cleanPhone.slice(1);
        if (cleanPhone.startsWith('8')) cleanPhone = '62' + cleanPhone;

        const jid = `${cleanPhone}@s.whatsapp.net`;

        // 2. Validasi nomor di WhatsApp
        let isValid = false;
        try {
          const checkRes = await sock.onWhatsApp(jid);
          if (Array.isArray(checkRes) && checkRes.length > 0 && checkRes[0].exists) {
            isValid = true;
          }
        } catch (checkErr) {
          // Fallback anggap valid jika method check gagal
          isValid = true;
        }

        if (!isValid) {
          this.failedCount++;
          this.results.push({
            siswa_id: item.id,
            nama: item.nama,
            phone: cleanPhone,
            deviceId: assignedDevice,
            status: 'failed',
            error: 'Nomor tidak terdaftar di WhatsApp',
            timestamp: new Date().toISOString()
          });
          this.currentIndex++;
          continue;
        }

        // 3. Simulasi status mengetik (Typing presence)
        try {
          await sock.sendPresenceUpdate('composing', jid);
        } catch (_) {}
        await new Promise((r) => setTimeout(r, 2500));

        // 4. Render gambar invoice
        let imageBuffer = null;
        if (this.invoiceRenderer) {
          imageBuffer = await this.invoiceRenderer.renderToImageBuffer(item.invoiceData || item);
        }

        // 5. Kirim pesan ke WhatsApp
        const messagePayload = {};
        if (imageBuffer) {
          messagePayload.image = imageBuffer;
          messagePayload.caption = item.caption || '';
        } else {
          messagePayload.text = item.caption || 'Tagihan SPP';
        }

        await sock.sendMessage(jid, messagePayload);

        this.successCount++;
        this.results.push({
          siswa_id: item.id,
          nama: item.nama,
          phone: cleanPhone,
          deviceId: assignedDevice,
          status: 'sent',
          error: null,
          timestamp: new Date().toISOString()
        });
      } catch (sendError) {
        this.failedCount++;
        this.results.push({
          siswa_id: item.id,
          nama: item.nama,
          phone: item.phone,
          deviceId: assignedDevice,
          status: 'failed',
          error: sendError.message || 'Gagal mengirim pesan',
          timestamp: new Date().toISOString()
        });
      }

      this.currentIndex++;

      // Cek apakah seluruh antrean selesai
      if (this.currentIndex >= this.items.length) {
        this.status = 'idle';
        this.currentStudent = null;
        this.delayRemaining = 0;
        break;
      }

      // 6. Anti-Ban Delay & Cooldown
      if (this.status === 'running') {
        const processedTotal = this.successCount + this.failedCount;
        if (processedTotal > 0 && processedTotal % this.cooldownEvery === 0) {
          // Cooldown berkala
          await this.sleepWithCountdown(this.cooldownSeconds, true);
        } else {
          // Jeda acak (jitter)
          const delaySec = this.getRandomDelay();
          await this.sleepWithCountdown(delaySec, false);
        }
      }
    }

    if (this.status === 'running' && this.currentIndex >= this.items.length) {
      this.status = 'idle';
    }
  }
}
