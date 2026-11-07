import assert from 'node:assert/strict';
import {writeFileSync} from 'node:fs';
import {cloudBrowser,snapshot} from './cloud-browser.mjs';
const c=await cloudBrowser('ordinary.sqlite'),{pages,fixture:f,db}=c,page=pages.pharmacist;
const baseline=snapshot(db,['pharmacy_prescriptions','pharmacy_patients','pharmacy_fills','pharmacy_stock_lots','pharmacy_events','invoices','payments']);
const measurements=[],writes=[];
for(const active of Object.values(pages))await active.route('**/*',route=>{if(!['GET','HEAD','OPTIONS'].includes(route.request().method())){writes.push(route.request().method());return route.abort();}return route.fallback();});
try{
 for(const width of [320,390,960,1280,1440]){
  await page.setViewportSize({width,height:1100});await page.goto('http://127.0.0.1:3000/dashboard/pharmacy',{waitUntil:'networkidle'});
  const open=page.getByRole('link',{name:'Open SYN-CLOUD-COLLECTION',exact:true});await open.waitFor();
  const rows=page.locator('[data-pharmacy-record]:visible');assert.equal(await rows.count(),2);
  const row=rows.filter({has:open});assert.equal(await row.count(),1);
  for(const text of ['SYN-CLOUD-COLLECTION','Synthetic Ordinary','PHARM-SYN','Synthetic medication','Synthetic strength','intake','unverified'])assert.ok((await row.innerText()).includes(text),`${width}: missing ${text}`);
  if(width<1280){for(const label of ['Pharmacy location','Patient / accident case','Medication','Latest fill','Coverage'])assert.ok((await row.locator('dt').allTextContents()).includes(label));assert.equal(await page.getByRole('table',{name:'Prescription worklist',exact:true}).count(),0);}
  else assert.equal(await page.getByRole('table',{name:'Prescription worklist',exact:true}).count(),1);
  const geometry=await row.evaluate(el=>({row:el.getBoundingClientRect().toJSON(),document_width:document.documentElement.scrollWidth,viewport:innerWidth,scroll_containers:[...el.querySelectorAll('*')].filter(n=>n.clientWidth&&n.scrollWidth>n.clientWidth+1).map(n=>({tag:n.tagName,width:n.clientWidth,scroll:n.scrollWidth}))}));
  assert.ok(geometry.row.left>=0&&geometry.row.right<=width+1,`${width}: record extends offscreen`);assert.ok(geometry.document_width<=width+1,`${width}: horizontal page overflow`);assert.deepEqual(geometry.scroll_containers,[],`${width}: hidden row content`);
  await page.getByRole('textbox',{name:'Search prescriptions',exact:true}).focus();let reached=false;
  for(let n=0;n<15;n++){await page.keyboard.press('Tab');if(await open.evaluate(el=>el===document.activeElement)){reached=true;break;}}
  assert.equal(reached,true,`${width}: Open must be reachable by keyboard`);
  const bounds=await open.boundingBox();assert.ok(bounds.x>=0&&bounds.x+bounds.width<=width+1&&bounds.y>=0&&bounds.y+bounds.height<=1101,`${width}: focused Open clipped`);
  await page.getByText('Prescription worklist',{exact:true}).first().evaluate(el=>el.scrollIntoView({block:'start'}));await page.screenshot({path:`${c.directory}/after-${width}.png`,fullPage:true});
  measurements.push({width,layout:width<1280?'cards':'table',...geometry,keyboard_open_reachable:true});
  await open.focus();await page.keyboard.press('Enter');await page.waitForURL(`**/dashboard/pharmacy/${f.prescriptions.collection}`);await page.getByText('Original prescription evidence',{exact:true}).waitFor();
  const sections=page.getByRole('navigation',{name:'Prescription sections',exact:true});
  for(const [name,id] of [['Current prescription','prescription-order'],['Original evidence','prescription-sources'],['Fills and billing','prescription-fills'],['Action history','prescription-history']]){
   const jump=sections.getByRole('link',{name,exact:true});await jump.focus();await page.keyboard.press('Enter');
   assert.equal(await page.evaluate(()=>document.activeElement?.id),id,`${width}: section focus missing`);
   const target=page.locator(`#${id}`);const top=await target.evaluate(el=>el.getBoundingClientRect().top);assert.ok(top>=-1&&top<1100,`${width}: jump target offscreen`);
   if(id==='prescription-fills')await page.screenshot({path:`${c.directory}/detail-after-${width}.png`});
   await target.getByRole('link',{name:'Back to prescription sections',exact:true}).focus();await page.keyboard.press('Enter');assert.equal(await page.evaluate(()=>document.activeElement?.id),'prescription-section-navigation');
  }

 }
 await page.goto('http://127.0.0.1:3000/dashboard/pharmacy',{waitUntil:'networkidle'});
 const search=page.getByRole('textbox',{name:'Search prescriptions',exact:true});await search.fill('SYN-CLOUD-COLLECTION');await page.waitForFunction(()=>document.querySelectorAll('[data-pharmacy-record]').length===2); // One record in each CSS layout.
 assert.equal(await page.locator('[data-pharmacy-record]:visible').count(),1);await search.fill('');await page.waitForFunction(()=>document.querySelectorAll('[data-pharmacy-record]').length===4);
 await page.getByRole('combobox',{name:'Latest fill stage',exact:true}).selectOption('ready');await page.getByText('No prescriptions found for these filters.',{exact:true}).waitFor();assert.equal(await page.locator('[data-pharmacy-record]:visible').count(),0);
 await page.getByRole('combobox',{name:'Latest fill stage',exact:true}).selectOption('');await page.getByRole('link',{name:'Open SYN-CLOUD-COLLECTION',exact:true}).waitFor();
 const biller=pages.biller;await biller.goto(`http://127.0.0.1:3000/dashboard/pharmacy/${f.prescriptions.collection}`,{waitUntil:'networkidle'});
 await biller.getByRole('navigation',{name:'Prescription sections',exact:true}).waitFor();
 assert.equal(await biller.getByRole('link',{name:'Original evidence',exact:true}).count(),0);
 assert.equal(await biller.locator('#prescription-sources').count(),0);
 assert.deepEqual(snapshot(db,['pharmacy_prescriptions','pharmacy_patients','pharmacy_fills','pharmacy_stock_lots','pharmacy_events','invoices','payments']),baseline);assert.deepEqual(writes,[]);assert.equal(c.responses.some(r=>r.status>=500),false);
 writeFileSync(`${c.directory}/responsive-measurements.json`,JSON.stringify(measurements,null,2)+'\n');
 await c.result('pharmacy-worklist-responsive',{measurements,search_and_empty_filter:true,keyboard_section_jumps_and_return:true,biller_evidence_link_absent:true,writes,protected_tables_unchanged:true});
}catch(error){await c.failure('pharmacy-worklist-responsive');throw error;}finally{await c.close();}
