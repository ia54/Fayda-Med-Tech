# Synthetic pilot candidate — October 19, 2026

**October 20 addendum:** Ibrahim accepted this synthetic pilot at 08:05:46 UTC. Exact hosted checks and fresh-environment replay are verified; see [dated release preparation](RELEASE-PREPARATION-20261020.md) and [11/12 evidence-gate snapshot](BUILD-PILOT-CLOSEOUT-20261020.json). The October 19 pending-owner/hosted/restore statements below are historical. Residual dependency disposition, draft-12 publication and operational acceptance remain separate.

The owner's delivery order is synthetic pilot first, then the full multi-location pharmacy product. This increment closes pilot engineering findings; it does not authorize production, real dispensing, clinical release, provider calls, hosting access or funds movement. Both sterile and nonsterile compounding remain full-product scope, with per-site clinical acceptance outstanding.

## Repairs

- Organization analytics now denies clients and requires organization context even for administrators. Rates and monthly signed receipts use consistent organization/case/invoice ownership, the current year/month and recorded statuses. Unavailable duration/payer data is explicit. Adjacent staff billing summaries, appeals and validation no longer disclose their organization-wide records to clients; appeal and validation links reject inconsistent ownership.
- Billing & Plans displays recorded organization fields and explicitly unavailable subscription billing information. Invented prices, transactions, dates and unsupported actions were removed.
- EOB history has working pagination and stable ordering. Appeal ordering is stable, and its form labels are associated with the controls.
- Browser tabs share an origin-wide refresh lock and synchronously update the existing persisted auth location. Waiting tabs adopt the valid replacement; logout/account changes prevent late refresh adoption. Single-use server rotation and replay rejection remain intact. The browser reproduction failed before this repair.
- Available patches: Next/eslint-config-next 15.5.27, DOMPurify 3.4.16, source-map-js 1.2.2 in both clients, sharp 0.35.5. No advisory ignores or verification bypasses were added.

## Validation

The code candidate is `31f689013f9204766bcd62345911432e624ec80b` on `codex/cloud-pharmacy-acceptance`, PR #2. The optimized UI build and first three browser runs are pinned to `8675f4d`; the later `31f6890` commit changes only shared-role harness timing. All five replays have identical hashes for **719 application files**. Backend application/routes/feature tests are unchanged since the passing full SQLite run. The subsequent closeout commit changes documentation/evidence only.

| Check | Actual result |
| --- | --- |
| Full API SQLite suite | 458 tests / 9,339 assertions passed |
| Focused billing, appeal and EOB on SQLite | 6 tests / 128 assertions passed |
| Same focused checks on MySQL 8.0.46 | 6 tests / 128 assertions passed |
| Same focused checks on MariaDB 10.11.19 | 6 tests / 128 assertions passed |
| Admin unit tests | Session 5; recovery 2; documents 2; reports 2; pharmacy 13: all passed |
| Shared rich-text DOM tests | 4 passed |
| Optimized local-origin admin build | Next 15.5.27; 120 routes generated; TypeScript passed; lint skipped by existing configuration |
| Public Vite build | Passed; bundle-size warning retained |
| Backend security smoke checks | 3,104 role decisions; 21 platform-only and 190 pharmacy declarations / 433 routes; CORS passed |
| Reusable install | PHP 8.3.35 / Composer 2.10.3 / Node 22.23.3; locked Composer, pnpm frozen lock and npm ci passed; synthetic migrations unchanged |
| Restored default runtime | Empty `faqs.data` at 200, anonymous pharmacy 401, login HTML 200 and actual Chromium login form rendered |

Focused checks overlap the full suite; these counts must not be added into a new total. Manual opt-in fixture seed counts are separate in the evidence JSON.

| Browser replay runner | Source pin | Result |
| --- | --- | --- |
| `session-tabs` | `8675f4d` | Passed; clean worktree |
| `auth-session` | `8675f4d` | Passed; clean worktree |
| `pilot-records` | `8675f4d` | Passed; clean worktree |
| `shared-role` | `31f6890` | Passed; clean worktree |
| `compound-handover` | `31f6890` | Passed; clean worktree |

