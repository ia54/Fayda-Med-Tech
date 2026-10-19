# Cloud browser acceptance — October 19, 2026

Continuation of draft PR #2 on `codex/cloud-pharmacy-acceptance`, based on owner-verified head `74d4c9f`. This is synthetic development evidence, not production or clinical acceptance. Both sterile and nonsterile compounding remain product scope; per-site clinical, device and real release decisions remain outstanding.

## Defects repaired

- A rejected compounded handover invalidated history and unmounted the outcome form, clearing the entered evidence and resetting the selected outcome. Cached forms now remain mounted during refresh/failure while submission stays disabled until history is current. Chromium verifies wrong-code rejection, retained fields and successful corrected submission. Three rendered regressions cover refreshing, failed and initial history.
- The provider payments page offered inactive payment/receipt buttons, invented aging totals and omitted correction history. It now reuses the existing role-aware ledger: provider staff can read recorded receipts and reversals, while authorized billing roles retain recording/correction controls. Browser checks verify both sides; no new financial workflow or payment provider was introduced.
- The manual compounded seed uses a live UTC clock so dating evidence and browser authentication agree. The normal request fixture retains its deterministic noon clock. Prepared time is clamped to the same day's midnight for first-hour runs; an explicit 00:15 regression verifies the API context remains readable. Browser seeds require explicit opt-in, testing, in-memory SQLite, a new directory under `/workspace/.cloud-tools`, and the known synthetic Passport public-key fingerprint.

## Actual browser coverage

Chromium 154.0.8037.57, Playwright Core 1.56.1 and Node 22 execute against only the loopback admin/API. All external browser requests are aborted, including attempted Vercel analytics. Each final journey uses a new isolated SQLite database and fresh synthetic role sessions. Seeded MFA-attested sessions do **not** establish login, MFA challenge, password recovery or session-expiry browser acceptance.

Compounded output: create/cancel a reservation; change synthetic patient source evidence, block stale handover and independently cancel; restore source, reject the wrong container code without losing entered evidence, then independently retain handover and reload it; deny self handover, technician mutation and cross-tenant evidence. Exactly two cancellations and one handover remain. Hashes prove ingredient/stock/fill/prescription/execution/label/invoice/payment tables are unchanged. The retained outcome is a rehearsal and `release_enabled=false`; no usable stock, supply, invoice, dispensing or clinical authorization is created.

Shared roles: provider creates a $100.25 draft and sends it for billing review; biller reviews, records $40.10 previously received, gets duplicate-reference 409 and overpayment 422, appends a -$40.10 reversal and records a corrected $100.25 receipt. The original row is byte-for-byte unchanged; net paid is $100.25 and the client sees zero balance on only their own invoice. Provider sees real correction history without writer controls. Client API probes verify staff notes/metadata are hidden. Attorney sees their assigned case, not another same-organization case; generic billing reads remain forbidden. Attorney plans a $1,000 settlement with fees/costs/deductions and net $725; firm administrator completes and appends a $1,100 correction with net $805, preserving the original. Firm report totals use the replacement, and a real Chromium download is saved to disk and checked against those totals. Firm/admin ledger scope and pharmacy-role financial denials are checked; pharmacy tables and hidden invoices remain unchanged. Browser-initiated API probes are listed separately from UI actions in the retained result.

## Focused validation

- `cd api && php vendor/bin/phpunit --colors=never --filter 'beyond_use|compound_handover_rehearsal|compound_rehearsal' tests/Feature/PharmacyWorkflowTest.php`: **7 tests / 147 assertions passed**.
- `cd admin-panel && corepack pnpm test:pharmacy`: **13 tests passed**, including three new handover form regressions.
- `cd admin-panel && corepack pnpm run typecheck`: **passed**.
- Explicit manual compounded seed filter: **2 tests / 33 assertions passed**; shared-role seed filter: **1 test / 16 assertions passed**.

These changes do not alter backend production code, schema, dependency declarations or lockfiles. Prior head `74d4c9f` had six successful hosted checks and previously recorded full SQLite/MySQL/MariaDB/build results. Those historical results are not transferred to a later head. Broad suites were not rerun locally for these UI/fixture changes; current hosted checks must be read against the pushed head.

## Reproduction and retained evidence

Activate `/workspace/.cloud-tools/env.sh` in each shell. The PHP 8.3/Composer 2 image, Node 22, locked dependencies, Chromium and external Playwright tools remain prepared. Use a NEW directory for every attempt; never reuse saved session tokens or point a seed at an operational database.

From the repository root, the retained manual cloud helpers are:

```sh
/workspace/.cloud-tools/run-compound-seed.sh /workspace/.cloud-tools/compound-browser-NEW
/workspace/.cloud-tools/run-shared-seed.sh /workspace/.cloud-tools/shared-browser-NEW
```

They run only the explicitly filtered fixture tests in `api/tests/Browser`, with `--network none`, then point the isolated local testing API at that exported SQLite file. Run journeys sequentially because both use port 8000. The final API is returned to the separate empty synthetic development database. The checked-in runners are `admin-panel/tests/browser/compound-handover.mjs` and `shared-role.mjs`; they verify the seed's application source hashes before opening the browser, record the full Git head/status, and retain sanitized responses, screenshots and SQL invariants. Session/key files are excluded from the retained review archive and from Git.

The [committed evidence index](evidence/CLOUD-BROWSER-20261019.json) binds the shared-role run to clean commit `69c42907f7c197dd70e66361e686c5ab8764790b` and the compounded run to clean commit `480f79e08bf6f54bd4deace830733ac082ae147f`. Both manifests contain 723 source files and agree on all 718 application source files. The only intervening change was the bounded SQLite wait in the harness. The archive `/workspace/.cloud-tools/evidence/cloud-browser-acceptance-20261019.tar.gz` is 3,355,528 bytes, SHA-256 `53357fb320b7a1b6cd3338bd8f9502e91f978512c8943a4e663d92bd3a8000de`. It retains the final databases, full source manifests/results, screenshots, seed/browser logs and downloaded CSV; the index records each file hash. Neither final browser run received an HTTP 5xx response. Earlier debug attempts remain outside Git; selector/harness failures are not represented as product defects or acceptance passes.

## Remaining acceptance and environment status

The selected compounded and shared-role journeys above now have browser/database evidence. Remaining acceptance includes real login/MFA/expiry and recovery in a browser, mobile/accessibility/device behavior, physical printer/scanner/container workflow, per-site sterile/nonsterile clinical and operational acceptance, and any policy/provider-dependent release or production workflow. This run does not establish comprehensive acceptance of every implemented pharmacy workflow.

The owner clicked Publish after saving the `74d4c9f` environment and last reported **Publishing**. The configuration read subsequently returned a newer base version without a draft ID, but this live runtime still reports the original source version. Neither observation establishes successful filesystem snapshot publication or independent fresh-task restoration. Configuration persistence, publication and fresh-task restoration remain separate evidence. No production access, credentials, provider calls, messages, insurer submissions or funds movement occurred.

Native Git push updates the same PR branch successfully. This runtime currently rejects `https://api.github.com` with Forbidden despite the saved configuration containing that custom domain. Therefore later-head hosted status and a PR-description update are not verified here. The prepared REST update is retained at `/workspace/.cloud-tools/pr2-final-update.json`; no credentials were requested or extracted. Review the latest pushed head and checks before merging. The refreshed environment draft is prepared separately after this report is committed; saving it requires user Review/Save and Publish, and does not establish publication.
