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
- `pnpm test:session`: 5/5 passed; `pnpm test:documents`: 2/2 passed.
- `pnpm build`: optimized build passed all 120 routes. First browser replay used this default localhost build while the harness permits only 127.0.0.1; it failed before acceptance. A fresh build with NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api and NEXT_PUBLIC_API_URL=http://127.0.0.1:8000 is used for final browser evidence. The test selector was also corrected to match the wrapped location label.
- A subsequent Chromium replay found a real missing focus return after closing the new readiness dialog. The component now restores focus explicitly and exposes dialog expanded state. Final browser evidence is recorded below when complete.
- `php tests/Security/role-access-smoke.php`: 3,104 decisions passed.
- `php tests/Security/route-boundaries-smoke.php`: initial exact-count assertion detected the added route; inventory updated from 190 to 191, with explicit readiness route presence assertion. Final result: 21 platform-only / 191 protected pharmacy / 434 total routes passed. Request-level tests remain separate.

Raw logs are retained in /workspace/.cloud-tools/continuation-20261020. Browser sessions/signing material are excluded from review artifacts. No broad suite rerun is claimed here.

## External blockers

Owner independently confirmed all six hosted checks SUCCESS at exact ce839f4; later heads require separate verification. Environment publication and independent fresh-task restoration remain unverified. Owner reports an old Connecting operation and newer unsaved UI changes. Exposed supported tools provide configuration/readiness but no publication-operation status/cancel/recovery action. Preserve the existing draft/privacy and use owner/platform UI recovery; current-runtime engineering continues.

The dated conditional launch path, owners and separate live-operation gates are in LAUNCH-CHECKLIST-20261027.md. No source archive, test count or synthetic walkthrough grants clinical, hosting, financial or owner launch approval.

## Final source-bound follow-up

Final Chromium replay passed at clean `6d4ff872eede0973f3de32e25fce7a53019886b9`, with 727 source-file hashes and five CSS/manifest/lock/patch hashes. The optimized local-origin build passed all 120 routes with the application source committed at 5ccae8f; subsequent source commits change only the browser runner. Existing lint skipping is not a lint pass. The new dialog's real focus-return defect and its interaction with the existing shrink-to-content modal CSS were repaired. Final desktop 1440×1100 / narrow 390×844 captures wait for opening animation completion; narrow review remains readable and scrolls to every disabled workflow.

Visible pharmacist and technician journeys passed: disabled button before location selection, documentary site evidence, four closed workflow flags, keyboard Escape/focus return, and one-shot injected HTTP 500 followed by successful real-API retry. Six separate browser-initiated authenticated API probes returned 403 for client, attorney, firm admin, provider, biller and organizationless platform admin. These probes are labelled separately from visible UI actions. All 79 pharmacy/financial tables retained identical baseline/after hashes. No external browser host was requested. Role sessions were seeded; this replay does not prove login/MFA. Historical authentication evidence remains in the prior closeout.

- [Machine-readable continuation record](evidence/READINESS-CONTINUATION-20261020.json)
- [Raw source-bound browser result](evidence/readiness-20261020/site-readiness-result.json)
- [Narrow review](evidence/readiness-20261020/site-readiness-technician.png) and [scrolled workflow gates](evidence/readiness-20261020/site-readiness-technician-scrolled.png)
- [Unsuppressed dependency audit disposition](evidence/readiness-20261020/dependency-evidence.json)

Fresh isolated MySQL 8.0.46 and MariaDB 10.11.19 rehearsals ran `php tests/Security/mysql-upgrade-smoke.php` then `bash tests/Security/database-restore-smoke.sh` through the guarded local wrappers. Each preserved legacy values across four additive migrations, repeated migration safely, and restored all 141 tables, rows and schema definitions to a separate disposable database. Private-file archive bytes matched. `GITHUB_ACTIONS=true` enables only the disposable local guard, not a hosted-CI claim. Actual production backup/restore and private storage remain unverified.

Verified source-only archive: `/workspace/.cloud-tools/continuation-20261020/source-6d4ff872eede0973f3de32e25fce7a53019886b9.tar.gz`, SHA-256 `736e64ecdab5853bb8c3e76fc3328259d2ceaaa7a652df2e73e5f8efc29ac556`, 1,215 files / 137 migrations, `production_ready=false`. This is a review/rehearsal package, not a deploy-ready release candidate or operational rollback snapshot. Follow deploy/production/LAUNCH.md for coordinated application/database/private-file rollback; no real host operation occurred.

Review evidence archive: `/workspace/.cloud-tools/continuation-20261020/readiness-review-evidence-20261020.tar.gz`, SHA-256 `8b9890aecfdfebd76fa4259d950197ca4876285ca5b1e2c533e2081ad8a4b392`, 23 files. It excludes session JSON, signing keys and the synthetic fixture database; the original private database and its SHA-256 remain retained locally, with table invariants in the committed browser evidence.

The saved environment draft read back as revision 12, source pin ce839f4, with Install script and Start skill both set, no secrets/runtime requirements, and custom api.github.com / packagist.org rules. This original runtime still enforces its old version without those rules. Updated start instructions are prepared at `/workspace/.cloud-tools/start-skill-20261020-prepared.md`; the saved draft was preserved because the owner reported unsaved UI edits and a coordination question remains pending. Reconcile the new source pin/instructions with those edits before publication. Saving or seeing a newer configuration version is not publication or fresh-task restoration evidence.

Remaining browser/acceptance gates: owner role walkthrough and feedback disposition; real-origin approved hosting/services; full accessibility conformance; actual mobile devices/printer/scanner; site-specific professional/clinical acceptance, including separate sterile and nonsterile compounding. No broad suite rerun or completion percentage is inferred from these focused checks.

After replay, the owned browser fixture API and both disposable SQL services were stopped. The separate empty synthetic API was restored: FAQs returned 200 with an empty data list, and anonymous pharmacy access returned 401. The optimized local admin server remains running.
