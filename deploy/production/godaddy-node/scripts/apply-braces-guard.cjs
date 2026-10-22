'use strict';
const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const {createRequire}=require('node:module');
const config=createRequire(require.resolve('eslint-config-next'));
const plugin=createRequire(config.resolve('@next/eslint-plugin-next'));
const glob=createRequire(plugin.resolve('fast-glob'));
const match=createRequire(glob.resolve('micromatch'));
const root=path.dirname(match.resolve('braces/package.json'));
if(JSON.parse(fs.readFileSync(path.join(root,'package.json'))).version!=='3.0.3')throw Error('Review new braces version before replacing the local guard');
const manifest=require('./braces-guard.json'),hash=b=>crypto.createHash('sha256').update(b).digest('hex');
// Verify ALL originals first; unexpected content causes failure before any write.
for(const [file,m] of Object.entries(manifest)){
 const found=hash(fs.readFileSync(path.join(root,file)));
 if(![m.original_sha256,m.patched_sha256].includes(found)||hash(m.patched_source)!==m.patched_sha256)throw Error('Braces source/hash mismatch: '+file);
}
for(const [file,m] of Object.entries(manifest))fs.writeFileSync(path.join(root,file),m.patched_source);
console.log('Applied verified braces depth guard; registry advisory remains visible.');
