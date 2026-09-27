# Current release status — September 27, 2026

This is the current launch checklist. `OUTSTANDING.md` retains the historical evidence and older increments; older counts and statements there are not current acceptance claims.

## Release decision

Not deployed. The public website and existing production application are separate from the tested local redevelopment. Pharmacy endpoints remain local/testing only; controlled and compounded final dispensing remains blocked. The owner requests completion and launch today, but no requirement below is satisfied merely by that deadline.

## Launch-critical work

| Requirement | Current evidence / remaining action |
| --- | --- |
| Authorized host access | Owner is having the administrator restore SSH to 144.126.132.98. Await current access confirmation or an authenticated provider terminal. No other host, domain, folder or port is authorized. |
| Production inventory | Reconfirm active API/admin/public roots, process owners, PHP/Node/database versions and existing admin port 3005 after access is restored. Do not infer these from the local runtime. |
| Recoverable production backup | Last panel inspection reported completed API/admin/public backups, but contents, actual admin tree, database/private files/keys and isolated restore remain unverified. CI synthetic recovery does not establish production recovery. |
| Migration and release rehearsal | Branch CI covers SQLite, MySQL 8 and MariaDB 10.11 plus synthetic upgrades/restore. Pinned source packaging records per-file and migration hashes and verifies archive integrity without extraction. The exact production schema/data and host permissions still require scoped read-only inspection and rehearsal. Preserve all existing keys and records. |
| Production builds and cutover | Build with the three real origins; existing local admin build targets localhost. Run production configuration checks and controlled rollback procedure in LAUNCH.md. No DNS changes or new ports. |
| Identity and account recovery | Synthetic MFA/session/reset checks exist. Owner-controlled production sign-in and actual reset/invitation mail delivery or accepted operator recovery remain to be demonstrated. |
| Shared platform acceptance | Synthetic financial, document, case, legal/report and role workflows have recorded acceptance. Verify the final release on the actual HTTPS origins and retained production configuration before reopening writes. Payments remain record-only. |
| Optional external services | Real signing delivery/archive, OCR and AI processing are not verified. Keep unconfigured functionality unavailable. GLM is a candidate only; do not send patient data without approved account/hosting/processing terms. |
| Real pharmacy operating scope | Need responsible pharmacist acceptance, actual location/staff credentials, supported controlled schedules, sterile/nonsterile/hazardous scope, catalogue/unit conventions and operating procedures. Engineering fixtures do not verify these facts. |
| Remaining pharmacy software | Operational prescription amendments/transfers, allowance-closure corrections where later supplies or other conflicts exist, and schedule/payer-specific partial-fill rules; clinical reference integration; validated labels/barcode/handover; schedule-specific controlled handling and reporting; operational stock transfer authorization/transport/actual-loss-or-excess resolution, returns/destruction; recall outreach procedures, verified external scope changes/termination, returns/disposition and closure; manufacturing deviations/quality/BUD/finished-product release; actual PIP payer transport and reconciliation. See PHARMACY-SYSTEM.md for boundaries. |
| Pharmacy integrations and acceptance | Verify approved clinical/prescribing/reporting/payer connections as applicable, labelled test outputs, real processing contracts and professional acceptance. Do not enable controlled/compounded dispensing on the strength of documentary preview screens. |
| Operational reliability | Demonstrate production backup restore, worker/scheduler recovery, private-file access controls, monitoring, incident procedures and record-retention acceptance. |

## Implemented pharmacy foundation

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
