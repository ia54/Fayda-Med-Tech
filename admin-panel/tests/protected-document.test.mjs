import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import ts from 'typescript';
const source = await readFile(new URL('../lib/protectedDocument.ts', import.meta.url), 'utf8');
const compiled = ts.transpileModule(source, {compilerOptions:{module:ts.ModuleKind.ESNext,target:ts.ScriptTarget.ES2022}}).outputText;
const {fetchProtectedDocument} = await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`);

test('signed files use authenticated private requests and expose the correct download type', async () => {
  const original = globalThis.fetch;
  try {
    for (const artifact of ['document','certificate']) {
      const controller = new AbortController();
      globalThis.fetch = async (url, options) => {
        assert.equal(url, `https://api.example.invalid/api/documents/4/${artifact === 'document' ? 'preview' : 'completion-certificate'}`);
        assert.equal(options.headers.Authorization,'Bearer synthetic-only');
        assert.equal(options.cache,'no-store');
        assert.equal(options.credentials,'omit');
        assert.equal(options.referrerPolicy,'no-referrer');
        assert.equal(options.signal,controller.signal);
        return new Response('%PDF-synthetic', {headers:{'Content-Type':'application/pdf','X-Document-Version':artifact === 'document' ? 'signed' : 'certificate','X-Completion-Certificate':'true'}});
      };
      const result = await fetchProtectedDocument('https://api.example.invalid/api/',4,'synthetic-only',artifact,controller.signal);
      assert.equal(result.version,artifact === 'document' ? 'signed' : 'certificate');
      assert.equal(result.hasCertificate,true);
      assert.equal(result.blob.type,'application/pdf');
    }
  } finally { globalThis.fetch=original; }
});

test('unavailable signed files never fall back to original documents or expose provider errors', async () => {
  const original=globalThis.fetch;
  let calls=0;
  try {
    globalThis.fetch=async () => { calls++; return new Response('PRIVATE provider diagnostics',{status:503}); };
    await assert.rejects(fetchProtectedDocument('https://api.example.invalid/api',4,'synthetic-only','document',new AbortController().signal),error => {
      assert.match(error.message,/signed files could not be retrieved/);
      assert.doesNotMatch(error.message,/PRIVATE/); return true;
    });
    assert.equal(calls,1);
    await assert.rejects(fetchProtectedDocument('https://api.example.invalid/api',4,undefined,'document',new AbortController().signal),/session expired/);
    assert.equal(calls,1);
  } finally { globalThis.fetch=original; }
});
