# Current release status — October 4, 2026

This is the current launch checklist. `OUTSTANDING.md` retains the historical evidence and older increments; older counts and statements there are not current acceptance claims.

## September 30 client pilot and dependency work

The owner authorized a narrower synthetic shared-platform client pilot while the full multi-location pharmacy mission continues. See [CLIENT-PILOT.md](CLIENT-PILOT.md) and [PROVIDER-OPERATIONS-REGISTER.md](PROVIDER-OPERATIONS-REGISTER.md). Dr. Ghadeh is the identified pharmacy/co-owner and proposed operational approver; her account inventory and operating acceptance remain pending. No provider contract, account or clinical approval is inferred from her identification.

An isolated synthetic pilot database preserves the original pharmacy fixture. Fresh real-HTTP checks cover six-role password/MFA, case retrieval and logout revocation; patient/attorney unassigned-case denial is verified. Fresh Chrome evidence covers provider draft/submission, biller review/receipt/reversal/duplicate prevention and patient paid-balance/correction visibility. Attorney and firm-admin settlement/correction/report journeys are verified in Chrome; private document HTTP retrieval and browser fallback are verified. Super Admin password/MFA and dashboard totals match the pilot database. EOB manual entry/review/filter/rejection/error-retry and saved appeal generation/view/search/status filtering are verified in Chrome. Missing organization dates exposed a display defect; its corrected list display now passes rebuilt Chrome acceptance. Workspace evidence: `audit-evidence/client-pilot-20260930/acceptance.md`.

## Latest verified baseline and active candidate

Published **21e04c568c14b6372077934a9693dec8824d6b6b** adds current-label checks across every nonempty finished container and complete audit hashes for retained output/comparison evidence. Local full regression passed **427 tests / 8,525 assertions**, TypeScript and the optimized **120-page** frontend build passed. Chrome verified the documentary result after reload, with unpackaged units explicitly excluded and quarantined. Database comparison confirmed all prior pharmacy rows were preserved.

Exact source archive verified: **1,174 files / 133 migrations**, SHA256 `d3637a457b36c0887d8fd2e5a4c6931d1b3fe214d5855d2f6978508c3eea1a28`. Hosted admin **37218257136** passed; backend **37218257126** and Linux **37218257125** were running at the latest observation. Matching Linux artifact verification is pending.

Previous **9479057cb02acd47c5f4b0ddc6c377e53c37b3b7** passed all three hosted checks and matching Linux archive verification: **3,039 files**, SHA256 `33aa7b495e7badbb67e9bf876f86541926c6f2153798b32628f74acbca290a1a`. It includes internal barcodes and retained manual/scanner-reported comparisons, but excludes the aggregate-check increment. No runtime extraction or deployment occurred.

A subsequent focused correction requires portal-linked label patients to remain active client-role accounts, consistent with prescription-transfer checks. Changed roles/status block current label context, document viewing and new print evidence; original retained history remains unchanged. Its focused regression passed **1 test / 86 assertions**; this is not a full-suite claim for that correction.

Earlier release evidence remains in the per-increment audit records. The full platform and pharmacy mission remains open; readiness gates are not a percentage of code completion.

## Release decision

Not deployed. The public website and existing production application are separate from the tested local redevelopment. Pharmacy endpoints remain local/testing only; controlled and compounded final dispensing remains blocked. The requested launch remains outstanding; every applicable requirement below still needs its own acceptance evidence.

## Launch-critical work

