#!/usr/bin/env node
/**
 * IMP-67 — provenance wrapper for a real CLI build (future authorized use).
 * Never runs on WordPress hosting. A stale dist/ tree cannot be signed as
 * code from the current commit: only this wrapper writes the provenance.
 */
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { existsSync, lstatSync, mkdirSync, readFileSync, readdirSync,
  rmSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const frontend=resolve(dirname(fileURLToPath(import.meta.url)),'..');
const root=resolve(frontend,'..');
const output=join(frontend,'dist/peru-commerce-ui');
const browser=join(output,'browser');
const notice='3rdpartylicenses.txt';
const sha=v=>createHash('sha256').update(v).digest('hex');
const fail=text=>{throw new Error('pc-build: '+text);};
const git=(...args)=>execFileSync('git',args,{
  cwd:root,encoding:'utf8',stdio:['ignore','pipe','pipe']
}).trim();
if(process.argv.length!==2)fail('No extra arguments accepted');
const commit=git('rev-parse','HEAD');
if(!/^[a-f0-9]{40}$/.test(commit)
  ||git('status','--porcelain','--untracked-files=normal')){
  fail('Commit the exact clean source and package-lock before building');
}
const tracked=git('ls-files','frontend/package-lock.json');
if(tracked!=='frontend/package-lock.json')fail('Missing tracked npm lockfile');
const packageJson=readFileSync(join(frontend,'package.json'));
const angularJson=readFileSync(join(frontend,'angular.json'));
const packageLock=readFileSync(join(frontend,'package-lock.json'));
const config=JSON.parse(angularJson.toString('utf8'));
const target=config.projects?.['peru-commerce-ui']?.architect?.build;
if(target?.builder!=='@angular/build:application'
  ||target?.options?.outputPath?.base!=='dist/peru-commerce-ui'
  ||target?.options?.outputPath?.browser!=='browser'
  ||target?.configurations?.production?.outputHashing!=='all'
  ||target?.configurations?.production?.extractLicenses!==true
  ||target?.options?.outputMode!=='static'){
  fail('Angular production settings are not the approved browser-only layout');
}
const pkg=JSON.parse(packageJson.toString('utf8'));
const lock=JSON.parse(packageLock.toString('utf8'));
if(lock.name!==pkg.name||lock.packages?.['']?.version!==pkg.version){
  fail('npm lockfile not consistent with package.json');
}
const cli=join(frontend,'node_modules/@angular/cli/bin/ng.js');
if(!existsSync(cli))fail('Angular CLI not installed; run npm ci only when authorized');
// Deliberately remove stale build output before invoking Angular; this is
// strictly frontend/dist, NOT a plugin directory or installation.
rmSync(output,{recursive:true,force:true});
execFileSync(process.execPath,[cli,'build','--configuration','production'],{
  cwd:frontend,stdio:'inherit'
});
if(!existsSync(browser))fail('Angular did not emit browser output');
if(git('rev-parse','HEAD')!==commit
  ||git('status','--porcelain','--untracked-files=normal')){
  fail('Source changed during compilation: discard this build, never sign it');
}
const entries=[];
const walk=(dir,relative='')=>{
  for(const item of readdirSync(dir,{withFileTypes:true})){
    const name=relative?relative+'/'+item.name:item.name;
    if(item.isSymbolicLink())fail('Symlink in browser output');
    if(item.isDirectory())walk(join(dir,item.name),name);
    else if(item.isFile()){
      const path=join(dir,item.name);
      const stat=lstatSync(path);
      if(!stat.isFile())fail('Unexpected output entry');
      const data=readFileSync(path);
      entries.push({path:name,bytes:data.length,sha256:sha(data)});
    }else fail('Unexpected output entry');
  }
};
walk(browser);
entries.sort((a,b)=>a.path<b.path?-1:a.path>b.path?1:0);
const noticesPath=existsSync(join(browser,notice))
  ?join(browser,notice):join(output,notice);
if(!existsSync(noticesPath))fail('CLI did not extract third-party notices');
const bytes=readFileSync(noticesPath);
const provenance={
  format:'perucommerce-angular-build-v1',
  source_release:commit,
  package_json_sha256:sha(packageJson),
  angular_json_sha256:sha(angularJson),
  package_lock_sha256:sha(packageLock),
  browser_inventory_sha256:sha(JSON.stringify(entries)),
  notices_sha256:sha(bytes),
  notices_bytes:bytes.length,
};
mkdirSync(output,{recursive:true});
writeFileSync(join(output,'pc-build-provenance.json'),
  JSON.stringify(provenance,null,2)+'\n',{encoding:'utf8',flag:'wx'});
process.stdout.write('Angular CLI returned success; output marked for commit '+commit+'.\n');
process.stdout.write('Manifest and package NOT generated; run prepare:assets later.\n');
