import assert from 'node:assert/strict';
import {readFileSync,writeFileSync,mkdirSync} from 'node:fs';
import {createHash} from 'node:crypto';
import {spawn,execFileSync} from 'node:child_process';
import {cloudBrowser,snapshot} from '/workspace/Fayda-Med-Tech/admin-panel/tests/browser/cloud-browser.mjs';
const output='/workspace/artifacts/biller-legal-walkthroughs-20261028';mkdirSync(output,{recursive:true});
const c=await cloudBrowser('shared.sqlite'),writes=[],chapters=[],clips=[];
const tables=['cases','invoices','payments','documents','pharmacy_source_documents','pharmacy_fills'];const before=snapshot(c.db,tables);
for(const [role,page] of Object.entries(c.pages))await page.route('**/*',route=>{const r=route.request();if(!['GET','HEAD','OPTIONS'].includes(r.method())){writes.push({role,method:r.method(),path:new URL(r.url()).pathname});return route.abort();}return route.fallback();});
const pause=ms=>new Promise(r=>setTimeout(r,ms));
let recorder=null,active=null;
async function begin(name){
 const path=`${output}/${name}.mp4`;let error='';const ff=spawn('/usr/bin/ffmpeg',['-hide_banner','-loglevel','error','-f','image2pipe','-framerate','10','-vcodec','mjpeg','-i','pipe:0','-an','-c:v','libx264','-preset','veryfast','-crf','20','-pix_fmt','yuv420p','-movflags','+faststart','-n',path],{stdio:['pipe','ignore','pipe']});
 ff.stderr.on('data',b=>error=(error+b.toString()).slice(-3000));ff.stdin.on('error',()=>{});
 const exited=new Promise((resolve,reject)=>{ff.on('error',reject);ff.on('exit',code=>code===0?resolve():reject(Error(error)));});exited.catch(()=>{});
 recorder={name,path,ff,exited,start:Date.now(),latest:null,frames:0};const r=recorder;
 r.timer=setInterval(()=>{if(r.latest&&!ff.stdin.destroyed&&ff.stdin.writableLength<r.latest.length*4){ff.stdin.write(r.latest);r.frames++;}},100);
}
async function activate(role){
 if(active)await active.cdp.send('Page.stopScreencast');
 const page=c.pages[role];await page.setViewportSize({width:1440,height:1000});
 const cdp=await page.context().newCDPSession(page);cdp.on('Page.screencastFrame',e=>{recorder.latest=Buffer.from(e.data,'base64');cdp.send('Page.screencastFrameAck',{sessionId:e.sessionId}).catch(()=>{});});
 await cdp.send('Page.startScreencast',{format:'jpeg',quality:92,maxWidth:1440,maxHeight:1000,everyNthFrame:1});active={role,page,cdp};return page;
}
async function show(name,seconds=5){
 await pause(900);const p=active.page;const index=chapters.length;
 chapters.push({clip:recorder.name,role:active.role,chapter:name,utc:new Date().toISOString(),video_seconds:recorder.frames/10,url:new URL(p.url()).pathname,visible_text:(await p.locator('main').innerText()).slice(0,5000)});
 await p.screenshot({path:`${output}/${String(index).padStart(2,'0')}-${active.role}-${name}.png`});await pause(seconds*1000);
}
async function goto(path){await active.page.goto('http://127.0.0.1:3000/dashboard/'+path,{waitUntil:'networkidle'});}
async function end(){
 await active.cdp.send('Page.stopScreencast');active=null;clearInterval(recorder.timer);recorder.ff.stdin.end();await recorder.exited;
 const media=JSON.parse(execFileSync('/usr/bin/ffprobe',['-v','quiet','-show_streams','-show_format','-of','json',recorder.path],{encoding:'utf8'}));
 assert.ok(Number(media.format.duration)>25);execFileSync('/usr/bin/ffmpeg',['-v','error','-i',recorder.path,'-f','null','-'],{stdio:['ignore','ignore','pipe']});
 clips.push({file:recorder.path.split('/').pop(),seconds:Number(media.format.duration),sha256:createHash('sha256').update(readFileSync(recorder.path)).digest('hex'),bytes:readFileSync(recorder.path).length,decoded_all_frames:true,codec:media.streams[0].codec_name,width:media.streams[0].width,height:media.streams[0].height});recorder=null;
}
try{
 await begin('medical-biller-walkthrough');let page=await activate('biller');await goto('billing/invoices');await page.getByRole('button',{name:'Review SYN-UNASSIGNED-INV',exact:true}).waitFor();await show('invoice-worklist',7);
 await page.getByRole('button',{name:'Review SYN-UNASSIGNED-INV',exact:true}).click();await page.getByRole('dialog').waitFor();await show('invoice-review-no-save',8);await page.keyboard.press('Escape');
 const search=page.getByPlaceholder('Search by invoice number...');await search.fill('SYN-UNASSIGNED-INV');await page.waitForLoadState('networkidle');await show('invoice-search',5);await search.fill('');
 await goto('billing/analytics');await page.getByRole('heading',{name:'Recorded billing analytics',exact:true}).waitFor();await show('recorded-analytics',8);await page.mouse.wheel(0,550);await show('analytics-basis-and-unavailable-states',6);
 await goto('billing/payments');await page.getByText('No payments recorded yet.',{exact:true}).waitFor();await show('empty-payment-ledger-no-receipt-created',8);await end();
 await begin('attorney-law-firm-walkthrough');page=await activate('attorney');await goto('legal/cases');await page.getByText('SYN-CLOUD-OWN',{exact:true}).waitFor();await show('assigned-case-worklist',7);
 await page.getByRole('row').filter({hasText:'SYN-CLOUD-OWN'}).getByRole('button').first().click();await page.waitForURL(`**/dashboard/legal/cases/${c.fixture.case_id}`);await page.getByRole('tab',{name:'Parties',exact:true}).waitFor();await show('case-overview-and-current-party-state',7);
 for(const tab of ['Documents','Insurance','Liens','Settlement','Medical','Timeline']){
  await page.getByRole('tab',{name:tab,exact:true}).click();await page.getByRole('tab',{name:tab,exact:true}).evaluate(el=>el.scrollIntoView({block:'center'}));await pause(700);await show('case-'+tab.toLowerCase(),6);
 }
 page=await activate('firm_admin');await goto('legal/cases');await page.getByText('SYN-CLOUD-UNASSIGNED',{exact:true}).waitFor();await show('firm-admin-organization-case-scope',8);
 await goto('legal/billing');await page.getByRole('heading',{name:'Billing & Plans',exact:true}).waitFor();await show('firm-recorded-plan-and-unavailable-billing',9);await end();
 assert.deepEqual(snapshot(c.db,tables),before);assert.deepEqual(writes,[]);assert.equal(c.responses.some(r=>r.status>=500),false);
 const probes={};for(const role of ['biller','attorney','firm_admin']){
  probes[role]=await c.pages[role].evaluate(async({token,foreign})=>{const result={};for(const [key,path]of Object.entries({foreign_case:`/cases/${foreign}`,pharmacy_source:'/pharmacy/prescriptions/999999/sources'}))result[key]=(await fetch('http://127.0.0.1:8000/api'+path,{headers:{Authorization:`Bearer ${token}`}})).status;return result;},{token:c.fixture.sessions[role].token.access_token,foreign:c.fixture.hidden.foreign.case_id});
  assert.equal(probes[role].pharmacy_source,403);assert.ok([403,404].includes(probes[role].foreign_case));
 }
 const sourceHashes=c.fixture.source_hashes;
 const provenance={recorded_utc:new Date().toISOString(),branch:execFileSync('git',['branch','--show-current'],{cwd:'/workspace/Fayda-Med-Tech',encoding:'utf8'}).trim(),head:execFileSync('git',['rev-parse','HEAD'],{cwd:'/workspace/Fayda-Med-Tech',encoding:'utf8'}).trim(),build_id:readFileSync('/workspace/Fayda-Med-Tech/admin-panel/.next/BUILD_ID','utf8').trim(),build_application_source:'131220899c988c2e3a43553a6fa689d95653464d',admin_source_identical_to_build_source:execFileSync('git',['diff','1312208','HEAD','--','admin-panel'],{cwd:'/workspace/Fayda-Med-Tech',encoding:'utf8'}).trim()==='',fixture:'Existing committed CloudSharedRoleFixtureTest, fresh isolated shared.sqlite; 1 seed test / 16 assertions',seeded_authentication:true,login_mfa_verified:false,recording:'Actual Chromium CDP live frames; continuous MP4 per walkthrough, attorney then firm-admin in separately seeded browser contexts; H264 1440x1000 10fps, no audio, no fabricated screens or external media uploads',browser:c.browser.version(),clips,chapters,role_probes:probes,protected_table_hashes_before:before,protected_table_hashes_after:snapshot(c.db,tables),writes_attempted:writes,blocked_external_hosts:[...c.blocked],responses:c.responses,source_hashes:sourceHashes,limits:['Local built synthetic UI, not hosted production readiness','No payer/provider/signature/payment submission','Existing empty and incomplete fields retained; no records created or corrected for filming']};
 writeFileSync(`${output}/provenance.json`,JSON.stringify(provenance,null,2)+'\n');writeFileSync(`${output}/SHA256SUMS`,clips.map(v=>`${v.sha256}  ${v.file}`).join('\n')+'\n');console.log(JSON.stringify(clips,null,2));
}catch(error){if(recorder){clearInterval(recorder.timer);recorder.ff.stdin.end();await recorder.exited.catch(()=>{});}throw error;}finally{await c.close();}
