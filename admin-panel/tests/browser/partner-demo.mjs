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
const out=`${dir}/partner-recording`;mkdirSync(out,{recursive:false});
const browser=await chromium.launch({executablePath:'/usr/bin/chromium',headless:true,args:['--no-sandbox','--disable-dev-shm-usage','--disable-background-networking']});
const context=await browser.newContext({serviceWorkers:'block',viewport:{width:1440,height:1100},recordVideo:{dir:out,size:{width:1440,height:1100}}});
const blocked=[];
await context.route('**/*',r=>{const u=new URL(r.request().url());const internalPdf=(u.protocol==='chrome-extension:'&&u.hostname==='mhjfbmdgcfjbbpaeojofohoefgiehjai')||(u.protocol==='chrome:'&&u.hostname==='resources');if(!internalPdf&&!['http://127.0.0.1:3000','http://127.0.0.1:8000'].includes(u.origin)){blocked.push(u.hostname);return r.abort();}return r.continue();});
const page=await context.newPage();page.setDefaultTimeout(30000);
const chapters=[];const start=Date.now();
async function caption(text,seconds=10){chapters.push({seconds:(Date.now()-start)/1000,text});await page.evaluate(text=>{document.querySelector('#demo-commentary')?.remove();const d=document.createElement('div');d.id='demo-commentary';d.textContent='DEMO COMMENTARY · '+text;Object.assign(d.style,{position:'fixed',bottom:'0',left:'0',right:'0',padding:'18px 28px',background:'#10253b',color:'white',font:'22px/1.4 sans-serif',zIndex:'2147483647',borderTop:'4px solid #26c6ab',pointerEvents:'none'});document.body.append(d);},text);await page.waitForTimeout(seconds*1000);}
async function card(title,lines,seconds=12){await page.goto('about:blank');await page.setContent(`<body style="margin:0;background:#10253b;color:white;font:28px/1.65 sans-serif;padding:100px"><p style="color:#26c6ab">FAYDA MEDTECH · SYNTHETIC PILOT · 20 OCT 2026</p><h1 style="font-size:48px">${title}</h1>${lines.map(t=>`<p>${t}</p>`).join('')}<p style="font-size:20px;color:#c5d2df">Owner review copy · No real patients · No real dispensing</p></body>`);chapters.push({seconds:(Date.now()-start)/1000,title,lines});await page.waitForTimeout(seconds*1000);}
async function as(role,path){await page.goto('http://127.0.0.1:3000/login');await page.evaluate(session=>{localStorage.clear();sessionStorage.clear();localStorage.setItem('persist:root',JSON.stringify({auth:JSON.stringify(session),_persist:JSON.stringify({version:1,rehydrated:true})}));},fixture.sessions[role]);await page.goto(`http://127.0.0.1:3000${path}`);await page.waitForLoadState('networkidle');}
try{
 await card('A working pilot. A separate path to production.',[
 'Ibrahim accepted the synthetic pilot on 20 October 2026.',
 'This recording shows actual browser interactions with synthetic records.',
 'Sessions are pre-authenticated test sessions. Login and MFA are not demonstrated here.',
 'External providers are not connected in this demo.'
 ],16);
 await as('biller','/dashboard/billing/eobs');await page.getByRole('button',{name:'Add EOB record',exact:true}).waitFor();
 await caption('Billing team: retained EOB records, search, paging and review. These are records, not payer submissions.',14);
 await page.getByRole('button',{name:'Add EOB record',exact:true}).click();
 for(const [id,value]of Object.entries({patient:'SYNTHETIC partner demo',payer:'SYNTHETIC payer - disconnected',provider:'SYNTHETIC provider',billed:'120.00',paid:'0.00'}))await page.locator(`#${id}`).fill(value);
 await caption('Enter a synthetic explanation-of-benefits record. No payer connection and no money moves.',10);
 const saved=page.waitForResponse(r=>r.url().endsWith('/api/eobs')&&r.request().method()==='POST');await page.getByRole('button',{name:'Save EOB',exact:true}).click();assert.equal((await saved).status(),201);
 await page.getByPlaceholder('Search by patient or payer...').fill('SYNTHETIC partner demo');await page.waitForTimeout(1000);
 await caption('The saved record is searchable and remains pending until reviewed.',10);
 await page.getByLabel(/^Actions for EOB/).click();await page.getByRole('menuitem',{name:'Review record',exact:true}).click();
 await caption('Human review is an explicit step. Recording an EOB does not collect a payment.',10);
 await page.getByRole('button',{name:'Mark reviewed',exact:true}).click();await page.getByRole('dialog').waitFor({state:'hidden'});
 await page.reload();await page.getByPlaceholder('Search by patient or payer...').fill('SYNTHETIC partner demo');await caption('Reload confirms the reviewed record persists.',10);
 await page.goto('http://127.0.0.1:3000/dashboard/billing/appeals');await page.getByLabel('Filter appeal status').selectOption('sent');await page.getByText('SYN-PILOT-00',{exact:true}).waitFor();await page.getByRole('button',{name:'View appeal SYN-PILOT-00',exact:true}).click();await page.getByRole('dialog').waitFor();
 await caption('Appeal history and local drafts are implemented. The seeded status does not prove transmission to a payer.',14);
 await page.keyboard.press('Escape');await page.goto('http://127.0.0.1:3000/dashboard/billing/appeals/create');await page.getByLabel('Select Denied Invoice',{exact:true}).click();await page.getByRole('option',{name:/SYN-PILOT-DENIED/}).click();await page.getByLabel('Denial Reason Category',{exact:true}).click();await page.getByRole('option',{name:'Medical Necessity',exact:true}).click();await page.getByRole('button',{name:'Generate Appeal Letter',exact:true}).click();await page.getByText('Generated Letter',{exact:true}).waitFor();
 await caption('This is a local template draft for human review. It is not connected AI and has not been submitted.',16);
 await as('client','/dashboard/client/documents');await page.getByLabel('Preview SYNTHETIC cloud pilot document',{exact:true}).click();await page.locator('[data-document-preview="true"] iframe').waitFor();
 await caption('Client view: a private synthetic PDF opens in the application. Access is scoped to the signed-in role.',16);await page.screenshot({path:`${out}/private-document.png`});
 await as('pharmacist','/dashboard/pharmacy');await caption('Pharmacy workspace: synthetic workflow preparation. Controlled and compounded real dispensing remain disabled.',18);await page.screenshot({path:`${out}/pharmacy-workspace.png`});
 await as('biller','/dashboard/billing/analytics');await page.getByText('Calculation basis',{exact:true}).waitFor();await caption('Analytics state their calculation basis and mark unavailable measures. Synthetic numbers are not business results.',16);
 await card('What remains before a production release',[
 'Security: braces high and Quill low remain unresolved; no owner risk acceptance is implied.',
 'Hosting: verified access, backup/restore, migration and coordinated rollback rehearsal.',
 'Operations: real identity/mail recovery, monitoring and support ownership.',
 'Draft pull requests require review; production deployment needs separate approval.'
 ],22);
 await card('What remains for the full pharmacy product',[
 'Approved prescribing, clinical reference, MAPS, payer and AI/OCR connections.',
 'Controlled-substance rules and actual compounded release/supply workflows.',
 'Site-specific sterile, nonsterile and hazardous procedures; physical devices.',
 'Dr. Ghadeh and site leads: clinical acceptance, training and release authority.'
 ],22);
 await card('Ready for a guided synthetic demonstration',[
 'Fresh cloud restore and representative browser journey verified.',
 'This owner-review recording can support a partner discussion.',
 'There is no verified partner-accessible live URL.',
 'Pilot acceptance is not production or clinical acceptance.'
 ],14);
 writeFileSync(`${out}/chapters.json`,JSON.stringify({source:'ce839f40d36656c147166d63c88689071ee40617',blocked_external_hosts:[...new Set(blocked)],chapters},null,2));
 console.log('Partner walkthrough captured successfully');
}finally{await context.close();await browser.close();}
