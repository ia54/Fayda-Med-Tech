# Backend regression tests

Use PHP 8.3, Composer, OpenSSL and PDO SQLite (SQLite 3.35 or later). Install the committed lockfile, then run:

```sh
composer install --no-interaction --no-scripts --no-plugins
php vendor/bin/phpunit --testdox
```

The PHPUnit configuration forces `APP_ENV=testing`, a test-only encryption key and an in-memory SQLite database. The Passport fixture contains only a public key; its private key was discarded. Authenticated request tests supply synthetic identities and token metadata; they do not prove real token issuance, refresh, MFA or password authentication.

Coverage includes public registration closure, real-router role declarations, role middleware, secret serialization, cross-tenant credential access, ownership-field rejection, secret-preserving edits, tenant/platform uniqueness, safe migration rollback and a complete fresh migration run. The test harness enables SQLite native schema operations where supported. Existing MySQL deployments still require a separate migration rehearsal and database-backed acceptance tests against the deployed MySQL version.

On the audited workstation, PHP runs through `@php-wasm/cli` 3.1.55 with `PHP=8.3`. Workspace reads stalled, so verification used the exact baseline commit in a temporary directory with repaired files overlaid. Composer's development autoloader was generated with optimization disabled in a temporary configuration; production configuration still enables optimization. The complete suite passed with PHP 8.3.33 / PHPUnit 10.5.63: 16 tests, 65 assertions. A Node deprecation warning from the WebAssembly runtime remains; it did not fail the suite.

For quick declaration checks without Laravel dependencies:

```sh
php tests/Security/role-access-smoke.php
php tests/Security/route-boundaries-smoke.php
php tests/Security/cors-config-smoke.php
```

These smoke checks supplement the Laravel suite. They do not establish release readiness.

## Credential uniqueness migration

`2026_09_22_000001_scope_api_credential_uniqueness.php` adds an indexed generated scope (`COALESCE(organization_id, 0)`) and replaces global provider/name uniqueness. Organization IDs must be positive; zero is reserved for the null/platform scope. Test on the deployment's actual MySQL version before release. Apply under a controlled release with backups and migration monitoring; MySQL schema changes are not assumed transactional.

Rollback refuses overlapping names across organizations rather than deleting records. When names do not overlap, it restores the original uniqueness constraint and removes the generated column. Forward and rollback behavior are verified on the synthetic SQLite fixture; MySQL behavior is not yet verified.
