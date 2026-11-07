// Manual cloud-only acceptance. Requires the opted-in PHP fixture and local servers.
import assert from 'node:assert/strict';
import {readFileSync,writeFileSync} from 'node:fs';
import {createRequire} from 'node:module';
import {DatabaseSync} from 'node:sqlite';
const require=createRequire(import.meta.url);
const {chromium}=require(process.env.FAYDAMED_PLAYWRIGHT_MODULE||'playwright-core');
const directory=process.env.FAYDAMED_BROWSER_FIXTURE_DIR;
assert.ok(directory?.startsWith('/workspace/.cloud-tools/'),'Use an isolated cloud synthetic fixture');
const fixture=JSON.parse(readFileSync(`${directory}/sessions.json`,'utf8'));
const db=new DatabaseSync(`${directory}/dispatch.sqlite`,{readOnly:true});
const originalLoss=db.prepare('SELECT * FROM pharmacy_dispatch_returns').get();
const originalFulfillment=db.prepare('SELECT fulfillment FROM pharmacy_fills WHERE id=?').get(fixture.fill_id);
const originalReturn=db.prepare('SELECT status,on_hand FROM pharmacy_stock_lots WHERE id=?').get(originalLoss.returned_lot_id);
assert.equal(Number(JSON.parse(originalLoss.record).lost_quantity),6);
assert.equal(originalReturn.status,'quarantined');
assert.equal(Number(originalReturn.on_hand),4);
assert.equal(Number(db.prepare('SELECT on_hand FROM pharmacy_stock_lots WHERE id=?').get(fixture.source_lot_id).on_hand),40);
assert.equal(db.prepare('SELECT count(*) AS n FROM pharmacy_dispatch_recoveries').get().n,0,'Always start with a fresh fixture');
const browser=await chromium.launch({executablePath:'/usr/bin/chromium',headless:true,args:['--no-sandbox','--disable-dev-shm-usage','--disable-background-networking','--disable-component-update']});
const external=new Set();
const steps=[];
try{
 const pages={};
 for(const role of ['author','reviewer']){
  const context=await browser.newContext({serviceWorkers:'block',viewport:{width:1440,height:1100}});
  await context.route('**/*',route=>{
   const u=new URL(route.request().url());
   if(!['http://127.0.0.1:3000','http://127.0.0.1:8000'].includes(u.origin)){external.add(u.hostname);return route.abort();}
   return route.continue();
  });
  await context.addInitScript(session=>localStorage.setItem('persist:root',JSON.stringify({auth:JSON.stringify(session),_persist:JSON.stringify({version:1,rehydrated:true})})),fixture.sessions[role]);
  pages[role]=await context.newPage();
 }
 const url=`http://127.0.0.1:3000/dashboard/pharmacy/${fixture.prescription_id}`;
 const open=async page=>{await page.goto(url,{waitUntil:'domcontentloaded'});await page.getByText('Fills and billing',{exact:true}).waitFor({timeout:60000});};
 const submit=async(page,button)=>{
  const response=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().endsWith(`/fills/${fixture.fill_id}/actions`),{timeout:30000});
  await button.click();const r=await response;
  assert.equal(r.status(),200,`UI action failed: ${await r.text()}`);
 };
 for(const quantity of ['2.125','3.875']){
  const author=pages.author;await open(author);
  const button=author.getByRole('button',{name:'Record medication recovered after loss',exact:true});
  await button.waitFor({timeout:30000});const form=button.locator('..');
  await form.locator('[name="note"]').fill('SYNTHETIC cloud browser recovered package');
  await form.locator('[name="transport_quantity"]').fill(quantity);
  await form.locator('[name="transport_label_code"]').fill(fixture.label_code);
  await form.locator('[name="transport_package_condition"]').fill('SYNTHETIC intact seal');
  await form.locator('[name="transport_segregation_reference"]').fill(`SYNTHETIC cloud quarantine ${quantity}`);
  await form.locator('[name="transport_never_received_by_patient"]').check();
  await form.locator('[name="transport_occurred_on"]').fill(new Date().toISOString().slice(0,10));
  await form.locator('[name="transport_evidence"]').fill('SYNTHETIC recovered quantity and original package independently checkable');
  await form.locator('[name="transport_confirmed"]').check();
  await submit(author,button);
  await author.getByText('Another assigned pharmacist must review your recovery record.',{exact:true}).waitFor();
  assert.equal(await author.getByRole('button',{name:'Review recovered medication',exact:true}).count(),0,'Author must not see independent review');
  steps.push(`Author proposed ${quantity}; self-review unavailable`);
  const reviewer=pages.reviewer;await open(reviewer);
  const review=reviewer.getByRole('button',{name:'Review recovered medication',exact:true});await review.waitFor({timeout:30000});const reviewForm=review.locator('..');
  await reviewForm.locator('[name="note"]').fill('SYNTHETIC independent cloud browser review');
  await reviewForm.locator('[name="transport_decision"]').selectOption('apply');
  await reviewForm.locator('[name="transport_evidence"]').fill(`SYNTHETIC independently counted ${quantity} and retained original label`);
  await reviewForm.locator('[name="transport_confirmed"]').check();
  await submit(reviewer,review);
  await reviewer.reload({waitUntil:'domcontentloaded'});
  await reviewer.getByText(`Remaining confirmed loss after reviewed recoveries: ${quantity==='2.125'?'3.875':'0.000'} units. Original evidence remains retained.`,{exact:true}).waitFor({timeout:30000});
  steps.push(`Independent reviewer applied ${quantity}; remaining loss persisted after reload`);
 }
 const recovered=db.prepare('SELECT r.quantity,r.returned_lot_id,l.status,l.on_hand FROM pharmacy_dispatch_recoveries r JOIN pharmacy_stock_lots l ON l.id=r.returned_lot_id ORDER BY r.id').all();
 assert.equal(recovered.length,2);
 assert.equal(new Set([originalLoss.returned_lot_id,...recovered.map(r=>r.returned_lot_id)]).size,3);
 assert.deepEqual(recovered.map(r=>Number(r.quantity)),[2.125,3.875]);
 assert.deepEqual(recovered.map(r=>Number(r.on_hand)),[2.125,3.875]);
 assert.ok(recovered.every(r=>r.status==='quarantined'));
 assert.deepEqual(db.prepare('SELECT * FROM pharmacy_dispatch_returns').get(),originalLoss);
 assert.deepEqual(db.prepare('SELECT status,on_hand FROM pharmacy_stock_lots WHERE id=?').get(originalLoss.returned_lot_id),originalReturn);
 assert.deepEqual(db.prepare('SELECT fulfillment FROM pharmacy_fills WHERE id=?').get(fixture.fill_id),originalFulfillment);
 assert.equal(db.prepare('SELECT fulfillment_status FROM pharmacy_fills WHERE id=?').get(fixture.fill_id).fulfillment_status,'cancelled');
 assert.equal(Number(db.prepare('SELECT on_hand FROM pharmacy_stock_lots WHERE id=?').get(fixture.source_lot_id).on_hand),40);
 assert.equal(db.prepare('SELECT count(*) AS n FROM invoices').get().n,0);
 assert.equal(await pages.reviewer.getByRole('button',{name:'Record medication recovered after loss',exact:true}).count(),0);
 await pages.reviewer.screenshot({path:`${directory}/dispatch-recovery-complete.png`,fullPage:true});
 const result={browser:browser.version(),steps,recovery_receipts:recovered,original_source_stock:40,original_lost_quantity:6,remaining_loss:0,fill:'cancelled',invoices:0,seeded_authentication:true,login_mfa_verified:false,external_page_requests_blocked:[...external]};
 writeFileSync(`${directory}/result.json`,JSON.stringify(result,null,2)+'\n');
 console.log(JSON.stringify(result,null,2));
}catch(error){
 // Screenshots/DOM contain synthetic records only; never serialize sessions.
 for(const context of browser.contexts())for(const page of context.pages())writeFileSync(`${directory}/failure-${context===browser.contexts()[0]?'author':'reviewer'}.txt`,await page.locator('body').innerText().catch(()=>''));
 throw error;
}finally{await browser.close();db.close();}
