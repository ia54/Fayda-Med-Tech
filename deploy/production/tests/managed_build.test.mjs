import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
const source=readFileSync(new URL('../godaddy-node/scripts/build-managed.cjs',import.meta.url),'utf8');
function run(status=0) {
 const calls=[]; const process={env:{NODE_ENV:'development',NEXT_PUBLIC_API_URL:'https://api.pilot.invalid',PORT:'3000'},execPath:'/node',exit:code=>{throw Error(`exit:${code}`)}};
 const require=name=>{assert.equal(name,'node:child_process');return {spawnSync:(exe,args,options)=>{calls.push({exe,args:Array.from(args),options});return {status}}}};
 require.resolve=name=>{assert.equal(name,'next/dist/bin/next');return '/next-cli'};
 vm.runInNewContext(source,{process,require});return {process,calls};
}
test('build overrides inherited development mode for all four required steps',()=>{
 const {process,calls}=run();assert.equal(process.env.NODE_ENV,'production');assert.equal(calls.length,4);
 assert.deepEqual(calls.map(c=>c.args),[['scripts/apply-braces-guard.cjs'],['--test','tests/braces-depth.test.mjs','tests/schema-validation.test.mjs'],['/next-cli','build'],['scripts/prepare-standalone.cjs']]);
 for(const call of calls){assert.equal(call.exe,'/node');assert.equal(call.options.env.NODE_ENV,'production');assert.equal(call.options.env.NEXT_PUBLIC_API_URL,'https://api.pilot.invalid');assert.equal(call.options.stdio,'inherit')}
});
test('build fails immediately when a required step fails',()=>{assert.throws(()=>run(17),/exit:17/)});
