# Managed build mode diagnosis — October 21, 2026

Owner logs establish that the earlier dev wrapper started Next 15.5.27 on injected PORT 20010, became ready in 3.4s, compiled `/` and returned GET `/` 200. The later replacement reports dashboard prerender failures and subsequently ENOENT `/app/package.json`. These are separate observations; missing `dev` is no longer the current diagnosis. Owner reports no configured NODE_ENV: only two public API origins plus vendor database fields. Host-inherited build mode has not been directly observed.

## Reproduction and patch

Using the existing extracted managed source package, its locked npm dependencies and Node 22.23.3, running the original build command with `NODE_ENV=development` reproduces `TypeError: Cannot read properties of null (reading 'useState')` during prerendering of multiple routes, including `/dashboard/legal`, `/dashboard/admin/security`, `/dashboard/admin/application` and `/auth/signup`; Next build exits 1. Which concurrent route reports first varies. Next also warns about the non-standard NODE_ENV for the build. The pharmacy page already has a client directive. No duplicate React installation or missing client directive has been established as the cause. This reproduction establishes a build-mode failure mechanism, not proof of the host's actual environment.

Deployment-only `scripts/build-managed.cjs` now sets NODE_ENV=production before resolving Next and starts each original build step in a child with production mode: verified braces guard, braces regression tests, Next build, standalone preparation. A failed step aborts immediately. No dependencies, locks, application pages, auth, clinical gates or API behavior change. Production start remains unchanged; private `npm run dev` deliberately retains development mode.

## Reproduce a replacement ZIP

Fetch the pushed commit from `codex/cloud-pharmacy-acceptance`, then use a **clean checkout at the exact fix commit**. The packager reads committed Git blobs and rejects tracked uncommitted changes. Preserve any other work in its current checkout; use a separate detached worktree if needed:

```sh
git worktree add --detach /tmp/fayda-managed-build-fix FIX_COMMIT
python3 /tmp/fayda-managed-build-fix/deploy/production/managed_node_source_package.py \
  --repo /tmp/fayda-managed-build-fix \
  --output /tmp/managed-node-admin-build-fix.zip
sha256sum /tmp/managed-node-admin-build-fix.zip
```

The command reports source commit, size and SHA256. ZIP root must contain package.json, package-lock.json and scripts/build-managed.cjs, dev-managed.cjs and start-managed.cjs; no enclosing directory, node_modules, .next or secret files. Verify SOURCE-MANIFEST.json and its file hashes. Do not hand-edit the immutable bed4caa ZIP. Use the same existing isolated app z1izdz2qau only through the owner-authorized update flow; no upload was performed by this engineering task.

Cloud rehearsal uses intentionally non-live origins:

```sh
npm ci
NODE_ENV=development \
NEXT_PUBLIC_API_BASE_URL=https://api.pilot.invalid/api \
NEXT_PUBLIC_API_URL=https://api.pilot.invalid npm run build
```

The script must force production mode even with that inherited setting. Host preview must still receive its injected PORT; do not hardcode port 20010 from historical logs. Build-time public origins must be set before any subsequent real isolated API integration.

## Remaining host evidence

Require a successful new build log and private preview response bound to this exact archive before claiming recovery. ENOENT /app/package.json after the failed replacement is a host source/extraction/runtime state requiring its next update logs or vendor support if it persists; a local production-mode fix does not itself prove that state repaired. The Watchpack EACCES scandir `/` warning is a separate host observation; no broad filesystem permission changes are proposed. No DNS/publication, duplicate app, external uploads or patient/provider transactions were performed. Synthetic-only and clinical release boundaries remain in force.

## Completed cloud validation

- Original inherited-development build: exit 1, matching null-useState prerender failure (retained `build-development-repro.log`).
- Patched `NODE_ENV=development ... npm run build`: exit 0; type checking completed, all 120 static pages generated, build traces finalized and standalone preparation completed (`build-forced-production.log`). Existing build configuration skips lint, so this is not a lint pass.
- `node --test deploy/production/tests/managed_build.test.mjs deploy/production/tests/managed_preview.test.mjs`: 5/5 passed (`managed-build-tests.log`). The build's separate braces regression suite also passed 3/3.
- Same installed locked dependencies and synthetic .invalid origins were used for both build attempts. The only packaging changes for the successful attempt were the build entrypoint and its wrapper. No dependency reinstall or app-page workaround was used.

This turn did not rerun the prior browser acceptance or claim host/browser recovery from the build result. Hosted CI for the new source commit remains separately unverified.