| Requirement | Current evidence / remaining action |
| --- | --- |
| Authorized host access | Owner is having the administrator restore SSH to 144.126.132.98. September 27, 28 and 29 credential-free connections reached OpenSSH/key exchange and stopped at strict host-key verification; this does not establish account authentication or a regional block. Independently confirm host identity and current access through an authenticated provider terminal. No other host, domain, folder or port is authorized. |
| Production inventory | Reconfirm active API/admin/public roots, process owners, PHP/Node/database versions and existing admin port 3005 after access is restored. Do not infer these from the local runtime. |
| Recoverable production backup | Last panel inspection reported completed API/admin/public backups, but contents, actual admin tree, database/private files/keys and isolated restore remain unverified. CI synthetic recovery does not establish production recovery. |
| Migration and release rehearsal | Branch CI covers SQLite, MySQL 8 and MariaDB 10.11 plus synthetic upgrades/restore. Pinned source packaging records per-file and migration hashes and verifies archive integrity without extraction. The exact production schema/data and host permissions still require scoped read-only inspection and rehearsal. Preserve all existing keys and records. |
| Production builds and cutover | The Linux web-build workflow builds with the three real origins and packages verifiable artifacts; local browser acceptance uses localhost. Match the selected artifact to the final release commit, verify its trusted digest, and establish host runtime compatibility. Run production configuration checks and controlled rollback procedure in LAUNCH.md. No DNS changes or new ports. |
| Identity and account recovery | Synthetic MFA/session/reset checks exist. Owner-controlled production sign-in and actual reset/invitation mail delivery or accepted operator recovery remain to be demonstrated. |
| Shared platform acceptance | Synthetic financial, document, case, legal/report and role workflows have recorded acceptance. Verify the final release on the actual HTTPS origins and retained production configuration before reopening writes. Payments remain record-only. |
| Optional external services | Real signing delivery/archive, OCR and AI processing are not verified. Keep unconfigured functionality unavailable. GLM is a candidate only; do not send patient data without approved account/hosting/processing terms. |
| Real pharmacy operating scope | Need responsible pharmacist acceptance, actual location/staff credentials, supported controlled schedules, sterile/nonsterile/hazardous scope, catalogue/unit conventions and operating procedures. Engineering fixtures do not verify these facts. |
| Remaining pharmacy software | Controlled/compounded amendments, controlled/compounded and external prescription transfers, operational acceptance of internal transfers, post-supply correction/replacement operating acceptance, allowance-closure corrections where later supplies or other conflicts exist, and schedule/payer-specific partial-fill rules; clinical reference integration; validated labels/barcode/handover; schedule-specific controlled handling and reporting; operational stock transfer authorization/transport/actual-loss-or-excess resolution, returns/destruction; recall outreach procedures, verified external scope changes/termination, returns/disposition and closure; manufacturing deviations/quality/BUD/finished-product release; actual PIP payer transport and reconciliation. See PHARMACY-SYSTEM.md for boundaries. |
| Pharmacy integrations and acceptance | Verify approved clinical/prescribing/reporting/payer connections as applicable, labelled test outputs, real processing contracts and professional acceptance. Do not enable controlled/compounded dispensing on the strength of documentary preview screens. |
| Dependency security and maintenance | Previously recorded full lockfile audits: no known API/public advisories; admin retains one low Quill HTML-export advisory with existing tested sanitization and no upstream patch listed. Swagger retains abandoned doctrine/annotations. Unused payment and legacy AI SDKs are removed; no advisory is suppressed. See DEPENDENCIES.md. |
| Operational reliability | Demonstrate production backup restore, worker/scheduler recovery, private-file access controls, monitoring, incident procedures and record-retention acceptance. |

## Implemented pharmacy foundation

Latest fully verified published candidate: `914ae750db9f5c56e3fa3768d59fa1ebd697a601`. Admin, web and backend CI passed; PHP 300 tests / 5,753 assertions; MySQL8 and MariaDB10.11 each passed 217 workflow tests and a 108-table synthetic restore with byte-identical private files. Matching source and Linux web archives are verified in `release-artifacts/candidate-914ae750db9f5c56e3fa3768d59fa1ebd697a601.json`. Production-ready and deployed remain false.

This candidate includes internal ordinary prescription transfers, retained manual source transcriptions and the pure extraction response contract. Chrome accepted synthetic source transcription/correction/reload and responsive layout checks. No OCR or model provider is connected.

The previously published extraction workflow retains extraction attempts, failed/uncertain outcomes, source-linked drafts and immutable pharmacist field decisions or dismissal. Three new scoped endpoints and the review interface are implemented. These retain proposals without amending prescriptions, creating fills or dispatching model requests. Local full regression passed 303 tests / 5,811 assertions; final focused checks passed 3 tests / 63 assertions. TypeScript, optimized 120-page build and synthetic Chrome review save/read-back passed. Those historical validation counts do not describe the latest candidate above. See `docs/PHARMACY-AI-DRAFTS.md` for remaining integration and acceptance requirements.

### Historical candidate records (superseded by the candidate above)

Multi-location access, independent patient charts, intake, exact original/refill quantity accounting for new noncontrolled/noncompounded partial supplies with preserved historical accounting and explicit pharmacist closure of unsupplied remainders, stock reservation and recorded handover/billing; private original prescription evidence; discontinuation and retained replacement links, including atomic discontinuation with a separately received replacement; formulation/worksheet review; ingredient receipt/quarantine/reservation; exact-quantity execution and independent documentary review; append-only addenda; independent ingredient and medication count reconciliation; receipt-specific ingredient and medication recall holds with allocation/fill tracing; an organization medication recall register covering existing and later matching receipts with location-scoped stock/fill traces and retained fill follow-up evidence, pharmacist completion/reopening and status filters; inter-location medication reservation, dispatch and independent quarantined receipt with retained source recall and discrepancy holds, visible custody warnings and independently reviewed receipt-entry corrections preserving original quantities. None of these entries claims the remaining operational workflows above are complete.

