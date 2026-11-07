import test from 'node:test';
import assert from 'node:assert/strict';
import { pilotGateway } from '../lib/pilot-gateway.mjs';
const origin = 'https://z1izdz2qau.preview.c36.airoapp.ai';
const env = { SYNTHETIC_PILOT_GATEWAY: 'true', PILOT_GATEWAY_KEY: 'a'.repeat(64) };
const req = (path = '/api/user', options = {}) => new Request(origin + path, options);
test('disabled or missing key fails closed without network', async () => {
  for (const bad of [{}, { ...env, PILOT_GATEWAY_KEY: 'short' }, { ...env, SYNTHETIC_PILOT_GATEWAY: 'false' }]) {
    assert.equal((await pilotGateway(req(), bad, () => assert.fail())).status, 503);
  }
});
test('rejects foreign origins, unsafe paths and unsupported methods before forwarding', async () => {
  for (const request of [req('/api/a%2Fb'), req('/api/a.b'), req('/api/user', { method: 'POST' }), req('/api/user', { headers: { origin: 'https://evil.invalid' } }), req('/api/user', { method: 'OPTIONS' })]) {
    assert.ok([400,403,405].includes((await pilotGateway(request, env, () => assert.fail())).status));
  }
});
test('fixed upstream, auth forwarded, caller gateway and proxy headers discarded', async () => {
  const response = await pilotGateway(req('/api/user?page=2', { headers: { authorization: 'Bearer test', 'x-fayda-pilot-key': 'evil', 'x-forwarded-for': '1.2.3.4', cookie: 'private=x' } }), env, async (url, options) => {
    assert.equal(url, 'https://pilot-api.faydamed.tech/api/user?page=2');
    assert.equal(options.headers.get('authorization'), 'Bearer test');
    assert.equal(options.headers.get('x-fayda-pilot-key'), env.PILOT_GATEWAY_KEY);
    assert.equal(options.headers.get('x-forwarded-for'), null);
    assert.equal(options.headers.get('cookie'), null);
    assert.equal(options.redirect, 'manual'); assert.equal(options.cache, 'no-store');
    return new Response('denied', { status: 401, headers: { 'set-cookie': 'leak=x', 'cache-control': 'public', 'x-fayda-pilot-key': env.PILOT_GATEWAY_KEY } });
  });
  assert.equal(response.status, 401); assert.equal(await response.text(), 'denied');
  assert.equal(response.headers.get('cache-control'), 'no-store');
  assert.equal(response.headers.get('set-cookie'), null); assert.equal(response.headers.get('x-fayda-pilot-key'), null);
});
test('same-origin mutation preserves body; redirects never followed', async () => {
  const response = await pilotGateway(req('/api/login', { method:'POST', headers: { origin, 'content-type':'application/json' }, body:'{"email":"synthetic@example.invalid"}' }), env, async (_, options) => {
    assert.equal(new TextDecoder().decode(options.body), '{"email":"synthetic@example.invalid"}');
    return new Response(null, { status:302, headers:{location:'https://evil.invalid'} });
  });
  assert.equal(response.status,502); assert.equal(response.headers.get('location'),null);
});
test('oversized request rejected without upstream call', async () => {
  assert.equal((await pilotGateway(req('/api/documents',{method:'POST',headers:{origin},body:'x'.repeat(20*1024*1024+1)}),env,()=>assert.fail())).status,413);
});
test('upstream failure does not expose details or keys', async () => {
  const response=await pilotGateway(req(),env,()=>{throw new Error(env.PILOT_GATEWAY_KEY)});
  assert.equal(response.status,502); assert.ok(!(await response.text()).includes(env.PILOT_GATEWAY_KEY));
});
