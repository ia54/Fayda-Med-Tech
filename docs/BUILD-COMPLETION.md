# Build completion worklist

Updated October 18, 2026. The owner has directed build work to proceed while server access is deferred. This is a software worklist, not a launch approval or a percentage-complete claim.

## Workflow navigation baseline

The compounding worksheet now presents worksheet review, ingredient allocation/preparation, quality/dating, container custody, suitability/labels and history in workflow order. Navigation preserves mounted forms and existing container deep links. Discontinuation and ingredient incident holds remain visible above the sections. Opening a detail record skips unrelated formulation/worksheet list requests; returning to the lists refreshes them. Worksheet retrieval has an explicit retry and refresh path.

This changes presentation and retrieval behavior only. It does not enable compounded release or change inventory, review, prescribing or handover authority.

The preceding published candidate, `09fd6ae`, passed hosted PHP regression (430 tests / 8,603 assertions), MySQL and MariaDB schema/workflow/recovery checks, admin checks and Linux web builds. Those results apply to that candidate; they do not verify newer changes or deployment.

## Completion sequence and acceptance evidence

### Prepared cancellation custody

Prepared-fill cancellation now requires the pharmacist to account for the entire quantity, confirm continuous pharmacy custody, and retain a segregation reference. The original preparation/label record remains intact. Cancellation removes the reservation without deducting on-hand quantity or creating a bill; an available source receipt becomes quarantined. Existing quarantine and recall states are preserved. The whole receipt is held because prepared units currently share its stock balance; a later stock review is a separate action.

This path deliberately rejects dispatched, missing or patient-returned medication. Loss and patient-return handling use the separate workflows documented below; they must not be represented as ordinary cancellation. Pending, unprepared fills retain their existing cancellation behavior. Historical cancelled fills receive no inferred custody evidence.

Three new API regression cases cover invalid custody/quantities, stock and financial invariants, replay protection, role/site/organization access, recall preservation and audit rollback. Existing successful prepared-cancellation fixtures now supply explicit custody evidence, and the product-correction journey explicitly reviews quarantined stock before reuse. PHP syntax and UI syntax/render/payload checks passed. The published candidate passed the hosted checks listed above after local validation stalled in file reads. Browser acceptance remains outstanding.

### Dispatch and undelivered return custody — verified baseline

Prepared ordinary fills now have a distinct dispatch record. Dispatch retains stock and allowance reservations; actual delivery requires matching dispatch method/date and the existing recipient/counseling/label checks. Pickup and ordinary cancellation are blocked after dispatch. Failed, damaged or missing deliveries remain held for investigation.

A complete undelivered return requires original package-label identification, quantity, custody/condition/segregation evidence and confirmation that no medication reached the patient or representative. A different currently assigned pharmacist reviews it. Application cancels the unfulfilled fill and atomically moves the exact units from its source balance into a separate quarantined receipt. That receipt cannot be released, dispensed or transferred; reviewed disposition follows the original source and recall history. Rejections remain in paginated history. The prescription worklist can filter unresolved dispatches.

No courier booking, patient communication, billing or provider transmission is performed. Partial/missing returns and patient-returned medication use the separate loss and patient-custody ledgers below. Controlled and compounded dispensing remain disabled. No historical dispatch evidence is inferred.

Focused synthetic cases cover dispatch validation, confirmed delivery, failed-delivery holds, independent returns, rejection/stale-source history, permanent reuse restrictions, disposition lineage, access boundaries and atomic audit rollback. Candidate `8ef2b6f` passed 435 PHP tests / 8,748 assertions, MySQL/MariaDB schema/workflow/recovery, admin TypeScript/session checks and the production-origin web build. Chrome and physical acceptance remain outstanding. It is not deployed.

### Confirmed dispatch losses and later recovery

A retained delivery exception can now proceed to documented loss reconciliation for ordinary unfulfilled medication. Returned plus confirmed-lost quantities must equal the entire dispatch. Evidence must identify the completed investigation, exact loss and nonreceipt by the patient or representative. Independent review deducts only the lost units from total inventory; any returned units enter permanently segregated quarantine. A wholly lost dispatch creates no fictitious returned-stock receipt. No charge, report transmission or automatic replacement is created.

Medication found later has a separate recovery proposal and independent review. Total accepted recovery cannot exceed the original documented loss. Recovered units enter separate non-releasable quarantine receipts, retaining original stock/recall lineage for disposition. The original loss, cancelled fill, invoice state and allowance history are preserved. Rejection, stale evidence, unauthorized authors and audit failures cannot create stock. Pending recovery reviews appear in the dispatch worklist.

