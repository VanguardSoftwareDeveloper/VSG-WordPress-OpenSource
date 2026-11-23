/**
 * IMP-70 — comprobación estática de inventarios y referencias de implementación.
 *
 * Solo al autorizar pruebas P-C/IMP-72:
 *   node --test tests/risk/coverage.test.mjs
 *
 * No evalúa PHP, no llama HTTP/SQL, no instala npm ni certifica UX/hosting.
 * Comprueba que el contrato, los mapeos y los enlaces de registro no
 * pierdan silenciosamente operaciones tras futuros cambios en main.
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { resolve, join } from 'node:path';

const root=resolve(fileURLToPath(new URL('../../',import.meta.url)));
const read=path=>readFileSync(join(root,path),'utf8');
const json=path=>JSON.parse(read(path));
const api=json('docs/openapi/peru-commerce-v1.openapi.draft.json');
const rest=read('includes/rest-router.php');
const access=read('includes/access-contract.php');
const dto=read('includes/rest-contract.php');
const schema=read('includes/schema.php');
const trace=read('docs/44-TRAZABILIDAD-REST-IMP-70.md');
const matrix=read('docs/45-MATRIZ-DATOS-Y-ACEPTACION-IMP-70.md');
const perDocument=read('docs/24-MATRIZ-REST-PERMISOS-DTO-P0-23-V1.md');
const acceptance=read('docs/08-PLAN-Y-ACEPTACION.md');
const DBA=read('docs/38-CONTRATO-PRECODIGO-DATOS-CONCURRENCIA-Y-RECUPERACION-V1.md');
const allMatches=(str,regex)=>[...str.matchAll(regex)].map(x=>x[1]);
const apiOperations=new Map();
for(const [path,methods] of Object.entries(api.paths)){
  for(const [method,op]of Object.entries(methods)){
    if(!['get','post','put','patch','delete'].includes(method))continue;
    assert.ok(!apiOperations.has(op.operationId), 'Duplicate OpenAPI: '+op.operationId);
    apiOperations.set(op.operationId,{method:method.toUpperCase(),path,auth:op['x-authz']});
  }
}
const expected=new Set(apiOperations.keys());
const direct=new Set(['R8-14','R8-48','R8-62','R8-63','R8-67','R8-68']);
const ids=new Set(Array.from({length:70},(_,i)=>'R8-'+String(i+1).padStart(2,'0')));
const phpStringEntries=src=>allMatches(src,/'(R8-\d{2})'\s*=>/g);
const routeIds=new Set(phpStringEntries(rest.slice(rest.indexOf('private const GATES'),rest.indexOf('];',rest.indexOf('private const GATES')))));
const accessIds=new Set(phpStringEntries(access));
const contractIds=new Set(phpStringEntries(dto));
const sourceControllers=new Map();

test('Operaciones y DTO OpenAPI 0.12 son el único contrato activo',()=>{
  assert.equal(api.info.version,'0.12.0-draft');
  assert.equal(apiOperations.size,69);
  assert.equal(Object.keys(api.paths).length,66);
  assert.equal(Object.keys(api.components.schemas).length,138);
  assert.equal(expected.has('R8-18'),false,'Página WP no puede ser operación REST');
  for(const collection of [routeIds,accessIds,contractIds]){
    assert.equal(collection.size,69);
    assert.deepEqual([...collection].sort(),[...expected].sort());
  }
});

test('Matriz 70 R8 registra dueño real, gate y consumidor/caso sin duplicaciones',()=>{
  const lines=trace.split('\n').filter(l=>/^\|\s*\*\*R8-\d{2}\*\*\s/.test(l));
  const mapped=new Set();
  for(const line of lines){
    if(/^\|\s*\*\*R8-18\*\*\s/.test(line)){
      assert.ok(!mapped.has('R8-18'));
      mapped.add('R8-18');
      assert.ok(line.includes('NO REST'),'R8-18 must be a WordPress page');
      continue;
    }
    const match=line.match(/^\|\s*\*\*(R8-\d{2})\*\*\s+(GET|POST|PUT|PATCH|DELETE)\s+\x60([^\x60]+)\x60\s*\|/);
    if(!match)throw new Error('Bad R8 table row: '+line.slice(0,160));
    const [,id,method,path]=match;
    assert.ok(!mapped.has(id), 'Duplicate R8 row '+id);
    mapped.add(id);
    if(id==='R8-18')continue;
    assert.ok(expected.has(id),'Not in OpenAPI '+id);
    assert.equal(apiOperations.get(id).method,method);
    assert.equal(apiOperations.get(id).path,path);
    const fileName=line.match(/\x60([a-z0-9-]+-controller\.php)\x60/);
    assert.ok(fileName,'Missing owning controller '+id);
    const relative='includes/'+fileName[1];
    assert.ok(existsSync(join(root,relative)), 'Controller not found '+relative);
    const source=read(relative);
    assert.ok(source.includes(id),'Owner does not mention operation '+id);
    if(direct.has(id)){
      assert.ok(source.includes('register_rest_route'),id+' special route missing WordPress registration');
      assert.ok(source.includes('Access_Control::registerGuard'),id+' special route missing guard');
    }else{
      assert.ok(new RegExp("Rest_Router::bind\\(\\s*['\\x22]"+id+"['\\x22]").test(source),
        id+' missing actual router binding');
    }
    const role=apiOperations.get(id).auth;
    assert.ok(role&&role.denyByDefault===true,id+' requires fail-closed contract');
    sourceControllers.set(id,relative);
  }
  assert.deepEqual([...mapped].sort(),[...ids].sort());
  assert.equal(sourceControllers.size,69);
  assert.ok(trace.includes('frontend/src/app/checkout-page.ts'),'R8-16 consumer not recorded');
});

test('Inventario PER 56 distingue exclusiones y superficies WP nativas',()=>{
  const rows=perDocument.split('\n').filter(l=>/^\|\s*PER-\d\d\s*\|/.test(l));
  assert.equal(rows.length,56);
  const entries=new Set(rows.map(l=>l.match(/PER-\d\d/)[0]));
  const mapped=new Set(allMatches(matrix,/^\|\s*\*\*(PER-\d{2})\*\*/gm));
  assert.equal(entries.size,56);
  assert.deepEqual([...mapped].sort(),[...entries].sort());
  for(const id of ['PER-09','PER-16','PER-36','PER-43']){
    assert.ok(matrix.includes('**'+id+'**'));
  }
});

