import assert from 'node:assert/strict';
import {cloudBrowser,snapshot} from './cloud-browser.mjs';

const c=await cloudBrowser('ordinary.sqlite'),{fixture:f,db,pages}=c;
const page=pages.pharmacist,base='http://127.0.0.1:3000',today=new Date().toISOString().slice(0,10);
const steps=[],probes=[];
const unchanged=['pharmacy_ingredient_lots','pharmacy_batch_executions','pharmacy_prescriptions'];
const baseline=snapshot(db,unchanged);
async function submit(p,path,method,button,status=200){
 const invalid=await button.evaluate(b=>Array.from(b.closest('form')?.elements||[]).filter(e=>e.willValidate&&!e.validity.valid).map(e=>e.name));
 assert.deepEqual(invalid,[],`Complete required synthetic evidence before ${method} ${path}`);
 const pending=p.waitForResponse(r=>new URL(r.url()).pathname===path&&r.request().method()===method);
 pending.catch(()=>{});await button.click();const response=await pending;
 assert.equal(response.status(),status,`${method} ${path}: ${await response.text()}`);
 const result=await response.json();
 // Reopen saved state after each mutation, including all route queries, rather
 // than editing a form while RTK invalidation replaces its source version.
 await p.reload({waitUntil:'networkidle'});return result;
}
async function fields(form,values){for(const [name,value] of Object.entries(values)){
 const field=form.locator(`[name="${name}"]`);if(await field.evaluate(e=>e.tagName)==='SELECT')await field.selectOption(value);else await field.fill(value);
}}
async function checked(form){for(const box of await form.locator('input[type=checkbox][required]').all())await box.check();}
async function probe(role,path,method='GET',body){
 if(!pages[role].url().startsWith(base+'/'))await pages[role].goto(base+'/dashboard/pharmacy',{waitUntil:'networkidle'});
 const result=await pages[role].evaluate(async({path,method,body,token})=>{
  const r=await fetch('http://127.0.0.1:8000'+path,{method,headers:{Authorization:`Bearer ${token}`,Accept:'application/json','Content-Type':'application/json'},...(body?{body:JSON.stringify(body)}:{})});return {status:r.status};
 },{path,method,body,token:f.sessions[role].token.access_token});
 probes.push({role,path,method,status:result.status});return result.status;
}
try{
 await page.goto(`${base}/dashboard/pharmacy/patients/${f.patient_id}`);
 await page.getByRole('heading',{name:'Clinical record',exact:true}).waitFor();
 assert.equal(db.prepare('SELECT version FROM pharmacy_patients WHERE id=?').get(f.patient_id).version,1);
 await page.getByText('Record clinical review',{exact:true}).click();
 const clinical=page.locator('form').filter({has:page.getByRole('button',{name:'Save pharmacist review',exact:true})});
 await fields(clinical,{allergies_status:'none_reported',medications_status:'none_reported',reviewed_on:today,source_reference:'SYNTHETIC interview; no real clinical approval'});
 await submit(page,`/api/pharmacy/patients/${f.patient_id}/clinical`,'PUT',clinical.getByRole('button',{name:'Save pharmacist review',exact:true}));
 await page.reload();await page.getByText('SYNTHETIC interview; no real clinical approval',{exact:true}).first().waitFor();
 steps.push('Pharmacist clinical review saved and reloaded from initially unknown chart');
 await page.goto(`${base}/dashboard/pharmacy/stock/${f.lot_id}`);
 const productButton=page.getByRole('button',{name:'Save product verification',exact:true});
 await page.getByText('Verify receipt product',{exact:true}).click();
 const product=productButton.locator('..');
 await fields(product,{generic_name:'SYNTHETIC established name',strength:'SYNTHETIC strength',dosage_form:'tablet',manufacturer:'SYNTHETIC manufacturer',package_code:'99999999999997',verified_on:today,evidence:'SYNTHETIC package receipt comparison',reason:'SYNTHETIC browser verification'});
 await checked(product);await submit(page,`/api/pharmacy/stock/${f.lot_id}/product`,'POST',productButton,201);
 steps.push('Product verification retained through UI without changing stock quantity');
 assert.equal(Number(db.prepare('SELECT on_hand FROM pharmacy_stock_lots WHERE id=?').get(f.lot_id).on_hand),50);
 for(const [journey,rx] of Object.entries(f.prescriptions)){
  console.log(`Starting ${journey}`);
  await page.goto(`${base}/dashboard/pharmacy/${rx}`);
  await page.getByText('Create fill and reserve stock',{exact:true}).click();
  await page.getByRole('combobox',{name:/^Stock at this pharmacy/}).selectOption(String(f.lot_id));
  await page.getByRole('spinbutton',{name:/^Planned quantity/}).fill('10');
  await page.getByLabel('Days’ supply',{exact:true}).fill('10');
  await submit(page,`/api/pharmacy/prescriptions/${rx}/fills`,'POST',page.getByRole('button',{name:'Reserve stock for review',exact:true}));
  const fill=db.prepare('SELECT id FROM pharmacy_fills WHERE prescription_id=?').get(rx).id;
  const card=page.locator(`#fill-${fill}`),actionForm=()=>card.locator('form').filter({has:page.getByRole('combobox',{name:/^Next action/})});
  let form=actionForm();await form.getByLabel('Action note / review findings',{exact:true}).fill('SYNTHETIC ordinary review');await checked(form);
  await submit(page,`/api/pharmacy/prescriptions/${rx}/fills/${fill}/actions`,'POST',form.getByRole('button',{name:'Approve pharmacist review',exact:true}));
  await card.getByText('Create label proof',{exact:true}).click();
  const label=card.locator('form').filter({has:page.getByRole('button',{name:'Retain label proof',exact:true})});
  await fields(label,{dispensed_on:today,use_by:today,use_by_reference:'SYNTHETIC packaging basis',daw:'false',substituted:'false',selection_reference:'SYNTHETIC order comparison',do_not_label:'false',reason:'SYNTHETIC initial label'});await checked(label);
  await submit(page,`/api/pharmacy/prescriptions/${rx}/fills/${fill}/labels`,'POST',label.getByRole('button',{name:'Retain label proof',exact:true}),201);
  const retained=db.prepare('SELECT id,barcode_code FROM pharmacy_fill_labels WHERE fill_id=?').get(fill);
  await card.getByText(/Print and reprint evidence/).click();
  const print=card.locator('form').filter({has:page.getByRole('button',{name:'Record print evidence',exact:true})});
  await fields(print,{purpose:'dispensing_label',occurred_on:today,reason:'SYNTHETIC print exercise',reference:'SYNTHETIC output comparison; no printer used'});await checked(print);
  await submit(page,`/api/pharmacy/prescriptions/${rx}/fills/${fill}/labels/${retained.id}/prints`,'POST',print.getByRole('button',{name:'Record print evidence',exact:true}),201);
  form=actionForm();await form.getByRole('combobox',{name:/^Next action/}).selectOption('ready');
  await fields(form,{note:'SYNTHETIC final comparison',scan_input_method:'manual',scan_package_code:'99999999999997',scan_lot_number:'SYN-LOT',scan_label_code:retained.barcode_code,scan_manual_reason:'SYNTHETIC manual comparison; hardware not tested'});await checked(form);
  await submit(page,`/api/pharmacy/prescriptions/${rx}/fills/${fill}/actions`,'POST',form.getByRole('button',{name:'Confirm final check',exact:true}));
  if(journey==='delivery'){
   form=actionForm();await form.getByRole('combobox',{name:/^Next action/}).selectOption('dispatch');
   await fields(form,{note:'SYNTHETIC custody',scan_input_method:'manual',scan_label_code:retained.barcode_code,scan_manual_reason:'SYNTHETIC comparison',transport_method:'pharmacy_staff',transport_quantity:'10',transport_courier_reference:'SYNTHETIC staff',transport_package_reference:'SYNTHETIC package',transport_occurred_on:today});await checked(form);
   await submit(page,`/api/pharmacy/prescriptions/${rx}/fills/${fill}/actions`,'POST',form.getByRole('button',{name:'Record dispatch',exact:true}));
  }
  form=actionForm();await form.getByRole('combobox',{name:/^Next action/}).selectOption(journey==='delivery'?'delivered':'collected');
  await fields(form,{note:'SYNTHETIC receipt',occurred_on:today,reference:'SYNTHETIC handover',scan_input_method:'manual',scan_label_code:retained.barcode_code,scan_manual_reason:'SYNTHETIC comparison',handover_recipient_type:'patient',handover_recipient_name:'Synthetic Ordinary',handover_identity_reference:'SYNTHETIC identity comparison',counseling:'provided',handover_counseling_reference:'SYNTHETIC counseling exercise',...(journey==='delivery'?{handover_delivery_method:'pharmacy_staff',handover_delivery_reference:'SYNTHETIC confirmed receipt'}:{})});await checked(form);
  await submit(page,`/api/pharmacy/prescriptions/${rx}/fills/${fill}/actions`,'POST',form.getByRole('button',{name:journey==='delivery'?'Record delivery':'Record pickup',exact:true}));
  await page.reload();await page.locator(`#fill-${fill}`).getByRole('heading',{name:'Retained handover evidence',exact:true}).waitFor();
  await page.locator(`#fill-${fill}`).screenshot({path:`${c.directory}/${journey}-complete.png`});
  const completed=db.prepare('SELECT * FROM pharmacy_fills WHERE id=?').get(fill);
  assert.equal(await probe('pharmacist',`/api/pharmacy/prescriptions/${rx}/fills/${fill}/actions`,'POST',{action:journey==='delivery'?'delivered':'collected',version:completed.version}),422);
  assert.equal(db.prepare("SELECT count(*) n FROM pharmacy_stock_events WHERE fill_id=? AND action='dispensed'").get(fill).n,1);
  const biller=pages.biller;await biller.goto(`${base}/dashboard/pharmacy/${rx}`);
  await biller.getByText('Update coverage evidence',{exact:true}).click();
  const coverage=biller.locator('form').filter({has:biller.getByRole('button',{name:'Save coverage evidence',exact:true})});
  await fields(coverage,{status:'verified',payer:'SYNTHETIC payer',claim_number:'SYN-ORDINARY-PIP',coordination:'SYNTHETIC coordination',verified_on:today,evidence:'SYNTHETIC coverage only'});
  await submit(biller,`/api/pharmacy/prescriptions/${rx}/coverage`,'PUT',coverage.getByRole('button',{name:'Save coverage evidence',exact:true}));
  const claim=biller.locator(`#fill-${fill} form`);await fields(claim,{note:'SYNTHETIC billing handoff',reference:'SYNTHETIC pricing basis',amount:'123.45'});
  await submit(biller,`/api/pharmacy/prescriptions/${rx}/fills/${fill}/actions`,'POST',claim.getByRole('button',{name:'Prepare billing draft',exact:true}));
  const invoice=db.prepare('SELECT invoice_id FROM pharmacy_fills WHERE id=?').get(fill).invoice_id;assert.ok(invoice);
  // The application opens this invoice only after a retained submission record.
  // This is explicitly synthetic evidence, not a payer call or transmission.
  const submission=biller.locator(`#fill-${fill} form`);
  await fields(submission,{note:'SYNTHETIC rehearsal only; no payer contacted',reference:'SYNTHETIC-NO-TRANSMISSION',occurred_on:today});
  await submit(biller,`/api/pharmacy/prescriptions/${rx}/fills/${fill}/actions`,'POST',submission.getByRole('button',{name:'Record external submission',exact:true}));
  await biller.goto(`${base}/dashboard/billing/payments`);await biller.getByRole('button',{name:'Record Payment',exact:true}).click();
  const dialog=biller.getByRole('dialog');await dialog.locator('#payment-invoice').selectOption(String(invoice));await dialog.locator('#payment-amount').fill('123.45');await dialog.locator('#payment-reference').fill(`SYN-ORDINARY-${journey}`);
  await submit(biller,'/api/payments','POST',dialog.getByRole('button',{name:'Record Payment',exact:true}),201);
  await biller.screenshot({path:`${c.directory}/${journey}-receipt.png`,fullPage:true});
  assert.equal(Number(db.prepare('SELECT SUM(amount) total FROM payments WHERE invoice_id=?').get(invoice).total),123.45);
  steps.push(`${journey}: reserve, approve, label, simulated print, prepare, handover, billing draft, synthetic submission record and recorded receipt through UI; persisted and duplicate handover rejected; no payer transmission`);
 }
 for(const role of ['outsider','unassigned']){
  for(const path of [`/api/pharmacy/patients/${f.patient_id}`,`/api/pharmacy/stock/${f.lot_id}`,`/api/pharmacy/prescriptions/${f.prescriptions.collection}`])assert.equal(await probe(role,path),404);
 }
 assert.equal(await probe('biller',`/api/pharmacy/patients/${f.patient_id}`),403);
 assert.equal(await probe('technician',`/api/pharmacy/patients/${f.patient_id}/clinical`,'PUT',{version:2}),403);
 assert.equal(Number(db.prepare('SELECT on_hand FROM pharmacy_stock_lots WHERE id=?').get(f.lot_id).on_hand),30);
 assert.equal(Number(db.prepare('SELECT reserved FROM pharmacy_stock_lots WHERE id=?').get(f.lot_id).reserved),0);
 assert.equal(db.prepare("SELECT count(*) n FROM pharmacy_stock_events WHERE action='dispensed'").get().n,2);
 assert.equal(db.prepare('SELECT count(*) n FROM invoices').get().n,2);assert.equal(db.prepare('SELECT count(*) n FROM payments').get().n,2);
 const audit=Object.fromEntries(db.prepare('SELECT action,count(*) n FROM pharmacy_events GROUP BY action').all().map(r=>[r.action,r.n]));
 for(const action of ['fill_created','approve','ready','prepare_claim','record_submission'])assert.equal(audit[action],2,`Retained ${action} history`);
 assert.equal(audit.collected,1);assert.equal(audit.delivered,1);assert.equal(audit.dispatch,1);
 assert.deepEqual(snapshot(db,unchanged),baseline);
 assert.equal(c.responses.some(r=>r.status>=500),false);
 await c.result('ordinary-pharmacy',{steps,api_probes:probes,stock:{initial:50,final:30,reserved:0,dispensed_events:2},invoices:2,recorded_receipts:2,audit_actions:audit,protected_table_hashes:baseline,clinical_release:false,hardware_verified:false,provider_calls:false});
}catch(error){await c.failure('ordinary-pharmacy');throw error;}finally{await c.close();}
