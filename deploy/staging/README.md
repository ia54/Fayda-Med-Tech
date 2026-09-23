# Synthetic staging deployment

Status (23 September 2026): hosting access and the three application locations are verified. The owner explicitly selected files-only preparation: no new ports, database provisioning, service startup, DNS changes or production deployment. Prepared copies remain outside the public web roots. The provisioning steps below are a later runbook, not completed operations.

## Host access needed

Authorized SSH inspection confirmed the host serving the three FaydaMedTech domains and its OpenLiteSpeed, PHP 8.3 and MariaDB 10.11 stack. Access to unrelated servers, applications and folders is outside scope. Credentials must remain outside this repository.

Record the deployed commit, PHP/MySQL/Node versions, vhosts, process manager, document roots, database grants, storage paths and backup destinations without copying secret values into reports. Inspect available capacity before adding staging. Prefer a separate server/account; if sharing a host, use a separate OS user, vhosts, database user, document storage, Passport keys and application key. Do not copy production patient records or credential tables.

## Provisioning and deployment order

1. Choose staging API/admin hostnames, provision trusted TLS and restrict ingress to authorized testers at the network or access-proxy layer. Avoid a proxy that consumes the application's Bearer Authorization header. Add noindex response headers. Do not expose staging before this is verified.
2. Create an empty MySQL database and a unique user granted only that database. Verify SHOW GRANTS and that this identity cannot access the production database. Separate PHP/runtime files and storage from production.
3. Check out the exact approved development commit in that isolated directory. Install PHP 8.3 dependencies from api/composer.lock; run Composer's platform check against the actual host. Keep development fixtures and keys out of the deployed webroot. Serve only api/public, never the repository root.
4. Copy api.env.example from this directory to the staging api/.env. Replace placeholder URLs, password and key with unique staging values through the host's protected configuration. Leave mail on array, queues on sync and vendor credentials absent for the initial synthetic test. Do not copy the production .env. Restrict outbound vendor traffic until reviewed; environment settings alone do not prevent calls using database-held or hardcoded credentials.
5. Run `php artisan key:generate` only on this NEW environment, `php artisan config:clear`, and `php artisan staging:check`. This is read-only and does not prove database separation. Inspect its failures before proceeding. Never regenerate a deployed environment's application key casually.
6. Run `php artisan migrate --force --no-interaction` only after confirming the database is empty and isolated. Do not run migrate:fresh, default db:seed, or the legacy demo-user seeders. Create fresh Passport keys using `php artisan passport:keys`, then a personal-access client using `php artisan passport:client --personal`; keep its ID/secret in staging configuration. Do not publish keys or passwords.
7. Create six owner-controlled synthetic tester accounts plus an unrelated synthetic organization through a reviewed fixture/invitation process. Label all data SYNTHETIC. Do not reuse the public demo credentials. This provisioning step remains to be implemented after host verification.
8. Build admin-panel using admin.env.example values for the chosen API host. Both Next public values are build-time settings: BASE_URL includes /api, API_URL does not. Install pnpm-lock.yaml with frozen lockfile. Bind the Node process to loopback behind the protected HTTPS proxy. No production API fallback is acceptable in the deployed bundle.
9. Run `php artisan config:cache` and repeat `php artisan staging:check`. Verify authenticated document previews and that direct private-file URLs return 403/404. Confirm no production mount, symlink, shared session or database grants are present.

## Acceptance and recovery evidence

Record tested commit and time, trusted HTTPS, API/frontend origins, config-check result, database grants, migration status and access-proxy behavior. Verify actual browser login/MFA/logout, invalid recovery/replay, all six role permissions, case creation, upload, signing, billing and payment persistence using synthetic data. Do not treat a health endpoint or empty dashboard as a working journey.

Snapshot the staging database and storage, restore into a second isolated target, and verify record/file hashes and login. Preserve the staging encryption and Passport keys through the protected backup mechanism. Do not run legacy-file removal on production as part of staging setup. Workers, outbound email, external signing, payments and AI remain disabled/unverified until individually tested with vendor sandboxes.

Current project gates: 19 pre-existing TypeScript errors, remaining dependency remediation, mobile header clipping, unfinished clinical/financial workflows and external-event idempotency. Existing CI proves scoped backend behavior and fresh MySQL migrations, not host readiness or launch readiness.

## Files-only verification limits

The host SQLite version is 3.34.1, below the 3.35 minimum documented in api/TESTING.md. The full in-memory suite cannot run there because native DROP COLUMN is unavailable; do not alter shared system packages to work around this during files-only preparation. Native CI remains the full-suite evidence until a compatible isolated test runtime or authorized staging database is available. Host platform requirements, declaration checks and the three staging configuration tests can run without a persistent database. Frontend compilation inherits the existing build configuration that skips TypeScript/lint gates and does not prove complete user journeys.
