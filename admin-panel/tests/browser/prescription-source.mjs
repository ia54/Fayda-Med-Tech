import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {cloudBrowser,sha256,snapshot} from './cloud-browser.mjs';

// Explicitly synthetic fixture: authenticated browser retrieval is not hosted or login acceptance.
const c=await cloudBrowser('ordinary.sqlite'),{pages,fixture,db}=c;
const page=pages.pharmacist,id=fixture.prescriptions.collection;
const path=`/api/pharmacy/prescriptions/${id}/sources`;
const bytes=Buffer.from('%PDF-1.4\n% SYNTHETIC prescription evidence ONLY; no clinical authority\n%%EOF\n');
const immutable=['pharmacy_fills','pharmacy_stock_lots','invoices','payments','pharmacy_batch_executions'];
const before=snapshot(db,immutable),denials=[];
try {
 await page.setViewportSize({width:390,height:844});
 await page.goto(`http://127.0.0.1:3000/dashboard/pharmacy/${id}`);
 await page.getByRole('button',{name:'Retain original evidence',exact:true}).focus();await page.keyboard.press('Enter');
 assert.equal(await page.getByLabel('Source / receipt reference and reason for attaching').evaluate(e=>e===document.activeElement&&!e.validity.valid),true,'Keyboard submission must identify the required receipt reference');
 assert.equal(db.prepare('SELECT COUNT(*) n FROM pharmacy_source_documents').get().n,0);
 await page.getByLabel('Source / receipt reference and reason for attaching').fill('SYNTHETIC browser source acceptance 20261027');
 await page.getByLabel('Prescription file (PDF, PNG or JPEG; up to 10 MB)').setInputFiles({name:'SYN-prescription.pdf',mimeType:'application/pdf',buffer:bytes});
 const upload=page.waitForResponse(r=>new URL(r.url()).pathname===path&&r.request().method()==='POST');
 await page.getByRole('button',{name:'Retain original evidence',exact:true}).click();
 const response=await upload;assert.equal(response.status(),201);const source=(await response.json()).data;
 await page.reload({waitUntil:'networkidle'});
 await page.getByText('SYN-prescription.pdf',{exact:true}).waitFor();
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'390px page must not overflow');
 const retrieve=page.getByRole('button',{name:'Retrieve original file',exact:true});
 await retrieve.focus();await page.keyboard.press('Enter');
 const link=page.getByRole('link',{name:'Download SYN-prescription.pdf',exact:true});await link.waitFor();
 await link.focus();assert.equal(await link.evaluate(e=>e===document.activeElement),true);
 const pending=page.waitForEvent('download');await page.keyboard.press('Enter');const download=await pending;
 assert.equal(await download.failure(),null);const saved=`${c.directory}/downloaded-SYN-prescription.pdf`;await download.saveAs(saved);
 assert.equal(readFileSync(saved).length,bytes.length);assert.equal(sha256(readFileSync(saved)),sha256(bytes));assert.equal(db.prepare('SELECT sha256 FROM pharmacy_source_documents WHERE id=?').get(source.id).sha256,sha256(bytes));
 for(const [role,status] of [['biller',403],['outsider',404],['unassigned',404]]) {
  const p=pages[role];await p.goto('http://127.0.0.1:3000/dashboard');
  for(const suffix of ['',`/${source.id}/file`]){
   const actual=await p.evaluate(async({url,token})=>(await fetch(url,{headers:{Authorization:`Bearer ${token}`}})).status,{url:`http://127.0.0.1:8000${path}${suffix}`,token:fixture.sessions[role].token.access_token});
   assert.equal(actual,status,`${role} ${suffix}`);denials.push({role,suffix,status:actual});
  }
 }
 const anonymous=await page.evaluate(async url=>(await fetch(url)).status,`http://127.0.0.1:8000${path}/${source.id}/file`);assert.equal(anonymous,401);
 await page.setViewportSize({width:320,height:740});await page.reload({waitUntil:'networkidle'});
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'320px page must not overflow');
 await page.getByRole('button',{name:'Retrieve original file',exact:true}).focus();await page.keyboard.press('Enter');await page.getByRole('link',{name:'Download SYN-prescription.pdf',exact:true}).waitFor();
 await page.screenshot({path:`${c.directory}/prescription-source-mobile.png`,fullPage:true});
 assert.deepEqual(snapshot(db,immutable),before);assert.equal(db.prepare('SELECT COUNT(*) n FROM pharmacy_source_documents').get().n,1);
 assert.equal(db.prepare("SELECT COUNT(*) n FROM pharmacy_events WHERE action='source_document_added'").get().n,1);
 assert.equal(c.responses.some(r=>r.status>=500),false);
 await c.result('prescription-source',{upload_reload_download:true,bytes:bytes.length,sha256:sha256(bytes),denials,anonymous_status:anonymous,widths:[390,320],keyboard_retrieve_download:true,required_field_keyboard_validation:true,inventory_fills_money_unchanged:true,source_records:1,source_audit_events:1,hosted_proof:false,release_authorized:false});
} catch(e){await c.failure('prescription-source');throw e;} finally{await c.close();}
