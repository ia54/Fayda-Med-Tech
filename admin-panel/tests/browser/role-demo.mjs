import assert from 'node:assert/strict';
import {readFileSync,writeFileSync,realpathSync,mkdirSync} from 'node:fs';
import {createHash} from 'node:crypto';
import {createRequire} from 'node:module';
import {execFileSync,spawn} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import {DatabaseSync} from 'node:sqlite';
const require=createRequire(import.meta.url),root=fileURLToPath(new URL('../../..',import.meta.url));
const sha=b=>createHash('sha256').update(b).digest('hex');
const directory=realpathSync(process.env.FAYDAMED_BROWSER_FIXTURE_DIR||'');
assert.ok(directory.startsWith('/workspace/.cloud-tools/'));
const fixture=JSON.parse(readFileSync(`${directory}/sessions.json`,'utf8'));
for(const [name,hash] of Object.entries(fixture.source_hashes))assert.equal(sha(readFileSync(`${root}/${name}`)),hash,`Stale source: ${name}`);
const {chromium}=require(process.env.FAYDAMED_PLAYWRIGHT_MODULE||'playwright-core');
const db=new DatabaseSync(`${directory}/shared.sqlite`),tables=['cases','invoices','payments','documents','pharmacy_source_documents','pharmacy_fills'];
const snapshot=()=>Object.fromEntries(tables.map(t=>[t,sha(JSON.stringify(db.prepare(`SELECT * FROM "${t}" ORDER BY rowid`).all()))]));
const before=snapshot(),roles=[],responses=[],blocked=new Set(),writes=[];
const browser=await chromium.launch({executablePath:'/usr/bin/chromium',headless:true,args:['--no-sandbox','--disable-dev-shm-usage','--disable-background-networking']});
mkdirSync(`${directory}/role-videos`,{mode:0o700});
try{
 for(const [role,routes]of Object.entries({provider:['provider/claims','provider/documents'],biller:['billing/invoices','billing/analytics'],attorney:['legal/cases'],firm_admin:['legal/cases','legal/billing']})){
  const session=fixture.sessions[role];assert.ok(session,`Missing seeded role ${role}`);
  const context=await browser.newContext({serviceWorkers:'block',viewport:{width:1280,height:900}});
  await context.route('**/*',route=>{const r=route.request(),u=new URL(r.url());if(!['http://127.0.0.1:3000','http://127.0.0.1:8000'].includes(u.origin)){blocked.add(u.hostname);return route.abort();}if(!['GET','HEAD','OPTIONS'].includes(r.method())){writes.push({role,path:u.pathname,method:r.method()});return route.abort();}return route.continue();});
  await context.addInitScript(s=>localStorage.setItem('persist:root',JSON.stringify({auth:JSON.stringify(s),_persist:JSON.stringify({version:1,rehydrated:true})})),session);
  const page=await context.newPage();page.setDefaultTimeout(30000);
  page.on('response',r=>{if(r.url().startsWith('http://127.0.0.1:8000/'))responses.push({role,path:new URL(r.url()).pathname,status:r.status()});});
  const video=`${directory}/role-videos/synthetic-${role}.webm`;
  const ff=spawn('/usr/bin/ffmpeg',['-hide_banner','-loglevel','error','-f','image2pipe','-framerate','5','-vcodec','mjpeg','-i','pipe:0','-an','-c:v','libvpx-vp9','-deadline','realtime','-cpu-used','8','-crf','38','-b:v','0','-pix_fmt','yuv420p','-n',video],{stdio:['pipe','ignore','pipe']});
  let errors='',latest=null;ff.stderr.on('data',b=>{errors=(errors+b.toString()).slice(-4000);});ff.stdin.on('error',()=>{});
  const exited=new Promise((resolve,reject)=>{ff.on('error',reject);ff.on('exit',code=>code===0?resolve():reject(Error(`Video encoder ${code}: ${errors}`)));});exited.catch(()=>{});
  const cdp=await context.newCDPSession(page);cdp.on('Page.screencastFrame',event=>{latest=Buffer.from(event.data,'base64');cdp.send('Page.screencastFrameAck',{sessionId:event.sessionId}).catch(()=>{});});
  await cdp.send('Page.startScreencast',{format:'jpeg',quality:85,maxWidth:1280,maxHeight:900,everyNthFrame:1});
  // Sample actual live rendered frames at 5 fps. No invented UI or substituted data.
  const timer=setInterval(()=>{if(latest&&!ff.stdin.destroyed&&ff.stdin.writableLength<latest.length*3)ff.stdin.write(latest);},200);
  const views=[];
  try{
   for(const route of routes){
    await page.goto(`http://127.0.0.1:3000/dashboard/${route}`,{waitUntil:'networkidle'});
    await page.locator('h1').first().waitFor({state:'visible'});assert.ok(page.url().includes(`/dashboard/${route}`));
    await page.waitForTimeout(3000);views.push({route,headings:await page.locator('h1').allTextContents()});
    await page.screenshot({path:`${directory}/role-videos/${role}-${route.replaceAll('/','-')}.png`});
   }
   const probes=await page.evaluate(async({token,foreign})=>{const result={};for(const [name,path]of Object.entries({case_list:'/cases',foreign_case:`/cases/${foreign}`,pharmacy_source:'/pharmacy/prescriptions/999999/sources'}))result[name]=(await fetch('http://127.0.0.1:8000/api'+path,{headers:{Authorization:`Bearer ${token}`}})).status;return result;},{token:session.token.access_token,foreign:fixture.hidden.foreign.case_id});
   assert.equal(probes.pharmacy_source,403);assert.ok([403,404].includes(probes.foreign_case));assert.equal(probes.case_list,200);
   roles.push({role,views,probes,video:video.split('/').pop()});
  }finally{clearInterval(timer);await cdp.send('Page.stopScreencast').catch(()=>{});ff.stdin.end();await exited;await context.close();}
  const media=JSON.parse(execFileSync('/usr/bin/ffprobe',['-v','quiet','-show_streams','-show_format','-of','json',video],{encoding:'utf8'}));
  roles.at(-1).video_seconds=Number(media.format.duration);assert.ok(roles.at(-1).video_seconds>=3);
 }
 assert.deepEqual(snapshot(),before);assert.equal(writes.length,0);assert.equal(responses.some(r=>r.status>=500),false);
 writeFileSync(`${directory}/role-demo-result.json`,JSON.stringify({head:execFileSync('git',['rev-parse','HEAD'],{cwd:root,encoding:'utf8'}).trim(),application_sources_unchanged_from_7bce:execFileSync('git',['diff','7bce3ca','--','admin-panel/app','admin-panel/components','admin-panel/lib','admin-panel/store','api/app','api/routes'],{cwd:root,encoding:'utf8'}).trim()==='',build_id:readFileSync(`${root}/admin-panel/.next/BUILD_ID`,'utf8').trim(),browser:browser.version(),seeded_sessions:true,login_mfa_verified:false,recording:'Actual Chromium CDP live screencast encoded by system FFmpeg, 1280x900 at 5 fps; no audio',roles,responses,blocked_hosts:[...blocked],writes_attempted:writes,protected_tables_unchanged:true,source_hashes:fixture.source_hashes},null,2)+'\n');
 console.log(JSON.stringify({roles,writes_attempted:writes,protected_tables_unchanged:true},null,2));
}finally{await browser.close();db.close();}