test('Los 39 objetos físicos y 16 DB-A cuentan con archivos fuente rastreables',()=>{
  const physical=new Set(allMatches(schema,/'(pc_[a-z_]+)'\s*=>\s*<<<'SQL'/g));
  const rows=matrix.split('\n').filter(l=>/^\|\s*\x60pc_[a-z_]+\x60\s*\|/.test(l));
  const mapped=new Set();
  for(const line of rows){
    const table=line.match(/^\|\s*\x60(pc_[a-z_]+)\x60\s*\|/)[1];
    assert.ok(!mapped.has(table),'Duplicate table '+table);
    mapped.add(table);
    const file=line.match(/\x60(includes\/[a-z0-9/_-]+\.php)\x60/);
    assert.ok(file&&existsSync(join(root,file[1])), 'Missing physical owner '+table);
  }
  assert.equal(physical.size,39);
  assert.deepEqual([...mapped].sort(),[...physical].sort());
  const conceptual=new Set(allMatches(DBA,/^\|\s*(DB-A\d\d)\s*\|/gm));
  const traced=new Set(allMatches(matrix,/^\|\s*\*\*(DB-A\d\d)\*\*/gm));
  assert.equal(conceptual.size,16);
  assert.deepEqual([...traced].sort(),[...conceptual].sort());
});

test('Cada aceptación A-01..A-202 está identificada sin etiquetarla como prueba ejecutada',()=>{
  const source=new Set(allMatches(acceptance,/^\|\s*(A-\d{2,3})\s*\|/gm));
  const traced=new Set(allMatches(matrix,/^\|\s*\*\*(A-\d{2,3})\*\*/gm));
  assert.equal(source.size,202);
  assert.deepEqual([...traced].sort(),[...source].sort());
  assert.match(matrix,/P-C, no ejecutado/);
  assert.match(trace,/no existe evidencia de pruebas ejecutadas/i);
});

test('PER-09 no genera REST comercial y la página R8-18 es WordPress',()=>{
  assert.equal(apiOperations.has('R8-18'),false);
  assert.ok(!rest.includes("'R8-18' =>"));
  assert.ok(trace.includes('frontend/src/app/return-page.ts'));
  assert.ok(matrix.includes('PER-09'));
});

test('IMP-71 integra R8-16 y distingue decisión digital, revocación y apertura A',()=>{
  const checkout=read('frontend/src/app/checkout-page.ts');
  const cases=read('frontend/src/app/admin-cases.ts');
  const template=read('frontend/src/app/admin-cases.html');
  const controller=read('includes/digital-cases-controller.php');
  const bootstrap=read('includes/bootstrap.php');
  const readCases=read('includes/orders-cases.php');
  assert.match(checkout,/public\/ubigeo\?page=1&per_page=30&q=/);
  assert.match(checkout,/delivery_available/);
  assert.match(cases,/admin\/digital-cases\//);
  assert.match(cases,/admin\/digital-rights\//);
  assert.match(template,/ngNoForm/);
  assert.match(template,/perucommerce_digital_case_open/);
  assert.match(controller,/admin_post_perucommerce_digital_case_open/);
  assert.match(controller,/check_admin_referer/);
  assert.match(controller,/Digital_Cases::openFromGuest/);
  assert.match(bootstrap,/Digital_Cases_Controller::registerAdminHooks/);
  assert.match(readCases,/closed_revocation_authorized/);
  assert.match(readCases,/evidence_ref/);
  // La acción nativa no incrementa el inventario REST canónico.
  assert.equal(apiOperations.size,69);
});
