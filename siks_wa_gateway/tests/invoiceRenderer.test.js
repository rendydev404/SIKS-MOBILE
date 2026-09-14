import test from 'node:test';
import assert from 'node:assert/strict';
import { InvoiceRenderer } from '../src/services/invoiceRenderer.js';

test('InvoiceRenderer.renderHtml replaces all variables accurately', () => {
  const renderer = new InvoiceRenderer();
  const sampleData = {
    nama: 'Muhammad Rizki',
    nis: '20241001',
    kelas: 'X TKJ 1',
    taglineBulan: 'September',
    taglineTahun: 2026,
    totalSppHanya: 150000,
    totalKenaikan: 25000,
    tunggakanTotal: 175000,
    tunggakanLainnya: [
      { namaBiaya: 'Ujian Praktik', sisa: 50000 }
    ]
  };

  const html = renderer.renderHtml(sampleData);
  assert.ok(html.includes('Muhammad Rizki'));
  assert.ok(html.includes('20241001'));
  assert.ok(html.includes('X TKJ 1'));
  assert.ok(html.includes('September 2026'));
  assert.ok(html.includes('Rp 150.000'));
  assert.ok(html.includes('Rp 25.000'));
  assert.ok(html.includes('Ujian Praktik'));
  assert.ok(html.includes('Rp 50.000'));
  assert.ok(html.includes('SeaBank: 9016 1237 8561'));
  assert.ok(html.includes('Mira Humairoh'));
});