Published candidate a6001a7 passed hosted PHP, MySQL and MariaDB regression. Patient-returned medication and compounded output remain separate workstreams; no production functionality is enabled.

| Workstream | Work to establish before calling it complete | Acceptance journey |
| --- | --- | --- |
| Ordinary prescription fulfillment exceptions | Inspect current cancellation, prepared-stock custody and handover correction behavior; implement missing failed-delivery, missing-stock and disposition paths without treating unreturned medication as available stock. | Intake → review → preparation → exception → independent resolution; verify exact stock, reservation, authorization allowance and billing changes, with rejected/stale/duplicate requests. |
| Compounded finished output | Connect the existing manufacturing, quality, dating, container and label ledgers to a synthetic reservation and handover lifecycle. Keep actual release unavailable until the required operating policy is configured and accepted. | Reviewed worksheet → allocation → execution → quality/date review → packaging → label check → synthetic reservation → handover/cancellation; changed evidence, expired dates, recall and custody exceptions must block progression. |
| Prescription changes and transfers | Reconcile implemented ordinary amendments/internal transfers against the remaining controlled, compounded and external workflows; preserve original source and supply history. | Source-backed change/transfer → independent decisions → destination intake; no duplicate usable authorization or lost prior supply history. Policy-dependent paths remain unavailable until configured. |
| Stock and recall exceptions | Reconcile existing transfer variance resolution, disposition and recall correction services before adding missing returns, termination or closure operations. | Dispatch/receipt variance → evidence/recount → independent resolution; recall across current and later receipts → follow-up/disposition → explicit closure. No silent stock restoration. |
| Provider connections | Build against explicit adapter contracts, retry/reconciliation behavior and synthetic fixtures. Approved vendor selection determines the real protocols. | Success, rejection, timeout, repeated response and outage recovery; never duplicate submissions or infer acceptance from transport alone. |
| Shared-platform journeys | Recheck the integrated role journeys after pharmacy changes; consolidate verification rather than rerunning all suites for every wording change. | Patient/provider/biller/attorney/admin access boundaries, documents, invoices, recorded payments/corrections, reporting and account recovery. |
| Final development candidate | Run the complete relevant regression and browser journeys, repair failures, then produce one pinned source/build candidate. | Commit and artifacts agree; migration/recovery checks pass; remaining provider, operational and hosting requirements are listed separately. |

## Decisions kept separate from engineering completion

- Dr. Ghadeh's actual operating scope, location/staff authority, controlled-substance and compounding procedures, and acceptance.
- Approved prescribing, drug-reference, reporting, claims and AI providers and processing terms. GLM remains a candidate, not a configured service.
- Production access, inventory, backup/restore, deployment and HTTPS acceptance. No server connections are part of this build-first work.

Use current source and test results to reconcile this list. Older release notes contain implemented items mixed with operating-acceptance requirements; do not reimplement a workflow solely because an old note lists it as outstanding.

### Patient-return custody (2026-10-18 development candidate)

Ordinary recall and dispensing-error patient receipts now have a separate custody ledger, independent recount/disposition review, retained evidence and worklist attention across completed fills. No dispensing-stock, invoice, payment or refill balance is changed. Actor-bound replay, fixed-point quantity caps, current site assignment, source integrity and audit transaction rollback are enforced. Controlled/compounded returns and general take-back or donation programs are not enabled. Actual receipt eligibility and disposal procedure require pharmacy operational approval.

Current Michigan R 338.503 provides the patient-return boundary: https://ars.apps.lara.state.mi.us/AdminCode/DownloadAdminCodeFile?FileName=R+338.471+to+R+338.592.pdf&ReturnHTML=True . The product keeps these receipts entirely separate from active inventory; it does not infer authorization from a checkbox or perform physical disposition.

Local PHP syntax, protected-route declarations and TypeScript transpilation passed. Six request-level regression scenarios added; exact candidate CI and browser acceptance remain to be verified. Previous dispatch loss/recovery candidate a6001a7 passed backend PHP, MySQL and MariaDB CI. No deployment or operational acceptance is implied.

### Compounded handover rehearsal (current development increment)

The existing reviewed quality, dating, packaging, suitability and container-label chain now feeds a separate synthetic reservation and handover exercise. Every held unit must be packaged with current labels and retained comparison evidence. A second currently assigned pharmacist must compare each exact container/code and document synthetic recipient, identity and counseling evidence. Source changes block simulated handover; documented cancellation remains possible with retained original history. Duplicate active/completed exercises cannot silently reserve the same output twice.

