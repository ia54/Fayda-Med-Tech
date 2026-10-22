# Earlier Passenger rehearsal proposal — superseded October 21, 2026

**Primary deployment path is now [managed Node admin + cPanel API/static](GODADDY-SPLIT-PILOT.md). Passenger below is a retained, unverified alternative assessment, not the selected deployment path. Latest confirmed account headroom is 14,694 slots and nine available managed Node slots; see the newer document.**

This is preparation for the owner's existing GoDaddy Deluxe account. It is not deployment authorization or host validation. Production hosting is deferred until handover. Synthetic data only; clinical release, real patient data, provider transactions and external delivery remain disabled. Do not change DNS, buy services, add host ports, touch unrelated sites, or deploy to the old server.

## Observed account constraints and decision

Owner verified October 20: other active products share this account; PHP 8.3; 1 GB RAM cap; 235,446 / 250,000 file usage (14,554 remaining); 21.29 / 50 GB disk usage. Passenger Application Manager exists with zero registered apps. Node version, actual Passenger loader/startup options, worker/process limits, MySQL, cron and extension availability remain unverified. No account/hosting access was performed here.

GoDaddy controls faydamed.tech DNS, but root A 144.126.132.98 and admin/api/www CNAME root still route to the old server. Ownership of DNS does not prove those origins reach this account. Do not visit the old server as a deployment test or change these records. Operator must identify an approved isolated GoDaddy preview origin/routing method that needs no DNS change. If that is unavailable, deployment is blocked.

The measured footprint and exact source/build evidence are in `godaddy-pilot-footprint-20261021.json` (added after measurement). Count regular files and all directory/symlink entries; confirm what the hosting meter counts. Include temporary extraction, logs, uploads, sessions, compiled views, email growth from unrelated products, and recovery space. A tight single-release fit is not safe two-release capacity. Never delete unrelated files to make room. If the existing quota cannot safely hold this package and rollback, defer the account deployment or seek an owner-approved capacity/architecture decision; no purchase is authorized.

## Package and reproducible cloud build

Use Linux x64, PHP 8.3 / Composer 2 / Node 22, locked dependencies. Build off-host; 1 GB shared-account runtime availability does not establish build capacity. Do not upload the checkout, tests, full node_modules, Composer dev dependencies or cloud runtime state.

1. Commit source and inspect exact HEAD/CI. Export `git archive HEAD api` into a NEW private staging directory outside the checkout. Remove only this export's tests, database/factories, database/seeders, storage, .env.example and phpunit*.xml. Create empty storage/app/private/documents, storage/framework/{cache/data,sessions,views}, storage/logs and bootstrap/cache. Keep migrations. Never copy existing storage, .env, credentials, Passport keys or databases.
2. In staged api, run `composer install --no-dev --classmap-authoritative --prefer-dist --no-interaction --no-progress --no-scripts --no-plugins`, then `composer check-platform-reqs --no-dev`. Preserve the lock and package integrity. No dependency update or audit suppression. Production requires packages currently declared in require, including Tinker/Swagger; do not silently remove declared runtime dependencies.
3. In admin-panel, stop only the owned cloud admin server before building. Set NEXT_PUBLIC_API_BASE_URL=https://api.pilot.invalid/api and NEXT_PUBLIC_API_URL=https://api.pilot.invalid; run `pnpm install --frozen-lockfile`, `pnpm test:dependencies`, then `pnpm build`. In frontend set VITE_BASE_URL and VITE_API_BASE_URL to https://api.pilot.invalid and run `npm ci --no-audit --no-fund` and `npm run build`. These intentionally unroutable rehearsal origins prevent an artifact from contacting the old server. They are not deployable origins. Rebuild with operator-approved origins after routing/host checks; Next/Vite public variables are compiled, not runtime settings.
4. From the clean repository root run:

