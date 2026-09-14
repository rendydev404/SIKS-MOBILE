import test from 'node:test';
import assert from 'node:assert/strict';
import { QueueEngine } from '../src/services/queueEngine.js';

test('QueueEngine initializes with idle status and default anti-ban delays', () => {
  const engine = new QueueEngine({
    sessionManager: { getAvailableDeviceIds: () => [] },
    minDelaySeconds: 10,
    maxDelaySeconds: 20,
    cooldownEvery: 25
  });

  const progress = engine.getProgress();
  assert.equal(progress.status, 'idle');
  assert.equal(progress.total, 0);
  assert.equal(progress.percentage, 0);

  const delay = engine.getRandomDelay();
  assert.ok(delay >= 10 && delay <= 20);
});

test('QueueEngine throws error if no devices are connected when starting', async () => {
  const engine = new QueueEngine({
    sessionManager: { getAvailableDeviceIds: () => [] }
  });

  await assert.rejects(async () => {
    await engine.startBatch('TEST-1', [{ id: 1, nama: 'Budi' }]);
  }, /Tidak ada nomor WhatsApp yang terhubung/);
});

test('QueueEngine controls (pause, resume, stop) update status correctly', () => {
  const engine = new QueueEngine({
    sessionManager: { getAvailableDeviceIds: () => ['device_1'] }
  });

  // Not running yet, pause should return success false
  assert.equal(engine.pause().success, false);

  engine.items = [{ id: 1, nama: 'Siswa Test' }];
  engine.currentIndex = 0;
  engine.status = 'running';
  const pauseRes = engine.pause();
  assert.equal(pauseRes.success, true);
  assert.equal(engine.status, 'paused');

  // Stub processQueue for pure unit control check
  engine.processQueue = () => {};
  const resumeRes = engine.resume();
  assert.equal(resumeRes.success, true);
  assert.equal(engine.status, 'running');

  const stopRes = engine.stop();
  assert.equal(stopRes.success, true);
  assert.equal(engine.status, 'stopped');
});
