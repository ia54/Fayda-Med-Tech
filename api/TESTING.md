# Backend regression tests

Use PHP 8.3, Composer, OpenSSL and PDO SQLite (SQLite 3.35 or later). Install the committed lockfile, then run:

```sh
composer install --no-interaction --no-scripts --no-plugins
php vendor/bin/phpunit --testdox
```

The PHPUnit configuration forces `APP_ENV=testing`, a test-only encryption key and an in-memory SQLite database. All identities and documents are synthetic. Most authorization tests supply synthetic token metadata; `TokenLifecycleTest` separately generates an ephemeral RSA key pair and exercises actual Passport token issuance, MFA, refresh rotation and logout.

At implementation commit `b06bb93`, native PHP 8.3 CI passed **32 tests / 296 assertions**, with no skips. Coverage includes registration closure, roles, tenant credential boundaries, document visibility for all six roles, private uploads, immutable originals, signer identity/order/replay, document archiving, legacy file migration, webhook authentication, MFA enrollment/challenge/recovery, and token lifecycle. The six-role token test covers login through private document access; it does not establish complete billing, settlement or clinical journeys.

The separate MySQL 8 CI job passes a fresh schema build. This is not a production-shaped upgrade or rollback rehearsal. Evidence: https://github.com/ia54/Fayda-Med-Tech/actions/runs/35716995491 .

Local WebAssembly PHP cannot generate the RSA keys required by two token tests; those tests skip locally and pass in native CI. Changed frontend screens pass targeted lint. The project-wide type check still reports 19 existing errors. Browser checks with a localhost-only synthetic response fixture verify MFA enrollment, invalid-code feedback, recovery acknowledgement, dashboard navigation and the account-security page at desktop/mobile sizes. That fixture is UI evidence only, not backend integration proof. The existing mobile dashboard header clips account text.

For quick declaration checks without Laravel dependencies:

```sh
php tests/Security/role-access-smoke.php
php tests/Security/route-boundaries-smoke.php
php tests/Security/cors-config-smoke.php
```

These smoke checks supplement the Laravel suite. They do not establish release readiness.

## Credential uniqueness migration

`2026_09_22_000001_scope_api_credential_uniqueness.php` adds an indexed generated scope (`COALESCE(organization_id, 0)`) and replaces global provider/name uniqueness. Organization IDs must be positive; zero is reserved for the null/platform scope. Test on the deployment's actual MySQL version before release. Apply under a controlled release with backups and migration monitoring; MySQL schema changes are not assumed transactional.

Rollback refuses overlapping names across organizations rather than deleting records. When names do not overlap, it restores the original uniqueness constraint and removes the generated column. Forward and rollback behavior are verified on the synthetic SQLite fixture; The MySQL 8 fresh migration path passes CI; migration against existing data and rollback on MySQL still require rehearsal.