```bash
node deploy/production/godaddy_pilot_package.mjs \
  --api-runtime /ABSOLUTE/NEW/STAGE/api \
  --output /ABSOLUTE/NEW/BUNDLE \
  --api-origin https://api.pilot.invalid \
  --admin-origin https://admin.pilot.invalid \
  --public-origin https://public.pilot.invalid
```

The builder binds staged API source to committed blobs, rejects Composer dev packages, copies the supported Next standalone tree plus `.next/static` and `public`, and includes only Vite dist for the public site. The minimal traced node_modules inside standalone is REQUIRED at runtime; excluding all node_modules breaks Next. Internal relative symlinks must survive transfer/extraction. No full workspace node_modules is copied. Only the following non-runtime examples are removed: firebase/php-jwt/README.md (published private-key example), and two explicitly autoload-dev-only vendor test directories: adhocore/jwt/tests and docusign/esign-client/test. They contain published test private-key fixtures, are absent from production classmaps, and must not ship. No production library files are pruned. Inventory rejects private-key material, runtime .env/database files and escaping symlinks, then records per-file hashes and measured components in PILOT-MANIFEST.json. It never claims production readiness.

Archive outside the bundle with tar preserving symlinks; record a SHA-256 through a separate trusted channel. Keep the manifest and measurement record. Verify every regular file/hash, symlink and member path after extraction, before execution. Source-only release packages are separate from this runtime package and from operational backups.

## Isolated operator layout — not executed on hosting

Choose a NEW namespace under the account home, outside public_html and existing site trees, e.g. `~/fayda-synthetic-pilot/releases/<SHA>/{api,admin,public,ops}`. Do not reuse existing app/database names. Create a NEW dedicated synthetic database and least-privilege user; no imports from production. Host-specific cPanel account prefixes, MySQL version, utf8mb4/collation, transactional InnoDB/foreign keys and migration privileges require verification. Default pilot quota/data budget must be approved before extraction.

Copy ops/api.env.template to api/.env only on the approved host, outside web roots; set verified origins and new DB credentials via secure operator handling. Generate fresh app/Passport keys and Passport clients only for this new pilot; never import cloud test keys or existing production values. Keep .env and private keys mode 0600, directories 0700/0750 as account/PHP ownership permits. Configure document storage outside served roots. Do NOT run storage:link. Runtime storage is private by default; operator must prove traversal, .env/vendor and unauthenticated upload reads are denied by the actual Apache/Passenger configuration. Do not expose API root, ops/preflight or admin node_modules as static document roots.

Run CLI-only `php ops/host-preflight.php` before setup and compare web PHP against CLI. Required baseline extensions include ctype, curl, dom/XML, fileinfo, filter, hash, intl, json, mbstring, openssl, pcre, PDO/pdo_mysql, session, sodium, tokenizer, zip and bcmath. Composer platform checking on cloud does not establish GoDaddy parity. Verify disk/entry quotas, permissions, upload/post limits, execution time and PHP memory limits.

After source/manifest verification and isolated DB review: package discovery, migrations, fresh keys/client generation and explicit synthetic account provisioning need an approved operator procedure. No installation/migration/seed script runs automatically from this package. Do not run the generic DatabaseSeeder or import browser fixture sessions. Initial users, synthetic examples and password/MFA acceptance remain operator/owner provisioning gates.

APP_ENV=production and APP_DEBUG=false stay set. SYNTHETIC_PILOT=true forces array mail, synchronous queues and null broadcasting; Laravel HTTP transport and signing SDK token acquisition fail before outbound calls. Empty provider credentials alone are not the enforcement. Do not add AI/OCR/signing/PDMP/payer/payment credentials. No external HTTP sends or live financial/provider operations are authorized. As defense in depth, ask the operator to verify account-level outbound restrictions where available. New future SDK transports require explicit review; the guard is not an operating-system firewall.

**Hosted pharmacy preview remains blocked by the existing production middleware (503), even in synthetic mode.** This package supports a limited shared-platform synthetic demo after its gates pass; it does not silently enable the pharmacy cloud rehearsal on a public host. Demonstrating pharmacy workflows on hosting requires a separately reviewed, hardened synthetic preview policy and acceptance. Cloud-only pharmacy evidence remains available. Never set APP_ENV=local/testing on hosting to bypass this boundary. Both sterile and nonsterile clinical acceptance remain outstanding.

