import test from 'node:test';
import assert from 'node:assert/strict';
import {refreshBrowserSession,readBrowserSession,writeBrowserSession} from '../store/browser-session.mjs';
import {sessionIdentity} from '../store/session-boundary.mjs';
function fixture(){
 let value=null,tail=Promise.resolve();
 const storage={getItem:()=>value,setItem:(_,next)=>{value=next;}};
 const locks={request:(_name,_options,run)=>{const result=tail.then(run);tail=result.catch(()=>{});return result;}};
 const user={id:1,organization_id:1,role:'client'},old={access_token:'old-synthetic',refresh_token:'old-synthetic-refresh'},rotated={access_token:'new-synthetic',refresh_token:'new-synthetic-refresh'};
 const initial={user,token:old,expiresAt:Date.now()+60000};writeBrowserSession(storage,initial);
 const realm=()=>{let auth=structuredClone(initial);return{getAuth:()=>auth,accept:(token,user)=>{auth={user,token,expiresAt:Date.now()+60000};writeBrowserSession(storage,auth);},identity:sessionIdentity(initial),refreshToken:old.refresh_token,storage,locks};};
 return{storage,locks,user,old,rotated,initial,realm};
}
test('two browser realms rotate once and both retain the single-use replacement',async()=>{
 const f=fixture(),a=f.realm(),b=f.realm();let calls=0;
 const refresh=async()=>{calls++;await Promise.resolve();return f.rotated;};
 const result=await Promise.all([refreshBrowserSession({...a,refresh}),refreshBrowserSession({...b,refresh})]);
 assert.equal(calls,1);assert.deepEqual(result,[f.rotated,f.rotated]);assert.equal(a.getAuth().token.refresh_token,b.getAuth().token.refresh_token);
});
test('logout in another tab wins over an already running refresh response',async()=>{
 const f=fixture(),a=f.realm();let release,started;const entered=new Promise(r=>{started=r;});
 const pending=refreshBrowserSession({...a,refresh:()=>{started();return new Promise(r=>{release=r;});}});await entered;
 writeBrowserSession(f.storage,{user:null,token:null,expiresAt:null});release(f.rotated);
 assert.equal(await pending,null);assert.equal(readBrowserSession(f.storage).token,null);assert.deepEqual(a.getAuth().token,f.old);
});
test('different organization or malformed persisted state cannot be adopted or refreshed',async()=>{
 for(const invalid of [{...fixture().initial,user:{id:1,organization_id:2,role:'client'}},null]){
  const f=fixture(),a=f.realm();writeBrowserSession(f.storage,invalid);let calls=0;
  assert.equal(await refreshBrowserSession({...a,refresh:async()=>{calls++;return f.rotated;}}),null);assert.equal(calls,0);
 }
});
