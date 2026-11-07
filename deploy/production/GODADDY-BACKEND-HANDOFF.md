# Isolated backend pilot handoff — October 22, 2026

Owner verified the existing managed app z1izdz2qau Healthy / InfraOK / SiteOK and Chrome `/auth/login` after uploading the 5192aba archive (SHA256 70a3d1b7bb08a0cc642e448beb752a6bedff7f0d32512ce9f70f0da9f5fd47d1). This is managed frontend startup evidence, not backend/clinical acceptance. Do not create another app or touch EMA.

## Exact existing API artifact

The immutable, already measured API/static artifact remains:

- Source `bed4caa6a364db0dc975d2cbef0716ca277b8286`.
- `/workspace/artifacts/fayda-godaddy-pilot-20261021/cpanel-api-static-bed4caa.tar.gz`.
- 37,119,766 bytes; SHA256 `d31327800d1d5da1e459bf6df772be790ae18f1ac89b8153289e1010f9337090`.
- 11,565 extracted entries, 89,487,182 logical bytes. Against the last owner-observed 14,694 free account entries, only 3,129 remain before namespace, archives, runtime files and other-site growth. Recheck current quota before extraction. Two extracted releases do not fit. Do not delete unrelated files.

This artifact already includes production vendor. Native PHP/Composer are needed to rebuild dependencies or execute the API, not to copy/verify the existing archive. Download it through the workspace artifact link if available. Do not substitute a new build's checksum for this immutable artifact.

## Container-free reproduction of the current candidate

Requirements: clean checkout of the exact selected commit, Git, Python 3, Node 22, native PHP 8.3 with all extensions checked by `host-preflight.php`, Composer 2, and npm for the frontend's package-lock.json. The cloud's PHP wrapper uses a container; its success does not establish availability on the owner's local machine. Do not use ignore-platform-reqs or composer update. PHP's configured sodium platform value is not extension evidence: check actual platform requirements.

Use new output paths outside the checkout. `REPO` below means that clean checkout and `WORK` a new local scratch directory; assign actual absolute paths before running. No credentials or `.env` are needed for packaging.

```sh
php -v
composer --version
php "$REPO/deploy/production/godaddy-pilot/host-preflight.php"
python3 "$REPO/deploy/production/stage_pilot_api.py" --repo "$REPO" --output "$WORK/api"
cd "$WORK/api"
composer install --no-dev --prefer-dist --no-interaction --no-scripts --optimize-autoloader
composer check-platform-reqs --no-dev
APP_ENV=production SYNTHETIC_PILOT=true php artisan package:discover --ansi
```

The helper uses Git blobs and excludes tests, seeders/factories, runtime storage and env examples; creates only empty storage/cache directories. Composer must report a production-only install. Explicit discovery is the only Laravel bootstrap step in this recipe: no migrations, seed, keys or DB connection are requested. Stop on any failure; do not copy a developer vendor or runtime storage over it.

The current packager includes the static frontend. In that same clean checkout install its package-lock.json with npm ci, then build with deliberate non-live origins until routing is approved:

```sh
cd "$REPO/frontend"
npm ci
VITE_BASE_URL=https://api.pilot.invalid VITE_API_BASE_URL=https://api.pilot.invalid npm run build
cd "$REPO"
node deploy/production/godaddy_pilot_package.mjs --without-admin \
  --api-runtime "$WORK/api" --output "$WORK/bundle" \
  --api-origin https://api.pilot.invalid \
  --admin-origin https://admin.pilot.invalid --public-origin https://public.pilot.invalid
python3 deploy/production/archive_pilot.py --root "$WORK/bundle" --output "$WORK/cpanel-pilot.tar.gz"
python3 deploy/production/verify_pilot_archive.py --help
```

Use the verifier with the printed SHA256 and exact source commit before transfer or extraction. The archive writer normalizes ordering, owner IDs/names and timestamps; repeated archiving of the same validated tree produces identical bytes. Composer-generated metadata, platform, frontend build/tool versions and manifest source identity can change across machines: a native rebuild is **not promised** to reproduce bed4caa's historical checksum. Compare manifests and actual artifact hash, not filenames. The new packager records exact directory paths and prunes only explicitly tested nonruntime vendor tests/CI metadata while refusing autoload references; runtime code and licenses are retained.

## Read-only host checks and bounded setup

Run `ops/host-preflight.php` with the actual PHP 8.3 binary. CLI extensions do not prove web-SAPI configuration. After an approved isolated extraction/configuration, `ops/host-readiness.php --api-root ABSOLUTE_RELEASE/api --served-root ACTUAL_WEB_ROOT` checks private path metadata only. Include each relevant actual served root; never supply invented safe roots. It must report private .env/key/documents/cache/log paths outside served roots, restricted modes and no public storage symlink. It does not establish HTTP denial or web-user write access.