## Passenger, memory, MySQL and cron checks

`admin/app.cjs` requires the Passenger global, explicitly selects first-http-server interception and then loads the generated standalone server.js. Without Passenger it throws before loading a server: no fallback listener/host port. Official Passenger stable-6.0 node-loader.js documents autoInstall=true interception of the first HttpServer.listen call; the exact host loader/version and Next start behavior are still unverified. Confirm Node 22 executable, Linux architecture/libc, startup file customization (app.cjs), environment handling, internal socket mapping, restart controls and logs with the operator. Do not add reverse-proxy ports such as 3000/3005 or register an application until authorized. A mocked loader unit test or cloud `node server.js` startup is not Passenger acceptance.

Measure actual account process RSS/peaks under a representative owner walkthrough. A single local Next process passing with a bounded V8 heap does not prove total account usage fits 1 GB alongside PHP/MySQL/other products. Node/PHP worker counts, concurrent requests, idle behavior, restart limits and account throttling are gates. No GoDaddy build is planned.

Pilot queue is sync; no daemon/Redis/Horizon is required. Current Console Kernel schedule has no active entries, so there is no reason to install a broad schedule:run cron for this demo. Verify cron availability before selecting a specific maintenance task; time zone, execution limit, overlap prevention and account load need review. Do not enable provider/signing recovery commands or external notification tasks. Any later background workflow needs its own acceptance.

## Restore and rollback — no existing sites affected

Before first upload, record fresh account file/disk usage and reserve extraction/runtime/recovery capacity. Archive only this pilot namespace, NEW database, private files and its keys/config into access-controlled backup outside public roots; host location/capacity/encryption/access must be verified. Keep trusted manifest/digests and record source/build SHA. Do not call a code tarball a full backup.

Rehearse restoration into a SECOND newly named synthetic database and private directory, validate tables/rows/schema, document/key consistency and private-read denial before exposing the demo. Prior cloud MySQL/MariaDB 141-table/private-file recovery evidence does not establish host recovery. Do not stage two extracted releases if quota cannot hold them. An archive-based recovery approach may need a maintenance window, but must be validated before use; no deletion of existing or unrelated products is proposed.

Cutover, if separately authorized, changes only the new pilot's Passenger registration/document root in approved routing; DNS/old-server changes remain prohibited. Freeze pilot writes, take a coordinated DB/private-file/key/config snapshot, verify backup, then switch that pilot namespace only. On failure keep pilot writes disabled, restore the matching application/config/DB/private-file snapshot as one unit, validate privacy and synthetic boundaries, then reopen only after owner approval. After new writes, reconcile them before rollback; never blindly restore stale state. Keep logs/source/evidence and named rollback owner. Never touch shared server settings or another product.

## Go/no-go evidence still required

- Quota fits a complete safe extraction/runtime/rollback plan; no assumptions about file-only versus inode counts.
- Approved GoDaddy preview origins, TLS/cookies/CORS and private access without DNS changes or old-server access; rebuild assets for those origins.
- Actual Node 22 / Passenger standalone startup, restart and representative concurrent memory/load evidence within shared account limits.
- Actual web/CLI PHP extensions, MySQL/version/grants/migrations, private storage and backup/restore proof.
- Current source-head hosted CI, canonical Composer audit and explicit residual braces advisory disposition. Audits are not suppressed.
- Synthetic account/password/MFA provisioning, owner role walkthrough/feedback and explicit limited-demo approval. API tests are not browser evidence; browser evidence is not clinical/device/host approval.

Clinical release, real patient operations, devices/printers/scanners, provider contracts/interfaces, site professional acceptance and owner full-product acceptance are separate gates. October 27 America/New_York remains conditional on the gates for the actual selected scope.
