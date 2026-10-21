#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import {inventory} from './web_asset_package.mjs';

export function footprint(root) {
 const counts={regular_files:0,directories:1,symlinks:0,logical_bytes:0,allocated_bytes:0};
 function walk(dir){for(const name of fs.readdirSync(dir)){const p=path.join(dir,name),s=fs.lstatSync(p);counts.allocated_bytes+=s.blocks*512;
  if(s.isSymbolicLink())counts.symlinks++;else if(s.isDirectory()){counts.directories++;walk(p);}else if(s.isFile()){counts.regular_files++;counts.logical_bytes+=s.size;}else throw Error('Unsupported entry');}}
 walk(root);return {...counts,entries:counts.regular_files+counts.directories+counts.symlinks};
}
export function validateOrigin(value){const u=new URL(value);if(u.protocol!=='https:'||u.username||u.password||u.port||u.pathname!=='/'||u.search||u.hash)throw Error('Bare HTTPS origin required');return u.origin;}
const inside=(a,b)=>b===a||b.startsWith(a+path.sep);
export function packagePilot({repo,apiRuntime,output,origins}){
 repo=fs.realpathSync(repo);apiRuntime=fs.realpathSync(apiRuntime);output=path.resolve(output);
 if(inside(repo,output)||fs.existsSync(output))throw Error('Use a new output directory outside the checkout');
 const git=(...a)=>execFileSync('git',a,{cwd:repo,encoding:'utf8'}).trim();
 if(git('status','--porcelain','--untracked-files=no'))throw Error('Commit tracked source changes before packaging');
 const commit=git('rev-parse','HEAD');
 for(const k of ['api','admin','public'])origins[k]=validateOrigin(origins[k]);
 const installed=JSON.parse(fs.readFileSync(path.join(apiRuntime,'vendor/composer/installed.json')));
 if(installed.dev!==false)throw Error('Production-only Composer install required');
 const lock=JSON.parse(fs.readFileSync(path.join(repo,'api/composer.lock')));
 const devNames=new Set(lock['packages-dev'].map(p=>p.name));
 if(installed.packages.some(p=>devNames.has(p.name)))throw Error('Development Composer package present');
 const allowedSource=p=>!p.startsWith('api/tests/')&&!p.startsWith('api/storage/')&&!p.startsWith('api/database/factories/')&&!p.startsWith('api/database/seeders/')&&!['api/.env.example','api/phpunit.xml','api/phpunit.mysql.xml'].includes(p);
 for(const source of git('ls-files','api').split('\n').filter(allowedSource)){
  const target=path.join(apiRuntime,source.slice(4)),bytes=execFileSync('git',['show',`${commit}:${source}`],{cwd:repo,maxBuffer:20*1024*1024});
  if(!fs.existsSync(target)||!fs.readFileSync(target).equals(bytes))throw Error(`Stale API runtime source: ${source}`);
 }
 for(const p of ['tests','database/factories','database/seeders','storage/oauth-private.key','storage/oauth-public.key','.env'])if(fs.existsSync(path.join(apiRuntime,p)))throw Error(`Excluded API runtime path present: ${p}`);
 const buildRoot=path.join(repo,'admin-panel/.next'),server=path.join(buildRoot,'standalone/server.js');
 if(!fs.existsSync(server))throw Error('Next standalone build required');
 fs.mkdirSync(output,{recursive:true});
 fs.cpSync(apiRuntime,path.join(output,'api'),{recursive:true,verbatimSymlinks:true});
 fs.cpSync(path.join(buildRoot,'standalone'),path.join(output,'admin'),{recursive:true,verbatimSymlinks:true});
 fs.cpSync(path.join(buildRoot,'static'),path.join(output,'admin/.next/static'),{recursive:true});
 if(fs.existsSync(path.join(repo,'admin-panel/public')))fs.cpSync(path.join(repo,'admin-panel/public'),path.join(output,'admin/public'),{recursive:true});
 fs.cpSync(path.join(repo,'frontend/dist'),path.join(output,'public'),{recursive:true});
 fs.cpSync(path.join(repo,'deploy/production/godaddy-pilot'),path.join(output,'ops'),{recursive:true});
 fs.copyFileSync(path.join(output,'ops/app.cjs'),path.join(output,'admin/app.cjs'));
 fs.copyFileSync(path.join(repo,'deploy/production/GODADDY-SYNTHETIC-PILOT.md'),path.join(output,'ops/README.md'));
 // These tests are explicitly autoload-dev-only, absent from production classmaps,
 // and contain published test private keys. Never ship them as runtime material.
 const pruned=['api/vendor/adhocore/jwt/tests','api/vendor/docusign/esign-client/test'];
 for(const p of pruned)fs.rmSync(path.join(output,p),{recursive:true,force:true});
 for(const name of ['admin','public']){
  let found=false;
  const scan=p=>{for(const e of fs.readdirSync(p,{withFileTypes:true})){const f=path.join(p,e.name);if(e.isDirectory())scan(f);else if(e.isFile()&&/\.(js|html)$/.test(e.name)){const text=fs.readFileSync(f,'utf8');if(text.includes(origins.api))found=true;if(/https?:\/\/(localhost|127\.0\.0\.1):8000/.test(text))throw Error('Local API origin in served build');}}};
  scan(path.join(output,name,name==='admin'?'.next/static':'.'));if(!found)throw Error(`Expected API build origin absent from ${name}`);
 }
 const files=inventory(output);
 const components=Object.fromEntries(['api','admin','public','ops'].map(k=>[k,footprint(path.join(output,k))]));
 const manifest={kind:'godaddy-synthetic-pilot-rehearsal',commit,origins,production_ready:false,host_validation:false,clinical_release:false,synthetic_only:true,requires_origin_rebuild:Object.values(origins).some(o=>new URL(o).hostname.endsWith('.invalid')),production_composer_packages:installed.packages.length,pruned_autoload_dev_fixtures:pruned,platform:{os:process.platform,arch:process.arch,node:process.versions.node},components,files};
 fs.writeFileSync(path.join(output,'PILOT-MANIFEST.json'),JSON.stringify(manifest,null,2)+'\n');
 const counts=footprint(output),available=14554;
 return {output,commit,origins,components,footprint:counts,available_slots:available,single_release_regular_files_fit:counts.regular_files<=available,single_release_all_entries_fit:counts.entries<=available,two_releases_all_entries_fit:counts.entries*2<=available,production_ready:false,host_validation:false};
}
if(process.argv[1]===fileURLToPath(import.meta.url)){
 try{const a=process.argv.slice(2),get=k=>{const i=a.indexOf(k);if(i<0||!a[i+1])throw Error(`Missing ${k}`);return a[i+1];};console.log(JSON.stringify(packagePilot({repo:process.cwd(),apiRuntime:get('--api-runtime'),output:get('--output'),origins:{api:get('--api-origin'),admin:get('--admin-origin'),public:get('--public-origin')}}),null,2));}catch(e){console.error(e.message);process.exitCode=1;}
}
