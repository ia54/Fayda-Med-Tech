// Owner-review recording of existing synthetic screens; never provision accounts or publish.
import assert from 'node:assert/strict';
import {readFileSync,writeFileSync,mkdirSync,realpathSync} from 'node:fs';
import {createHash} from 'node:crypto';
import {createRequire} from 'node:module';
import {fileURLToPath} from 'node:url';
const require=createRequire(import.meta.url);
const {chromium}=require(process.env.FAYDAMED_PLAYWRIGHT_MODULE||'playwright-core');
const dir=realpathSync(process.env.FAYDAMED_BROWSER_FIXTURE_DIR||'');
assert.ok(dir.startsWith('/workspace/.cloud-tools/'));
const fixture=JSON.parse(readFileSync(`${dir}/sessions.json`));
const root=fileURLToPath(new URL('../../..',import.meta.url));
for(const [path,hash]of Object.entries(fixture.source_hashes))assert.equal(createHash('sha256').update(readFileSync(`${root}/${path}`)).digest('hex'),hash);
const out=`${dir}/core-recording`;mkdirSync(out,{recursive:false});
const browser=await chromium.launch({executablePath:'/usr/bin/chromium',headless:true,args:['--no-sandbox','--disable-dev-shm-usage','--disable-background-networking']});
const context=await browser.newContext({serviceWorkers:'block',viewport:{width:1440,height:1100},recordVideo:{dir:out,size:{width:1440,height:1100}}});
const blocked=[];
await context.route('**/*',r=>{const u=new URL(r.request().url());const internalPdf=(u.protocol==='chrome-extension:'&&u.hostname==='mhjfbmdgcfjbbpaeojofohoefgiehjai')||(u.protocol==='chrome:'&&u.hostname==='resources');if(!internalPdf&&!['http://127.0.0.1:3000','http://127.0.0.1:8000'].includes(u.origin)){blocked.push(u.hostname);return r.abort();}return r.continue();});
const page=await context.newPage();page.setDefaultTimeout(30000);
const {DatabaseSync}=await import('node:sqlite');const db=new DatabaseSync(`${dir}/shared.sqlite`);const {PDFDocument}=require('pdf-lib');const chapters=[];const start=Date.now();
async function caption(text,seconds=10){chapters.push({seconds:(Date.now()-start)/1000,text});await page.evaluate(text=>{document.querySelector('#demo-commentary')?.remove();const d=document.createElement('div');d.id='demo-commentary';d.textContent='DEMO COMMENTARY · '+text;Object.assign(d.style,{position:'fixed',bottom:'0',left:'0',right:'0',padding:'18px 28px',background:'#10253b',color:'white',font:'22px/1.4 sans-serif',zIndex:'2147483647',borderTop:'4px solid #26c6ab',pointerEvents:'none'});document.body.append(d);},text);await page.waitForTimeout(seconds*1000);}
async function card(title,lines,seconds=12){await page.goto('about:blank');await page.setContent(`<body style="margin:0;background:#10253b;color:white;font:28px/1.65 sans-serif;padding:100px"><p style="color:#26c6ab">FAYDA MEDTECH · CORE MEDTECH · 20 OCT 2026</p><h1 style="font-size:48px">${title}</h1>${lines.map(t=>`<p>${t}</p>`).join('')}<p style="font-size:20px;color:#c5d2df">Owner review copy · Synthetic demonstration · No live payments or provider connections</p></body>`);chapters.push({seconds:(Date.now()-start)/1000,title,lines});await page.waitForTimeout(seconds*1000);}
async function as(role,path){await page.goto('http://127.0.0.1:3000/auth/login');await page.evaluate(session=>{localStorage.clear();sessionStorage.clear();localStorage.setItem('persist:root',JSON.stringify({auth:JSON.stringify(session),_persist:JSON.stringify({version:1,rehydrated:true})}));},fixture.sessions[role]);await page.goto(`http://127.0.0.1:3000${path}`);await page.waitForLoadState('networkidle');}
const wait=async(path,method,action,status)=>{const promise=page.waitForResponse(r=>new URL(r.url()).pathname===path&&r.request().method()===method);await action();const r=await promise;assert.equal(r.status(),status);return r.json();};
const baseline=db.prepare('SELECT * FROM invoices ORDER BY id').all();
const recordingEvidence={};
async function receipt(invoice,amount,reference){await page.getByRole('button',{name:'Record Payment',exact:true}).click();const d=page.getByRole('dialog');await d.locator('#payment-invoice').selectOption(String(invoice.id));await d.locator('#payment-amount').fill(amount);await d.locator('#payment-reference').fill(reference);await caption('Record a payment received elsewhere. MedTech keeps the evidence; it does not collect or move money.',8);const r=await wait('/api/payments','POST',()=>d.getByRole('button',{name:'Record Payment',exact:true}).click(),201);await d.waitFor({state:'hidden'});await page.waitForLoadState('networkidle');return r.data;}
try{
 await card('Core MedTech: one connected working process',[
 'The pharmacy foundation is the operational heart: patient, prescription, stock and handover records.',
 'The wider platform brings case teams, providers, billing staff and clients into a shared workflow.',
 'Follow work from an assigned case to invoice review, receipt records and reporting.',
 'Actual implemented screens. Synthetic records. Pre-authenticated test sessions.',
 'Owner accepted the synthetic pilot. Production approval is a separate step.'
 ],16);
 const pharmacy=fixture.ordinary_pharmacy;
 await as('pharmacist',`/dashboard/pharmacy/${pharmacy.prescription_id}`);await page.getByRole('heading',{name:/SYN-CORE-ORDINARY/}).waitFor();
 await caption('The pharmacy foundation starts with the patient, prescription and case context. These are synthetic records.',14);await page.screenshot({path:`${out}/pharmacy-prescription.png`});
 await page.locator(`#fill-${pharmacy.fill_id}`).scrollIntoViewIfNeeded();
 await caption('This seeded ordinary fill retains pharmacist review, preparation and handover evidence. No medicine was dispensed.',15);await page.screenshot({path:`${out}/pharmacy-fill.png`});
 await page.goto(`http://127.0.0.1:3000/dashboard/pharmacy/stock/${pharmacy.stock_lot_id}`);await page.getByText(/On hand 40/).waitFor();
 await caption('The ordinary stock record retains the receipt and quantity. This segment is separate from the billing example that follows.',14);await page.screenshot({path:`${out}/pharmacy-stock.png`});
 await as('attorney',`/dashboard/legal/cases/${fixture.case_id}`);await page.getByText('SYNTHETIC cloud assigned case',{exact:true}).first().waitFor();
 await caption('Start with an assigned case. Teams can work from shared case context instead of disconnected handoffs.',13);await page.screenshot({path:`${out}/assigned-case.png`});
 await as('provider','/dashboard/provider/claims/create');await page.locator('#patient_name').fill('SYNTHETIC DEMO CLIENT');await page.locator('#service_date').fill(new Date().toISOString().slice(0,10));await page.locator('#case-id').selectOption(String(fixture.case_id));await page.locator('#amount').fill('100.25');
 await caption('Provider: prepare an invoice against the right case, save a draft, then send it to the billing team.',13);
 const invoice=(await wait('/api/invoices','POST',()=>page.getByRole('button',{name:'Save as Draft',exact:true}).click(),201)).data;
 await page.getByRole('button',{name:`View ${invoice.invoice_number}`,exact:true}).click();await page.getByLabel('Draft amount',{exact:true}).fill('100.25');await wait(`/api/provider/invoices/${invoice.id}/draft`,'PUT',()=>page.getByRole('button',{name:'Send for billing review',exact:true}).click(),200);
 await caption('The draft now moves into internal billing review. This is not a live insurer submission.',10);
 await as('biller','/dashboard/billing/invoices');await page.getByRole('button',{name:`Review ${invoice.invoice_number}`,exact:true}).click();await page.getByLabel('Review note',{exact:true}).fill('SYNTHETIC core demo: internal review complete');
 await caption('Billing staff review the invoice and leave a note. Responsibility stays clear as work changes hands.',10);await wait(`/api/invoices/${invoice.id}/review`,'POST',()=>page.getByRole('button',{name:'Mark reviewed',exact:true}).click(),200);
 await page.goto('http://127.0.0.1:3000/dashboard/billing/payments');const first=await receipt(invoice,'40.10','SYN-DEMO-RECEIPT');const original=db.prepare('SELECT * FROM payments WHERE id=?').get(first.id);
 await page.getByRole('button',{name:'Correct SYN-DEMO-RECEIPT',exact:true}).click();await page.locator('#correction-reason').fill('SYNTHETIC amount entered incorrectly; no funds moved');
 await caption('Mistakes can be corrected with a reason. The original receipt remains in history.',11);await wait(`/api/payments/${first.id}/reverse`,'POST',()=>page.getByRole('button',{name:'Reverse receipt entry',exact:true}).click(),201);await receipt(invoice,'100.25','SYN-DEMO-CORRECTED');assert.deepEqual(db.prepare('SELECT * FROM payments WHERE id=?').get(first.id),original);
 await caption('The original entry, its reversal and the corrected receipt form a traceable record.',12);await page.screenshot({path:`${out}/receipt-history.png`});
 await as('client','/dashboard/client/invoices');const row=page.getByRole('row').filter({has:page.getByRole('cell',{name:invoice.invoice_number,exact:true})});await row.waitFor();assert.match(await row.innerText(),/100\.25.*100\.25.*0\.00/s);for(const h of Object.values(fixture.hidden))assert.equal(await page.getByText(h.invoice_number,{exact:true}).count(),0);
 await caption('Client: see the relevant invoice and recorded balance. Other clients and unassigned case invoices stay out of view.',14);
 await as('attorney',`/dashboard/legal/cases/${fixture.case_id}`);await page.getByRole('tab',{name:/Settlement/}).click();await page.getByLabel('Gross Settlement Amount ($)',{exact:true}).fill('1000.00');await page.getByLabel('Attorney Fee %',{exact:true}).fill('20');await page.getByLabel('Litigation Costs ($)',{exact:true}).fill('50.00');await page.getByLabel('Other Deductions / Liens ($)',{exact:true}).fill('25.00');
 await caption('Settlement planning brings fees, costs and deductions together so the team can review a clear allocation.',13);const settlement=(await wait('/api/settlements','POST',()=>page.getByRole('button',{name:'Save Pending Settlement',exact:true}).click(),201)).data;assert.equal(Number(settlement.net_to_client),725);
 await as('firm_admin','/dashboard/legal/settlements');await page.getByRole('button',{name:`Edit settlement ${settlement.id}`,exact:true}).last().click();await page.locator('#status').click();await page.getByRole('option',{name:'Completed',exact:true}).click();await wait(`/api/settlements/${settlement.id}`,'PUT',()=>page.getByRole('button',{name:'Save Settlement',exact:true}).click(),200);
 await caption('An authorized administrator records completion. This changes a record; it does not transfer settlement funds.',11);
 const originalSettlement=db.prepare('SELECT * FROM case_settlements WHERE id=?').get(settlement.id);await page.getByRole('button',{name:`Correct settlement ${settlement.id}`,exact:true}).last().click();for(const [id,value] of Object.entries({'correct-settlement_amount':'1100.00','correct-attorney_fees':'220.00','correct-costs':'50.00','correct-other_deductions':'25.00','correct-reason':'SYNTHETIC revised allocation; no funds movement'}))await page.locator(`#${id}`).fill(value);
 const corrected=(await wait(`/api/settlements/${settlement.id}/corrections`,'POST',()=>page.getByRole('button',{name:'Record Correction',exact:true}).click(),201)).data;assert.equal(Number(corrected.net_to_client),805);assert.deepEqual(db.prepare('SELECT * FROM case_settlements WHERE id=?').get(settlement.id),originalSettlement);
 await caption('Settlement corrections also retain the earlier record. The updated allocation stays explainable.',11);
 await page.goto('http://127.0.0.1:3000/dashboard/legal/reports');await page.getByRole('button',{name:'Open Settlement Summary',exact:true}).click();await page.getByRole('button',{name:'Download CSV',exact:true}).waitFor();
 await caption('Reports turn the recorded work into a useful summary. Export a CSV for review and follow-up.',14);const downloadEvent=page.waitForEvent('download');await page.getByRole('button',{name:'Download CSV',exact:true}).click();const downloaded=await downloadEvent;assert.equal(await downloaded.failure(),null);await downloaded.saveAs(`${out}/synthetic-settlement-summary.csv`);const csv=readFileSync(`${out}/synthetic-settlement-summary.csv`,'utf8');assert.match(csv,/1100/);assert.match(csv,/805/);await page.screenshot({path:`${out}/settlement-report.png`});
 await as('client','/dashboard/client/documents/upload');const pdf=await PDFDocument.create();pdf.addPage().drawText('SYNTHETIC MEDTECH DEMO - NO REAL PATIENT DATA',{x:35,y:700,size:14});const bytes=Buffer.from(await pdf.save());await page.getByLabel('Choose document').setInputFiles({name:'SYN-core-demo.pdf',mimeType:'application/pdf',buffer:bytes});await page.locator('#title').fill('SYNTHETIC core demo document');await page.locator('#upload-case').click();await page.getByRole('option',{name:/SYN-CLOUD-OWN/}).click();await wait('/api/documents','POST',()=>page.getByRole('button',{name:'Upload Document',exact:true}).click(),201);
 await page.goto('http://127.0.0.1:3000/dashboard/client/documents');await page.getByLabel('Preview SYNTHETIC core demo document',{exact:true}).click();await page.locator('[data-document-preview="true"] iframe').waitFor();await page.waitForTimeout(2500);
 await caption('Private documents stay connected to the case. Authorized users can preview the original inside the workspace.',16);await page.screenshot({path:`${out}/private-document.png`});
 await as('biller','/dashboard/billing/eobs');await page.getByRole('button',{name:'Add EOB record',exact:true}).click();for(const [id,value]of Object.entries({patient:'SYNTHETIC core demo',payer:'SYNTHETIC payer',provider:'SYNTHETIC provider',billed:'100.25',paid:'0.00'}))await page.locator(`#${id}`).fill(value);await wait('/api/eobs','POST',()=>page.getByRole('button',{name:'Save EOB',exact:true}).click(),201);await page.getByPlaceholder('Search by patient or payer...').fill('SYNTHETIC core demo');await page.waitForTimeout(700);await page.getByLabel(/^Actions for EOB/).click();await page.getByRole('menuitem',{name:'Review record',exact:true}).click();
 await caption('Manual EOB records support billing review today. No live payer feed is being shown.',13);await page.getByRole('button',{name:'Mark reviewed',exact:true}).click();await page.getByRole('dialog').waitFor({state:'hidden'});
 await page.goto('http://127.0.0.1:3000/dashboard/billing/appeals/create');await page.getByLabel('Select Denied Invoice',{exact:true}).click();await page.getByRole('option',{name:/SYN-PILOT-DENIED/}).click();await page.getByLabel('Denial Reason Category',{exact:true}).click();await page.getByRole('option',{name:'Medical Necessity',exact:true}).click();await page.getByRole('button',{name:'Generate Appeal Letter',exact:true}).click();await page.getByText('Generated Letter',{exact:true}).waitFor();
 await caption('A local appeal template gives staff a starting point. Human review is required; no AI or payer submission is connected.',16);
 await card('The commercial opportunity',[
 'One workflow connects legal, provider, billing and client work.',
 'Clear handoffs can reduce follow-up friction and fragmented records.',
 'Retained corrections make financial records easier to explain and review.',
 'Shared documents and reports support a more organized service operation.'
 ],18);
 await card('A substantial core is working. The next step is focused.',[
 'The synthetic pilot is accepted and the core journeys run in a restored environment.',
 'Next: owner feedback, remaining dependency findings and final release review.',
 'Then: approved hosting, real backup/recovery checks, identity/mail and support readiness.',
 'Any live provider connections need their own configuration and acceptance.'
 ],20);
 await card('Ready for a core MedTech demonstration',[
 'This is working software demonstrated with synthetic records.',
 'The value is in connecting a complex, multi-role process with traceable history.',
 'Production readiness remains a separate decision; no launch date is promised.',
 'Owner review copy. No public live link or partner access has been created.'
 ],16);
 const payments=db.prepare('SELECT amount,reversal_of_id FROM payments ORDER BY id').all();assert.equal(payments.length,3);assert.equal(Math.round(payments.reduce((s,p)=>s+Number(p.amount),0)*100),10025);assert.equal(db.prepare('SELECT status FROM invoices WHERE id=?').get(invoice.id).status,'paid');assert.deepEqual(db.prepare('SELECT * FROM invoices WHERE id IN ('+baseline.map(()=>'?').join(',')+') ORDER BY id').all(...baseline.map(x=>x.id)),baseline);
 Object.assign(recordingEvidence,{invoice_id:invoice.id,receipt_original_preserved:true,settlement_original_preserved:true,net_to_client:805,csv_sha256:createHash('sha256').update(csv).digest('hex'),roles:['attorney','provider','biller','client','firm_admin'],real_money_movement:false,external_provider_calls:false,pharmacy_screens_included:'foundational ordinary records only; seeded through tested request workflow',pharmacy_billing_integration_demonstrated:false});
 writeFileSync(`${out}/chapters.json`,JSON.stringify({source:'ce839f40d36656c147166d63c88689071ee40617',blocked_external_hosts:[...new Set(blocked)],chapters,recordingEvidence},null,2));console.log('Core MedTech and foundational pharmacy demo invariants passed');
}catch(e){await page.screenshot({path:`${out}/failure.png`}).catch(()=>{});throw e;}finally{await context.close();await browser.close();db.close();}
