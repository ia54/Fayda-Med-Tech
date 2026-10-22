# Synthetic cloud validation — October 19, 2026

This work ran in the managed Linux cloud environment, independently of the owner's local computer. The platform reported provider `cloud`, connected/running state, and current observations. No cloud task URL was supplied to the execution tools; none is invented here.

Source: `codex/audit-foundation` at `5d18293d1bb4a3faf48b58623a69e407d4d1e1af`. Changes continue on `codex/cloud-pharmacy-acceptance`, based on that exact handoff. Draft PR #1 and the handoff/worklist/scope/quality/operations documents were reviewed. The existing checkout was preserved; no worktree, reset, merge or deployment was performed.

## Runtime and reusable setup

- PHP 8.3.35 and Composer 2.10.3 run through a cloud-local Docker image, with mbstring, dom, pdo_sqlite, pdo_mysql, curl, zip, bcmath, intl and sodium verified.
- Node 22.23.3, Corepack 0.36.0, repository-pinned pnpm 11.19.0 and Python 3.12.14 are available. Activate with `source /workspace/.cloud-tools/env.sh`.
- Dependencies were installed from unchanged lockfiles: `composer install --no-interaction --prefer-dist --no-progress --no-scripts --no-plugins`, `corepack pnpm install --frozen-lockfile`, and `npm ci --no-audit --no-fund` in their respective app directories. Installation/refresh was exercised repeatedly.
- Initial GitHub archive restrictions were resolved using exact locked GitHub revisions through the allowed codeload HTTPS endpoint. ZIP integrity and available lockfile checksums were checked; TLS verification stayed enabled. The owner subsequently saved `api.github.com` and `packagist.org` additions; native Git, PR metadata and Composer auditing work with existing platform authentication. No GitHub token or environment secret was requested or added.
- Composer validation and platform checks pass. Audit reports no vulnerability advisories and one abandoned package, `doctrine/annotations`, with no suggested replacement.
- An exported PHP image and SHA256 checksum are retained under `/workspace/.cloud-tools`, so startup can load it if Docker images do not survive a snapshot. Image export, checksum and reload were exercised. Helpers, caches and a fresh synthetic SQLite database are outside the tracked checkout. Generated Passport keys are synthetic and ignored; no production environment file was imported.
- The saved install script preserves the separate development SQLite file and existing synthetic keys. The saved start skill records tool activation, API/UI commands, readiness requests, test commands, disposable database safeguards and the instruction to reuse the isolated checkout without creating a worktree.

The draft save confirms configuration persistence, **not publication**. At preparation time the tools return `requires_publish: true`; review/save and Publish in environment settings remain user actions. Fresh-task snapshot restoration is not yet verified. Running services are not assumed to survive publication or task completion.

## Correction and dispatch acceptance

The baseline full SQLite run completed **452 tests / 8,217 assertions, with 45 failures**. The shared `beyondUseFixture` advanced the clock to noon after setup issued a one-hour synthetic token. Morning execution expired that token before the fixture's authenticated requests; expiration middleware then attempted to revoke an intentionally unpersisted test token. Three compounded handover tests failed in the first focused cloud run (**7 tests / 111 assertions, 3 failures**).

The fixture now refreshes only its synthetic actor token after moving the clock, following the existing time-travel fixture pattern. Authentication middleware and application behavior are unchanged. A deterministic regression begins at 08:00 with a token expiring at 09:00, builds the reviewed compounding evidence at noon, and requests its dating context successfully.

The existing request-level loss fixture creates fresh synthetic actors and records. It preserves a six-unit original loss after a four-unit undelivered return; two independent recovery reviews accept **2.125** and **3.875** units. Additional assertions require three distinct quarantined receipts containing **4.000**, **2.125** and **3.875** units. Original available stock remains **40**, the original fill stays cancelled, original loss/fulfillment evidence stays unchanged, no invoice is created, and remaining loss is **0.000**. Existing checks reject duplicate/stale/over-recovery, author self-review, unauthorized roles, revoked assignments, cross-tenant access and failed audit writes.

The request-level results remain **API test evidence**. The additional fresh Chromium journey below separately verifies browser interaction. The previous local pending recovery was not resumed or imported. No new clinical or real dispensing lifecycle was enabled.

## Fresh Chromium dispatch recovery

