import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
const script = readFileSync(new URL('../godaddy-node/scripts/dev-managed.cjs', import.meta.url), 'utf8');
function run(port) {
  const loaded = [];
  const process = { env: { PORT: port }, execPath: '/node', argv: [] };
  const require = name => loaded.push(name);
  require.resolve = name => { assert.equal(name, 'next/dist/bin/next'); return '/next-cli'; };
  vm.runInNewContext(script, { process, require });
  return { process, loaded };
}
test('preview refuses absent, malformed and out-of-range platform ports', () => {
  for (const port of [undefined, '', '0', '65536', '-1', '3000x', ' 3000', '3.5']) {
    assert.throws(() => run(port), /injected PORT/);
  }
});
test('preview uses Next dev on the injected port and all interfaces after the guard', () => {
  for (const port of ['1', '3000', '65535']) {
    const { process, loaded } = run(port);
    assert.equal(process.env.NODE_ENV, 'development');
    assert.equal(process.env.HOSTNAME, '0.0.0.0');
    assert.deepEqual(Array.from(process.argv), ['/node', '/next-cli', 'dev', '--hostname', '0.0.0.0', '--port', port]);
    assert.deepEqual(loaded, ['./apply-braces-guard.cjs', '/next-cli']);
  }
});
test('deployment manifest preserves production commands', () => {
  const { scripts } = JSON.parse(readFileSync(new URL('../godaddy-node/package.json', import.meta.url)));
  assert.equal(scripts.dev, 'node scripts/dev-managed.cjs');
  assert.equal(scripts.start, 'node scripts/start-managed.cjs');
  assert.equal(scripts.build, 'node scripts/apply-braces-guard.cjs && node --test tests/braces-depth.test.mjs && next build && node scripts/prepare-standalone.cjs');
});
