/**
 * IMP-69 — pruebas de contrato OFFLINE sin dependencias.
 * Preparadas, NO ejecutadas ni interpretadas como evidencia P-C.
 * Futuro: node --test tests/risk/contract.test.mjs
 *
 * Verifica la matriz independiente de expectativas, no prueba PHP/WP/SQL.
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';

const root = fileURLToPath(new URL('../../', import.meta.url));
const readJson = path => JSON.parse(readFileSync(join(root, path), 'utf8'));
const scenarios = readJson('tests/risk/scenarios.json');
const fixtures = readJson('tests/risk/fixtures.json');
const spec = readJson('docs/openapi/peru-commerce-v1.openapi.draft.json');
const operations = new Map();
for (const [path, methods] of Object.entries(spec.paths)) {
  for (const [method, definition] of Object.entries(methods)) {
    if (!['get', 'post', 'put', 'patch', 'delete'].includes(method)) continue;
    assert.equal(typeof definition.operationId, 'string');
    assert.ok(!operations.has(definition.operationId), 'Duplicate operation ID: ' + definition.operationId);
    operations.set(definition.operationId, { path, method, definition });
  }
}

test('OpenAPI congelado para IMP-69: 69 REST / 66 rutas / 138 DTO, R8-18 página no REST', () => {
  assert.equal(spec.info.version, '0.12.0-draft');
  assert.equal(Object.keys(spec.paths).length, 66);
  assert.equal(Object.keys(spec.components.schemas).length, 138);
  assert.equal(operations.size, 69);
  assert.equal(operations.has('R8-18'), false);
  for (const id of ['R8-06', 'R8-07', 'R8-12', 'R8-14', 'R8-17',
                    'R8-26', 'R8-30', 'R8-47', 'R8-50', 'R8-69', 'R8-70']) {
    assert.ok(operations.has(id), 'Missing real operation ' + id);
  }
});

test('Escenarios tienen IDs, entradas, resultados y evidencia verificables sin duplicados', () => {
  assert.equal(scenarios.schema, 'perucommerce-imp69-scenarios-v1');
  assert.equal(scenarios.status, 'DESIGNED_NOT_EXECUTED');
  assert.equal(scenarios.source_contract, 'docs/openapi/peru-commerce-v1.openapi.draft.json');
  assert.ok(scenarios.cases.length >= 30, 'Expected a representative, not exhaustive, risk matrix');
  const ids = new Set();
  const modes = new Set(['unit', 'provider', 'integration', 'concurrent',
                         'environment', 'manual-ux', 'rest-denial']);
  const areas = new Set();
  for (const c of scenarios.cases) {
    assert.match(c.id, /^R69-\d{2}$/);
    assert.ok(!ids.has(c.id), 'Duplicate scenario: ' + c.id);
    ids.add(c.id);
    areas.add(c.area);
    assert.ok(modes.has(c.kind), c.id + ': invalid test mode');
    assert.ok(Array.isArray(c.operations) && Array.isArray(c.fixtures));
    assert.ok(c.setup.length > 15 && c.action.length > 15);
    assert.ok(Array.isArray(c.expected) && c.expected.length >= 2);
    assert.ok(c.expected.every(e => typeof e === 'string' && e.length >= 14));
    assert.ok(typeof c.observe === 'string' && c.observe.length > 18);
    assert.ok(Array.isArray(c.criteria) && c.criteria.length > 0);
    assert.ok(c.criteria.every(e => /^A-\d+$/.test(e)));
    assert.ok(Array.isArray(c.expected_status));
    for (const ref of c.operations) {
      const operation = operations.get(ref);
      assert.ok(operation, c.id + ': unknown OpenAPI operation ' + ref);
      for (const status of c.expected_status) {
        assert.ok(Object.hasOwn(operation.definition.responses, String(status)),
          c.id + ': HTTP ' + status + ' not declared on ' + ref);
      }
    }
    if (c.expected_status.length) {
      assert.ok(c.operations.length > 0, c.id + ': response expectation requires a REST operation');
    }
  }
  for (const area of ['money', 'inventory', 'appointments', 'payments', 'idempotency',
                      'permissions', 'privacy', 'migration', 'recovery', 'ux',
                      'digital', 'legal', 'notifications']) {
    assert.ok(areas.has(area), 'Missing risk area: ' + area);
  }
});

test('Los controles de autorización están descritos en el contrato, no en el frontend', () => {
  for (const id of ['R8-21', 'R8-25', 'R8-27', 'R8-47', 'R8-50', 'R8-69', 'R8-70']) {
    const security = operations.get(id)?.definition.security;
    assert.ok(Array.isArray(security) && security.some(item => 'WordPressSession' in item),
      id + ': missing WordPressSession security declaration');
  }
  const signed = operations.get('R8-17').definition.security;
  assert.ok(signed.some(item => 'MercadoPagoSignature' in item));
  const guest = operations.get('R8-12').definition.security;
  assert.ok(guest.some(item => 'GuestSession' in item && 'ActionGrant' in item));
});

test('Datos ficticios y totales monetarios independientes de PHP', () => {
  assert.equal(fixtures.schema, 'perucommerce-imp69-fixtures-v1');
  assert.equal(fixtures.fictional, true);
  assert.match(fixtures.site.domain, /^https:\/\/example\.invalid$/);
  assert.equal(fixtures.external.is_real, false);
  assert.equal(fixtures.site.schema_target, '1.0.2');
  assert.equal(fixtures.identities.idempotency_key.length, 36);
  const sellables = new Map(fixtures.sellables.map(s => [s.id, s]));
  assert.equal(sellables.size, fixtures.sellables.length);
  for (const cart of fixtures.carts) {
    let subtotal = 0n;
    for (const item of cart.items) {
      assert.ok(sellables.has(item.sellable_id), 'Fixture sellable absent');
      assert.ok(Number.isInteger(item.quantity) && item.quantity >= 1);
      subtotal += BigInt(sellables.get(item.sellable_id).price_cents) * BigInt(item.quantity);
    }
    const total = subtotal + BigInt(cart.delivery_cents);
    assert.equal(total.toString(), cart.expected_total_cents, cart.id + ': incorrect independent total');
  }
  assert.equal(fixtures.carts.find(c => c.id === 'cash-physical').contact.email, null);
  assert.equal(fixtures.carts.find(c => c.id === 'mp-mixed-pickup').expected_digital_rights, 2);
  assert.ok(fixtures.sellables.find(s => s.kind === 'digital').file_ids.length > 1);
  for (const ref of scenarios.cases.flatMap(c => c.fixtures)) {
    assert.ok(fixtures.carts.some(c => c.id === ref), 'Scenario references unknown cart ' + ref);
  }
});

test('Plazos UTC sintéticos no reviven por restaurar BD', () => {
  const now = Date.parse(fixtures.clock.fixed_utc);
  assert.equal(Date.parse(fixtures.clock.admission_expires_utc) - now, 60_000);
  assert.equal(Date.parse(fixtures.clock.mp_hold_expires_utc) - now, 15 * 60_000);
  assert.equal(Date.parse(fixtures.clock.otp_expires_utc) - now, 10 * 60_000);
  assert.equal(Date.parse(fixtures.clock.grant_expires_utc) - now, 5 * 60_000);
  assert.equal(fixtures.clock.site_timezone, 'America/Lima');
});

test('Cada modo ejecutable tiene código real preparado; escenarios manuales no se marcan probados', () => {
  for (const file of ['tests/risk/domain.php', 'tests/risk/http-denials.test.mjs',
                      'tests/risk/README.md']) {
    assert.ok(existsSync(join(root, file)), 'Missing test implementation: ' + file);
  }
  assert.ok(scenarios.cases.some(c => c.kind === 'concurrent'));
  assert.ok(scenarios.cases.some(c => c.kind === 'provider'));
  assert.ok(scenarios.cases.some(c => c.kind === 'manual-ux'));
  assert.ok(scenarios.cases.some(c => c.action.includes('COMMIT') || c.setup.includes('COMMIT')));
});