Chromium **154.0.8037.57**, driven by Playwright Core **1.56.1**, completed the two-stage journey twice using separately generated synthetic fixtures. The final manual PHP seed passed **1 test / 29 assertions**. Its explicit opt-in, testing/in-memory database guard runs before inherited migration setup; missing opt-in was separately verified to refuse execution. Existing output files cannot be overwritten. The fixture verifies the public-key fingerprint recorded when this environment generated its synthetic Passport keys before reading the matching synthetic private key.

The browser author entered 2.125, original package code, condition, segregation, nonreceipt, date and custody confirmation. The author could not see independent review. A second pharmacist applied the review and reloaded the record with 3.875 loss remaining. The author then entered 3.875; the second pharmacist applied it and reloaded with 0.000 remaining and no further recovery action.

Independent SQL readback preserved the entire original six-unit loss record, original cancelled fulfillment and original four-unit quarantine receipt. Both recovered receipts were distinct and quarantined with exact quantities 2.125 and 3.875; source on-hand remained 40 and invoices remained zero. A separate authenticated local stock read returned `status: available`, `on_hand: 40`, `reserved: 0`.

These were **seeded, pre-authenticated synthetic sessions**. Login and MFA were not exercised. Browser page traffic was restricted to the two loopback origins; an automatic Vercel analytics-script request was blocked before network access. No patient data or external provider call was involved. Chromium runs headlessly in the container; this is browser evidence, not physical pharmacy, scanner/printer or clinical acceptance.

Reproduction requires a fresh, empty directory under `/workspace/.cloud-tools` and the snapshot's synthetic key provenance. From `/workspace/Fayda-Med-Tech/api`:

```sh
mkdir -p /workspace/.cloud-tools/browser-fixture-new
# Requires fayda-cloud-php83 and the snapshot's generated synthetic keys.
docker run --rm --network none --user "$(id -u):$(id -g)" \
  -e FAYDAMED_BROWSER_FIXTURE=1 \
  -e FAYDAMED_BROWSER_FIXTURE_DIR=/workspace/.cloud-tools/browser-fixture-new \
  -v /workspace:/workspace -w /workspace/Fayda-Med-Tech/api \
  fayda-cloud-php83 php vendor/bin/phpunit -c phpunit.xml \
  tests/Browser/CloudDispatchFixtureTest.php \
  --filter test_seed_cloud_dispatch_browser_fixture --colors=never
```

Start the API with the saved synthetic startup settings, changing only `DB_DATABASE` to that directory's `dispatch.sqlite`. Stop only the API instance you started before reusing port 8000; preserve its prior database. Keep the admin on port 3000 using the local API URLs. From the repository root, after tool activation:

```sh
npm install --prefix /workspace/.cloud-tools/browser-tools --no-save playwright-core@1.56.1
FAYDAMED_PLAYWRIGHT_MODULE=/workspace/.cloud-tools/browser-tools/node_modules/playwright-core \
FAYDAMED_BROWSER_FIXTURE_DIR=/workspace/.cloud-tools/browser-fixture-new \
node admin-panel/tests/browser/dispatch-recovery.mjs
```

`result.json` and `dispatch-recovery-complete.png` are retained in the selected fixture directory. `sessions.json` and the synthetic database are mode 0600, remain outside Git and must not be attached to a PR. The runner deliberately refuses a used fixture. The original empty development API database is restored as the default service after acceptance; browser databases remain separate evidence.

## Verification results

| Command / check | Cloud result |
| --- | --- |
| `cd api && php vendor/bin/phpunit --colors=never` | **453 tests / 9,255 assertions passed**, zero failures; 2m46s |
| Focused dispatch recovery, compounded handover, sterile/hazard evidence and morning-clock fixture filter | **8 tests / 200 assertions passed** before the final three receipt assertions; the full run above includes all final assertions |
| `php tests/Security/role-access-smoke.php` | 3,104 role decisions passed |
| `php tests/Security/route-boundaries-smoke.php` | 21 platform-only and 190 protected pharmacy declarations / 433 total routes passed |
| `php tests/Security/cors-config-smoke.php` | Passed |
| Admin `test:session`, `test:recovery`, `test:documents`, `test:reports`, `test:pharmacy`, `run typecheck` | All six commands exited 0 |
| Admin `corepack pnpm build` with local API URLs | Optimized build passed all 120 pages |
| Public `npm run test:security` and `npm run build` | 4 tests passed; Vite build passed (nonblocking bundle-size warning) |
| `node --test deploy/production/tests/web_asset_package.test.mjs` | 6 tests passed |
| `python3 -m unittest discover -s deploy/production/tests -p 'test_*.py' -v` | 18 tests passed |
| Local API requests | `/api/faqs`: 200 with empty synthetic results; unauthenticated `/api/pharmacy/prescriptions`: 401 |
| Local UI requests | Admin login HTML and transformed public React entry served successfully |
| MySQL 8.0.46 and MariaDB 10.11.19 schema / upgrade / restore | Both migrated; legacy preservation/repeat migration passed; separate restore matched 141 tables and private-file archive bytes |

