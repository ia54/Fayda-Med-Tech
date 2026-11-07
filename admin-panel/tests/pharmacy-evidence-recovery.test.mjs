import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {createRequire} from 'node:module';
import test from 'node:test';
import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import ts from 'typescript';
const source=await readFile(new URL('../components/pharmacy/evidence-recovery.tsx',import.meta.url),'utf8');
const compiled=ts.transpileModule(source,{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2022,jsx:ts.JsxEmit.ReactJSX}}).outputText;
const require=createRequire(import.meta.url);const exports={};
new Function('require','exports',compiled)(name=>name==='@/components/ui/button'?{Button:props=>React.createElement('button',props)}:name==='./fields'?{errorMessage:e=>e?.data?.message||'Request unavailable'}:require(name),exports);
const defaults={label:'Quality record',id:17,summary:{id:17,status:'pending',created_by:1},role:'pharmacist',userId:3,excludedAuthors:[2],busy:false,requestError:{status:409,data:{message:'Integrity mismatch'}},retry:()=>{},reject:async()=>{}};
const render=props=>renderToStaticMarkup(React.createElement(exports.EvidenceRecovery,{...defaults,...props}));
test('integrity-conflict recovery offers rejection only, with record identity and explicit confirmation',()=>{
 const html=render({});assert.match(html,/Quality record #17/);assert.match(html,/Author #1/);assert.match(html,/Reject unusable proposal/);assert.match(html,/type="checkbox" required/);assert.match(html,/name="evidence" required/);assert.doesNotMatch(html,/<select|value="reviewed"|Approve|Retain independent documentary review/);
});
test('network, access and server errors never expose a destructive recovery action',()=>{
 for(const status of ['FETCH_ERROR','PARSING_ERROR',401,403,404,422,500,503])assert.doesNotMatch(render({requestError:{status}}),/<form/);
});
test('authors, preparation contributors, technicians and missing/stale history cannot reject',()=>{
 for(const props of [{userId:1},{userId:2},{userId:0},{userId:NaN},{role:'pharmacy_technician'},{role:'admin'},{summary:undefined},{summary:{...defaults.summary,id:18}},{summary:{...defaults.summary,status:'reviewed'}},{summary:{...defaults.summary,status:'rejected'}}])assert.doesNotMatch(render(props),/<form/);
});
test('fresh history requests disable submission and draft protocol recovery escapes error content',()=>{
 const html=render({busy:true});assert.match(html,/<fieldset disabled/);
 const draft=render({summary:{...defaults.summary,status:'draft',version:1},requestError:{status:409,data:{message:'<script>alert(1)</script>'}}});assert.match(draft,/<form/);assert.doesNotMatch(draft,/<script>/);assert.match(draft,/&lt;script&gt;/);
});
