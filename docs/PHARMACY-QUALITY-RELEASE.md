# Compounding quality evidence and product release

Development plan and local implementation status, October 4, 2026. Pharmacy remains synthetic-only; no operational acceptance or release is asserted.

## Source basis and decisions still required

Michigan R 338.533 adopts specified USP compounding chapters and requires applicable 503A pharmacies to comply. R 338.535 separately governs starting/resuming sterile compounding. Site scope and the applicable operating framework need Dr. Ghadeh's confirmation; sterile scope is not inferred from the request for compounded medications.

Primary source inspected October 4: [Michigan pharmacy general rules, R 338.533 and R 338.535](https://ars.apps.lara.state.mi.us/AdminCode/DownloadAdminCodeFile?FileName=R+338.471+to+R+338.592.pdf&ReturnHTML=True).

The [USP stability reference](https://doi.usp.org/USPNF/USPNF_S203286_10101_01.html) explicitly distinguishes its explanatory content from official USP-NF text. No numeric BUD schedule or quality threshold is derived from search snippets or this explanatory article. Obtain applicable official standards and pharmacist-approved protocols before enabling operational rules.

## Required complete workflow

1. Retain versioned site/formulation quality protocols with criterion, method, source and responsible pharmacist. Protocol review and retirement must preserve prior versions and require current site access. Software completeness does not prove the protocol is adequate.
2. Bind recorded quality observations to an immutable execution, current corrected yield/custody, ingredient provenance and protocol revision. Preserve pass, fail and not-assessed outcomes with evidence; never substitute missing observations with a pass.
3. Independently review the retained results. Changed execution, corrections, ingredient recall, custody or protocol evidence invalidates freshness. Failed or missing results cannot be hidden by later success; retain supersession and reason.
4. Retain a pharmacist-proposed BUD decision with explicit preparation timestamp/timezone, packaging/container, storage conditions, supporting stability/standard references and applicable limits. Do not infer a timestamp from the current date-only execution field, generate a universal duration or extend a date automatically.
5. Create traceable finished-product custody, with reconciled quantities, disposition, quarantine and label lineage. Avoid double counting quantities already disposed through output custody. No ingredient restoration follows finished-output disposal.
6. Separately authorize product release only after accepted clinical/site controls, current quality/BUD review, reconciled custody and prescription/label checks. Revalidate at reservation and handover; recalls, expiry, changed evidence and incomplete provider/operational requirements block dispensing.
7. Verify persistence, independent review, tenant/site isolation, stale-version rejection, atomic audit rollback, recalls, failed tests, date/time boundaries, label lineage and no duplicate stock movements across supported databases. Complete synthetic browser journeys and pharmacist/device acceptance before production activation.

## Current implementation and evidence

Published packaging head: `39ff082de4af7b75dbb58467021a8c93337c5bd4`. All three hosted workflows passed: admin `37207799599`, backend `37207799604`, Linux `37207799694`. The exact source archive is verified (1,127 files / 125 migrations). Matching Linux artifact 11305935970 is verified (3,039 files). All pharmacy routes remain local/testing only; production is unchanged.

- Versioned, site-scoped quality protocols preserve criteria, methods and source references. Independent review binds the current formulation; replacement retains prior revisions.
- Batch observations bind execution, prescription, protocol, corrected ingredient consumption, output custody, addenda and pending corrections. Each criterion requires pass, fail or not-assessed plus evidence. Documentary review preserves failures; it does not resolve them.
- Current quality eligibility requires fresh independently reviewed results, all supplied criteria passed, authorized reviewers and no unresolved holds or recalls. Original failed observations remain retained after later results.
- Beyond-use proposals retain explicit pharmacist-supplied timestamps, IANA timezone/offset, supporting limits, container/storage evidence and rationale. They do not generate a universal duration or verify clinical adequacy. Independent review rechecks freshness, source hashes, preparation date and expiry. Superseded, stale and rejected records remain retained.
- API and screen boundaries preserve organization/location access, technician read-only access and contributor independence. Requests bind unchanged payloads; forms freeze the source and preserve validation input. Audit failures roll back mutations.

Prior Chrome acceptance covered independent protocol, quality and dating review, date-limit rejection, stale-source rejection and reload persistence. Earlier failed quality evidence remained unchanged. Evidence files under `audit-evidence/client-pilot-20260930` retain per-increment migration, browser and publication results. All migrations were limited to backed-up synthetic pilot or disposable test databases.

## Remaining implementation and acceptance

- Finished-container custody persistence, reconciliation with the existing aggregate output ledger, labels and final release authorization, with revalidation through reservation and handover.
- Applicable official standards, site capability, sterile/nonsterile scope, provider connections and pharmacist/device acceptance. Software tests do not establish clinical adequacy.
- Recovery UI for corrupt evidence: history and rejection APIs preserve a recovery path, but detail error screens do not yet expose rejection.

The full seven-step workflow above remains in scope. No clinical assurance, operational acceptance or medication release is claimed.

## Finished-output packaging — published synthetic workflow

The packaging workflow records identifiable containers against the latest independently reviewed quality and beyond-use evidence. Fixed decimal quantities must reconcile container contents plus explicit unpackaged remainder to current held output; previously disposed output cannot be packaged again. Unit conversion, duplicate identifiers, missing quantities, excess precision and absent evidence are rejected.

Migration 044 retains proposals and organization-scoped container identities. Identifiers stay bound to the original preparation even after rejection. Exact retries return the original proposal; replacements preserve history. Independent review rechecks source freshness, active site assignments, contributor independence, evidence integrity and identifier ownership. Audit failure rolls back the transaction. Packaging creates no stock, release authority or dispensing permission.

Five protected API routes provide prerequisites, paginated history, curated detail, creation and review/rejection. Internal source snapshots and request hashes are not returned. The batch-detail screen retains form input on validation failure, freezes source evidence and proposal ancestry, and offers review only to independent pharmacists. Server checks remain authoritative.

Validation as of October 4:
- Focused packaging/context/API checks: 7 tests / 112 assertions.
- Route inventory: 150 protected pharmacy routes / 393 total declarations.
- TypeScript and optimized admin build passed (120 pages).
- Synthetic pilot migration ran after backup; all 124 prior data tables were unchanged.
- Chrome: excess quantity rejected without losing input; 4 packaged + 2 unpackaged = 6 held saved; self-review unavailable; independent review retained and visible after reload.
- Database read-back: all 60 prior pharmacy data tables except appended compounding events unchanged, including execution and inventory.
- Full direct PHPUnit regression: 382 tests / 7,585 assertions, clean. The earlier Artisan wrapper reported optional missing local .env warnings; the CI-equivalent command passed.

Packaging is published and all hosted checks passed; matching Linux artifact verification passed (3,039 files). Finished-container custody movements, labels and final release integration remain unfinished. Production is unchanged, and operational acceptance and provider dependencies remain outstanding.

## Container custody — published synthetic baseline

`PharmacyPackagingCustodyAnchor` verifies intact historical packaging, its independent review audit, quantity projection and original identifier lineage. Initial custody can be documented after expiry or ingredient recall. Historical evidence never grants current clinical eligibility: `PharmacyReviewedPackagingContext` separately retains all fresh quality/dating checks. No pending-hold exception is used.

`PharmacyContainerCustody` reconciles retained, disposed and unaccounted quantities independently for every container and the unpackaged remainder. Cross-container offsets cannot hide a shortage. It preserves prior disposal, exact units and unresolved losses, with no ingredient movement or release authority. Numerically equal decimal formats compare equally without rewriting the original execution.

Migration 045 and `PharmacyContainerCustodyLedger` retain container evidence linked one-to-one to the existing output disposition. Both records and the audit commit together; the output ledger owns independent review and the single aggregate movement. Applied histories replay container findings and compare aggregate balances, source versions, identities and audit evidence. Audit failure rolls back both records or the decision. Changed retries and source versions fail. Unaccounted findings remain retained but cannot be applied; rejection preserves stale or damaged evidence.

Five protected endpoints expose curated context, paginated history, detail, retention and independent decisions. Technicians have read-only access to retained records. Internal snapshots and request hashes stay internal. The aggregate-only decision endpoint requires linked proposals to use container review; its organization/site checks run before revealing that linkage. The aggregate screen hides its decision form for linked records and hides new aggregate-only proposals once container custody is established. Typed container API bindings and dedicated container history, entry, detail and independent-review screens are implemented. Entry requires explicit per-container and unpackaged quantities/evidence; source changes disable a stale form and validation errors preserve input. Incomplete accounting exposes rejection but not application. Chrome acceptance passed.

Validation: initial expired/recalled custody succeeds while fresh clinical eligibility stays blocked and ingredient stock stays unchanged. The focused custody/API suite passed 13 tests / 214 assertions before the final revoked-location assertion; the final HTTP check then passed 1 test / 40 assertions. TypeScript passed. Route inventory is 155 protected pharmacy / 398 total declarations. Earlier broader regression (before these latest API/historical-anchor changes) passed 392 tests / 7,734 assertions; it does not prove the current full worktree.

Published as e45a776; all three hosted workflows and matching source/Linux archives are verified. Still required: container-aware quantity/repackaging reconciliation, labels, release integration and operational disposal controls. Migration 045 ran in disposable tests and the backed-up synthetic client pilot. All 126 pre-existing pilot data tables were unchanged and the new table started empty. Production is unchanged.

Container screens pass TypeScript. Full current backend regression passed 395 tests / 7,800 assertions; the optimized admin build passed all 120 pages. Chrome rejected a balanced aggregate that hid per-container discrepancies, retained input, saved corrected findings, denied author self-review, applied independent review and persisted after reload. The next form showed the reconciled container/unpackaged balances. Database read-back preserved 60 prior pharmacy tables, original execution bytes and earlier dispositions; only the execution version/status timestamp and linked disposition/audit history changed. Held output moved from 6 to 5 tablets, cumulative disposal from 2 to 3, without ingredient movement. Evidence is synthetic only.

## Container-aware quantity corrections — local implementation

`PharmacyContainerQuantityCorrection` retains a measured recount for every existing container and the unpackaged remainder. Each line preserves the previous quantity, observed quantity, direction, amount and supporting reason/measurement/original-source evidence. Identifiers cannot be added, dropped, duplicated or changed through a recount. Current quantities must reconcile before correction; corrected held output plus all prior disposal must equal the explicitly supplied corrected yield. Original recorded yield and disposal stay retained; ingredients do not move and release remains disabled.

A measured correction may change individual container balances while leaving total yield unchanged. That is retained as explicit per-container correction evidence, not treated as a physical transfer or silently discarded because the aggregate delta is zero. An entirely unchanged recount is rejected as a quantity correction. Decimal quantities use fixed precision; missing values, floats, extra precision, incomplete evidence and attempts to erase disposal fail.

Migration 046 links each retained container recount one-to-one to the existing yield correction proposal. The yield ledger owns organization/execution locking, actor-bound retries, pending-correction exclusion, independent review, source freshness, one execution-version increment and the audit transaction. Applied corrections and container custody replay together in execution-version order; original packaging identities, prior disposal and per-container ancestry stay retained. Zero aggregate delta remains an explicit reviewed revision. Failed audit insertion rolls back proposal/link creation or the decision. Stale or damaged proposals can be rejected without destroying their evidence.

Five protected endpoints expose curated context/history/detail and retention/review. Existing aggregate-only review refuses linked proposals after scope checks. The UI shows each before/observed amount and all supporting evidence; authors cannot self-review. Input is retained after validation errors, exact payload retries reuse their request identifier, and refreshed source changes disable stale forms. Aggregate yield history links reviewers to the container detail and suppresses aggregate-only creation once container quantities are established.

Focused regression: 17 tests / 355 assertions, covering replay, retries, zero aggregate delta, scope, technician restrictions, independent review, stale/corrupt evidence, revoked authors, atomic audit rollback and production 503 boundaries. TypeScript passes. Route inventory: 160 protected pharmacy / 403 total declarations. Full backend regression passed 403 tests / 7,947 assertions; the optimized admin build passed all 120 pages. Chrome rejected an incorrect total without losing entered evidence, retained a valid author proposal, required independent pharmacist review, and persisted the applied result after reload. A subsequent form used the corrected balances. Database read-back preserved 61 other pharmacy tables, all earlier correction/audit rows and the original execution bytes; exactly one execution-version increment retained the correction. Accounted yield changed from eight to seven synthetic tablets; four remained held and all three prior disposals remained recorded. Ingredient stock did not change. Migration 046 ran only in tests and the backed-up synthetic client pilot; all 127 existing data tables were unchanged. This work is unpublished and production remains unchanged.
