import test from 'node:test';
import assert from 'node:assert/strict';
import {configureStore, combineReducers, createSlice} from '@reduxjs/toolkit';
import {createApi} from '@reduxjs/toolkit/query';
import {sessionBoundary} from '../store/session-boundary.mjs';

function fixture() {
  const auth = createSlice({name:'auth',initialState:{user:null,token:null},reducers:{set:(_,a)=>a.payload}});
  const pending=[];
  const api=createApi({reducerPath:'api',baseQuery:()=>new Promise(resolve=>pending.push(resolve)),endpoints:b=>({locations:b.query({query:()=>'/locations'})})});
  const boundary=sessionBoundary(api,combineReducers({auth:auth.reducer,[api.reducerPath]:api.reducer}));
  const store=configureStore({reducer:boundary.reducer,middleware:g=>g().concat(boundary.middleware,api.middleware)});
  const signIn=(id,organization_id=1,role='pharmacist',token='synthetic-token')=>store.dispatch(auth.actions.set({user:{id,organization_id,role},token:{access_token:token}}));
  return {api,store,pending,signIn,logout:()=>store.dispatch(auth.actions.set({user:null,token:null}))};
}

test('switching accounts clears cached locations immediately and late responses cannot replace new account data', async()=>{
  const f=fixture();f.signIn(1);
  const first=f.store.dispatch(f.api.endpoints.locations.initiate());f.pending.shift()({data:['Site A','Site B']});await first;
  assert.deepEqual(f.api.endpoints.locations.select()(f.store.getState()).data,['Site A','Site B']);
  const late=f.store.dispatch(f.api.endpoints.locations.initiate(undefined,{forceRefetch:true}));const resolveOld=f.pending.shift();
  f.signIn(2);
  assert.equal(f.api.endpoints.locations.select()(f.store.getState()).data,undefined);
  const current=f.store.dispatch(f.api.endpoints.locations.initiate());const resolveNew=f.pending.shift();
  resolveOld({data:['Site B - old account']});await late;
  assert.equal(f.api.endpoints.locations.select()(f.store.getState()).data,undefined);
  resolveNew({data:['Site A']});await current;
  assert.deepEqual(f.api.endpoints.locations.select()(f.store.getState()).data,['Site A']);
  f.logout();assert.equal(f.api.endpoints.locations.select()(f.store.getState()).data,undefined);
  f.store.dispatch(f.api.util.resetApiState());
});

test('token rotation preserves cache but organization and role changes clear it',async()=>{
  const f=fixture();f.signIn(1);
  const request=f.store.dispatch(f.api.endpoints.locations.initiate());f.pending.shift()({data:['Site A']});await request;
  f.signIn(1,1,'pharmacist','rotated-token');assert.deepEqual(f.api.endpoints.locations.select()(f.store.getState()).data,['Site A']);
  f.signIn(1,2);assert.equal(f.api.endpoints.locations.select()(f.store.getState()).data,undefined);
  const next=f.store.dispatch(f.api.endpoints.locations.initiate());f.pending.shift()({data:['Site C']});await next;
  f.signIn(1,2,'pharmacy_technician');assert.equal(f.api.endpoints.locations.select()(f.store.getState()).data,undefined);
  f.store.dispatch(f.api.util.resetApiState());
});
