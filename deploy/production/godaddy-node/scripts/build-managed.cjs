'use strict';
// A host may export NODE_ENV=development for its preview. Next build and every
// child must instead resolve React's production exports during prerendering.
process.env.NODE_ENV = 'production';
const { spawnSync } = require('node:child_process');
for (const args of [
  ['scripts/apply-braces-guard.cjs'],
  ['--test', 'tests/braces-depth.test.mjs', 'tests/schema-validation.test.mjs'],
  [require.resolve('next/dist/bin/next'), 'build'],
  ['scripts/prepare-standalone.cjs'],
]) {
  const result = spawnSync(process.execPath, args, { stdio: 'inherit', env: { ...process.env, NODE_ENV: 'production' } });
  if (result.error) throw result.error;
  if (result.status !== 0) process.exit(result.status || 1);
}
