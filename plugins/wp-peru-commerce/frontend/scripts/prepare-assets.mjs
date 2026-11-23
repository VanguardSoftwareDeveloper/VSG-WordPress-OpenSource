#!/usr/bin/env node
/**
 * IMP-67 — Prepare browser assets ONLY from a real Angular production build.
 *
 * No build/install/ZIP is performed by this script. Node runs on the
 * developer's release machine, never on WordPress hosting. It refuses
 * untracked/uncommitted source, missing third-party notices, symlinks,
 * unreferenced module paths, unknown output types and external dependencies.
 *
 * Usage (only when execution is authorized in a later phase):
 *   npm run build
 *   npm run prepare:assets
 *   npm run verify:assets
 */
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { existsSync, lstatSync, readFileSync, readdirSync, mkdtempSync,
  mkdirSync, copyFileSync, renameSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, extname, isAbsolute, join, relative, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const here=dirname(fileURLToPath(import.meta.url));
const workspace=resolve(here,'..');
const root=resolve(workspace,'..');
const browser=resolve(workspace,'dist/peru-commerce-ui/browser');
const target=resolve(root,'assets/app');
const verify=process.argv.length===3&&process.argv[2]==='--verify';
if(process.argv.length>2&&!verify)throw new Error('Only --verify is accepted.');
const SCHEMA='perucommerce-assets-v1';
const API='peru-commerce/v1';
const MEDIA='media';
const NOTICE='3rdpartylicenses.txt';
const accepted=new Set(['.js','.css','.woff','.woff2','.png','.jpg',
  '.jpeg','.webp','.svg','.ico']);
const sha=data=>createHash('sha256').update(data).digest('hex');
const die=what=>{throw new Error('pc-assets: '+what);};
const validFile=path=>/^[A-Za-z0-9_-][A-Za-z0-9._-]*(?:\/[A-Za-z0-9_-][A-Za-z0-9._-]*)*$/.test(path)
  &&!path.includes('..')&&!path.includes('\\')&&!path.includes(':');
const assertRegular=(path,context)=>{
  const st=lstatSync(path);
  if(!st.isFile()||st.isSymbolicLink())die('Invalid '+context+' file');
  return st;
};
const read=(path,context)=>{
  const st=assertRegular(path,context);
  return {content:readFileSync(path),bytes:st.size};
};
const pluginFile=read(join(root,'wp-peru-commerce.php'),'plugin').content.toString('utf8');
const match=(re,label)=>{
  const value=re.exec(pluginFile)?.[1];
  if(!value)die('Missing PHP '+label);
  return value;
};
const release=match(/define\('PERUCOMMERCE_VERSION',\s*'([^']+)'\)/,'release');
const schemaVersion=match(/define\('PERUCOMMERCE_SCHEMA_VERSION',\s*'([^']+)'\)/,'schema version');
const header=match(/^\s*\*\s*Version:\s*(\S+)\s*$/m,'plugin header version');
if(release!==header)die('Plugin header / constant version mismatch');
if(!/^[0-9]+\.[0-9]+\.[0-9]+(?:-[a-z0-9.-]+)?$/.test(release))die('Bad release version');
if(!/^[0-9]+\.[0-9]+\.[0-9]+$/.test(schemaVersion))die('Bad database schema version');
const metadata=(sourceRelease,entry,styles,files,notice)=>
  [
    'schema='+SCHEMA,'release='+release,'schema_version='+schemaVersion,
    'source_release='+sourceRelease,'entry_module='+entry,
    ...styles.map(s=>'style='+s),
    ...files.map(f=>'file='+f.path+'\t'+f.bytes+'\t'+f.sha256),
    'notices='+notice.bytes+'\t'+notice.sha256,
  ].join('\n')+'\n';
const normalizePath=(name,what)=>{
  if(!name||name.startsWith('/')||isAbsolute(name)||name.includes('?')
    ||name.includes('#')||name.includes('%')||name.includes('\\')
    ||name.includes(':'))die('Unsafe '+what+' path: '+name);
  let path=name.startsWith('./')?name.slice(2):name;
  if(!validFile(path))die('Unsafe '+what+' path: '+name);
  return path;
};
const attr=(tag,name)=>{
  const m=new RegExp('(?:^|\\s)'+name+'\\s*=\\s*(?:"([^"]*)"|\\x27([^\\x27]*)\\x27)','i').exec(tag);
  return m?m[1]??m[2]:null;
};
const fromIndex=(html)=>{
  const scripts=[...html.matchAll(/<script\b[^>]*>/gi)];
  const moduleScripts=[];
  for(const tag of scripts){
    const src=attr(tag[0],'src'),type=attr(tag[0],'type');
    if(type!=='module'||!src)die('Unexpected inline, external or non-module script in Angular index');
    moduleScripts.push(normalizePath(src,'script'));
  }
  // Current V1 has polyfills:[] and exactly one ESM entry. Do not
  // silently drop a second entry or break bootstrap order.
  if(moduleScripts.length!==1)die('Expected exactly one generated ESM entry');
  const styles=[];
  for(const match of html.matchAll(/<link\b[^>]*>/gi)){
    const rel=(attr(match[0],'rel')??'').toLowerCase();
    const href=attr(match[0],'href');
    if(rel==='stylesheet'){
      if(!href)die('Missing stylesheet href');
      styles.push(normalizePath(href,'stylesheet'));
    }else if(rel==='modulepreload'&&href){
      normalizePath(href,'modulepreload');
    }else if(rel==='preconnect'||rel==='dns-prefetch'){
      die('Index has unexpected network preconnect');
    }
  }
  if(styles.length<1||styles.length>20
    ||new Set(styles).size!==styles.length)die('Missing/duplicate stylesheet');
  if(!moduleScripts[0].endsWith('.js')
    ||styles.some(path=>!path.endsWith('.css')))die('Wrong entry or styles extension');
  if(!/-[a-z0-9_-]{8,}\.js$/i.test(moduleScripts[0])
    ||styles.some(x=>!/-[a-z0-9_-]{8,}\.css$/i.test(x))){
    die('Production outputHashing=all not visible in generated index');
  }
  if(/<style\b|<script\b[^>]*>\s*[^<\s]/i.test(html)){
    die('Generated index contains inline CSS/JS not safe to discard');
  }
  return {entry:moduleScripts[0],styles};
};
const allFiles=dir=>{
  const out=[];
  const walk=(folder,prefix='')=>{
    for(const e of readdirSync(folder,{withFileTypes:true})){
      const local=prefix?prefix+'/'+e.name:e.name;
      if(e.isSymbolicLink())die('Symlink in Angular browser output: '+local);
      if(e.isDirectory()){
        if(!validFile(local))die('Unexpected output folder: '+local);
        walk(join(folder,e.name),local);
      }else if(e.isFile()){
        if(!validFile(local))die('Unexpected output file: '+local);
        out.push(local);
      }else die('Unexpected output entry: '+local);
    }
  };
  walk(dir);
  return out.sort();
};
const deps=(source,extension)=>{
  const found=[];
  if(extension==='.js'){
    // Angular/esbuild emits static ESM imports and import("./chunk-*.js").
    const re=/(?:\bfrom\s*|\bimport\s*\(\s*|\bimport\s*)(['"])([^'"]+)\1/g;
    for(const m of source.matchAll(re))found.push(m[2]);
  }else if(extension==='.css'){
    for(const m of source.matchAll(/\burl\(\s*(['"]?)([^'")]+)\1\s*\)/gi)){
      const x=m[2].trim();
      if(!x.startsWith('data:')&&!x.startsWith('#'))found.push(x);
    }
    for(const m of source.matchAll(/@import\s+(['"])([^'"]+)\1/gi)){
      found.push(m[2]);
    }
  }
  return found;
};
const confirmDependencies=(files,dir)=>{
  const set=new Set(files);
  for(const path of files){
    const extension=extname(path).toLowerCase();
    if(extension!=='.js'&&extension!=='.css')continue;
    const txt=read(join(dir,...path.split('/')),'bundle asset').content.toString('utf8');
    for(const dependency of deps(txt,extension)){
      if(extension==='.js'
        &&!dependency.startsWith('./')&&!dependency.startsWith('../')){
        die('Non-relative JS module import in '+path);
      }
      // Angular CSS may emit url(media/hashed-name.woff2) without "./";
      // it is still a local URL relative to this CSS file.
      if(extension==='.css'
        &&(/^[A-Za-z][A-Za-z0-9+.-]*:/.test(dependency)
          ||dependency.startsWith('/')||dependency.includes('\\'))){
        die('External/absolute CSS dependency in '+path);
      }
      const abs=resolve(dir,dirname(path),dependency.split('?')[0].split('#')[0]);
      const relativePath=relative(dir,abs).split(sep).join('/');
      if(!validFile(relativePath)||!set.has(relativePath)){
        die('Missing/outside '+extension+' dependency in '+path+': '+dependency);
      }
    }
  }
};
const validated=(dir)=>{
  const list=allFiles(dir);
  if(!list.includes('index.html')){
    die('Missing actual Angular generated index.html');
  }
  // The application builder may emit extracted license notices at the
  // output base or in browser/ depending on builder version/config.
  const noticeBrowser=join(browser,NOTICE);
  const noticeBase=join(dirname(browser),NOTICE);
  const noticeSource=existsSync(noticeBrowser)?noticeBrowser
    :existsSync(noticeBase)?noticeBase:null;
  if(!noticeSource || !noticeSource.startsWith(dirname(browser))){
    die('Missing extracted third-party license notices');
  }
  const index=read(join(dir,'index.html'),'generated index').content.toString('utf8');
  const info=fromIndex(index);
  const payloads=list.filter(x=>x!=='index.html'&&x!==NOTICE);
  if(payloads.length<2||payloads.length>500||payloads.some(x=>!accepted.has(extname(x).toLowerCase()))) {
    die('Unsupported/missing browser asset in build output');
  }
  if(!payloads.includes(info.entry)||info.styles.some(s=>!payloads.includes(s))){
    die('Generated entry or styles are not present on disk');
  }
  confirmDependencies(payloads,dir);
  const files=payloads.map(path=>{
    const buf=read(join(dir,...path.split('/')),'browser asset');
    if(buf.bytes<1||buf.bytes>20000000)die('Invalid asset size: '+path);
    return {path,sha256:sha(buf.content),bytes:buf.bytes};
  });
  const notices=read(noticeSource,'third-party licenses');
  if(notices.bytes<100||notices.bytes>2000000){
    die('Missing/unreasonable license notices emitted by builder');
  }
  return {
    entry:info.entry,styles:info.styles,files,
    notices:{path:NOTICE,sha256:sha(notices.content),bytes:notices.bytes},
  };
};
/**
 * A commit SHA is not evidence that stale dist/ came from that commit.
 * Only the release-machine wrapper can produce this marker after a fresh
 * successful Angular build; hashes then bind the actual browser bytes.
 */
const validateProvenance=commit=>{
  const markerFile=join(dirname(browser),'pc-build-provenance.json');
  if(!existsSync(markerFile))die('Missing fresh-build provenance; use npm run build wrapper');
  const p=JSON.parse(read(markerFile,'build provenance').content.toString('utf8'));
  if(p.format!=='perucommerce-angular-build-v1'
    ||p.source_release!==commit
    ||p.package_json_sha256!==sha(read(join(workspace,'package.json'),'package').content)
    ||p.angular_json_sha256!==sha(read(join(workspace,'angular.json'),'angular config').content)
    ||p.package_lock_sha256!==sha(read(join(workspace,'package-lock.json'),'npm lockfile').content)){
    die('Stale build or source/dependency/configuration drift');
  }
  const entries=allFiles(browser).map(path=>{
    const f=read(join(browser,...path.split('/')),'browser output');
    return {path,bytes:f.bytes,sha256:sha(f.content)};
  });
  // allFiles() is ASCII-sorted, matching the build-browser wrapper.
  if(p.browser_inventory_sha256!==sha(JSON.stringify(entries))){
    die('Browser output changed after the recorded Angular build');
  }
  const noticesPath=existsSync(join(browser,NOTICE))
    ?join(browser,NOTICE):join(dirname(browser),NOTICE);
  if(!existsSync(noticesPath))die('Build notices disappeared');
  const notice=read(noticesPath,'build notices');
  if(notice.bytes!==p.notices_bytes||sha(notice.content)!==p.notices_sha256){
    die('Third-party notices changed after Angular build');
  }
};
const manifestFor=(info,sourceRelease)=>{
  const data={
    schema:SCHEMA,
    release,schema_version:schemaVersion,source_release:sourceRelease,
    frontend_api:API,entry_module:info.entry,stylesheets:info.styles,
    media_root:MEDIA,files:info.files,third_party_notices:info.notices
  };
  data.build_id=sha(metadata(data.source_release,data.entry_module,
    data.stylesheets,data.files,data.third_party_notices));
  return data;
};
if(verify){
  if(!existsSync(target))die('Published assets/app is missing');
  const manifest=JSON.parse(read(join(target,'pc-assets.json'),'manifest').content.toString('utf8'));
  // Only output assets are enumerated; index.html was intentionally not
  // copied. Validate the inventory and metadata against all actual bytes.
  if(manifest.schema!==SCHEMA||manifest.release!==release
    ||manifest.schema_version!==schemaVersion||manifest.frontend_api!==API
    ||!(/^[a-f0-9]{40}$/.test(manifest.source_release??''))
    ||manifest.media_root!==MEDIA)die('Unexpected published manifest');
  const expected=allFiles(target).filter(x=>x!=='pc-assets.json');
  const permitted=[...manifest.files.map(x=>x.path),NOTICE].sort();
  if(JSON.stringify(expected)!==JSON.stringify(permitted))die('Published output has extra/missing files');
  for(const file of manifest.files){
    if(!validFile(file.path)||!accepted.has(extname(file.path).toLowerCase()))die('Unsafe asset');
    const buf=read(join(target,...file.path.split('/')),'verified asset');
    if(buf.bytes!==file.bytes||sha(buf.content)!==file.sha256)die('Asset hash mismatch: '+file.path);
  }
  const notes=read(join(target,NOTICE),'notices');
  if(notes.bytes!==manifest.third_party_notices?.bytes
    ||sha(notes.content)!==manifest.third_party_notices?.sha256)die('Third-party license mismatch');
  const canonical=manifestFor({
    entry:manifest.entry_module,styles:manifest.stylesheets,
    files:manifest.files,notices:manifest.third_party_notices
  },manifest.source_release);
  if(JSON.stringify(canonical)!==JSON.stringify(manifest))die('Manifest build_id/structure mismatch');
  confirmDependencies(manifest.files.map(f=>f.path),target);
  process.stdout.write('Assets verified from exact bytes, no build/package executed.\n');
}else{
  const sourceRelease=execFileSync('git',['rev-parse','HEAD'],{
    cwd:root,encoding:'utf8',stdio:['ignore','pipe','pipe']
  }).trim();
  if(!/^[a-f0-9]{40}$/.test(sourceRelease))die('No full git commit for source');
  const dirty=execFileSync('git',['status','--porcelain','--untracked-files=normal'],{
    cwd:root,encoding:'utf8',stdio:['ignore','pipe','pipe']
  }).trim();
  if(dirty)die('Source tree has uncommitted or untracked files; commit sources/lockfile before release');
  if(!existsSync(join(workspace,'package-lock.json'))){
    die('Missing npm resolved package-lock.json; cannot attest source dependencies');
  }
  validateProvenance(sourceRelease);
  const info=validated(browser);
  const manifest=manifestFor(info,sourceRelease);
  const tmp=mkdtempSync(join(root,'assets','.pc-app-stage-'));
  let published=false;
  try{
    for(const asset of info.files.map(x=>x.path)){
      const dest=join(tmp,...asset.split('/'));
      mkdirSync(dirname(dest),{recursive:true});
      copyFileSync(join(browser,...asset.split('/')),dest);
    }
    // Third-party notices must be copied from the actual build output,
    // never composed from a guessed list of dependency licenses.
    const noticeSource=existsSync(join(browser,NOTICE))
      ?join(browser,NOTICE):join(dirname(browser),NOTICE);
    copyFileSync(noticeSource,join(tmp,NOTICE));
    writeFileSync(join(tmp,'pc-assets.json'),JSON.stringify(manifest,null,2)+'\n',
      {encoding:'utf8',flag:'wx'});
    // Assert no build output changed during copying. A concurrent ng build
    // would otherwise make a mixed manifest and unsafe release.
    for(const f of info.files){
      const buf=read(join(tmp,...f.path.split('/')),'staged asset');
      if(buf.bytes!==f.bytes||sha(buf.content)!==f.sha256)die('Concurrent/mixed browser build: '+f.path);
    }
    // Publish only on a local release build machine, never directly to WP.
    if(existsSync(target)){
      die('Refuse to overwrite existing assets/app; remove prior release staging only after review');
    }
    renameSync(tmp,target);
    published=true;
  }finally{
    if(!published)rmSync(tmp,{recursive:true,force:true});
  }
  process.stdout.write('Staged browser assets for '+release+' at assets/app (no ZIP).\n');
  process.stdout.write('Source commit '+sourceRelease+'; build_id '+manifest.build_id+'\n');
}