Chromium **154.0.8037.57**, with external HTTP requests blocked. The final runs attempted no external hosts. Password/MFA/recovery/reset forms, expired challenge, access/refresh expiry and overlapping navigation passed. Two real tabs retained the single rotated session using exactly **one automatic refresh**; replay of the old refresh was denied. The earlier failing reproduction and repaired replay are retained.

Private-document UI upload, visible inline PDF, actual saved PDF and legacy fallback downloads, reload, unsupported text-upload rejection and dialog keyboard focus passed. Browser-initiated probes separately verified anonymous/foreign-client denial, client analytics denial and revoked attorney assignment. Legacy text has a clear unavailable-preview/download fallback; inline text preview is not claimed. PDF download SHA-256: `ea14d59438f05b320be1ad497962db99782ee3f9d1d44edc450cd97970e7dadd`.

EOB creation, pagination, filter, list failure/retry, retained review failure/retry and rejection passed without changing payments. Appeal paging/filter/view/retry and retained local template generation passed with no provider call. Eight roles passed receipt/reversal/correction, settlement, report and access journeys. The actual saved CSV SHA-256 is `703b43949878ac7b056136f324e0d426a91d3bdb95e0d990e4de1979337f95c3`. Compounded reservation/handover rehearsal passed while stock/fill/label/financial baselines remained unchanged and clinical release stayed false.

Targeted 390×844 keyboard/layout checks passed for all eight role routes plus analytics, appeals, EOB, plan and client-document screens. Desktop journeys ran at 1440×1100. These are targeted checks, with the limits below.

Evidence: [machine-readable results](evidence/SYNTHETIC-PILOT-20261019.json), [private PDF screenshot](evidence/pilot-20261019/private-pdf-preview.png), [mobile receipt screen](evidence/pilot-20261019/mobile-biller-payments.png) and [mobile pharmacy preview](evidence/pilot-20261019/mobile-pharmacy.png).

- Browser/database/download/log archive: `/workspace/.cloud-tools/evidence/synthetic-pilot-browser-final-20261019.tar.gz`; SHA-256 `4a31e879ffd9ced4a8f534cd6d95b1b07e34143e3c1a1556c296945baac8ab28`. Session JSON and signing keys are excluded; the archive contains synthetic data only.
- Verified source-only archive: `/workspace/.cloud-tools/evidence/faydamed-pilot-candidate-31f6890.tar.gz`; SHA-256 `1c260527bfb6c7c27a2abe77bb02f866ad079bcacd5cd2a5bd6e34f10ffa2199`; 1,209 files / 137 migrations, `production_ready=false`.
- Complete application source manifest: `/workspace/.cloud-tools/evidence/pilot-final-application-source-manifest.json`; SHA-256 `bf588ceca606b85d111a2d8281bfd923f9ca855a6aabed1c229b86eb012db475`.

The first integrated full API attempt rejected an obsolete appeal fixture without a case link. The fixture was corrected to use a valid synthetic case and the full rerun passed. A shared-role replay initially raced dialog/cache settlement; the corrected harness passed without changing application behavior. Failed/superseded observations are labelled separately in the archive.


API tests are request-level evidence. Browser UI steps, browser-initiated API probes, direct synthetic database mutations and database invariants are recorded separately. MFA-attested fixture sessions in role/record/pharmacy runs are not login evidence; the auth/session runners use actual password forms without seeded authenticated sessions. Expiry is controlled synthetic database/browser expiry, not sustained wall-clock inactivity.

Exact commands (activate `/workspace/.cloud-tools/env.sh` first):

