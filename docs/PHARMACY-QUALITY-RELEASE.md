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

## Implemented locally

`PharmacyQualityEvidence` validates a supplied protocol/result pair: each declared requirement has one explicit reported outcome, observation and evidence reference. Unknown/duplicate/missing results and unexpected result fields are rejected. Results are normalized to protocol order; failures and unassessed criteria remain visible. Even all reported passes retain human review, quarantine and no clinical-quality/release assertion.

Focused verification: four tests / 21 assertions. This pure validator is not connected to a database, HTTP endpoint or UI yet. It does not authenticate protocols, validate laboratory evidence, choose clinical thresholds, assign BUD, or create finished stock. Persistence, scoped APIs, independent review and UI are the next implementation work; all seven workflow requirements above remain part of the delivery scope.

## Retained protocol governance — local follow-up

Migration 041 and `PharmacyQualityProtocolLedger` add organization/location/formulation-scoped immutable protocol revisions, draft/reviewed/rejected/retired states and append-only decision events. Creation binds the current reviewed formulation and explicit previous revision; retries are actor/payload-bound. A different active assigned pharmacist reviews or rejects. Review rechecks the author assignment, formulation and record integrity. A reviewed replacement retires the earlier active protocol in the same transaction; audit failure restores both states. Retirement never deletes the protocol or promotes another revision. Rejection remains possible when the formulation has become stale.

Focused local checks now pass six tests / 49 assertions: evidence structure, independent review, revision lifecycle, unchanged records, author revocation, wrong role/organization, changed content, retired formulation, production denial and audit rollback including replacement retirement. The migration has run only inside isolated test databases. No preview or production migration, HTTP route, UI, batch-result persistence, BUD or release capability has been added yet. Protocol records remain staff-supplied documentary governance, not validated clinical standards.

## Protected protocol API — local follow-up

Five local/testing-only routes now support paginated listing, detail, event history, creation and decisions. Reads require an active assigned pharmacist or technician; writes use the pharmacist-only ledger. The independent-review filter excludes the author and is unavailable to technicians. Organization/location scope applies before detail, history and filtering. Responses omit internal request/evidence hashes, show formulation freshness and explicitly retain no operational acceptance or release authority. Decision responses do not force a damaged protocol body to be re-read, so a stale/corrupt draft can still be rejected with its history retained.

Seven focused tests / 88 assertions pass, including HTTP creation/history, self-review denial, technician read-only access, revoked-site and cross-organization denial, queue removal and production 503. Declaration inventory covers 135 pharmacy routes / 378 total routes. Frontend typed query/mutation bindings are added; protocol screens and batch-result recording remain unfinished. No preview or production migration occurred.

## Protocol interface — local follow-up

Formulation detail now includes an assigned-location protocol panel with paginated revision list and decision history, explicit criterion/method entry, independent review/rejection, retirement and formulation-freshness guidance. Technician controls remain read-only. Creation freezes the previous revision at form-open and binds retry identifiers to unchanged payloads. Controls disable during refresh; current-record forms remain mounted. TypeScript passed; optimized build and synthetic two-account browser acceptance remain pending. No batch-quality result or product-release UI is claimed.

Migration 041 was subsequently applied only to the backed-up synthetic pilot on October 4; all 120 prior tables retained identical rows. Production is unchanged.

October 4 browser acceptance completed: an invalid identifier returned validation without losing entered evidence; corrected submission retained one draft, excluded author self-review, and a separate pharmacist reviewed it. Reload preserved the reviewed state. Database comparison found all 57 prior pharmacy tables unchanged, with exactly one creation and one independent-review event in the new ledger. The optimized admin build passed (120 pages). Full regression passed 362 tests / 7,260 assertions; publication, hosted checks, batch results and product release remain outstanding.

## Batch-quality source context — local work in progress

`PharmacyBatchQualityContext` resolves a current reviewed protocol for the execution's exact organization, location and formulation, checks canonical protocol/formulation hashes and retained independent review, and captures prescription, execution, addenda, output custody, consumed ingredient lineage and pending holds. Ingredient context reuses corrected-consumption replay; output context reuses corrected-yield/custody replay. Unknown/missing ingredient lineage, retired protocols and cross-location protocols fail. A recalled ingredient remains explicitly visible in the evidence so adverse observations can still be recorded; this context never grants release.

A focused synthetic test passes 21 assertions, covering stable repeated reads, preserved original execution, changed recall evidence, exact protocol/location binding and retirement rejection. This is a read-only foundation: result persistence, independent result review, scoped result APIs/UI and current-result eligibility remain to be implemented. It must not be described as completed batch quality assurance.

## Retained batch results and protected API — unpublished October 4 increment

Migration 042 and the batch-quality ledger retain immutable observations, source snapshots, hashes, revision ancestry and independent documentary review. Failed and unassessed outcomes remain visible after review; reviewed does not mean clinical quality approval. Source changes prevent review and require rejection/replacement. Creation and decisions roll back if their audit event cannot persist. No inventory movement, BUD or release is performed.

Five protected routes provide current protocol context, paginated execution history, retained result detail, creation and review/rejection. Responses omit raw prescription/source snapshots and internal request hashes. Assigned technicians may read retained records; context generation and writes require pharmacists. Revoked location access denies history and detail. Nine focused tests / 124 assertions passed. Full regression is pending. Migration 042 has run only in disposable test databases; pilot migration, UI, browser acceptance and publication remain outstanding.

Validation completed for this unpublished increment: full local backend suite passed 367 tests / 7,363 assertions; frontend API bindings passed TypeScript; route inventory passed 140 protected pharmacy routes / 383 total. No optimized frontend build or browser acceptance is claimed for batch-result screens, which remain unfinished.

Batch-quality entry, paginated history and independent decision screens are now connected on batch detail. Entry freezes source and revision ancestry at form-open, requires an explicit outcome for each criterion, and binds retries to unchanged payloads. Author/preparation/addenda contributors do not receive review controls. TypeScript passed before the final contributor-control refinement; optimized build and browser acceptance are pending. Migration 042 was applied to the backed-up synthetic pilot with all 122 existing tables unchanged and zero initial result records. Production is unchanged.

October 4 batch-quality browser acceptance completed: author 1 retained a synthetic failed observation; author review controls were absent; reviewer 3 independently retained documentary review. Failure warning and quarantined output remained, and reload preserved reviewed history. All 58 prior pharmacy tables excluding the append-only compounding event log remained unchanged. Optimized admin build passed all 120 pages. Publication and hosted verification remain outstanding; no clinical quality, BUD or release capability is claimed.

Unpublished follow-up: quality source snapshots now retain pending consumption-correction rows for every linked ingredient receipt, including corrections from other executions sharing that receipt. A hold flag alone cannot detect changes while the hold remains true. The focused suite passes 10 tests / 137 assertions, including changed pending evidence with an unchanged hold. Existing snapshots remain retained; their source fingerprint becomes stale under this expanded context and a pending record requires rejection/replacement. No stock or review state was migrated.
