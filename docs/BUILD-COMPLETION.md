# Build completion worklist

Updated October 18, 2026. The owner has directed build work to proceed while server access is deferred. This is a software worklist, not a launch approval or a percentage-complete claim.

## Current increment

The compounding worksheet now presents worksheet review, ingredient allocation/preparation, quality/dating, container custody, suitability/labels and history in workflow order. Navigation preserves mounted forms and existing container deep links. Discontinuation and ingredient incident holds remain visible above the sections. Opening a detail record skips unrelated formulation/worksheet list requests; returning to the lists refreshes them. Worksheet retrieval has an explicit retry and refresh path.

This changes presentation and retrieval behavior only. It does not enable compounded release or change inventory, review, prescribing or handover authority.

The preceding published candidate, `09fd6ae`, passed hosted PHP regression (430 tests / 8,603 assertions), MySQL and MariaDB schema/workflow/recovery checks, admin checks and Linux web builds. Those results apply to that candidate; they do not verify newer changes or deployment.

## Completion sequence and acceptance evidence

### Prepared cancellation custody

Prepared-fill cancellation now requires the pharmacist to account for the entire quantity, confirm continuous pharmacy custody, and retain a segregation reference. The original preparation/label record remains intact. Cancellation removes the reservation without deducting on-hand quantity or creating a bill; an available source receipt becomes quarantined. Existing quarantine and recall states are preserved. The whole receipt is held because prepared units currently share its stock balance; a later stock review is a separate action.

This path deliberately rejects dispatched, missing or patient-returned medication. Actual loss and patient-return resolution remain unfinished; these must not be represented as completed cancellation functionality. Pending, unprepared fills retain their existing cancellation behavior. Historical cancelled fills receive no inferred custody evidence.

Three new API regression cases cover invalid custody/quantities, stock and financial invariants, replay protection, role/site/organization access, recall preservation and audit rollback. Existing successful prepared-cancellation fixtures now supply explicit custody evidence, and the product-correction journey explicitly reviews quarantined stock before reuse. PHP syntax and UI syntax/render/payload checks passed. The published candidate passed the hosted checks listed above after local validation stalled in file reads. Browser acceptance remains outstanding.

### Dispatch and undelivered return custody — current increment

Prepared ordinary fills now have a distinct dispatch record. Dispatch retains stock and allowance reservations; actual delivery requires matching dispatch method/date and the existing recipient/counseling/label checks. Pickup and ordinary cancellation are blocked after dispatch. Failed, damaged or missing deliveries remain held for investigation.

A complete undelivered return requires original package-label identification, quantity, custody/condition/segregation evidence and confirmation that no medication reached the patient or representative. A different currently assigned pharmacist reviews it. Application cancels the unfulfilled fill and atomically moves the exact units from its source balance into a separate quarantined receipt. That receipt cannot be released, dispensed or transferred; reviewed disposition follows the original source and recall history. Rejections remain in paginated history. The prescription worklist can filter unresolved dispatches.

No courier booking, patient communication, billing or provider transmission is performed. Partial/missing returns and patient-returned medication remain unresolved rather than being written off or restored to usable stock. Controlled and compounded dispensing remain disabled. No historical dispatch evidence is inferred.

Focused synthetic cases cover dispatch validation, confirmed delivery, failed-delivery holds, independent returns, rejection/stale-source history, permanent reuse restrictions, disposition lineage, access boundaries and atomic audit rollback. Syntax checks passed; execution and build checks for this increment are pending. This increment is not deployed.

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
