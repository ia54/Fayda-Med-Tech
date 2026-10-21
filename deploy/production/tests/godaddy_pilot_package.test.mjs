import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import vm from 'node:vm';
import {footprint,validateOrigin} from '../godaddy_pilot_package.mjs';

test('footprint counts directories and symlinks instead of understating file slots',()=>{
 const root=fs.mkdtempSync(path.join(os.tmpdir(),'pilot-count-'));
 try{fs.mkdirSync(path.join(root,'private'));fs.writeFileSync(path.join(root,'private/file'),'abc');fs.symlinkSync('private/file',path.join(root,'link'));const c=footprint(root);assert.equal(c.regular_files,1);assert.equal(c.directories,2);assert.equal(c.symlinks,1);assert.equal(c.entries,4);assert.equal(c.logical_bytes,3);}finally{fs.rmSync(root,{recursive:true,force:true});}
});
test('origins reject embedded credentials, paths, ports and non-HTTPS schemes',()=>{
 assert.equal(validateOrigin('https://api.pilot.invalid'),'https://api.pilot.invalid');
 for(const s of ['http://api.pilot.invalid','https://key@api.pilot.invalid','https://api.pilot.invalid:3005','https://api.pilot.invalid/api','https://api.pilot.invalid?key=x'])assert.throws(()=>validateOrigin(s));
});
test('Passenger adapter refuses fallback ports and delegates only through the loader',()=>{
 const source=fs.readFileSync(new URL('../godaddy-pilot/app.cjs',import.meta.url),'utf8');
 assert.throws(()=>vm.runInNewContext(source,{global:{},require(){throw Error('Should not load');}}),/Passenger loader required/);
 const calls=[];const sandbox={PhusionPassenger:{configure:c=>calls.push(c.autoInstall)},process:{env:{}},require:p=>calls.push(p)};sandbox.global=sandbox;
 vm.runInNewContext(source,sandbox);assert.deepEqual(calls,[true,'./server.js']);assert.equal(sandbox.process.env.NODE_ENV,'production');assert.equal(sandbox.process.env.PORT,undefined);
});
test('managed startup requires injected PORT and never invents a fallback listener',()=>{
 const source=fs.readFileSync(new URL('../godaddy-node/scripts/start-managed.cjs',import.meta.url),'utf8');
 for(const port of [undefined,'0','passenger','65536'])assert.throws(()=>vm.runInNewContext(source,{process:{env:{PORT:port}},require(){throw Error('No load allowed');}}),/injected PORT/);
 const calls=[],process={env:{PORT:'3000'}};vm.runInNewContext(source,{process,require:p=>calls.push(p)});assert.deepEqual(calls,['../.next/standalone/server.js']);assert.equal(process.env.PORT,'3000');assert.equal(process.env.HOSTNAME,'0.0.0.0');
});
