import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {createRequire} from 'node:module';
import test from 'node:test';
import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import ts from 'typescript';
const source=await readFile(new URL('../components/pharmacy/handover-rehearsal.tsx',import.meta.url),'utf8');
const compiled=ts.transpileModule(source,{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2022,jsx:ts.JsxEmit.ReactJSX}}).outputText;
const require=createRequire(import.meta.url);const exports={};
let query,context;
const snapshot={unit:'tablet',containers:[{container:{identifier:'SYN-CONTAINER',quantity:'9.000'},proof_id:17}]};
const row={id:4,status:'reserved',created_by:1,version:1,snapshot:JSON.stringify(snapshot),created_at:'synthetic',reservation_evidence:'SYNTHETIC package'};
new Function('require','exports',compiled)(name=>name==='react'?{...React,useState:initial=>React.useState(initial===false?true:initial)}:name==='@/hooks/useAuth'?{useAuth:()=>({user:{role:'pharmacist',id:2}})}:name==='@/components/ui/button'?{Button:props=>React.createElement('button',props)}:name==='./fields'?{Field:({label,...props})=>React.createElement('label',null,label,React.createElement('input',props)),errorMessage:e=>e?.data?.message||'unavailable'}:name==='@/store/api/pharmacyApiSlice'?{
 useGetHandoverRehearsalsQuery:()=>query,useGetHandoverRehearsalContextQuery:()=>context,usePharmacyStockActionMutation:()=>[()=>{}, {isLoading:false}],
}:require(name),exports);
function render(q={}){query={currentData:{can_start:false,data:{data:[row],total:1,last_page:1}},isError:false,isFetching:false,refetch:()=>{},...q};context={currentData:{data:{source_hash:'synthetic',snapshot}},isError:false,isFetching:false};return renderToStaticMarkup(React.createElement(exports.HandoverRehearsal,{execution:1}));}
test('refresh retains the same reserved form tree while disabling outcome submission',()=>{const html=render({isFetching:true});assert.match(html,/Refreshing rehearsal history/);assert.match(html,/Rehearsal #4/);assert.match(html,/name="evidence"/);assert.match(html,/<button disabled="">Save simulated outcome/);});
test('failed history retains cached evidence and a disabled form, with explicit retry',()=>{const html=render({isError:true});assert.match(html,/Rehearsal history unavailable/);assert.match(html,/SYNTHETIC package/);assert.match(html,/<button disabled="">Save simulated outcome/);assert.match(html,/>Retry<\/button>/);});
test('initial loading or failed history without data offers no outcome form',()=>{for(const q of [{isFetching:true},{isError:true}])assert.doesNotMatch(render({...q,currentData:undefined}),/<form/);});
