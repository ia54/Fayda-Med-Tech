#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';

export const origins = Object.freeze({api:'https://api.faydamed.tech',admin:'https://admin.faydamed.tech',public:'https://faydamed.tech'});
const hash = bytes => crypto.createHash('sha256').update(bytes).digest('hex');
const inside = (root, target) => target === root || target.startsWith(root + path.sep);
const fail = message => { throw new Error(message); };
const forbidden = /(?:^|\/)(?:\.env(?:\..*)?|\.git|\.runtime|credentials\.json|.*\.(?:sqlite|sqlite3|pem|key|p12|pfx))$/i;

export function inventory(root) {
  root=fs.realpathSync(root); const rows=[];
  function walk(dir) {
    for (const name of fs.readdirSync(dir).sort()) {
      const absolute=path.join(dir,name), relative=path.relative(root,absolute).split(path.sep).join('/');
      if(forbidden.test(relative)) fail(`Runtime or credential path refused: ${relative}`);
      const stat=fs.lstatSync(absolute);
      if(stat.isSymbolicLink()) {
        const target=fs.readlinkSync(absolute);
        if(path.isAbsolute(target) || !inside(root,path.resolve(dir,target)) || !inside(root,fs.realpathSync(absolute))) fail(`Escaping symlink refused: ${relative}`);
        rows.push({path:relative,type:'symlink',target});
      } else if(stat.isDirectory()) walk(absolute);
      else if(stat.isFile()) {
        const bytes=fs.readFileSync(absolute);
        if(/-----BEGIN (?:RSA |EC |OPENSSH |DSA |ENCRYPTED )?PRIVATE KEY-----/.test(bytes.toString('utf8'))) fail(`Private-key material refused: ${relative}`);
        rows.push({path:relative,type:'file',size:bytes.length,mode:stat.mode&0o777,sha256:hash(bytes)});
      } else fail(`Unsupported file type: ${relative}`);
    }
  }
  walk(root); return rows;
}