**MySQL 8.0.46:** `timeout --signal=TERM --kill-after=15s 8m php vendor/bin/phpunit -c phpunit.mysql.xml --colors=never` passed **324 tests / 8,252 assertions** in 2m20s. **MariaDB 10.11.19:** the same command passed **324 tests / 8,252 assertions** in 1m08s. The database containers use an internal Docker network and tmpfs data; the PHP container shares only the selected disposable database's network namespace. `GITHUB_ACTIONS=true` is supplied solely to satisfy the repository's existing disposable-CI safety guards; these results are cloud-local reproductions, not hosted Actions runs. No operational database was accessed.

### Repeating the disposable database suites in this snapshot

From `/workspace/Fayda-Med-Tech/api`, activate the retained helpers and select only a known disposable container:

```sh
source /workspace/.cloud-tools/env.sh
export PATH=/workspace/.cloud-tools/mysql-bin:$PATH
export FAYDA_SYNTHETIC_DB=fayda-cloud-mysql # or fayda-cloud-mariadb
# Start only the named synthetic service if it is stopped; its tmpfs is disposable.
timeout --signal=TERM --kill-after=15s 8m php vendor/bin/phpunit -c phpunit.mysql.xml --colors=never
```

The helper refuses other container names and supplies only the explicit testing/CI database settings. A fresh schema is rebuilt by the existing test harness; no production connection values are read. To create services in a new Docker daemon, use MySQL 8 / MariaDB 10.11 images with `--network fayda-synthetic` (create that network with `--internal`), `--tmpfs /var/lib/mysql:rw,size=1g`, database `faydamed_ci` and the CI-only password from the checked-in workflow. Wait for each image's admin `ping` before running the suite. Existing workflow commands remain authoritative for hosted CI and restore rehearsals.

## Hosted checks

Backend workflow [39227943872](https://github.com/ia54/Fayda-Med-Tech/actions/runs/39227943872) passed all four jobs for test correction commit `9a32c27`: PHP 8.3 regression, MySQL 8, MariaDB 10.11 and pinned source package. The later manual browser fixture/runner and this report are reviewed through [draft PR #2](https://github.com/ia54/Fayda-Med-Tech/pull/2); their final hosted check status must be read against the PR head. No hosted result is represented as deployment or clinical acceptance.

## Remaining boundaries and next work

- Fresh two-stage dispatch recovery browser acceptance is now recorded above. Compounded reservation/handover and shared-role browser journeys remain outstanding on this cloud candidate. HTTP/rendered/API checks alone do not establish browser, scanner/printer or pharmacist acceptance.
- Existing compounding handover rehearsal is implemented and covered; do not duplicate it based on older outstanding lists. It requires independent comparison and preserves quarantine, inventory, financial and real release boundaries. Continue from current code and source-bound results.
- Both sterile and nonsterile compounding remain confirmed product scope. Hazardous handling is separate. Dr. Ghadeh's per-site capability, procedures, clinical acceptance and real release authority remain outstanding.
- Controlled/compounded actual dispensing, provider calls, production hosting, patient information, payment collection and external messages remain excluded. No production credentials or provider accounts are required for this work.
- Publication and a new-task restore check remain separate from the development result. Tasks can finish or exhaust resources; this evidence does not promise indefinite unattended execution.

## Subsequent browser work

The continuation from owner-verified head `74d4c9f` completes the selected compounded reservation/handover and shared-role financial browser journeys. See [the source-bound browser report](CODEX-CLOUD-BROWSER-ACCEPTANCE-20261019.md) and [evidence index](evidence/CLOUD-BROWSER-20261019.json). Its source pins and focused checks are separate from the historical full-suite/hosted results above. Publication and fresh-task restoration remain independently unverified.
