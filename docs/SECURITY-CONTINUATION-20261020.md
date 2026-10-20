# October 20 security and launch continuation

Branch: codex/cloud-pharmacy-acceptance, PR #2. Baseline ce839f40d36656c147166d63c88689071ee40617. Work remains synthetic-only; no production access, PHI, provider transactions or clinical release.

## Dependency disposition

Removed unused react-quill-new / Quill and its dead MyInp rich-text branch and CSS import. Repository application callers use other field types; no rich-text caller was found. Existing DOMPurify helpers and other protected document sinks remain. No replacement editor or unrelated dependency was added.

braces 3.0.3 is used by the development eslint-config-next → @next/eslint-plugin-next → fast-glob → micromatch chain. Registry metadata still advertises 3.0.3 while GHSA-vfj7-8cjw-p6xm lists a patched version >=3.0.4. A local pnpm patch bounds parser nesting and direct compile/expand/stringify AST traversal at depth 64. Excessively nested patterns now throw an explicit RangeError. This intentionally limits unusually deep lint globs. The original package integrity and patch hash stay in pnpm-lock.yaml; no version rename, audit exclusion or severity filter hides the finding.

An independently downloaded original braces@3.0.3 tarball reproduced `compile: RangeError: Maximum call stack size exceeded` on 4,000 nested braces, within the existing maximum input length. The patched dependency regression checks balanced/unbalanced deep braces and parentheses, direct 10,000-node ASTs, and ordinary Next globs/ranges/nested alternatives. Three tests pass. Frozen install reapplies the locked patch. This is a local mitigation, not a claim that the registry advisory or all denial-of-service risks are cleared. Remove the local patch only after reviewing an upstream fix and rerunning regression/build checks.

Latest `pnpm audit --json` exits 1: **one high braces advisory**, zero low/moderate/critical; Quill is absent. Both live package registry audit output and patched behavior are retained separately. Residual risk requires explicit candidate disposition.

`composer audit --locked --abandoned=report --no-interaction` exits 100: CONNECT tunnel HTTP 403 for https://packagist.org/api/security-advisories/. Composer audit status remains unknown, not clean. Supported current-runtime network policy must permit that canonical advisory destination before a successful audit can be recorded. No proxy/host-header bypass or credential request was used.

## Consequential readiness work

Added a read-only assigned-site readiness review on the existing pharmacy queue. It reports documentary license-reference presence and currently active, unexpired, same-organization staff assignments. It lists missing site/jurisdiction, credentials, catalogue, interfaces, controlled policy, sterile/nonsterile acceptance, hazardous classification, devices and accountable release approval. These are unknown/unverified requirements, not an invented clinical policy or credential verification. Every ordinary/controlled/sterile/nonsterile workflow remains operational_release_enabled=false. Production preview middleware still returns 503.

Focused API tests cover role/organization/current-assignment/expiry/inactive-site restrictions, unchanged operational/financial records, the four closed release gates and production denial. Browser evidence is recorded separately; API assertions are not browser evidence.

## Execution record

- PHP 8.3.35 / Composer 2.10.3 / Node 22.23.3 / pnpm 11.19.0, locked dependencies.
- `pnpm install --frozen-lockfile`: passed with local patch.
- `pnpm test:dependencies`: 3/3 passed.
- `pnpm typecheck`: passed.
- `php vendor/bin/phpunit tests/Feature/PharmacySiteReadinessTest.php --display-warnings`: SQLite 3 tests / 36 assertions passed, no warnings. Artisan pretty reporter emitted missing .env warnings; direct PHPUnit above is the authoritative clean run.
- `FAYDA_SYNTHETIC_DB=fayda-cloud-mysql /workspace/.cloud-tools/mysql-bin/php vendor/bin/phpunit -c phpunit.mysql.xml --filter PharmacySiteReadinessTest`: 3 tests / 36 assertions passed on isolated MySQL 8.0.46.
- Same command with fayda-cloud-mariadb: 3 tests / 36 assertions passed on isolated MariaDB 10.11.19.
- Optimized build and browser evidence: recorded in the follow-up evidence section when complete.

Raw logs are retained in /workspace/.cloud-tools/continuation-20261020. Browser sessions/signing material are excluded from review artifacts. No broad suite rerun is claimed here.

## External blockers

Owner independently confirmed all six hosted checks SUCCESS at exact ce839f4; later heads require separate verification. Environment publication and independent fresh-task restoration remain unverified. Owner reports an old Connecting operation and newer unsaved UI changes. Exposed supported tools provide configuration/readiness but no publication-operation status/cancel/recovery action. Preserve the existing draft/privacy and use owner/platform UI recovery; current-runtime engineering continues.

The dated conditional launch path, owners and separate live-operation gates are in LAUNCH-CHECKLIST-20261027.md. No source archive, test count or synthetic walkthrough grants clinical, hosting, financial or owner launch approval.
