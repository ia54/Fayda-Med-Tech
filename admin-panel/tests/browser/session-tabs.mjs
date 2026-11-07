import assert from 'node:assert/strict';
import {cloudBrowser,snapshot} from './cloud-browser.mjs';
const c=await cloudBrowser('auth.sqlite'),{db,fixture:f}=c,page=c.pages.visitor,base='http://127.0.0.1:3000';
const protectedTables=['invoices','payments','pharmacy_stock_events','pharmacy_fills','case_settlements'];
let tab;
try{
 const baseline=snapshot(db,protectedTables);assert.equal(db.prepare('SELECT COUNT(*) n FROM oauth_access_tokens').get().n,0);
 await page.goto(base+'/auth/login');await page.locator('#email').fill(f.accounts.reset_client.email);await page.locator('#password').fill(f.password);await page.getByRole('button',{name:'Sign In',exact:true}).click();await page.waitForURL('**/dashboard/**');await page.waitForLoadState('networkidle');
 const original=await page.evaluate(()=>JSON.parse(JSON.parse(localStorage.getItem('persist:root')).auth));assert.ok(original.token.refresh_token);
 tab=await page.context().newPage();tab.setDefaultTimeout(20000);tab.setDefaultNavigationTimeout(60000);await tab.goto(base+'/dashboard/client/invoices');await tab.waitForLoadState('networkidle');
 let attempts=0;let release;const barrier=new Promise(resolve=>{release=resolve;});const timeout=setTimeout(()=>release(),1000);
 await page.context().route('**/api/refresh-token',async route=>{attempts++;if(attempts>=2)release();await barrier;await route.continue();});
 db.prepare("UPDATE oauth_access_tokens SET expires_at='2000-01-01 00:00:00' WHERE user_id=? AND revoked=0").run(f.accounts.reset_client.id);
 await Promise.all([page.goto(base+'/dashboard/client/invoices'),tab.goto(base+'/dashboard/client/documents')]);await Promise.all([page.waitForLoadState('networkidle'),tab.waitForLoadState('networkidle')]);clearTimeout(timeout);
 const states=await Promise.all([page,tab].map(p=>p.evaluate(()=>JSON.parse(JSON.parse(localStorage.getItem('persist:root')).auth))));
 assert.ok(states.every(s=>s.token?.access_token),'Both tabs must retain the rotated authenticated session');
 for(const p of [page,tab]){assert.ok(!new URL(p.url()).pathname.startsWith('/auth/'));const status=await p.evaluate(async token=>(await fetch('http://127.0.0.1:8000/api/client/invoices',{headers:{Accept:'application/json',Authorization:`Bearer ${token}`}})).status,states[0].token.access_token);assert.equal(status,200);}
 const automaticRefreshAttempts=attempts;assert.equal(automaticRefreshAttempts,1,'Tabs must share exactly one automatic refresh');
 const replay=await page.evaluate(async token=>(await fetch('http://127.0.0.1:8000/api/refresh-token',{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({refresh_token:token})})).status,original.token.refresh_token);assert.equal(replay,401);
 assert.deepEqual(snapshot(db,protectedTables),baseline);
 await c.result('session-tabs',{seeded_authentication:false,password_login_verified:true,concurrent_tabs_rotated_session_retained:true,automatic_refresh_attempts:automaticRefreshAttempts,old_refresh_replay_denied:true,protected_tables_unchanged:true});
}catch(error){await page.locator('input').evaluateAll(els=>els.forEach(el=>{el.value='';el.setAttribute('value','');})).catch(()=>{});if(tab)await tab.screenshot({path:`${c.directory}/session-tabs-second-tab-failure.png`}).catch(()=>{});await c.failure('session-tabs');throw error;}finally{await c.close();}