export function checkOrigins(adminStatic, publicDist) {
  const groups=[['admin',adminStatic,origins.api+'/api'],['public',publicDist,origins.api]];
  const result={};
  for(const [name,root,expected] of groups) {
    let found=false, scanned=0;
    function scan(dir) {
      for(const item of fs.readdirSync(dir,{withFileTypes:true})) {
        const full=path.join(dir,item.name);
        if(item.isSymbolicLink()) fail(`Served asset symlink refused: ${full}`);
        if(item.isDirectory()) scan(full);
        else if(/\.(?:js|html|json|css)$/i.test(item.name)) {
          const text=fs.readFileSync(full,'utf8');scanned++;
          if(/https?:\/\/(?:localhost|127\.0\.0\.1|\[::1\]):(?:8000|3102|3005)(?:[\/"'`\s]|$)/i.test(text)) fail(`Local application origin in ${name} asset: ${path.relative(root,full)}`);
          if(text.includes(expected)) found=true;
        }
      }
    }
    scan(root); if(!found) fail(`Expected API origin absent from ${name} assets`);
    result[name]={scanned_files:scanned,expected_api_origin:expected};
  }
  return result;
}

export function assemble({repo,output}) {
  if(process.platform!=='linux' || process.arch!=='x64') fail('Build candidate requires Linux x64; match the actual host before deployment.');
  repo=fs.realpathSync(repo); output=path.resolve(output);
  if(inside(repo,output) || fs.existsSync(output)) fail('Output must be a new directory outside the source checkout.');
  const expected={NEXT_PUBLIC_API_URL:origins.api,NEXT_PUBLIC_API_BASE_URL:origins.api+'/api',VITE_BASE_URL:origins.api};
  for(const [key,value] of Object.entries(expected)) if(process.env[key]!==value) fail(`Incorrect build origin: ${key}`);
  const git=(...args)=>execFileSync('git',['-c',`safe.directory=${repo}`,...args],{cwd:repo,encoding:'utf8'}).trim();
  if(git('status','--porcelain','--untracked-files=no')) fail('Tracked source changes exist; build a pinned clean commit.');
  const commit=git('rev-parse','HEAD'),tree=git('rev-parse','HEAD^{tree}');
  const admin=path.join(repo,'admin-panel'),frontend=path.join(repo,'frontend');
  for(const app of [admin,frontend]) for(const name of fs.readdirSync(app)) if(name!=='.env.example' && /^\.env(?:\.|$)/.test(name)) fail('Application environment files are not permitted in this build workspace.');
  const standalone=path.join(admin,'.next/standalone');
  for(const required of [path.join(standalone,'server.js'),path.join(admin,'.next/BUILD_ID'),path.join(frontend,'dist/index.html')]) if(!fs.statSync(required).isFile()) fail('Missing required build output.');
  const originChecks=checkOrigins(path.join(admin,'.next/static'),path.join(frontend,'dist'));
  fs.mkdirSync(output,{mode:0o700});
  try {
    const bundle=path.join(output,'web-assets');fs.mkdirSync(bundle);
    fs.cpSync(standalone,path.join(bundle,'admin'),{recursive:true,verbatimSymlinks:true});
    fs.cpSync(path.join(admin,'.next/static'),path.join(bundle,'admin/.next/static'),{recursive:true,verbatimSymlinks:true});
    fs.cpSync(path.join(admin,'public'),path.join(bundle,'admin/public'),{recursive:true,verbatimSymlinks:true});
    fs.cpSync(path.join(frontend,'dist'),path.join(bundle,'public'),{recursive:true,verbatimSymlinks:true});
    const files=inventory(bundle);
    const manifest={format:1,kind:'linux-web-build-candidate',production_ready:false,commit,tree,origins,
      target:{host:'144.126.132.98',admin_port:3005,bind:'127.0.0.1'},
      runtime:{platform:process.platform,architecture:process.arch,node:process.version,glibc:process.report.getReport().header.glibcVersionRuntime??null},
      next_build_id:fs.readFileSync(path.join(admin,'.next/BUILD_ID'),'utf8').trim(),
      lockfiles:Object.fromEntries(['admin-panel/pnpm-lock.yaml','frontend/package-lock.json'].map(p=>[p,hash(fs.readFileSync(path.join(repo,p)))])),
      origin_checks:originChecks,files};
    fs.writeFileSync(path.join(bundle,'WEB-ASSET-MANIFEST.json'),JSON.stringify(manifest,null,2)+'\n',{mode:0o600});
    const archive=path.join(output,`web-assets-${commit}.tar.gz`);
    execFileSync('tar',['--sort=name','--mtime=@0','--owner=0','--group=0','--numeric-owner','-czf',archive,'-C',output,'web-assets']);
    fs.chmodSync(archive,0o600);
    const record={archive:path.basename(archive),sha256:hash(fs.readFileSync(archive)),commit,tree,files:files.length,production_ready:false,runtime:manifest.runtime,origins};
    fs.writeFileSync(path.join(output,'BUILD-RECORD.json'),JSON.stringify(record,null,2)+'\n',{mode:0o600});
    fs.copyFileSync(path.join(bundle,'WEB-ASSET-MANIFEST.json'),path.join(output,'WEB-ASSET-MANIFEST.json'));
    return record;
  } catch(error) {
    // Preserve a failed candidate for investigation; it is never uploaded by the workflow.
    throw error;
  }
}

if(process.argv[1] && path.resolve(process.argv[1])===fileURLToPath(import.meta.url)) {
  try {
    const [repo,output]=process.argv.slice(2);
    if(!repo||!output) fail('Usage: node web_asset_package.mjs REPOSITORY NEW_OUTPUT_DIRECTORY');
    console.log(JSON.stringify(assemble({repo,output})));
  } catch(error) { console.error(error.message);process.exitCode=1; }
}
