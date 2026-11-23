/**
 * IMP-69 — pruebas NEGATIVAS HTTP contra WP REST REAL en un staging LOCAL.
 * NO EJECUTADAS. No crea usuarios, no siembra datos y no realiza un POST válido.
 *
 * Ejecutar solo con autorización P-C:
 * PC_RISK_ALLOW_HTTP_NEGATIVE=I_CONFIRM_LOCAL_STAGING
 * PC_RISK_BASE_URL=http://127.0.0.1:8080/wp-json/peru-commerce/v1/
 * node --test tests/risk/http-denials.test.mjs
 *
 * No acepta hosts remotos para no apuntar por error a clientes/producción.
 * Estas pruebas verifican rechazo anónimo/CSRF/firma, NO prueban roles A/O,
 * correo real, ledger, webhooks auténticos ni concurrencia financiera.
 */
import test from 'node:test';
import assert from 'node:assert/strict';

const ack = process.env.PC_RISK_ALLOW_HTTP_NEGATIVE;
const rawBase = process.env.PC_RISK_BASE_URL;
const enabled = ack === 'I_CONFIRM_LOCAL_STAGING' && typeof rawBase === 'string' && rawBase.length > 0;
let base;
if (enabled) {
  base = new URL(rawBase);
  const loopback = new Set(['localhost', '127.0.0.1', '[::1]']);
  if (!loopback.has(base.hostname)
      || !['http:', 'https:'].includes(base.protocol)
      || base.username || base.password || base.search || base.hash
      || !base.pathname.endsWith('/wp-json/peru-commerce/v1/')) {
    throw new Error('Risk HTTP suite permits only explicit loopback WP REST staging URL');
  }
}
const json = () => ({ 'Content-Type': 'application/json' });
const deniedStatuses = new Set([400, 401, 403, 404, 409, 410, 422, 423, 429, 503]);
const cases = [
  { id: 'anonymous-orders', method: 'GET', path: 'admin/orders' },
  { id: 'anonymous-case-detail', method: 'GET', path: 'admin/orders/1' },
  { id: 'anonymous-financial', method: 'GET', path: 'admin/reconciliation' },
  { id: 'anonymous-settings', method: 'GET', path: 'admin/settings' },
  { id: 'anonymous-private-evidence', method: 'GET', path: 'admin/privacy-requests/1/evidence/synthetic' },
  { id: 'anonymous-digital-download', method: 'GET', path: 'admin/digital-files/1/download' },
  { id: 'anonymous-credentials-write', method: 'PUT', path: 'admin/payment-credentials',
    body: {}, headers: json() },
  { id: 'wrong-origin-cash-checkout', method: 'POST', path: 'public/checkout/contraentrega',
    body: { idempotency_key: '00000000-0000-4000-8000-000000000069' },
    headers: { ...json(), Origin: 'https://malicious.example.invalid' } },
  { id: 'wrong-origin-mp-checkout', method: 'POST', path: 'public/checkout/mercado-pago',
    body: { idempotency_key: '00000000-0000-4000-8000-000000000069' },
    headers: { ...json(), Origin: 'https://malicious.example.invalid' } },
  { id: 'guest-no-cookie', method: 'GET', path: 'guest/order' },
  { id: 'guest-no-grant', method: 'POST', path: 'guest/requests',
    body: { purpose: 'reissue_tracking' }, headers: json() },
  { id: 'webhook-no-signature', method: 'POST', path: 'webhooks/mercado-pago?type=order&data.id=synthetic-order',
    body: { type: 'order', data: { id: 'synthetic-order' }, live_mode: false },
    headers: json() },
];

test('Se exige opt-in explícito y URL loopback antes de cualquier HTTP', { skip: !enabled }, () => {
  assert.equal(ack, 'I_CONFIRM_LOCAL_STAGING');
  assert.ok(base.hostname === 'localhost' || base.hostname === '127.0.0.1' || base.hostname === '[::1]');
});

for (const c of cases) {
  test('REST deniega ' + c.id + ' sin actor/CSRF/firma',
    { skip: !enabled, timeout: 10000 },
    async () => {
      const url = new URL(c.path, base);
      assert.equal(url.origin, base.origin, 'No redirect/cross-origin initial URL');
      const response = await fetch(url, {
        method: c.method,
        headers: c.headers ?? {},
        body: c.body === undefined ? undefined : JSON.stringify(c.body),
        credentials: 'omit',
        redirect: 'manual',
        signal: AbortSignal.timeout(7000),
      });
      assert.ok(deniedStatuses.has(response.status),
        c.id + ': expected explicit denial, got HTTP ' + response.status);
      // No leer respuestas gigantes ni interpretar un 200 como denegación.
      const body = (await response.text()).slice(0, 32_768);
      assert.doesNotMatch(body,
        /(?:\"access_token\"\s*:\s*\"|Bearer\s+[A-Za-z0-9._-]{16,}|#tracking=|#download=|-----BEGIN .*PRIVATE KEY-----|wp-config\.php)/i,
        c.id + ': response leaks credential/private material');
      assert.equal(response.headers.get('Location'), null,
        c.id + ': unexpected redirect from denied endpoint');
    });
}
