import assert from 'node:assert/strict';
import {createHash} from 'node:crypto';
import {readFileSync,writeFileSync,realpathSync} from 'node:fs';
import {execFileSync} from 'node:child_process';
import {createRequire} from 'node:module';
import {fileURLToPath} from 'node:url';
import {DatabaseSync} from 'node:sqlite';
const require=createRequire(import.meta.url);
const root=fileURLToPath(new URL('../../..',import.meta.url));
export const sha256=value=>createHash('sha256').update(value).digest('hex');
export const snapshot=(db,tables)=>Object.fromEntries(tables.map(table=>[table,sha256(JSON.stringify(db.prepare(`SELECT * FROM "${table}" ORDER BY rowid`).all()))]));
export async function cloudBrowser(database){
 const directory=realpathSync(process.env.FAYDAMED_BROWSER_FIXTURE_DIR||'');
 assert.ok(directory.startsWith('/workspace/.cloud-tools/'),'Use an isolated synthetic cloud fixture');
 const fixture=JSON.parse(readFileSync(`${directory}/sessions.json`,'utf8'));
 for(const [path,hash] of Object.entries(fixture.source_hashes||{}))assert.equal(sha256(readFileSync(`${root}/${path}`)),hash,`Stale fixture source: ${path}`);
 const {chromium}=require(process.env.FAYDAMED_PLAYWRIGHT_MODULE||'playwright-core');
 const browser=await chromium.launch({executablePath:'/usr/bin/chromium',headless:true,args:['--no-sandbox','--disable-dev-shm-usage','--disable-background-networking','--disable-component-update']});
 const blocked=new Set(),pages={},responses=[];
 for(const [role,session] of Object.entries(fixture.sessions)){
  const context=await browser.newContext({serviceWorkers:'block',viewport:{width:1440,height:1100}});
  await context.route('**/*',route=>{const u=new URL(route.request().url());if(!['http://127.0.0.1:3000','http://127.0.0.1:8000'].includes(u.origin)){blocked.add(u.hostname);return route.abort();}return route.continue();});
  if(session.user || session.token) await context.addInitScript(session=>localStorage.setItem('persist:root',JSON.stringify({auth:JSON.stringify(session),_persist:JSON.stringify({version:1,rehydrated:true})})),session);
  pages[role]=await context.newPage();
  pages[role].setDefaultTimeout(20000);pages[role].setDefaultNavigationTimeout(60000);
  pages[role].on('response',r=>{if(r.url().startsWith('http://127.0.0.1:8000/'))responses.push({role,path:new URL(r.url()).pathname,method:r.request().method(),status:r.status()});});
 }
 const db=new DatabaseSync(`${directory}/${database}`);
 db.exec('PRAGMA busy_timeout=5000');
 return {directory,fixture,browser,pages,db,responses,blocked,
  async result(name,details){
   const sourceHashes={...fixture.source_hashes};
   for(const path of ['admin-panel/tests/browser/cloud-browser.mjs',`admin-panel/tests/browser/${name}.mjs`])sourceHashes[path]=sha256(readFileSync(`${root}/${path}`));
   const result={git_head:execFileSync('git',['rev-parse','HEAD'],{cwd:root,encoding:'utf8'}).trim(),git_status:execFileSync('git',['status','--porcelain'],{cwd:root,encoding:'utf8'}).trim(),browser:browser.version(),seeded_authentication:true,login_mfa_verified:false,source_hashes:sourceHashes,blocked_external_hosts:[...blocked],responses,...details};
   writeFileSync(`${directory}/${name}-result.json`,JSON.stringify(result,null,2)+'\n');console.log(JSON.stringify({...details,browser:browser.version(),blocked_external_hosts:[...blocked]},null,2));
  },
  async failure(name){for(const [role,page]of Object.entries(pages)){writeFileSync(`${directory}/${name}-failure-${role}.txt`,await page.locator('body').innerText().catch(()=>''));await page.screenshot({path:`${directory}/${name}-failure-${role}.png`,fullPage:true}).catch(()=>{});}},
  async close(){await browser.close();db.close();}
 };
}
