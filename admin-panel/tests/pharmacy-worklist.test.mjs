import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {createRequire} from 'node:module';
import test from 'node:test';
import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import ts from 'typescript';
const require=createRequire(import.meta.url),exports={};
const source=readFileSync(new URL('../components/pharmacy/prescription-worklist.tsx',import.meta.url),'utf8');
const js=ts.transpileModule(source,{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2022,jsx:ts.JsxEmit.ReactJSX}}).outputText;
new Function('require','exports',js)(name=>name==='next/link'?{default:({children,...props})=>React.createElement('a',props,children)}:require(name),exports);
const rx={id:41,rx_number:'SYN-WORKLIST',location_id:7,first_name:'Synthetic',last_name:'Patient',case_number:'SYN-CASE',medication:'Synthetic medication',strength:'Synthetic strength',coverage_status:'verification_needed',stage:'pending',review_status:'review_required',claim_status:'not_prepared',patient_return_count:2,patient_return_fill_id:12,pending_handover_count:3,pending_handover_fill_id:13,reviewable_handover_count:1,reviewable_handover_fill_id:14,pending_correction_count:1,open_fill_count:2,reserved_batch_count:1,discontinued_at:'2026-10-28',controlled:true,compounded:true,compound_type:'sterile',dispatch_status:'awaiting_delivery'};
const render=(row=rx,role='pharmacist')=>renderToStaticMarkup(React.createElement(exports.PrescriptionWorklist,{rows:[row],locations:[{id:7,name:'Synthetic location'}],role}));
function layouts(html){return [html.slice(html.indexOf('<ul'),html.indexOf('</ul>')),html.slice(html.indexOf('<table'),html.indexOf('</table>'))];}
test('both layouts retain identity, case, medication, all status and custody/hold signals',()=>{
 for(const html of layouts(render()))for(const text of ['SYN-WORKLIST','Synthetic Patient','SYN-CASE','Synthetic location','Synthetic medication','Synthetic strength','Controlled','Sterile compound','Patient-return custody: 2','Handover corrections pending: 3','Available for your independent review: 1','Allowance correction pending · new fills held','Discontinued','2 open fills','1 batches with reservations','Dispatch: awaiting delivery','Review: review required','Claim: not prepared','verification needed','Open SYN-WORKLIST'])assert.ok(html.includes(text),text);
});
test('original Open and follow-up URLs preserve role-specific independent-review destinations',()=>{
 for(const html of layouts(render()))for(const href of ['/dashboard/pharmacy/41','/dashboard/pharmacy/41#fill-12','/dashboard/pharmacy/41#fill-14'])assert.ok(html.includes(`href="${href}"`));
 for(const html of layouts(render(rx,'pharmacy_technician'))){assert.ok(html.includes('href="/dashboard/pharmacy/41#fill-13"'));assert.ok(html.includes('View pending handover correction'));assert.ok(!html.includes('Available for your independent review'));assert.ok(!html.includes('#fill-14'));}
});
test('missing review/claim and compound classification states are explicit and do not imply review or clearance',()=>{
 for(const html of layouts(render({...rx,review_status:null,claim_status:null,compound_type:null,discontinued_at:null,patient_return_count:0,pending_handover_count:0,pending_correction_count:0,dispatch_status:null}))){assert.ok(html.includes('Review: Not recorded'));assert.ok(html.includes('Claim: Not recorded'));assert.ok(html.includes('Compound · classification pending'));assert.ok(!html.includes('Discontinued'));assert.ok(!html.includes('Allowance correction pending'));}
});
test('card records have named headings, and desktop headers retain table semantics',()=>{
 const html=render();assert.ok(html.includes('aria-labelledby="worklist-rx-41"'));assert.ok(html.includes('id="worklist-rx-41"'));assert.equal((html.match(/scope="col"/g)||[]).length,6);for(const label of ['Patient / accident case','Medication','Latest fill','Coverage','Action'])assert.ok(html.includes(label));
});

const sectionSource=readFileSync(new URL('../components/pharmacy/prescription-section-links.tsx',import.meta.url),'utf8');
const sectionExports={};
new Function('require','exports',ts.transpileModule(sectionSource,{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2022,jsx:ts.JsxEmit.ReactJSX}}).outputText)(require,sectionExports);
test('prescription section navigation exposes evidence only for the existing allowed roles',()=>{
 const renderLinks=canAccessSources=>renderToStaticMarkup(React.createElement(sectionExports.PrescriptionSectionLinks,{canAccessSources}));
 const allowed=renderLinks(true),restricted=renderLinks(false);
 for(const target of ['prescription-order','prescription-fills','prescription-history']){assert.ok(allowed.includes(`href="#${target}"`));assert.ok(restricted.includes(`href="#${target}"`));}
 assert.ok(allowed.includes('href="#prescription-sources"'));assert.ok(!restricted.includes('prescription-sources'));assert.ok(allowed.includes('aria-label="Prescription sections"'));
 assert.match(renderToStaticMarkup(React.createElement(sectionExports.PrescriptionSectionReturn)),/href="#prescription-section-navigation"/);
});
