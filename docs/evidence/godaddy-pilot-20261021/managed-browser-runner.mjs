import assert from 'node:assert/strict';
import {createRequire} from 'node:module';
import {writeFileSync} from 'node:fs';
const require=createRequire(import.meta.url);const {chromium}=require('/workspace/.cloud-tools/browser-tools/node_modules/playwright-core');
const browser=await chromium.launch({executablePath:'/usr/bin/chromium',headless:true,args:['--no-sandbox','--disable-dev-shm-usage','--disable-background-networking']});
try{
 const page=await browser.newPage(),blocked=new Set(),responses=[];
 await page.route('**/*',route=>{const u=new URL(route.request().url());if(u.origin!=='http://127.0.0.1:3000'){blocked.add(u.hostname);return route.abort();}return route.continue();});
 page.on('response',r=>responses.push({path:new URL(r.url()).pathname,status:r.status()}));
 await page.goto('http://127.0.0.1:3000/auth/login');
 await page.getByRole('button',{name:'Sign In',exact:true}).waitFor();
 await page.locator('input[type="email"]').fill('synthetic@example.invalid');
 await page.locator('input[type="password"]').fill('Synthetic-only-not-an-account');
 assert.equal(await page.locator('input[type="email"]').inputValue(),'synthetic@example.invalid');
 assert.ok(responses.some(r=>r.path.includes('/_next/static/')&&r.status===200));assert.equal(responses.filter(r=>r.path.includes('/_next/static/')&&r.status>=400).length,0);
 await page.screenshot({path:'/workspace/.cloud-tools/godaddy-pilot-20261021/managed-login.png'});
 writeFileSync('/workspace/.cloud-tools/godaddy-pilot-20261021/managed-browser.json',JSON.stringify({passed:true,browser:browser.version(),scope:'Extracted ZIP npm ci + npm build + npm start with injected cloud PORT=3000; hydrated login form and static assets; no login submission or hosting evidence',login_verified:false,host_validation:false,blocked_hosts:[...blocked],responses},null,2)+'\n');
}finally{await browser.close();}
