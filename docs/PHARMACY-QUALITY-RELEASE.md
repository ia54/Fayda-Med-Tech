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

Published batch-quality implementation: `96cd9c3f118b76abe2656f5dc4ee0c2d7a6755c3`. Published evidence follow-up: `02d581b6effce0b96eca7bc534195a4ab1699da2`. The follow-up hosted checks are pending as of this update. All pharmacy routes remain local/testing only; production is unchanged.

- Protocol revisions are scoped to organization, assigned location and formulation. Independent review binds immutable criteria and the current reviewed formulation. Replacement retires the earlier reviewed protocol atomically; rejection and retirement preserve history.
- Batch observations bind execution, prescription, protocol, corrected ingredient consumption, output custody, addenda and pending correction evidence. Every criterion requires an explicit pass, fail or not-assessed outcome, observation and evidence reference. Unknown, duplicate or missing criteria fail validation.
- The follow-up also snapshots pending consumption corrections for every linked receipt. Changes remain detectable when a hold stays true. Older source snapshots remain retained; pending records using the earlier fingerprint require rejection/replacement rather than silent migration.
- Independent documentary review excludes observation authors, preparation authors and addendum contributors. Review rechecks authorization, integrity and current source evidence. Failed/unassessed results remain unresolved after review. Creation and decisions roll back if audit insertion fails.
- Protected APIs and batch-detail screens provide context, creation, paginated history, detail and review/rejection. Technicians read retained records; pharmacists write. Request identifiers bind unchanged payloads. Entry forms freeze source and previous-record ancestry rather than silently rebasing observations after refresh.

## Verification completed

At `96cd9c3`: local backend **367 tests / 7,363 assertions**, TypeScript, route inventory **140 pharmacy / 383 total**, optimized **120-page** admin build, and all hosted workflows passed. Hosted runs: admin `37205628327`, backend `37205628324`, Linux `37205628329`. Matching source archive contains **1,113 files / 123 migrations**; matching Linux archive verifies **3,039 files**. See `audit-evidence/client-pilot-20260930/batch-quality-publication.json` outside the development checkout for hashes and artifact identity.

Migration 041 preserved all 120 pre-existing synthetic pilot tables. Migration 042 preserved all 122 pre-existing tables after a SQLite backup. Neither migration ran in production.

Chrome acceptance used separate synthetic users: author 1 retained a failed observation; author review controls were absent; reviewer 3 retained documentary review. Failure and quarantine remained visible, and reload preserved history. All 58 prior pharmacy tables excluding the append-only compounding event log remained unchanged. Evidence: `audit-evidence/client-pilot-20260930/batch-quality-browser.json` outside this checkout.

The `02d581b` pending-correction follow-up passes **10 focused tests / 137 assertions**. The prior full-suite and browser evidence do not prove fresh hosted acceptance of that follow-up.

## Remaining implementation and acceptance

- Current-result eligibility must explicitly require fresh, independently reviewed results, all criteria passed and no unresolved holds. A reviewed status alone never grants eligibility.
- Retained pharmacist-proposed BUD decisions with explicit timestamps/timezones, storage, packaging and supporting limits; independent review and stale-evidence handling.
- Finished-product custody and traceability, reconciled output quantities and disposition, labels and separate release authorization with rechecks through handover.
- Applicable official standards, site capability, sterile/nonsterile scope, provider connections and pharmacist/device acceptance. Software tests do not establish adequacy of clinical protocols.
- Corrupt quality records remain visible in history and can be rejected through the decision API; the detail error screen does not yet expose this recovery action.

No clinical quality assurance, BUD assignment, finished stock creation, operational acceptance or medication release is claimed. All seven workflow requirements above remain within the delivery scope.

## BUD proposal structure — unpublished foundation

`PharmacyBeyondUseEvidence` checks explicit pharmacist-entered preparation and proposed BUD timestamps, IANA timezone/offset agreement, packaging/storage evidence, rationale and supplied limiting dates. It rejects date-only values, invalid calendar dates, DST gaps, duplicate limits and proposals exceeding any supplied limit. It preserves supplied evidence and calculates UTC comparison values only; it does not generate a duration, validate the clinical adequacy of limits, authenticate preparation time or authorize release. Three focused tests / 19 assertions pass, including the repeated DST hour with distinct offsets. Persistence, source binding, independent review, API and UI are still required.

The unpublished `PharmacyReviewedQualityContext` now requires the latest reviewed quality record, retained audit, currently assigned author/reviewer, independent preparation review, canonical integrity and fresh source evidence. Failed/unassessed results, pending replacements, changed sources, recalled ingredients and pending holds block this documentary prerequisite. Four focused tests / 35 assertions pass with the BUD validator. This service is not yet wired to retained BUD proposals or release.

Migration 043 and `PharmacyBeyondUseLedger` now retain immutable dating proposals tied to the current independently reviewed quality context, with explicit ancestry, actor-bound retries and atomic audit events. Creation and review require an exact preparation-date match, nonfuture preparation time and an unelapsed proposed BUD. A different assigned pharmacist reviews or rejects; preparation/addendum authors cannot review. Changed quality evidence or source integrity prevents review. Rejection remains available for stale or expired proposals. Replacements preserve all earlier records. Six focused tests / 67 assertions pass; migration 043 ran only in disposable test databases. APIs, UI, pilot migration and full/hosted regression remain outstanding.

Five protected BUD routes now provide current dating context, paginated history, retained proposal detail with elapsed status, creation and review/rejection. Internal source/prescription snapshots and request hashes are not exposed. Seven focused tests / 97 assertions pass, including technician read-only access, independent review, revoked assignment and production 503. Route inventory passes 145 pharmacy / 388 total declarations. Frontend typed bindings are connected; proposal screens and browser acceptance remain outstanding.

BUD screens are now connected on batch detail: paginated proposal history, prerequisite loading, explicit timestamp/timezone and supporting-limit entry, retained evidence display, elapsed-date messaging and independent review/rejection. Forms freeze source evidence and proposal ancestry, retain input on validation errors and bind request IDs to unchanged payloads. Author/preparation/addendum contributors do not receive review controls. TypeScript passed; full backend regression, optimized build, pilot migration and browser acceptance are pending.

Migration 043 subsequently ran on the backed-up synthetic pilot only. All 123 prior tables retained identical rows, and the new proposal table was empty. Production remained unchanged.

Optimized BUD admin build passed all 120 pages. Chrome verified that the dating form refuses an older quality snapshot after the source-context expansion. Successful proposal creation and independent dating review still require browser acceptance. Published 02d581b Linux artifact 11303769918 was independently verified (3039 files); it does not include this unpublished BUD work.

Full local backend regression completed: 375 tests / 7,473 assertions. The optimized build passed. Browser acceptance is in progress: synthetic quality record 2 is a separate pending passing fixture for the dating journey; prior reviewed failed record 1 remains retained. No BUD proposal has been saved yet.

BUD Chrome acceptance completed October 4: separate users retained passing synthetic quality replacement 2 while preserving failed record 1, rejected a proposed date beyond its supplied limit without losing evidence, corrected and saved dating proposal 1, denied author self-review, independently reviewed it as user 3, and verified reviewed history after reload. Database comparison preserved 58 prior pharmacy tables excluding quality records and appended compounding events; original failed quality record 1 was byte-for-byte unchanged. Dating review did not change stock or execution. Publication and hosted BUD checks remain outstanding.

## Finished-output packaging — local acceptance complete, unpublished

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

Publication and hosted packaging checks remain pending. Finished-container custody movements, labels and final release integration remain unfinished. Production is unchanged, and operational acceptance and provider dependencies remain outstanding.