```sh
cd /workspace/Fayda-Med-Tech/api
php vendor/bin/phpunit --colors=never
php vendor/bin/phpunit --colors=never tests/Feature/BillingAnalyticsBoundaryTest.php tests/Feature/AppealTemplateTest.php tests/Feature/EobAssociationTest.php
FAYDA_SYNTHETIC_DB=fayda-cloud-mysql /workspace/.cloud-tools/mysql-bin/php vendor/bin/phpunit -c phpunit.mysql.xml --colors=never tests/Feature/BillingAnalyticsBoundaryTest.php tests/Feature/EobAssociationTest.php tests/Feature/AppealTemplateTest.php
# Repeat that focused disposable-database command with FAYDA_SYNTHETIC_DB=fayda-cloud-mariadb.
cd ../admin-panel
pnpm test:session
pnpm test:recovery
pnpm test:documents
pnpm test:reports
pnpm test:pharmacy
pnpm typecheck
NEXT_PUBLIC_API_URL=http://127.0.0.1:8000 NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api pnpm build
cd ../frontend
npm run test:security
npm run build
```

Do not rerun these broad checks merely for closeout documentation. The MySQL wrapper only permits the named disposable synthetic database containers; they were stopped after validation. Current-instance startup/installation commands and fresh browser-helper invocations are recorded in the saved environment start instructions.

## Remaining decisions and limits

- Two admin advisory findings remain visible: braces 3.0.3 (high, development lint dependency chain; registry advertises latest 3.0.3 despite advisory patch 3.0.4), and Quill 2.0.3 (low, no published fix). Quill's covered rich-text sinks retain the DOMPurify allowlist and passing DOM security tests. This does not patch Quill or clear all application security risks. Composer advisory status is unknown because the current runtime blocks the Packagist security destination.
- Latest hosted GitHub checks must be verified for the exact pushed head. Prior success at 74d4c9f is historical. GitHub API access is blocked in this runtime; native Git push is a separate working capability.
- Cloud configuration saving is separate from publication and independent fresh-task restoration. The owner previously saw Publishing; final publication/restoration is not established here. Review/Save and Publish are user-controlled environment actions. Keep Only me privacy and no added secrets/runtime variables.
- Owner walkthrough, feedback disposition and pilot acceptance remain outstanding. The evidence is a concrete synthetic review package, not owner approval.
- Narrow/wide Chromium and targeted keyboard evidence does not establish full accessibility conformance, physical/mobile-device acceptance, printers/scanners, production-origin deployment, external email/provider delivery or sterile/nonsterile clinical acceptance. The full-product backlog remains separate.

## Short owner walkthrough

1. Review the synthetic scope and exclusions, source pin and exact checks below.
2. Review the private PDF screenshot/download hash, client/staff denial evidence and EOB/appeal error-retry records.
3. Review the eight-role receipt/reversal/correction, settlement and downloaded CSV journey, plus the separately labelled compounded rehearsal.
4. Review password/MFA/recovery, two-tab rotation, logout and expiry evidence.
5. Record pass/fail feedback, resolve residual advisory disposition, verify the exact hosted checks, and review the saved environment configuration for publication. A new task must independently prove restoration.

Fresh guarded fixtures and runner commands are retained in the cloud snapshot under /workspace/.cloud-tools; source runners are in admin-panel/tests/browser and fixtures in api/tests/Browser. Every replay requires a NEW isolated directory, testing/in-memory seed opt-in and the known synthetic signing-key fingerprint. Role/record runs must never reuse production keys, records or credentials.

## Finish-line status

The original [sizing audit](BUILD-COMPLETION-AUDIT-20261019.md) remains a historical baseline. Current [package/gate closeout](BUILD-PILOT-CLOSEOUT-20261019.json) records 10 of the 12 pilot evidence gates met, with dependency disposition and final hosted-check/owner acceptance not closed. This is an unweighted gate count, not a product-completion percentage. Cloud publication/restoration is separately outstanding in P10.

The full product still requires the remaining pharmacy, integration, physical/site, regulatory and production work in the audited backlog. No full-product completion estimate is reduced merely because the synthetic demonstration paths pass.

Replay from the repository root after tool activation, always with a NEW directory, using `/workspace/.cloud-tools/run-session-tabs.sh`, `run-auth-seed.sh`, `run-pilot-records.sh`, `run-shared-seed.sh`, and `run-compound-seed.sh`. Run sequentially because they switch the owned local synthetic API fixture. The main source runners and guarded fixture classes are committed; helper processes do not survive task restoration. The default empty synthetic API has been restored after this run.
