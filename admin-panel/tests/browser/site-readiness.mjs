import assert from 'node:assert/strict';
import {cloudBrowser,snapshot} from './cloud-browser.mjs';
const c=await cloudBrowser('shared.sqlite'),{db,pages:p,fixture:f}=c;
const tables=db.prepare("SELECT name FROM sqlite_master WHERE type='table' AND (name LIKE 'pharmacy_%' OR name IN ('invoices','payments')) ORDER BY name").all().map(r=>r.name);
const before=snapshot(db,tables),site=db.prepare('SELECT id FROM pharmacy_locations WHERE organization_id=1 ORDER BY id LIMIT 1').get().id;
const path=`/api/pharmacy/locations/${site}/readiness`,base='http://127.0.0.1:3000';
const probe=role=>p[role].evaluate(async({path,token})=>{const response=await fetch('http://127.0.0.1:8000'+path,{headers:{Authorization:`Bearer ${token}`,Accept:'application/json'}});return response.status;},{path,token:f.sessions[role].token.access_token});
try{
 for(const role of ['pharmacist','technician']){
  const page=p[role];if(role==='technician')await page.setViewportSize({width:390,height:844});await page.goto(base+'/dashboard/pharmacy');
  const button=page.getByRole('button',{name:'Site readiness',exact:true});
  await button.waitFor();assert.equal(await button.isDisabled(),true);
  await page.getByRole('combobox',{name:/^Pharmacy location/}).selectOption(String(site));
  await button.click();const dialog=page.getByRole('dialog');await dialog.getByText('Operational release: disabled.',{exact:false}).waitFor();
  assert.equal(await dialog.getByText(/: release disabled$/).count(),4);
  await dialog.getByText('not verified',{exact:false}).first().waitFor();
  if(role==='technician'){const box=await dialog.boundingBox();assert.ok(box.x>=0 && box.x+box.width<=390,'Narrow dialog fits viewport');}
  await page.screenshot({path:`${c.directory}/site-readiness-${role}.png`,fullPage:true});
  await page.keyboard.press('Escape');await dialog.waitFor({state:'hidden'});assert.equal(await button.evaluate(e=>e===document.activeElement),true);
 }
 const page=p.pharmacist;
 // Evict prior RTK cache through a real reload, then inject one bounded failure.
 await page.reload();await page.getByRole('combobox',{name:/^Pharmacy location/}).selectOption(String(site));
 await page.route('**'+path,async route=>{await page.unroute('**'+path);await route.fulfill({status:500,contentType:'application/json',body:'{"message":"Synthetic retry exercise"}'});});
 await page.getByRole('button',{name:'Site readiness',exact:true}).click();
 await page.getByRole('alert').getByText('Site evidence could not be loaded.',{exact:false}).waitFor();
 await page.getByRole('button',{name:'Retry',exact:true}).click();await page.getByRole('dialog').getByText('Operational release: disabled.',{exact:false}).waitFor();
 const denied={};for(const role of ['client','attorney','firm_admin','provider','biller','platform_admin']){await p[role].goto(base+'/dashboard');denied[role]=await probe(role);assert.equal(denied[role],403,role);}
 assert.deepEqual(snapshot(db,tables),before,'Readiness must not alter operational/financial tables');
 assert.equal(c.blocked.size,0);
 await c.result('site-readiness',{passed:true,roles_with_visible_review:['pharmacist','technician'],denied_role_browser_fetch:denied,retry_verified:true,keyboard_escape_and_focus_return:true,all_workflows_release_disabled:true,database_tables_unchanged:tables,baseline_hashes:before,after_hashes:snapshot(db,tables),limits:'Seeded role sessions; no login/MFA, clinical, physical-device or deployment acceptance implied.'});
}catch(error){await c.failure('site-readiness');throw error;}finally{await c.close();}