## Working order

1. Finish and verify each in-flight safety/operations change; retain exact source and test evidence.
2. Close remaining software gaps against explicit acceptance cases, without enabling unsupported operations.
3. Once authorized server access is restored, perform the read-only host/configuration and backup checks while development continues.
4. Prepare the reviewed release and production-shaped rehearsal. Escalate only decisions/access that require the owner or responsible professional.
5. Cut over only the authorized applications after their actual launch requirements pass. Record deployed commit, migration/backup/rollback evidence and real HTTPS acceptance.

Local preview credentials, OAuth keys, databases and patient-like fixtures stay outside Git. No real prescribing, dispensing, payments, messages or patient-data exports are authorized by this checklist.

### Product verification increment

Receipt-specific immutable product verification and review freshness checks are implemented in the current worktree. Retained synthetic label and print records are covered by the following increment. Validated container labels, barcode checks and licensed catalogue/substitution workflows remain outstanding. Existing and transferred receipts require actual source-product verification; there is no inferred legacy backfill. See `docs/PHARMACY-SYSTEM.md`. Local and branch validation results are recorded with the final published increment.

### Label proof increment

Retained synthetic HTML label documents, immutable revisions, scoped retrieval, separately recorded print evidence and label-bound preparation/handover controls are implemented. Physical printer/container formats, barcode/medication-guide and purchaser-receipt workflows, completed-supply relabeling procedures and responsible-pharmacist acceptance remain outstanding. No proof is an operationally validated dispensing label; all are visibly marked synthetic.

Local label validation: 210 tests / 3,377 assertions; optimized admin build/type checks pass (119 static pages); 66 protected pharmacy route declarations / 309 total and 3,104 role decisions. Chrome verifies conflicting-substitution rejection, immutable corrected proofs, missing-print rejection, label-bound preparation, and cancellation without handover/billing. Read-back confirms unchanged earlier fills, intact document/snapshot hashes, and the test reservation released. Production and physical-label acceptance remain unverified.

### Stock disposition increment

Synthetic supplier-return/disposal records now support exact quantity, source/classification/completion evidence, independent pharmacist review, a pending-use hold and atomic one-time deduction. Recall/custody holds and historical fills remain retained. Actual transport/destination/waste documentation, controlled/compounded/hazardous stock, patient returns, incorrectly applied record corrections and professional acceptance are still outstanding. Local/CI/Chrome results are recorded with the final published increment.

Local disposition validation passes: 218 tests / 3,562 assertions, optimized 119-page admin build/type checks, 69 protected pharmacy routes / 312 total, and 3,104 role decisions. Chrome verifies date rejection with preserved inputs, pending quarantine without immediate deduction, author exclusion from review, and independent one-unit application. Database read-back confirms unchanged prior fills/custody/labels and a single deduction; this is synthetic acceptance only.


### Allowance correction increment

Erroneous closure records can be independently reviewed and corrected before any later supply, with a pending new-fill hold, exact original-allowance restoration and permanent decision history. Stock, completed supplies and financial records remain unchanged; later valid closures create new records. Corrections involving later supplies or other conflicts and responsible-pharmacist operating acceptance remain outstanding. Tests and synthetic browser evidence are recorded with the published increment.


### Erroneous recall-entry correction increment

Internal recall notices entered in error now support retained correction requests and independent pharmacist decisions. Pending review preserves the active hold. Application withdraws that entry while quarantining all existing matching organization receipts; original notice scope, overlapping/receipt recalls, balances, custody and patient follow-up remain retained. Separate location-level release is still required. Register filters and paginated correction history expose pending and withdrawn records. External source authentication, actual recall changes/termination and operating acceptance remain outstanding; synthetic validation is recorded with the published increment.

### Recipient handover evidence increment

New pickup/delivery completion requires retained recipient identity-check and counseling evidence, representative authority when applicable, and confirmed recipient delivery evidence. Handover dates cannot precede final preparation. The recording pharmacist, timestamp and prepared label remain linked; failed audit writes roll back inventory and completion together. Historical records gain no inferred checks. Physical identity/barcode/delivery procedures, failed-delivery custody, completed-record correction and professional acceptance remain outstanding. Local, CI and synthetic browser evidence is recorded with the published increment.


### Package and label code-check increment

