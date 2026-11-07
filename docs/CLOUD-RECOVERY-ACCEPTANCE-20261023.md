# Cloud recovery and ordinary pharmacy acceptance — October 23, 2026

## Recovery and packaging

This edit/setup runtime fetched `ia54/Fayda-Med-Tech` through the inherited HTTPS proxy. At 17:58:49 UTC, GitHub and its API returned HTTP 200 and the candidate resolved to `22bd78c800cb6487a51389b43829488ed859c982`. Running/connected status did not establish recovery of the separate development chat. No supported workspace-preserving restart/repair action was exposed; network-policy readiness was reported as unknown. No proxy bypass, network-policy change, credential inspection or production access was used.

The legacy source packager selected the managed-pilot `.npmrc` and rejected it as credential-sensitive. Commit `4873a97ad4af6963f45b33923523056aa846e579` excludes exactly `deploy/production/godaddy-node/.npmrc`; the managed Node packager remains unchanged. Regression coverage proves the excluded bytes are absent, rejects reinsertion through a forged manifest/archive, and still rejects `admin-panel/.npmrc`. Existing arbitrary private-key rejection tests pass.

- Python package tests: **22 passed** (`python3 -m unittest discover -s deploy/production/tests -p 'test_*.py' -v`).
- Node package tests: **16 passed** (`node --test deploy/production/tests/*.test.mjs`).
- Exact source build and independent verification at `9e2468b1985c56a9e5a259326db82b35ac357317`: **1,259 files / 137 migrations**, SHA-256 `bbd37e764fb2a7c21863f841eae7255ad727864b9889f8823254b8e62f2d4ef4`. `source_only=true`, `production_ready=false`.
- Hosted packaging, PHP, MySQL, MariaDB and asset-candidate checks passed at packaging commit `4873a97`. These results are source-bound; later-head checks must be inspected separately.

Archive commands use a full immutable SHA and a new output outside the checkout:

```sh
python3 deploy/production/release_package.py build --commit <full-sha> --output <new-outside-checkout.tar.gz>
python3 deploy/production/release_package.py verify --archive <archive> --sha256 <recorded-digest> --commit <same-full-sha>
```

## Dependency and regression validation

Activated `/workspace/.cloud-tools/env.sh`. Node 22.23.3, project-scoped pnpm 11.19.0 and PHP 8.3.35 were used. Composer install with scripts/plugins disabled, pnpm frozen-lockfile install and npm ci succeeded through supported inherited networking. Composer platform requirements passed. No manifests, locks or application code changed.

- The two extended pharmacy regressions passed: **2 tests / 86 assertions**.
- Full `api/tests/Feature/PharmacyWorkflowTest.php`: **269 tests / 7,520 assertions**.
- Admin `corepack pnpm test:pharmacy`: **13 passed**; `corepack pnpm run typecheck`: passed.

```sh
cd /workspace/Fayda-Med-Tech/api
php vendor/bin/phpunit --colors=never --filter 'test_full_workflow_records_stock_once_and_preserves_billing_link|test_independent_patient_chart_is_private_idempotent_and_invalidates_fill_review' tests/Feature/PharmacyWorkflowTest.php
php vendor/bin/phpunit --colors=never tests/Feature/PharmacyWorkflowTest.php
```

## Fresh ordinary pharmacy browser evidence

The final browser run used clean commit **`9e2468b1985c56a9e5a259326db82b35ac357317`**, Chromium 154.0.8037.57 and the existing guarded cloud-browser helper. The [evidence index](evidence/CLOUD-ORDINARY-20261023.json) records 732 source hashes, sanitized results, screenshot/database hashes and the retained evidence archive. The archive excludes session exports and private keys. Its local path is `/workspace/.cloud-tools/evidence/recovery-20261023/ordinary-browser-acceptance.tar.gz`, 624,452 bytes, SHA-256 `34c2905657081150799d5e2846e27cb60d9926a496455f59927930577006711e`.

Actual UI actions: record and reload a pharmacist clinical review from an initially unknown chart; verify the stock product; reserve and approve each ordinary fill; retain label proof and simulated print evidence; manually compare synthetic codes at preparation and handover; record pickup and, separately, dispatch followed by confirmed delivery; switch to the explicitly site-assigned biller; retain coverage, billing drafts, synthetic submission records and two $123.45 receipts. Every mutation is followed by reopening saved state. The submission records are explicitly marked synthetic and send no payer request. No actual money is collected.

Separate browser-initiated API probes rejected duplicate completed handovers with 422, cross-organization and unassigned-site reads with 404, biller chart reads with 403 and technician clinical edits with 403. Database assertions retain exactly two dispense events, two invoices, two receipts and the expected audit actions. Stock falls from 50 to 30; reserved stock is zero. Prescription, ingredient and compounded-execution snapshots are unchanged. No observed API response was 5xx. External browser requests were aborted; the attempted analytics host was blocked.

This run uses seeded MFA-attested sessions; it does not verify login/MFA, physical print/scanner operation, clinical suitability or operational release. Label proofs and simulated print records were retained; physical output and the separate hosted source-document browser-to-disk download remain unverified. No pilot dataset or deployment was changed. The synthetic test API container was stopped after the run; the separate default synthetic development database was preserved.

Earlier attempts corrected runner selectors, mutation-refresh timing, missing fixture assignment, required synthetic fields and probe origins. They are not counted as acceptance passes or application defects. No reproducible application defect requiring a production-code change was established in these journeys.

## Reproduction and next work

The manual seed is `api/tests/Browser/CloudOrdinaryFixtureTest.php`, intentionally outside default suites. Use a new directory below `/workspace/.cloud-tools`, explicit testing/in-memory SQLite, `FAYDAMED_BROWSER_FIXTURE=1` and the existing known synthetic key fingerprint. Run only `test_seed_cloud_ordinary_browser_fixture` in a PHP container with `--network none`. The seed exports `ordinary.sqlite` and fresh role sessions. Never reuse historical tokens or point it at another database.

The retained helper `/workspace/.cloud-tools/run-ordinary-recovery.sh <new-fixture-directory>` performs the seed, starts a uniquely named loopback synthetic API using that database and invokes `admin-panel/tests/browser/ordinary-pharmacy.mjs`. Start the admin UI separately using the saved local API startup instructions. Ensure port 8000 is free first; stop only a test container you created. The helper does not clean up its API automatically, so stop that named container after preserving evidence. Runners require `FAYDAMED_BROWSER_FIXTURE_DIR` and the retained Playwright Core module at `/workspace/.cloud-tools/browser-tools/node_modules/playwright-core`.

Continue from draft PR #5 on `codex/cloud-recovery-20261023` against `codex/pilot-gateway`; fetch before new work and preserve concurrent changes. Remaining work includes the hosted source-document browser download, owner walkthrough review, device/accessibility coverage, provider decisions and site-specific clinical/operational acceptance. Controlled and compounded dispensing remain disabled. Keep environment-draft review/publication separate from runtime execution and production release. No merge, deployment or environment publication occurred in this task.