The rehearsal never writes real inventory reservations, fills, prescription allowances, payments or release status. Production endpoints remain disabled. No physical handover, printer/scanner acceptance, controlled-substance authority or clinical compounding acceptance is implied. Real compounded release requires a separate operationally approved lifecycle; this exercise establishes the connected documentary path only.

Added three focused request-level regression scenarios for replay, independent authorization, source changes, cancellation, role/site/tenant and production gates, audit binding and rollback. Published b397ee0 passed 449 PHP tests / 9,132 assertions and all hosted MySQL, MariaDB, admin and web-build checks. Browser acceptance remains pending. Patient-return worklist badges now link directly to the earliest affected fill, including older completed fills; additional regression verifies existing invoice preservation.

### Unusable-evidence recovery

Pending quality, dating, packaging and suitability proposals, and draft protocols, now expose a rejection-only recovery form when the detail API explicitly reports an integrity conflict. The form requires fresh matching history metadata, an independent pharmacist, investigation evidence and confirmation. Network, authorization and server failures do not expose rejection; review/approval is never offered for unreadable evidence. Original records remain retained. Four rendered UI checks cover confirmation, action restrictions, contributor independence, stale/missing history, refresh disabling and HTML escaping. They are included in admin CI; browser acceptance is still separate.

## Prescription transfer evidence recovery — October 18 follow-up

Unreadable or structurally incomplete retained transfer snapshots now return an explicit unavailable state alongside the scoped transfer metadata. The UI withholds acceptance and file retrieval while allowing an authorized sending pharmacist to cancel, or an independent receiving pharmacist to reject. Original evidence is preserved. These decisions release only the pending transfer hold; they do not create receiving authorization, discontinue the prescription, or alter stock. Retries remain actor/body-bound and audit failures roll back the entire decision.

Two request-level regressions cover malformed evidence, blocked acceptance, rejection/cancellation, replay and audit rollback. Four rendered UI cases cover role/site restrictions, unavailable source content, pending refresh, retained decisions and the intact acceptance journey. Hosted verification must be recorded against the final commit; rendered component checks are not browser or pharmacist acceptance.

Synthetic acceptance journey: open a pending transfer with a deliberately damaged snapshot in an isolated test database; verify the unavailable-evidence notice and absence of acceptance/file retrieval; reject as an independent receiving pharmacist or cancel at the source with investigation evidence; refresh and confirm the decision, unchanged original snapshot, cleared transfer hold, unchanged stock and no receiving prescription/receipt. Never damage production records for this exercise.


## Recall follow-up overview

Recall detail now summarizes all affected fills at the requesting staff member's current assigned locations, before display filtering or pagination. It separates unfinished follow-up from unresolved fulfillment and provides filters for not-started, open and completed follow-up. It does not count other locations/tenants or imply recall termination, patient communication or stock release. Read-only aggregate regressions cover pagination, status filters, assignment revocation, inconsistent location links, foreign-tenant rows, zero matches and follow-up transitions. Rendered UI checks cover scope language, counts and refresh-disabled controls. Browser and operational acceptance remain separate.

## Independent cloud continuation — October 19

Cloud development continues from handoff `5d18293` on `codex/cloud-pharmacy-acceptance`, reviewed through draft PR #2. A morning-run authentication failure in the compounding dating fixture is corrected without changing application authentication or clinical gates. The existing dispatch-loss API journey now explicitly verifies all three separate quarantined receipts after independent 2.125/3.875 recovery reviews; the original six-unit loss, available source stock of 40, cancelled fill and invoice absence remain preserved.

The corrected cloud SQLite regression passes 453 tests / 9,255 assertions. See [cloud validation evidence](CODEX-CLOUD-VALIDATION-20261019.md) for exact runtime, database, build, configuration and publication status. A fresh Chromium journey also independently applied both recovery quantities and verified reload persistence plus SQL invariants; the old local pending review was not imported. The selected compounded handover and shared-role browser journeys now pass; see [fresh browser/database evidence](CODEX-CLOUD-BROWSER-ACCEPTANCE-20261019.md) for source pins, fixes, exact focused checks, remaining browser limits and publication status. The cloud report distinguishes seeded authentication from login/MFA acceptance. Both sterile and nonsterile scope is confirmed; per-site clinical, physical-device and real release acceptance remain separate.
