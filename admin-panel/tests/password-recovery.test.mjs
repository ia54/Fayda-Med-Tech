import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import ts from 'typescript';

const source = await readFile(new URL('../lib/passwordRecovery.ts', import.meta.url), 'utf8');
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const { postPasswordRecovery } = await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`);

test('recovery submits only to the configured API with no session or referrer', async () => {
  const originalFetch = globalThis.fetch;
  const originalBase = process.env.NEXT_PUBLIC_API_BASE_URL;
  try {
    process.env.NEXT_PUBLIC_API_BASE_URL = 'https://api.example.invalid/api/';
    globalThis.fetch = async (url, options) => {
      assert.equal(url, 'https://api.example.invalid/api/reset-password');
      assert.equal(options.method, 'POST');
      assert.equal(options.credentials, 'omit');
      assert.equal(options.cache, 'no-store');
      assert.equal(options.referrerPolicy, 'no-referrer');
      assert.equal(options.headers.Authorization, undefined);
      assert.deepEqual(JSON.parse(options.body), { token: 'synthetic', password: 'synthetic-only' });
      return new Response('{}', {status: 200});
    };
    await postPasswordRecovery('reset-password', {token: 'synthetic', password: 'synthetic-only'});
  } finally {
    globalThis.fetch = originalFetch;
    if (originalBase === undefined) delete process.env.NEXT_PUBLIC_API_BASE_URL;
    else process.env.NEXT_PUBLIC_API_BASE_URL = originalBase;
  }
});

test('failed requests never resolve as success or expose provider diagnostics', async () => {
  const originalFetch = globalThis.fetch;
  try {
    for (const status of [400, 422, 429, 500]) {
      globalThis.fetch = async () => new Response('private diagnostic', {status});
      await assert.rejects(postPasswordRecovery('reset-password', {}), error => {
        assert.doesNotMatch(error.message, /private diagnostic/);
        assert.match(error.message, status === 429 ? /Too many attempts/ : status === 500 ? /could not be completed/ : /invalid or expired/);
        return true;
      });
    }
    globalThis.fetch = async () => { throw new Error('private connection detail'); };
    await assert.rejects(postPasswordRecovery('forgot-password', {}), /Could not connect/);
  } finally { globalThis.fetch = originalFetch; }
});