SQL is opt-in: add `--sql --expected-database ACCOUNT_fayda_pilot_NAME` only after a new dedicated local MySQL database/user has been explicitly selected and configured. The tool requires APP_ENV=production, SYNTHETIC_PILOT=true, mysql on existing localhost/127.0.0.1:3306 and an exact matching isolated name. It executes a read-only transaction and schema metadata SELECTs, then rolls back. It prints no credentials or patient records; migrations/privileges remain unverified. No broad grants or account-wide database access.

Setup is an operator-reviewed step after backup/restore and routing gates: fresh isolated database only, migrations only to that selected database, fresh local APP_KEY/Passport keys outside served roots, and at most one synthetic organization plus one explicitly scoped initial user with an example.invalid address. Keep passwords out of command arguments/history/chat. Do not run generic DatabaseSeeder, import a local database, create wildcard staff access, disable MFA, seed stock/fills/claims/balances, or create sample real patients. Stop if business tables already contain data; no destructive reset or repeat seed. This document does not claim a seed implementation or owner login acceptance.

Archive-based rollback is preferable to two extracted trees under current quota: verify the trusted artifact with `verify_pilot_archive.py` without extraction (zero new filesystem entries), retain an independently verified isolated DB/private-file backup and config/key restore procedure, then plan replacement within the approved isolated namespace during maintenance. Archive verification is not a database restore rehearsal. Never automate deletion of the active release without a tested recovery path. Runtime/cache/session/upload growth needs a measured budget; 3,129 free entries is not operating approval.

## Same-origin private-preview option (review only, not implemented)

Browser → existing private managed preview over TLS → bounded server-side proxy → isolated PHP public/index.php over validated TLS is technically possible. A browser base `/api` and same-origin document URLs would need a rebuild/config change; existing code constructs most API calls from NEXT_PUBLIC_API_BASE_URL and a document preview from NEXT_PUBLIC_API_URL. A proxy alone does not repair the intentionally .invalid current origins.

Required before implementing:

1. A GoDaddy-supported upstream routing address reachable **from managed Node**, with valid TLS identity and routing to only this pilot's public/index.php. Existing cPanel paths/domains currently serve other products/old routing; no route or DNS change is authorized here. No plain HTTP, certificate bypass, arbitrary URL target, new listening port or direct IP/Host workaround.
2. Upstream access restricted independently (supported private network or gateway authentication plus deny direct access). CORS is not access control. If cPanel can only provide a public endpoint and no enforceable restriction, this does not satisfy the requested private-only backend: report the hosting limitation rather than expose it.
3. Preserve the preview access gate on `/api`, application bearer auth/MFA/role/location authorization, TLS and the exact approved Origin. A proxy must not bypass Laravel auth or grant privileges. Keep any upstream gateway secret server-only; never NEXT_PUBLIC or a query parameter. Strip untrusted forwarding/gateway headers and use a fixed allowlisted upstream/path, bounded body sizes/timeouts and no shared caching of authenticated data. Do not follow cross-origin redirects with credentials.
4. Verify Authorization forwarding through cPanel/PHP; Origin/Host behavior and trusted proxy configuration; login/MFA/password recovery; authenticated private document streams/download headers; and non-exposure of .env, vendor, keys, storage and exception traces. Public document links must not point outside the private preview. Test unauthenticated denial and role/site isolation through the full route. No broad CORS wildcard, signed-URL leakage or debug mode.

Critical external gates: owner-selected cPanel isolated folder/document root and reachable protected routing; native/host PHP extensions and least-privilege new MySQL capability; fresh quota/runtime headroom; isolated backup/restore/private-storage denial; and full browser API/auth/document acceptance. The current production pharmacy gate remains closed even with SYNTHETIC_PILOT=true. No live patient data, dispensing, financial/provider/external sends or clinical release is authorized. No deployment, DNS/publication or new endpoint was performed in this task.

## Tool validation retained with this preparation

Focused Node package tests: 5/5; PHP metadata/SQL selector and readonly rollback tests: PASS (fake PDO only, no real DB); Python archive verifier: 3/3 including digest/source mismatch, tampering/duplicates/traversal/unlisted files. Committed source staging was exercised against clean source5192aba: exact Composer lock, no env/vendor/test/seed/key/runtime files. Two normalized archives of the existing prepared bed4caa tree both measured36,814,810 bytes and SHA2568ce2c5cf403aa312bd03ca77f9bc1f2b56b11c67afbe869dda4631d60a2dcc82, demonstrating deterministic serialization; these are scratch verification files, not replacements for the handoff's immutable original archive. Readonly verification of that scratch archive validates source and all file hashes without extraction. No local-owner PHP availability or fresh native Composer install was inferred from these tests.
