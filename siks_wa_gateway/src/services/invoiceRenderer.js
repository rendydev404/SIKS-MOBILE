import puppeteer from 'puppeteer';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

export class InvoiceRenderer {
  constructor(options = {}) {
    this.browser = null;
    this.executablePath = options.executablePath || process.env.PUPPETEER_EXECUTABLE_PATH || undefined;
    this.templatePath = options.templatePath || path.join(__dirname, '..', 'templates', 'invoiceTemplate.html');
    this.templateHtml = null;
    this.logoBase64 = null;
    this.initTemplate();
  }

  initTemplate() {
    if (fs.existsSync(this.templatePath)) {
      this.templateHtml = fs.readFileSync(this.templatePath, 'utf8');
    }

    // Cari logo sekolah
    const possibleLogoPaths = [
      path.join(__dirname, '..', '..', '..', 'assets', 'img', 'logo_sekolah.png'),
      path.join(__dirname, '..', 'templates', 'logo_sekolah.png')
    ];

    for (const logoPath of possibleLogoPaths) {
      if (fs.existsSync(logoPath)) {
        const bytes = fs.readFileSync(logoPath);
        this.logoBase64 = `data:image/png;base64,${bytes.toString('base64')}`;
        break;
      }
    }
  }

  async getBrowser() {
    if (!this.browser || !this.browser.connected) {
      const launchOptions = {
        headless: 'new',
        args: [
          '--no-sandbox',
          '--disable-setuid-sandbox',
          '--disable-dev-shm-usage',
          '--disable-gpu',
          '--no-first-run',
          '--no-zygote'
        ]
      };

      if (this.executablePath && fs.existsSync(this.executablePath)) {
        launchOptions.executablePath = this.executablePath;
      }

      this.browser = await puppeteer.launch(launchOptions);
    }
    return this.browser;
  }

  formatRupiah(num) {
    return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
  }

  renderHtml(studentData) {
    if (!this.templateHtml) {
      this.initTemplate();
      if (!this.templateHtml) throw new Error('Template invoice tidak ditemukan');
    }

    let html = this.templateHtml;

    const data = {
      nama: studentData.nama || '-',
      nis: studentData.nis || '-',
      kelas: studentData.kelas || studentData.nama_kelas || '-',
      taglineBulan: studentData.bulanName || studentData.taglineBulan || 'Bulan Ini',
      taglineTahun: studentData.tahun || studentData.taglineTahun || new Date().getFullYear(),
      logoBase64: this.logoBase64 || '',
      totalSppFormatted: this.formatRupiah(studentData.totalSppHanya || studentData.spp || 0),
      hasKenaikan: Number(studentData.totalKenaikan || 0) > 0,
      totalKenaikanFormatted: this.formatRupiah(studentData.totalKenaikan || 0),
      totalTunggakanFormatted: this.formatRupiah(studentData.tunggakanTotal || studentData.total || 0),
      tunggakanLainnya: studentData.tunggakanLainnya || []
    };

    // Replace scalar tags
    html = html.replace(/\{\{nama\}\}/g, data.nama)
      .replace(/\{\{nis\}\}/g, data.nis)
      .replace(/\{\{kelas\}\}/g, data.kelas)
      .replace(/\{\{taglineBulan\}\}/g, data.taglineBulan)
      .replace(/\{\{taglineTahun\}\}/g, String(data.taglineTahun))
      .replace(/\{\{logoBase64\}\}/g, data.logoBase64)
      .replace(/\{\{totalSppFormatted\}\}/g, data.totalSppFormatted)
      .replace(/\{\{totalKenaikanFormatted\}\}/g, data.totalKenaikanFormatted)
      .replace(/\{\{totalTunggakanFormatted\}\}/g, data.totalTunggakanFormatted);

    // Replace hasKenaikan block
    if (data.hasKenaikan) {
      html = html.replace(/\{\{#hasKenaikan\}\}/g, '').replace(/\{\{\/hasKenaikan\}\}/g, '');
    } else {
      html = html.replace(/\{\{#hasKenaikan\}\}[\s\S]*?\{\{\/hasKenaikan\}\}/g, '');
    }

    // Replace tunggakanLainnya block
    const lainnyaRegex = /\{\{#tunggakanLainnya\}\}([\s\S]*?)\{\{\/tunggakanLainnya\}\}/;
    const match = html.match(lainnyaRegex);
    if (match) {
      const itemTemplate = match[1];
      if (Array.isArray(data.tunggakanLainnya) && data.tunggakanLainnya.length > 0) {
        const renderedItems = data.tunggakanLainnya.map((item) => {
          return itemTemplate
            .replace(/\{\{namaBiaya\}\}/g, item.nama || item.namaBiaya || 'Biaya Lain')
            .replace(/\{\{sisaFormatted\}\}/g, this.formatRupiah(item.sisa || item.amount || 0));
        }).join('');
        html = html.replace(lainnyaRegex, renderedItems);
      } else {
        html = html.replace(lainnyaRegex, '');
      }
    }

    return html;
  }

  async renderToImageBuffer(studentData) {
    const html = this.renderHtml(studentData);
    const browser = await this.getBrowser();
    const page = await browser.newPage();

    try {
      await page.setViewport({
        width: 520,
        height: 850,
        deviceScaleFactor: 2 // Resolusi tinggi (Retina) agar teks tajam
      });

      await page.setContent(html, { waitUntil: 'domcontentloaded' });

      const card = await page.$('#invoiceCard');
      if (!card) throw new Error('Elemen #invoiceCard tidak ditemukan dalam template');

      const imageBuffer = await card.screenshot({
        type: 'png',
        omitBackground: true
      });

      return Buffer.from(imageBuffer);
    } finally {
      await page.close();
    }
  }

  async close() {
    if (this.browser) {
      await this.browser.close();
      this.browser = null;
    }
  }
}
