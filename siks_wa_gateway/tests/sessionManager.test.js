import test from 'node:test';
import assert from 'node:assert/strict';
import { SessionManager } from '../src/services/sessionManager.js';
import fs from 'fs';
import path from 'path';

test('SessionManager initializes devices with disconnected state', (t) => {
  const tmpDir = path.join(process.cwd(), 'tests', 'tmp_sessions');
  const manager = new SessionManager({ sessionsDir: tmpDir, logLevel: 'silent' });

  const allStatus = manager.getAllStatus();
  assert.ok(allStatus.device_1);
  assert.ok(allStatus.device_2);
  assert.equal(allStatus.device_1.status, 'disconnected');
  assert.equal(allStatus.device_2.status, 'disconnected');
  assert.equal(allStatus.device_1.label, 'Nomor 1 (Utama)');
  assert.equal(allStatus.device_2.label, 'Nomor 2 (Pendamping)');

  assert.deepEqual(manager.getAvailableDeviceIds(), []);

  // Cleanup test dir
  if (fs.existsSync(tmpDir)) {
    fs.rmSync(tmpDir, { recursive: true, force: true });
  }
});

test('SessionManager handles device status and invalid device query', (t) => {
  const manager = new SessionManager({ sessionsDir: './tests/tmp_sessions', logLevel: 'silent' });
  const status1 = manager.getStatus('device_1');
  assert.equal(status1.id, 'device_1');

  const invalid = manager.getStatus('device_999');
  assert.equal(invalid, null);
});