Exact pharmacist-verified package GTINs, unique retained Code 128 label proofs and preparation/handover code matching are implemented. Manual entry requires evidence and is distinguished from reported scanner input. Current source/product/label, inventory and clinical controls remain in force; historical records gain no inferred package or scan verification. Physical printer/scanner acceptance, unsupported/GS1 compound payloads, package association and operating procedures remain outstanding. Synthetic validation and independent image-decoder evidence are retained with the published increment.


### Pinned source package preparation

A local, read-only Git source packager now builds reproducible archives from a full commit SHA. It excludes runtime data/environment/build/backup files, rejects unsafe source entries and unresolved LFS pointers, retains file and migration hashes, and refuses overwrites. Verification checks the separately recorded archive digest, exact commit, approved target metadata, paths, member types and all file hashes without extracting anything. CI exercises fixture failures and the actual checked-out project. This is source preparation only; Linux production builds, host access, restore/rehearsal, physical pharmacy acceptance and cutover remain required. See SOURCE-PACKAGE.md.


### Completed handover addenda

Append-only documentary requests and independent pharmacist acceptance/rejection retain the original completed fill. Scoped pagination, stale-source checks, actor-bound retries and atomic audit writes are implemented; no inventory or financial correction is implied. Migration 000022 adds the retained addendum ledger without modifying historical completions. Local/CI/browser evidence is recorded in the workspace results; operational incident resolution and professional acceptance remain required.


### Pre-supply prescription amendment increment

Assigned pharmacists can retain source-backed corrections to strength, dosage form, directions, quantity and refills before any handover. Open fills must first be cancelled. Complete before/after snapshots, source-document integrity, consultation evidence, actor/time and increasing revisions preserve history; new fills reference the amended revision and require new review. Supplied, expired/discontinued, controlled/compounded orders and existing manufacturing/allowance decisions remain outside this workflow. Patient/drug/prescriber identity, dates and unit changes require separately received orders. This does not authenticate the prescriber, enable restricted dispensing or complete operational acceptance. Local regression: 259 tests / 4,684 assertions; optimized 119-page admin build and 78 protected pharmacy routes / 321 total pass. Exact published-head CI and synthetic browser/read-back evidence are recorded in the workspace results.

### Patient detail correction increment

Independent pharmacy patient charts now have an assigned-pharmacist correction path for names, birth date and contact details, with same-person confirmation, reason/evidence and retained before/after history. Record identity, organization/location links and clinical records remain unchanged. The shared patient version invalidates older fill reviews; unsupported patient merging/reassignment is not enabled. Stale/no-op/invalid requests and failed audit writes cannot produce partial corrections. Validation evidence is retained with the final published increment; this remains synthetic-only and requires professional operating acceptance.

### Patient history access

Patient histories now have scoped, read-only pagination and readable before/after details for demographic and clinical corrections. Older retained records are accessible beyond the legacy 100-event response limit. Validation and browser acceptance are recorded with the published increment; production deployment and pharmacy operational acceptance remain outstanding.

### Multi-location patient enrollment

The same independent patient chart can be explicitly enrolled at another same-organization location by a pharmacist assigned to both sites, with retained identity and sharing-authority evidence. Source prescriptions and stock retain their original access boundaries. This does not establish consent/legal authority or enable prescription transfer. Erroneous enrollment correction, operational sharing procedures and professional acceptance remain required.

### Erroneous enrollment correction

Additional patient enrollment can now be withdrawn with retained event/version/evidence when the target site has no prescriptions for that chart. The membership remains inactive in history and cannot authorize new intake or chart access; re-enrollment requires fresh evidence. Existing data is preserved by migration000025. Corrections after prescriptions exist, prior-disclosure response and operational privacy acceptance remain outstanding.

## Current intake increment

Independent-chart prescription intake now requires explicit patient-to-case evidence, confirmation and current chart version, retaining an immutable identity/case snapshot in the intake audit event. The detail page exposes that evidence separately from recent history and identifies missing historical evidence honestly. This does not verify identity automatically, remediate historical links, or complete the pharmacy operating/acceptance gates. See PHARMACY-SYSTEM.md.

The pharmacy record checklist now exposes missing source/clinical evidence and approvals made stale by new patient/source records, with scoped navigation links. It is read-only deterministic guidance and explicitly not complete dispensing clearance or connected AI. No dispensing or production gate is relaxed.

Controlled intake now records explicit schedule/received-format/evidence with unresolved values preserved and historical fields left null. This is the prerequisite data foundation only; schedule-specific rules, credentials, EPCS/MAPS and controlled dispensing acceptance remain incomplete. Source-backed documentary classification corrections are implemented in the current increment; operational acceptance remains outstanding.
