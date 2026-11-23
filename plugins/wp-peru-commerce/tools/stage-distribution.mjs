#!/usr/bin/env node
/**
 * IMP-67 — prepare TWO matching staging trees, never a fake ZIP:
 *   dist/stage/<release>/plugin/wp-peru-commerce/
 *   dist/stage/<release>/source/wp-peru-commerce-source/
 *
 * This is release-machine tooling only. No WordPress deployment, build,
 * package, network, database or test execution is performed by this script.
 * The actual ZIP/source archive and distribution are IMP-72 deliverables.
 */
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import {
  existsSync, lstatSync, mkdirSync, mkdtempSync, readFileSync,
  copyFileSync, readdirSync, renameSync, rmSync, writeFileSync
} from 'node:fs';
import { dirname, join, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const root=resolve(dirname(fileURLToPath(import.meta.url)),'..');
const fail=msg=>{throw new Error('pc-release: '+msg);};
const runGit=(...args)=>execFileSync('git',args,{
  cwd:root,encoding:'utf8',stdio:['ignore','pipe','pipe']
}).trim();
const commit=runGit('rev-parse','HEAD');
if(!/^[a-f0-9]{40}$/.test(commit))fail('Expected a complete source commit');
if(runGit('status','--porcelain','--untracked-files=normal')){
  fail('Uncommitted/untracked source; stage only the exact release commit');
}
const tracked=execFileSync('git',['ls-files','-z'],{
  cwd:root,encoding:'utf8',stdio:['ignore','pipe','pipe']
}).split('\0').filter(Boolean);
const trackedSet=new Set(tracked);
const safe=path=>/^[A-Za-z0-9_.-]+(?:\/[A-Za-z0-9_.-]+)*$/.test(path)
  &&!path.startsWith('.')&&!path.includes('..')&&!path.includes('\\');
const read=path=>{
  const st=lstatSync(path);
  if(!st.isFile()||st.isSymbolicLink())fail('File or symlink not permitted: '+path);
  return readFileSync(path);
};
const sha=buf=>createHash('sha256').update(buf).digest('hex');
const plugin=read(join(root,'wp-peru-commerce.php')).toString('utf8');
const release=/define\('PERUCOMMERCE_VERSION',\s*'([^']+)'\)/.exec(plugin)?.[1];
const schema=/define\('PERUCOMMERCE_SCHEMA_VERSION',\s*'([^']+)'\)/.exec(plugin)?.[1];
if(!release||!/^\d+\.\d+\.\d+(?:-[a-z0-9.-]+)?$/.test(release)
  ||!schema||!/^\d+\.\d+\.\d+$/.test(schema)
  ||!plugin.includes('License: GPL-2.0-or-later')){
  fail('Plugin header/version/license do not match the contract');
}
// Verify actual bundle bytes, import references, license notices and
// canonical build_id before staging either archive. This never compiles.
execFileSync(process.execPath,[join(root,'frontend/scripts/prepare-assets.mjs'),'--verify'],{
  cwd:root,stdio:['ignore','pipe','pipe']
});
const appDir=join(root,'assets/app');
if(!existsSync(appDir))fail('No REAL assets/app/ manifest available');
const manifest=JSON.parse(read(join(appDir,'pc-assets.json')).toString('utf8'));
if(manifest.release!==release||manifest.schema_version!==schema
  ||manifest.source_release!==commit
  ||manifest.schema!=='perucommerce-assets-v1'
  ||manifest.frontend_api!=='peru-commerce/v1'
  ||!Array.isArray(manifest.files)
  ||manifest.files.length<2
  ||manifest.third_party_notices?.path!=='3rdpartylicenses.txt'){
  fail('Assets manifest does not belong to this release/commit');
}
const files=[...manifest.files.map(x=>x.path),'3rdpartylicenses.txt'];
if(new Set(files).size!==files.length ||files.some(x=>!safe(x)))fail('Unsafe/duplicate asset paths');
const actual=[];
const walk=(dir,prefix='')=>{
  for(const ent of readdirSync(dir,{withFileTypes:true})){
    const relative=prefix?prefix+'/'+ent.name:ent.name;
    if(ent.isSymbolicLink()||!safe(relative))fail('Unsafe asset: '+relative);
    if(ent.isDirectory())walk(join(dir,ent.name),relative);
    else if(ent.isFile())actual.push(relative);
    else fail('Unexpected asset type: '+relative);
  }
};
walk(appDir);
const expected=[...files,'pc-assets.json'].sort();
if(JSON.stringify(actual.sort())!==JSON.stringify(expected)){
  fail('Extra/missing browser output; cannot stage this source/release');
}
for(const item of manifest.files){
  const data=read(join(appDir,...item.path.split('/')));
  if(data.length!==item.bytes||sha(data)!==item.sha256)fail('Changed asset '+item.path);
}
const thirdParty=read(join(appDir,'3rdpartylicenses.txt'));
if(thirdParty.length!==manifest.third_party_notices.bytes
  ||sha(thirdParty)!==manifest.third_party_notices.sha256){
  fail('Third-party notices differ from compiled build');
}
const fullLicense=read(join(root,'LICENSE')).toString('utf8');
if(!fullLicense.startsWith('GNU GENERAL PUBLIC LICENSE\nVersion 2, June 1991')
  ||fullLicense.length<15000){
  fail('Full GPL-2.0 text not found');
}
if(!trackedSet.has('frontend/package-lock.json')){
  fail('Exact resolved npm package-lock.json is missing from source release');
}
const lock=JSON.parse(read(join(root,'frontend/package-lock.json')).toString('utf8'));
const pkg=JSON.parse(read(join(root,'frontend/package.json')).toString('utf8'));
if(lock.name!==pkg.name||lock.lockfileVersion<2
  ||lock.packages?.['']?.version!==pkg.version){
  fail('npm lockfile does not match package metadata');
}
const includePlugin=tracked.filter(path=>
  path==='wp-peru-commerce.php' ||path==='index.php'
  ||path==='LICENSE'
  ||(/^includes\/[A-Za-z0-9_./-]+\.php$/.test(path)&&safe(path))
  ||(/^assets\/(?:css\/[A-Za-z0-9_.-]+\.css|js\/[A-Za-z0-9_.-]+\.js)$/.test(path))
);
const disallowed=tracked.filter(path=>
  /^includes\//.test(path)&&!includePlugin.includes(path));
if(disallowed.length)fail('Unknown includes file, review before packaging');
const sourceOnly=tracked.filter(path=>
  /^frontend\/(?:src\/|scripts\/)/.test(path)
  || /^frontend\/(?:package(?:-lock)?\.json|angular\.json|tsconfig(?:\.app)?\.json|README\.md)$/.test(path)
  || /^tools\/stage-distribution\.mjs$/.test(path)
  || /^distribution\/(?:SOURCE-INSTRUCTIONS|RECOVERY-RUNBOOK)\.md$/.test(path)
);
if(!sourceOnly.includes('frontend/scripts/prepare-assets.mjs')
  ||!sourceOnly.includes('frontend/package-lock.json')
  ||!sourceOnly.includes('frontend/angular.json')){
  fail('Missing source/build/lockfile for matching source archive');
}
const requested=[...new Set([...includePlugin,...sourceOnly])].sort();
for(const path of requested){
  if(!safe(path))fail('Unexpected path in release: '+path);
  const data=read(join(root,...path.split('/')));
  const str=data.toString('utf8');
  if(/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/.test(str)){
    fail('Private key embedded in tracked source: '+path);
  }
}
const dist=join(root,'dist');
mkdirSync(dist,{recursive:true});
const stage=join(dist,'stage');
mkdirSync(stage,{recursive:true});
const temp=mkdtempSync(join(stage,'.staging-'));
const rootRelease=join(stage,release);
if(existsSync(rootRelease))fail('Release already staged; refuse to overwrite');
const pluginDir=join(temp,'plugin','wp-peru-commerce');
const sourceDir=join(temp,'source','wp-peru-commerce-source');
const copy=(src,dst)=>{
  mkdirSync(dirname(dst),{recursive:true});
  copyFileSync(src,dst);
};
let published=false;
try {
  for(const path of includePlugin){
    copy(join(root,...path.split('/')),join(pluginDir,...path.split('/')));
  }
  for(const path of files){
    copy(join(appDir,...path.split('/')),join(pluginDir,'assets/app',...path.split('/')));
  }
  copy(join(appDir,'pc-assets.json'),join(pluginDir,'assets/app/pc-assets.json'));
  copy(join(appDir,'3rdpartylicenses.txt'),join(pluginDir,'THIRD-PARTY-NOTICES.txt'));
  for(const path of requested){
    copy(join(root,...path.split('/')),join(sourceDir,...path.split('/')));
  }
  const note=[
    'PeruCommerce GPL-2.0-or-later',
    'Plugin release: '+release,
    'Database schema: '+schema,
    'Source commit: '+commit,
    'Assets build_id: '+manifest.build_id,
    '',
    'The matching source archive is distributed with this plugin ZIP.',
    'Source contains TypeScript/Angular templates, PHP, configuration',
    'and a resolved package-lock.json. Build Node is NOT needed in WordPress.',
    '',
    'Third-party notices: THIRD-PARTY-NOTICES.txt.',
    'The installed ZIP grants the rights specified by LICENSE.',
    'Providing the source archive to the actual recipient remains mandatory.',
    'No secrets, customer data, backups or vendor-private storage belong here.',
    ''
  ].join('\n');
  writeFileSync(join(pluginDir,'SOURCE-NOTICE.txt'),note,{flag:'wx'});
  writeFileSync(join(sourceDir,'SOURCE-RELEASE.txt'),note,{flag:'wx'});
  const inventory=requested.map(path=>({
    path,bytes:read(join(root,...path.split('/'))).length,
    sha256:sha(read(join(root,...path.split('/'))))
  }));
  writeFileSync(join(sourceDir,'SOURCE-INVENTORY.json'),
    JSON.stringify({release,source_release:commit,assets_build_id:manifest.build_id,
      files:inventory},null,2)+'\n',{flag:'wx'});
  writeFileSync(join(temp,'RELEASE-STAGING.json'),
    JSON.stringify({release,source_release:commit,assets_build_id:manifest.build_id,
      php_schema:schema,plugin_folder:'plugin/wp-peru-commerce',
      source_folder:'source/wp-peru-commerce-source',
      zip_generated:false,tests_executed:false,distributed:false},null,2)+'\n',
      {flag:'wx'});
  renameSync(temp,rootRelease);published=true;
}finally{
  if(!published)rmSync(temp,{recursive:true,force:true});
}
process.stdout.write('Staged MATCHING plugin and source trees for '+release+'.\n');
process.stdout.write('No ZIP, build, tests, deployment or public distribution performed.\n');
