process.env.NODE_ENV = 'test';
import test from 'node:test';
import assert from 'node:assert/strict';
import { apiKeyMiddleware } from '../src/server.js';

test('apiKeyMiddleware blocks request without valid X-API-KEY', () => {
  const req = { path: '/api/status', headers: {} };
  let statusCode = 200;
  let responseData = null;
  const res = {
    status: (code) => {
      statusCode = code;
      return {
        json: (data) => { responseData = data; }
      };
    }
  };
  let nextCalled = false;
  apiKeyMiddleware(req, res, () => { nextCalled = true; });

  assert.equal(nextCalled, false);
  assert.equal(statusCode, 401);
  assert.ok(responseData.error.includes('Unauthorized'));
});

test('apiKeyMiddleware allows request with correct X-API-KEY', () => {
  const key = process.env.API_KEY || 'siks_secret_wa_key_2026_alamin';
  const req = { path: '/api/status', headers: { 'x-api-key': key } };
  const res = {};
  let nextCalled = false;
  apiKeyMiddleware(req, res, () => { nextCalled = true; });

  assert.equal(nextCalled, true);
});

test('apiKeyMiddleware allows /health check without API key', () => {
  const req = { path: '/health', headers: {} };
  const res = {};
  let nextCalled = false;
  apiKeyMiddleware(req, res, () => { nextCalled = true; });

  assert.equal(nextCalled, true);
});
