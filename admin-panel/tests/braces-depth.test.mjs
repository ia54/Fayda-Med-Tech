import test from 'node:test';
import assert from 'node:assert/strict';
import {createRequire} from 'node:module';
const require=createRequire(import.meta.url);
const config=createRequire(require.resolve('eslint-config-next'));
const plugin=createRequire(config.resolve('@next/eslint-plugin-next'));
const glob=createRequire(plugin.resolve('fast-glob'));
const match=createRequire(glob.resolve('micromatch'));
const braces=match('braces');
const guarded=error=>error instanceof RangeError && error.message==='braces nesting exceeds safe depth 64';
test('deep balanced and unbalanced patterns reject explicitly before stack exhaustion',()=>{
 for(const input of ['{'.repeat(4000)+'a,b'+'}'.repeat(4000),'('.repeat(4000)+'a', '{'.repeat(4000)+'a']){
  for(const run of [braces,braces.parse,braces.compile,braces.expand,braces.stringify])assert.throws(()=>run(input),guarded);
 }
});
test('direct deep AST traversals also reject with the bounded nesting error',()=>{
 const make=()=>{let node={type:'text',value:'a'};for(let i=0;i<10000;i++)node={type:'root',nodes:[node]};return node;};
 for(const run of [braces.compile,braces.expand,braces.stringify])assert.throws(()=>run(make()),guarded);
});
test('ordinary Next lint globs, ranges, escaped literals and nested alternatives retain results',()=>{
 assert.deepEqual(braces.expand('src/{app,components}/**/*.{ts,tsx}'),['src/app/**/*.ts','src/app/**/*.tsx','src/components/**/*.ts','src/components/**/*.tsx']);
 assert.deepEqual(braces.expand('x{1..3}'),['x1','x2','x3']);
 assert.deepEqual(braces.expand('{a,{b,c}}'),['a','b','c']);
 assert.equal(braces.stringify(braces.parse('file/\\{literal\\}')),'file/{literal}');
 assert.equal(match('micromatch').isMatch('src/app/page.tsx','src/{app,components}/**/*.{ts,tsx}'),true);
});
