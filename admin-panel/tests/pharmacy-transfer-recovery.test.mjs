import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {createRequire} from 'node:module';
import test from 'node:test';
import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import ts from 'typescript';
const source=await readFile(new URL('../components/pharmacy/prescription-transfer-review.tsx',import.meta.url),'utf8');
const compiled=ts.transpileModule(source,{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2022,jsx:ts.JsxEmit.ReactJSX}}).outputText;
const require=createRequire(import.meta.url),exports={};
let transfer,sites,fetching,failed;
const defaults={id:17,source_prescription_id:9,source_location_id:1,destination_location_id:2,source_location_name:'Sending site',destination_location_name:'Receiving site',sent_by:1,status:'pending',source_token:'a'.repeat(64),source_snapshot:null,source_snapshot_unavailable:true};
const wrap=props=>React.createElement('div',null,props.children);
new Function('require','exports',compiled)(name=>{
 if(name==='next/link')return {default:props=>React.createElement('a',props)};
 if(name==='react-redux')return {useSelector:()=>null};
 if(name==='@/store/api/pharmacyApiSlice')return {useGetRxTransferQuery:()=>({currentData:{data:transfer},isFetching:fetching,isError:failed,refetch:()=>{}}),useGetPharmacyLocationsQuery:()=>({currentData:{data:sites},isFetching:fetching,isError:false}),useReviewRxTransferMutation:()=>[()=>{}, {isLoading:false}]};
 if(name==='@/components/ui/card')return {Card:wrap,CardHeader:wrap,CardTitle:wrap,CardContent:wrap};
 if(name==='@/components/ui/button')return {Button:props=>React.createElement('button',props)};
 if(name==='./fields')return {Field:({name,label})=>React.createElement('label',null,label,React.createElement('input',{name,required:true})),errorMessage:()=>''};
 if(name==='./prescription-transfer')return {TransferQuantities:()=>React.createElement('p',null,'Remaining quantities')};
 return require(name);
},exports);
const render=({record={},locations=[{id:1},{id:2}],busy=false,error=false,role='pharmacist',actorId=2}={})=>{transfer={...defaults,...record};sites=locations;fetching=busy;failed=error;return renderToStaticMarkup(React.createElement(exports.PrescriptionTransferReview,{id:17,actorId,role}));};
test('unreadable snapshot renders recovery without acceptance or source download',()=>{
 const html=render();assert.match(html,/snapshot is unreadable/);assert.match(html,/value="reject"/);assert.match(html,/value="cancel"/);assert.doesNotMatch(html,/value="accept"|Retrieve original prescription file|Remaining quantities/);assert.match(html,/type="checkbox" required/);
});
test('recovery respects role, current sites and independent receiver',()=>{
 assert.doesNotMatch(render({role:'pharmacy_technician'}),/<form/);
 assert.doesNotMatch(render({locations:[]}),/<form/);
 const sender=render({actorId:1,locations:[{id:1}]});assert.match(sender,/value="cancel"/);assert.doesNotMatch(sender,/value="reject"/);
 const receiver=render({locations:[{id:2}]});assert.match(receiver,/value="reject"/);assert.doesNotMatch(receiver,/value="cancel"/);
 assert.doesNotMatch(render({error:true}),/<form/);
});
test('resolved damaged evidence shows retained decision and refreshed records disable submission',()=>{
 assert.match(render({busy:true}),/<button[^>]*disabled/);
 const html=render({record:{status:'cancelled',reviewed_by:1,review_evidence:'Investigated <script>bad</script>'}});assert.match(html,/Retained decision/);assert.match(html,/&lt;script&gt;/);assert.doesNotMatch(html,/<form|<script>/);
});
test('readable pending evidence retains normal independent acceptance and source review',()=>{
 const snapshot={identity:{patient:{first_name:'Synthetic',last_name:'Patient'},case:{case_number:'SYN-1'}},order:{rx_number:'SYN-RX',medication:'Synthetic only'},allowances:[],source_documents:[{id:7,original_name:'synthetic.pdf',reference:'Synthetic source'}]};
 const html=render({record:{source_snapshot:snapshot,source_snapshot_unavailable:false}});assert.match(html,/value="accept"/);assert.match(html,/Retrieve original prescription file/);assert.match(html,/Synthetic Patient/);assert.doesNotMatch(html,/snapshot is unreadable/);
});
