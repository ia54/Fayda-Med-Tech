# FaydaMedTech internal pharmacy system

Decision recorded September 24, 2026. Pharmacy is the core product, not an optional provider portal. Build the pharmacy-management and dispensing system inside FaydaMedTech. Support multiple potential pharmacy locations, controlled substances, and compounded preparations. On September 25 the owner confirmed BOTH sterile and nonsterile compounding. The owner will confirm an AI account, most likely GLM; no account, endpoint, contractual approval or patient-data processing has been verified.

## Discontinuation follow-up worklist

The prescription queue can filter discontinued records that still have pending/ready fills or ingredient reservations. Counts include all open fills and distinct reserved worksheets, not just the latest fill or number of ingredient lines. Tenant and location access apply before results are returned. Detail links lead to the exact fill or worksheet. Explicit cancellation/release removes the resolved item from this worklist without deleting history or claiming a physical return/disposal. Consumed ingredients and quarantined output require their own follow-up; this filter is not a complete recall or manufacturing exception queue.

## Retained replacement prescription links

An assigned pharmacist can link a separately received, later prescription to a discontinued original. Both must have the same patient/accident episode and pharmacy location. The replacement cannot be discontinued or already assigned as another original's replacement. Each original has at most one successor; each successor has at most one predecessor, and increasing record IDs prevent cycles. Later replacements can form a navigable retained chain.

An active original can also be discontinued and linked in one pharmacist-confirmed transaction. The pharmacist must first receive the replacement separately. The replacement must be unexpired for this action. All access and candidate checks finish before stopping the original; a failure in either audit write rolls back both the stop and link. The retained link records whether it discontinued the original, and exact retries must preserve that choice. If someone already discontinued the original, refresh and use linkage alone to preserve the earlier decision. Open fills and reservations remain on the discontinuation follow-up worklist until explicitly resolved. Pre-supply amendments are handled by the separate retained amendment workflow below. Neither workflow establishes the validity of the received authority.

The link retains reason, authority reference, pharmacist and time, with audit events on both records and exact-retry protection. It cannot be overwritten or deleted through this workflow. It does not reactivate the original or copy prescription values, files, approvals, refills, invoices or stock. Receiving and independently verifying the new prescription remains necessary. This is record continuity, not prescribing, an authorized electronic transfer, external cancellation, or automated renewal.

## Prescription discontinuation

An assigned pharmacist can record a one-way discontinuation with reason and supporting authority reference. Exact retries retain a single event; a later request cannot overwrite the decision. Worklists filter prescriptions that are or are not discontinued and show the stop on the prescription and linked worksheet.

The backend blocks new fills, approval, final preparation and handover, new compounding worksheets, worksheet approval, ingredient reservation and execution. Open fills/reservations are retained for explicit cancellation or release; discontinuation does not claim stock was returned. Existing completed fills, source files, executions and financial history are preserved. Billing for completed fills and historical document review remain available. There is no automatic prescriber/patient notification, transfer, amendment, reactivation or renewal; those require separate workflows. These controls are synthetic-preview functionality and do not independently establish clinical authority.

## Original prescription evidence

Assigned pharmacists and pharmacy technicians can append PDF, PNG or JPEG source files (up to 10 MB) to a prescription. Separate private storage and prescription-location checks apply to listing, uploading and downloading; these attachments are not exposed through the general organization document area. Each file retains its original bytes, SHA-256, receipt reference, actor and time. Download verifies the checksum and uses authenticated, non-cached responses. Exact upload retries return the existing record; conflicting request reuse is rejected. Failed writes remove newly stored files.

There is no replacement or deletion endpoint. Adding evidence requires fresh pharmacist review before final preparation or handover; a prepared fill must be cancelled and restarted. Completed fill history remains unchanged. This is source retention, not e-prescribing transport, OCR, prescriber authentication, prescription amendment/transfer authorization or clinical verification. Existing prescriptions without uploaded originals are still development fixtures. Deployment must include the private documents directory in backup/restore and retention procedures; scanner integration and operational retention acceptance remain outstanding.

## Recall worklist navigation

Ingredient receipt search now covers ingredient, supplier, lot and recall-reference text, with explicit stock-status and assigned-location filters. Recalled receipts show the source reference in the list. Filtering does not grant access to unassigned or foreign-organization receipts, and text matches must still be checked against the actual notice. Batch allocation rows link directly to the exact receipt, providing navigation in both directions between recall evidence and worksheet history. No bulk recall or notification is performed.

## September 27 receipt-specific recall holds

Assigned pharmacists can record immutable recall notice/evidence, actor/time and a recalled stock status on an individual ingredient receipt. Stock quantities and original batch records are preserved. Ordinary receiving/status review cannot clear the hold, new count adjustments are blocked, and existing pending counts cannot be applied after the recall changes the stock version. A pending count may be rejected without clearing the recall. Reservation release remains available and does not return consumed ingredients.

Receipt detail traces each allocation to its worksheet, distinguishing reserved, consumed and released records. Worksheets warn when reserved or consumed ingredients are recalled. Existing stock-status checks block new reservation and execution; all produced output remains quarantined. This is a receipt-specific internal hold and provenance view, not organization-wide recall matching, notification, patient exposure determination, disposition, recall closure or regulatory reporting. Other receipts/locations must be checked separately against the notice. No new production permission is enabled.

## September 27 ingredient discrepancy reconciliation

Assigned pharmacy staff can record a physical count or observed-loss discrepancy against an ingredient receipt. A count retains the original ledger balance, actual counted balance, evidence, author/time and stock version. It immediately quarantines the lot without altering on-hand or reserved quantities. One pending discrepancy is allowed per receipt; retries do not duplicate the record.

A different assigned pharmacist must apply or reject the proposal with evidence. Apply uses fixed-precision arithmetic, changes on-hand once, and retains the signed stock adjustment and reviewer evidence. The count cannot fall below reservations; any intervening stock version change prevents application. Staff must reject a stale proposal, resolve worksheet reservations as appropriate and recount. Rejection does not change quantities. Both decisions retain quarantine, and pending counts block release from quarantine. Existing reservations and executed batches are never rewritten.

This is synthetic ingredient-count reconciliation, not controlled-substance loss reporting, disposal documentation, transfers, recall handling or reconciliation of actual manufacturing measurements. Physically present damaged stock remains part of the physical count; disposal requires its own workflow. No real drug or patient processing is enabled.

## Current implementation boundary

**Development preview with synthetic records only. Not cleared for real dispensing.** The pharmacy API rejects requests outside local/testing environments. Existing production workflows are not replaced. Controlled and compounded prescriptions can be received and held, but final dispensing is deliberately unavailable until the required workflows are built and validated. This is an engineering release boundary, not a statement that these medication types are outside the product scope.

Implemented in this branch:

- Dedicated pharmacist and pharmacy-technician roles; pharmacist-only clinical approval/final verification, biller-only invoice preparation. Platform administration does not imply pharmacist authority. Only platform administrators can grant pharmacy roles.
- Locations belonging to an organization, with address and license reference. A stored reference is not verified licensure. Explicit staff location assignments are implemented; verified professional credentials remain outstanding.
- Accident/patient-linked prescription intake, source reference, directions, quantity/unit, expiration, authorized refill count, controlled and compounded classifications.
- Separate coverage, clinical review, fulfillment and billing states. Coverage verification is not dispensing permission or a promise of payment.
- Location-specific stock receipts, lot/expiration tracking, reservations, quarantine and exact fractional quantity accounting. Stock is deducted once at handover; cancellation releases its reservation. Availability and expiration are checked again at final preparation and handover.
- Per-fill pharmacist review checks and final product/label verification attestation. These record a professional review; they do not provide an automated clinical decision. Retained synthetic label proofs and their preparation controls are described below; physical dispensing-label acceptance remains outstanding.
- One retained invoice linkage per fulfilled fill, evidence-backed external submission records, payer issue classification, and the existing payment-recording ledger. No payment collection or payer transmission.
- Version checks, duplicate-request protection and action history. Operational records are not deleted on migration rollback.
- Deterministic, source-referenced missing-information checks. No generative model connection and no patient data sent to an AI service.

## September 27 execution addenda

Execution records now accept append-only pharmacist addenda with a section, correction/additional statement, reason and evidence reference. The original remains unchanged; the addendum retains author, timestamp and execution version. Exact request retries return the existing result, while changed requests and stale versions are rejected. Location assignments and organization boundaries are checked before access or retry.

Adding an addendum to a document-reviewed record returns its document status to quarantine for fresh independent review. A reviewer must have authored neither the original execution nor any addendum. Previous review history remains retained and new reviews identify the execution version reviewed. Rejected records stay rejected even when annotated. No addendum adjusts quantities, reverses stock consumption, assigns BUD, creates finished stock or enables dispensing. Measurement/yield corrections are documentary notices; inventory deviation reconciliation is still required separately.

The worksheet displays retained addenda and a pharmacist correction form. This closes the documentary-addendum gap only. Clinical, inventory adjustment, finished-product release and operational acceptance gates remain.

## September 25 compounding record increment

The pharmacy worklist now links to **Compounding records**. Pharmacists can create immutable formulation revisions containing source references, exact output and ingredient quantities/units, preparation method, quality criteria, storage and beyond-use dating basis. Sterile formulations require aseptic-process requirements; hazardous formulations require handling controls. No clinical formula, calculated dose or beyond-use date is generated by the software.

A different pharmacist must review a draft before staff can use it for a worksheet. This independent-review requirement is an engineering workflow control, not a claim about a universally applicable legal requirement. Retiring a formulation blocks new worksheets and pending worksheet review. Corrections create a new revision; prior records and review evidence remain retained.

Assigned pharmacy staff can select a compounded prescription and record a one-batch worksheet against a reviewed formulation. Preparation type and exact output quantity/unit must match; ingredients must match the reviewed formulation without conversions or substitutions. Supplier, ingredient lot, expiry and certificate references are recorded. Prescription-match, calculation and site-process evidence references are required. Independent pharmacist review/rejection, version conflicts, duplicate-request protection and site restrictions apply. Formula history excludes location-specific batch events.

**These are documentary planning records, not executed manufacturing records.** Supplier/lot references are staff-entered and do not verify purchase or inventory. Saving or reviewing a worksheet does not itself reserve or consume stock, and no finished product is created. The subsequent ingredient-custody increment below adds an explicit reservation step. A reviewed worksheet cannot enable dispensing. Inventory reconciliation/adjustments, equipment/personnel/environmental records, actual weights/yields and deviations, validated calculations, BUD assignment, quality test results, release/quarantine, recalls and finished-product lineage remain to be built and accepted. Sterile/nonsterile and controlled-substance final dispensing remain blocked.

UI path: `/dashboard/pharmacy/compounding`. Workflow: create formulation revision → independent pharmacist review → select matching prescription → record ingredient-source worksheet → independent worksheet review. This route remains part of the synthetic preview; new UI browser acceptance is still outstanding.

## Ingredient custody and worksheet reservation increment

The compounding workspace now links to a dedicated ingredient inventory screen. Assigned pharmacists and technicians can receive ingredient stock with supplier/lot, unit, expiration, specification, certificate and receiving-document references. New receipts start quarantined. Only pharmacists can mark a lot available or return it to quarantine, with evidence and a version check. Expired lots cannot be made available. Duplicate request replay returns the original receipt; a changed payload or repeated physical receipt reference is rejected. Receipt references should identify the receiving document and line, not just a supplier invoice covering multiple deliveries.

After independent worksheet review, a pharmacist can reserve the exact ingredients from stock at the same location. Each selected receipt must match the worksheet supplier, lot, certificate, expiry and unit, and the formulation ingredient name/specification. Quantities come from the reviewed record, not client input. No substitutions, automatic conversions or scaling are supported. Quarantine, expiration, stale planned dates, retired formulations and insufficient unreserved stock block allocation. All ingredient lines succeed together or roll back, including events. Organization-level write serialization prevents concurrent requests oversubscribing the same stock.

Reservations increase reserved quantity without changing physical on-hand stock. Pharmacists can release all worksheet allocations with a reason, including after a lot is quarantined. Released allocation history is retained; planning again requires a new worksheet. Quarantining a reserved lot does not silently discard its reservation. This custody increment does not provide adjustment, transfer, finished-product receipt or dispensing. The execution increment below adds explicit exact-quantity ingredient consumption. Quality/source references are staff-supplied evidence, not automated certificate validation. Existing dispensing guards remain enabled.

UI: `/dashboard/pharmacy/ingredients`, and the Ingredient stock reservation section of each batch worksheet. Browser acceptance is still outstanding; API regression and frontend checks are recorded with the release evidence.

## Executed preparation record increment

A pharmacist can record an exact-quantity synthetic preparation after independent worksheet review and ingredient reservation. Actual measured quantities and measurement references are required; planned quantities are not filled in as measured values. Personnel, equipment, completed process and quality-results references, actual output quantity/unit and deviations/yield findings are retained. Sterile preparations additionally require environmental/aseptic-process evidence, and hazardous preparations require handling/containment evidence. These are staff-supplied references, not automated validation of facilities, technique or quality results.

Recording execution rechecks the current formulation, prescription, planned date, each lot's quarantine/expiry status and stock balances. It atomically deducts the exact reserved quantity from on-hand and reserved stock, marks allocation lines consumed and retains custody events. Retries cannot consume stock twice. A failed later ingredient line rolls back the earlier lines and events. Consumed allocations cannot be released back into stock by the reservation endpoint.

A different pharmacist may record document review or rejection with evidence. **All output remains quarantined, including after document review.** No finished-product inventory entry, BUD, physical label, release approval or dispensing permission is generated. Execution records are retained and cannot be overwritten. The preview only supports same-day recording on the planned date, exact reserved ingredient amounts and positive output no greater than the planned quantity. If actual measurements differ, do not change them to fit the software: deviation reconciliation, corrections/addenda, loss/destruction, retrospective records and controlled adjustments must be implemented before operational use.

UI: executed preparation section of the batch worksheet. Focused tests cover duplicate consumption, independent document review, stock holds, expiry, access revocation, partial-write rollback, sterile/hazard evidence requirements and persistent output quarantine. Browser acceptance remains outstanding.

## September 25 patient and location access increment

Implemented and request-tested:

- Explicit, expiring staff-to-location assignments with administrator-only changes, version conflicts and retained reason/actor/history. No location grants are created automatically by the migration. Unassigned pharmacy staff see an empty worklist and cannot read or modify another location's prescriptions, stock or patient charts.
- Organization-locked operational writes serialize assignment revocation with pharmacy changes. Failed writes roll back, including error responses rendered inside middleware.
- Independent pharmacy patient records with record number, DOB, contact/identity evidence and location enrollment. No portal account is created. Existing case-linked portal patients are retained without automatic merging.
- Pharmacist-maintained allergy, medication and clinical-history records with explicit unknown/none-reported/documented states and previous values retained. Technicians can register/view patients but cannot sign the clinical review; billers and administrators cannot open the clinical chart endpoint.
- Prescription intake can select a pharmacy chart. New-chart fill approval requires a completed allergy/medication review, and subsequent clinical edits invalidate the recorded chart version at preparation/handover.

This is a foundation, not a complete clinical record system: structured drug/allergen terminology, external clinical checks, identity corrections/merge review, authorized representatives, patient sharing across additional locations, prescription-to-case identity confirmation, licensure verification and legacy-record conversion remain required. Legacy case-linked patients retain the earlier manual review flow; they must be reconciled before a clinical launch. An entered license reference or staff assignment is not proof of professional licensure.

## Sterile and nonsterile implementation scope — September 25

Both are required. New compounded intake must explicitly identify sterile or nonsterile preparation; contradictory or missing classifications are rejected. Existing records remain unclassified rather than receiving an invented default. Both types remain blocked from final dispensing.

Next shared implementation: immutable approved formulation versions; ingredient identities, supplier lots, expiry, potency and units; calculation evidence; a patient/prescription-linked batch record; preparer and checker attribution; quarantine, rejection and recall traceability. An AI suggestion must remain a proposal until reviewed.

Keep separate production pathways. The sterile pathway needs site-specific aseptic process, personnel/environment/equipment qualification, monitoring and applicable test/release evidence. The nonsterile pathway needs its applicable preparation, equipment, cleaning and quality-control evidence. Hazardous handling is a separate classification that can apply to either. Do not infer beyond-use dates or clinical calculations from a general template. Obtain pharmacy-approved standards, formula references and source-backed rationale before enabling production release.

This change implements intake classification only. Formula, ingredient and batch workflows above are still outstanding; no regulatory or production readiness is claimed.

## Product structure

Organization → licensed pharmacy location → authorized staff and stock.

Patient → accident/PIP episode → prescription → fill → reserved stock → pharmacist release → handover → claim → payer response → recorded receipt/reconciliation.

Compounding adds a versioned formulation and a production batch between prescription and fill. Controlled-substance handling adds schedule-specific rules, custody, reporting and reconciliation. A prescription can require both sets of controls.

Intake supports independent pharmacy patient charts and the legacy case-party/client path. Patients using a pharmacy chart do not require a portal login. Keep a stable link to existing case/client identifiers rather than merging patients by email.

## Medication physical-count reconciliation

Assigned pharmacy staff can record a retained discrepancy between the physical medication count and the receipt balance. The receipt enters quarantine immediately, without changing on-hand or reserved quantities. A different assigned pharmacist must apply or reject the proposal with evidence. Applying uses fixed-precision quantities, requires the receipt version to remain unchanged and cannot reduce the balance below reservations. Both decisions retain quarantine until a separate pharmacist stock-status review.

Cancellation of an open fill changes the stock version: reject the stale proposal and recount after reservations are resolved. Recall holds supersede counts: they block new counts and application of pending adjustments; rejecting an obsolete proposal does not clear the recall. Conflicting retries, repeat reviews, same-person review, other locations/organizations and unauthorized roles are rejected. Stock/count/audit changes commit together; audit failure rolls back the entire action. Count history is paginated and retained, and affected prescriptions display quarantine guidance.

This reconciles physically observed stock only. It does not record purchase corrections, transfer, disposal, return, controlled-substance loss reporting or professional acceptance. Physically present damaged stock belongs in the count; removing it requires its own disposition workflow. Synthetic-only restrictions remain.

## Medication stock recall holds and fill trace

Assigned pharmacists can record a retained recall hold on a specific medication receipt, with notice reference, receipt-match evidence, actor and time. Exact retries return the retained record without duplicate stock events; conflicting or stale submissions fail. The hold and stock event commit together. Ordinary quarantine/release cannot clear a recall hold. Recorded on-hand and reserved quantities remain unchanged until explicit existing stock actions occur.

New reservations, final preparation and handover reject recalled stock. Pharmacists can cancel open fills to release reservations; completed handovers and billing history remain retained. The receipt detail exposes paginated fill links and stock events to assigned pharmacists/technicians only. Organization and location checks apply to each request. Inventory search covers NDC, product, manufacturer lot and recall reference with status filters. Unreserved quantity is labelled as such rather than implying that quarantined or recalled units are available for use.

This is receipt-level containment and traceability, not an organization-wide recall campaign. Matching notices across receipts/locations (including future receipts), patient outreach, external reporting, physical returns/destruction, reconciliation and recall closure remain required. A link to a completed fill identifies recorded use, not confirmed patient exposure or a completed clinical follow-up. No operational drug handling is enabled by this preview.

## Required work before pharmacy operational launch

| Area | Next implementation | Acceptance evidence |
| --- | --- | --- |
| Patient records | Patient identity/DOB, contacts, authorized representatives, allergies, current medications, clinical history and source dates; distinguish unknown from none | Duplicate/mismatch review, restricted updates, documented clinical review and patient history |
| Pharmacy locations | Verified license/PIC/controlled-substance credentials, staff assignments and location switching; pharmacy-specific Rx numbering and settings | A staff member cannot operate at an unassigned location; verification expires visibly |
| Prescriptions | Operational acceptance of original record retention, prescriber identity/authority verification, e-prescribing receipt, corrections, transfers, discontinuation, renewals and partial fills | Original and amended history retained; refill and transfer edge cases tested |
| Clinical verification | Licensed drug reference data for interactions, allergies, duplicate therapy, dose and product/strength/form matching; pharmacist override reasons | Clinically reviewed test cases, source/version shown and no automatic clearance when data is missing |
| Labels and handover | Validated labels and reprints, barcode checks, medication guides, counseling, recipient verification and delivery evidence | Physical print/barcode tests and pharmacist acceptance for each supported workflow |
| Controlled substances | Explicit schedule, schedule-specific refill/partial-fill/expiration handling, credential checks, controlled inventory/custody, MAPS submission/acknowledgment/corrections and outage queue | End-to-end reporting verification, denied-action tests, reconciliation and responsible pharmacist acceptance |
| Compounding | Sterile/nonsterile/hazardous classification, approved formula versions, ingredient lots/potency/units, calculations, production/batch record, pharmacist checks, beyond-use date rationale, labeling, recalls and quarantine | Independently checked calculations and batch traceability from every ingredient to every supplied patient |
| Inventory | Catalogue and package-to-dispensing-unit conversion, purchasing, receipt correction, cycle count, transfers, returns/destruction and recall tracing | No negative stock or duplicate deduction; audited inter-location receipt; reversals preserve history |
| PIP billing | Versioned pricing rules, coverage/coordination, payer routing and enrollment, actual claim transport/acknowledgments, EOB reconciliation, corrected/reversed claims and issue-specific dispute tasks | Payer acceptance, finance reconciliation and reviewed fee/deadline rules; no blanket fee multiplier |
| Reliability | Monitoring, immutable/exportable audit, backups and restore exercise, outage procedures, retention, migration rehearsal and concurrency testing on production database engine | Demonstrated restore and recovery; operational acceptance signed by the responsible pharmacy team |
| AI | Confirm provider/model/hosting/account, processing terms, retention and permitted data; then add a replaceable backend adapter | Approved-data tests, source-linked outputs, tenant isolation, abstention/error handling and human approval history |

## Controlled substances and compounding

Do not implement a generic refill count as the full rule engine. A Boolean flag is only an intake classification; schedule, prescription origin, timing, partial-fill circumstances and applicable rules are required for operational decisions. MAPS lookup and dispensing-data submission are separate integrations. A manually entered confirmation is evidence supplied by staff, not verified delivery.

Do not treat a compounded preparation as a product with one NDC. Ingredient-level records, formula versions and batch provenance must be represented explicitly. Sterile/nonsterile and hazardous preparation requirements determine the production controls. The owner has confirmed both controlled substances and compounding; both sterile and nonsterile preparation types are confirmed. The responsible pharmacist must still identify hazardous handling, actual formulas and site capabilities, and validate procedures.

Reference starting points, retrieved September 24, 2026:

- [Michigan pharmacy administrative rules](https://ars.apps.lara.state.mi.us/AdminCode/DownloadAdminCodeFile?FileName=R+338.471+to+R+338.592.pdf&ReturnHTML=True): validate record, labeling and pharmacist-verification requirements against the current rules.
- [Michigan MAPS data submitters](https://www.michigan.gov/lara/bureau-list/bpl/health/maps/data-submitters): required reporting and correction procedures; build acknowledgments and failure recovery, not just an export button.
- [Michigan pharmacy licensing guide](https://www.michigan.gov/lara/bureau-list/bpl/health/hp-lic-health-prof/pharmacy/pharmacy-licensing-guides-and-faqs/pharmacy-licensing-guide-and-faqs): site and controlled-substance credential requirements.
- [FDA human drug compounding](https://www.fda.gov/drugs/guidance-compliance-regulatory-information/human-drug-compounding): determine the applicable compounding pathway with the pharmacy team.
- [USP compounding](https://www.usp.org/compounding): obtain the applicable standards and establish preparation-specific procedures.
- [Michigan DIFS utilization review](https://www.michigan.gov/difs/utilization-review/health-care-provider): route appropriate disputes using the applicable evidence and rules; not every payer issue is a utilization-review appeal.

These sources inform requirements. This document does not certify regulatory compliance or replace pharmacy/legal review.

## AI design commitment

GLM is a candidate, not a selected or configured service. Keep provider-specific credentials and transport on the backend. Start with source-linked extraction into proposed fields, record summaries, missing-evidence checks and draft correspondence. Retain source/page references, model/version, approval or rejection and the accepted changes. Imported documents are untrusted content and cannot issue system instructions. A provider failure must leave the pharmacist's core workflow usable.

AI must not independently issue a prescription, approve a fill, calculate an unreviewed compound, override a contraindication, claim coverage, post a financial receipt or send a payer submission. Use explicit permissions and human approval for consequential actions, not merely a disclaimer in the prompt.

## Deployment and open decisions

No production pharmacy launch is authorized by passing a build. Complete the operational requirements above and verify integrations and recovery before replacing a pharmacy's existing processes. Preserve the owner's three-domain scope and existing ports. Record payments only.

Still needed from pharmacy operations: location roster and credentials; controlled schedules; hazardous preparation scope, sterile service categories and actual formulas; stock catalogue and unit conventions; target payers/e-prescribing/reporting arrangements. These do not prevent building the shared foundation.

Still needed for AI: confirmed provider account and hosting/processing approval. Do not assume a GLM model name establishes suitable patient-data handling.


## Inter-location medication custody preview (September 27, 2026)

Stock receipts can now be reserved for another active location in the same organization. Planning reserves only unreserved source stock. Dispatch deducts source on-hand and reservation exactly once; cancellation is allowed only before dispatch and releases the reservation without adding stock. Transfers are visible only to staff assigned to an involved location, and each action checks the relevant location assignment. Only pharmacists may plan, dispatch, cancel or receive.

A different pharmacist at the destination records the actual received quantity, including zero, shortages and excess. A new destination receipt begins quarantined and retains the source transfer and source receipt lineage. A quantity mismatch becomes `received_discrepancy`; neither an ordinary release nor a later physical-count adjustment clears that custody hold. A matching receipt needs a separate stock release review. Upstream recalls block descendant release, reservation, preparation, handover and onward transfer, including recalls recorded after dispatch. Cancellation of fill reservations remains possible.

Request identifiers, versions and transactional custody events prevent duplicate movements and roll back a stock change if its evidence cannot be saved. This is synthetic custody accounting, not regulatory authorization to move medications. Controlled-drug transfer procedures, shipping/transport records, exception investigation and resolution, returns/destruction, external traceability and professional acceptance remain outstanding. It does not create an organization-wide recall campaign or notify recipients.

## Independently reviewed receipt-entry correction

A transfer with a recorded quantity discrepancy may have an entry error corrected only after an independently applied destination physical count matches the original dispatch. A destination pharmacist proposes the correction with the specific retained count and supporting evidence; a different pharmacist assigned to the source location applies or rejects it. Pending counts, changed stock versions, reservations, recall history and mismatched physical quantities prevent application. A rejected or stale proposal is retained and must be replaced explicitly.

The original arrival quantity, dispatch quantity and evidence remain unchanged. An approved correction records its own quantity and review link, changes custody status to `received_corrected` and leaves the destination quarantined. It does not move inventory a second time: the earlier count adjustment is the sole physical-balance correction. Later source recalls still block use of corrected receipts and descendants. Ordinary stock release, physical counts and a receipt-entry correction cannot write off a genuine transit loss, accept unexplained excess, document new arrivals or clear recalls. Actual loss/excess investigation and disposition remain separate operational work.

Transfer correction history includes the linked count and its independent review evidence, including when the source reviewer does not have destination stock-screen access. Correction and custody history are paginated. Inventory lists, receipt details and prescription fills expose a generic custody-hold reason without disclosing another location's private recall evidence. All write-time checks remain authoritative; UI warnings do not substitute for them.


## Organization medication recall register (synthetic preview)

Assigned pharmacists can record an immutable notice with source/evidence, product description, explicitly verified NDC representations, and either one manufacturer lot or explicit all-lot scope. Hyphens are removed for NDC matching; leading zeros are preserved. No 10-to-11-digit conversion is guessed. Each verified representation must be supplied. Lot matching uses trimmed ASCII uppercase identifiers, not ranges or fuzzy matching. Register separate specific-lot notices where appropriate.

An active notice holds matching medication receipts across the organization and later receipts, including internal transfer arrivals. Existing physical balances, recorded statuses and historical fills are preserved. A matching supplier receipt is recorded in quarantine. Reservations, ready/handover, dispatch, ordinary release and physical-count application cannot bypass a notice. Cancellation can release unused reservations; dispatched arrivals can still be recorded in quarantine. Receipt-entry corrections cannot clear recalls. Organization write serialization orders notice creation against operational writes.

Notice metadata is shared with currently assigned pharmacy staff; stock and prescription/fill traces are paginated and restricted to their currently assigned locations and organization. Technician access is read-only. Registration is replay-safe for the same actor/request/payload and transactional with code registration. A failed code write leaves no orphan notice. Migration backfills search keys without changing stock balances, versions or events.

This is an internal medication-stock hold register, not a regulatory recall status, automated FDA feed, patient-notification service or clinical assessment. Ingredient lots retain their separate receipt-level recall process. Erroneous internal entries can be corrected through the independent workflow below. Verified catalogue coverage, verified external recall changes/termination, actual patient outreach procedures, returns/destruction and operational recall closure remain development and acceptance requirements. No real dispensing or patient contact is enabled by these screens.


## Recall fill follow-up records (synthetic preview)

Each affected fill has a location-scoped follow-up view, initially not started. The notice's fill list filters not-started, open and completed records. Assigned staff can retain factual contact attempts, responses and further follow-up; only pharmacists can record assessments, complete or reopen the work. Date, method for contact/response, note, evidence reference, actor and entry time are retained. This records past actions; it sends no messages and makes no clinical decision. Event dates cannot predate the fill or be in the future.

Completion requires a cancelled or already handed-over fill, plus pharmacist evidence. Pending/ready reservations must first be resolved in the prescription workflow. Completed follow-up can be reopened by a pharmacist; earlier entries remain immutable. Version checks prevent concurrent overwrites, request identifiers prevent duplicate entries, and transactional event failure rolls back status/version changes or new records. History is paginated and no follow-up notes appear in organization-wide notice metadata. Completion never releases stock, changes a fill, records a return, posts a payment or closes a recall notice.

Design reference checked September 27, 2026: [FDA Product Recalls, Including Removals and Corrections](https://www.fda.gov/regulatory-information/search-fda-guidance-documents/product-recalls-including-removals-and-corrections) and [FDA guidance PDF](https://www.fda.gov/media/136987/download). These support retaining notification and response evidence as distinct facts; this internal pharmacy record is not a recalling firm's regulatory effectiveness report. Professional assessment, actual outreach procedures, return/destruction evidence and notice closure remain required.

## Original/refill quantity accounting (synthetic preview)

New noncontrolled, noncompounded fill records identify the original allowance (1) or refill allowance (2 onward) separately from the immutable fill-attempt number. Several partial supplies may use the same allowance until its prescribed quantity has been handed over. A fill smaller than the current remainder requires a retained reason. Every supply retains its own stock receipt, NDC, days supply, pharmacist review, handover and billing history. Quantities are calculated in integer thousandths, never floating-point arithmetic. Allowances cannot be pooled or skipped. This engineering accounting does not decide clinical appropriateness, refill timing, payer acceptance or legal authority.

Only one open fill is permitted. Reservations reduce the displayed unreserved remainder; cancellation releases that reservation, while completed handovers permanently consume quantity. A subsequent refill allowance opens only after the preceding allowance is fully handed over or its remaining quantity is explicitly closed through the retained pharmacist decision below. Prescription locking serializes allocation and handover; request hashes prevent duplicate allocation. Stock and prescription audit writes remain in the same transaction. Expiry, discontinuation, location access, clinical review and custody/recall holds still apply to each supply. Controlled and compounded records retain their restricted accounting and cannot be approved or handed over.

The additive migration leaves previous fills' authorization number unknown. Each noncancelled historical fill continues to consume a whole allowance under its previous rules; historical short quantities do not create inferred dispensing entitlement. Cancelled records remain in the history. Screens distinguish allowance balances from individual fill records. A historical fill number is not relabelled as a refill count.

Design reference checked September 27, 2026: [Michigan Pharmacy General Rules, R 338.586–338.587](https://ars.apps.lara.state.mi.us/AdminCode/DownloadAdminCodeFile?FileName=R+338.471+to+R+338.592.pdf&ReturnHTML=True) requires original/refill quantity and dispensing records with pharmacist identification and safeguards against alteration/loss. This implementation addresses quantity allocation only; it is not a claim that the full record, labeling, retention or pharmacy-operation requirements are met. The Legislature section 333.17751 was unavailable through the research tool on this check; no additional-quantity pooling authority is assumed.

Prescription amendments/transfers, schedule-specific partial deadlines and refill timing, professional acceptance and payer-specific partial claim rules remain outstanding. A user cannot move to the next refill by silently forfeiting a remainder or editing retained history. The responsible pharmacist must accept operating rules before production use.


## Explicit closure of an unsupplied remainder (synthetic preview)

An assigned pharmacist may close only the current partially handed-over original/refill allowance on an active, noncontrolled, noncompounded prescription. The patient request or prescriber instruction, decision date, reason, evidence reference, actor and recorded time are retained. The UI requires an explicit acknowledgement of the exact quantity and retained accounting consequence. A closure is a recorded professional decision, not automatic clinical advice or verification of the supplied authority. It does not substitute for discontinuing a prescription or receiving an amended order.

Open fills must be resolved first. Closure cannot predate the latest handover or be future-dated. A server-derived quantity-record token prevents use of stale state, including after a reservation was created and cancelled back to the same balance. Only a nonzero unsupplied remainder after an actual recorded handover may be closed; full unused allowances and historical unknown-authority records cannot be forfeited through this action. Current role/location/organization access applies on every request. Exact retries are actor- and payload-bound; changed evidence conflicts. Closure and its audit entry are atomic, and failed evidence writes leave neither a closure nor a changed balance.

The closed quantity is unavailable from that allowance unless the separate independently reviewed correction workflow below is applied. It is never added to the next refill, stock, an invoice or a payment. Prior handovers, prescription fields and physical stock remain unchanged. A later authorized refill retains its own original quantity and needs fresh pharmacist review; closure does not establish that a refill is due. If no refill remains, closure exhausts the remaining allowance. Screens distinguish handed-over, reserved, closed-without-supply and available quantities and retain the decision evidence. There is no edit or delete action. Eligible erroneous closures can be corrected through the retained independent-review process below; neither the original decision nor its evidence is overwritten. Operating acceptance of this workflow and its error-resolution procedure remains required before clinical launch.

## Verified receipt product information (synthetic preview)

Each stock receipt now has separately retained, pharmacist-verified product revisions: established/generic name, optional brand, strength, dosage form, manufacturer/supplier, verification date, source evidence, correction reason and actor. No manufacturer or strength is inferred from the existing free-text medication description or NDC. Existing receipts and transferred receipts start without verification; a pharmacist assigned to the receiving location must check their source product information. This is receipt-specific evidence, not a licensed drug catalogue, substitution determination or product authenticity guarantee.

The additive table preserves every revision. Organization/location checks apply to reads and writes; technicians may read, only assigned pharmacists may record. Exact request retries are actor/payload-bound, current revision checks reject stale submissions, and audit failure rolls back the product record. Verification does not alter inventory quantity/version, clear a recall/quarantine/custody hold, or change completed fills. Dates cannot be future-dated or precede the prior verification date. Source NDC, lot and expiry remain in the original receipt; a verification is not a way to edit them.

Approval of each general fill now requires a verified receipt product and retains its immutable product-record ID. The submitted product ID must match the latest record, preventing an older screen from approving a newer unseen revision; a changed product also resets the browser review form. Preparation and handover compare that selection against the latest revision. Any newer revision requires reviewing a pending fill again; a prepared fill must be cancelled and restarted. Legacy approvals without product evidence are not grandfathered into preparation. Completed handovers retain their original selection even after later corrections. The receipt screen exposes current verification and paginated history; the fill screen shows the product retained at review and directs staff to receipt verification.

Subsequent sections describe retained label proofs, print evidence and code checks. Licensed product catalogue maintenance, prescribing substitutions, medication guides, patient-price receipts and physical printer/scanner acceptance remain requirements. Controlled/compounded dispensing and production pharmacy routes stay blocked.

## Retained dispensing-label proofs and print evidence (synthetic preview)

Each approved general-medication fill can retain a label proof before final preparation. The server snapshots the source pharmacy/address, patient identity, prescription/prescriber/directions, quantity/unit, verified product, clinical-review reference and source-document revision. The pharmacist supplies the dispensing date, an evidence-based use-by date, product-selection/dispense-as-written decisions, substitution-notification evidence where applicable, explicit disclosure instructions, optional source-verified auxiliary text and an issue/correction reason. Dates must fit the prescription and source-stock expiry; no use-by or clinical instruction is automatically calculated.

Design reference checked September 27, 2026: [Michigan Pharmacy General Rules, R 338.582–338.583](https://ars.apps.lara.state.mi.us/AdminCode/DownloadAdminCodeFile?FileName=R+338.471+to+R+338.592.pdf&ReturnHTML=True). These distinguish required label information, substitution/disclosure handling and purchaser receipts. This implementation is an engineering preview, not confirmation of compliance. It does not infer a patient's payment from a billed invoice or replace a purchaser receipt.

Every revision retains its exact escaped HTML document and source snapshot, separate integrity hashes, actor, reason and recorded time. The document is explicitly marked **SYNTHETIC PROOF — NOT FOR DISPENSING**. No external fonts, scripts or images are loaded. Staff retrieve it through scoped authentication, no-store responses and sandbox restrictions; the browser renders it in a sandboxed frame and supports downloading the retained proof. Re-rendering a newer template never changes an older document. Label text suppresses medication/strength/manufacturer when the pharmacist records an explicit prescriber instruction with evidence; internal source records remain retained. Substitution against a dispense-as-written instruction is rejected.

The organization write transaction serializes label changes with other pharmacy writes. Issuance requires current pharmacist review, an exact source token, current fill version and expected prior label ID. A new label increments the fill version and cannot silently replace a concurrently retained revision. Exact retries are actor/payload-bound. Missing identity, stale source/product/clinical evidence, expired/discontinued/restricted prescriptions and unusable stock block issuance. Corrections are available only before preparation; prepared fills must be cancelled and restarted. There is no label delete or overwrite action.

Print/reprint evidence is separate from document retrieval. Assigned staff record copies, date, reason and output-check evidence for an open fill's current intact label. Retrieval does not create a print record or claim that physical output exists. Print records are immutable and retry-safe; failed audit writes roll back them. The preview records simulated output explicitly and sends no printer commands. Current-label/source/stock and date checks also apply to subsequent print records. Historical labels remain retrievable for audit, while superseded labels and cancelled/completed fills cannot gain new operational print evidence through this initial workflow.

Final preparation requires the latest intact/source-current label ID, at least one retained print record and the existing pharmacist final-label attestation. The prepared label ID and document hash are retained in fulfillment. Handover requires that same current label, unchanged source details and a handover date within the label's dates. A changed patient identity, location, product, review or original evidence invalidates source freshness without altering the retained document. Any failure rolls back stock transitions. Old prepared fills without label evidence do not gain inferred authorization; completed historical fills are unchanged.

This provides retained label proofs and their workflow controls. Actual container/printer formats, physical output and barcode validation, medication guides, patient-price receipts, completed-supply relabeling/reprint procedures and responsible-pharmacist acceptance remain required. No controlled/compounded release, production pharmacy access, external clinical lookup, payer transmission or payment collection is enabled.

## Independently reviewed stock disposition records (synthetic preview)

Assigned pharmacists can retain a completed supplier-return or disposal event for an exact quantity of manufactured, noncontrolled, nonhazardous medication stock. The record requires current product verification, classification evidence, destination, applicable return/disposal instructions, completion/custody reference, event date and reason. This is evidence of a reported event, not permission to transport or destroy a product. The software cannot determine hazardous-waste or controlled-substance classification. Linked controlled/compounded prescriptions, including source-transfer lineage, are rejected; the pharmacist must explicitly verify the remaining scope. Patient returns, donation/reuse, ingredient/compound output disposition and restricted-product procedures remain separate requirements.

Creating a record quarantines the receipt (preserving recalled status), advances its version and adds a zero-quantity audit event. Existing fill/transfer reservations and pending physical counts must first be resolved. Pending disposition blocks release, reservation, transfer dispatch and count adjustments. The proposed quantity stays in the recorded on-hand balance until independently reviewed; the UI identifies it as pending and unavailable. A different assigned pharmacist applies or rejects it with explicit evidence. Applying deducts the exact quantity once, retains the original record and records a negative inventory event. Rejection makes no quantity change and does not release the quarantine. There is no automatic restock, supplier credit, invoice/payment change or recall closure.

Application requires the original stock version, intact source snapshot, unchanged verified product and recall notices throughout the transfer chain. Unresolved transfer discrepancies cannot be written off through this path. Corrected receipts must retain their applied independent custody correction. Product/recall changes require rejection and a fresh record against the same physical event; operators must not repeat a physical disposition because a record was rejected. Exact author/reviewer retries are actor/payload-bound. Changed retries fail; all evidence, status and inventory writes roll back together if audit persistence fails. History is paginated and technicians can read it, but cannot create or review dispositions. No update/delete endpoint exists.

This increment does not establish actual transport, authorized destination status, waste manifests, receipt/certificate authenticity, restricted-product procedures, correction/reversal procedures for incorrectly applied records or responsible-professional acceptance. Those remain launch requirements. All usage remains synthetic/local only.

Design reference checked September 27, 2026: [Michigan EGLE non-household drug disposal](https://www.michigan.gov/egle/about/organization/materials-management/hazardous-waste/drug-disposal/non-household). Michigan's healthcare hazardous-pharmaceutical-waste standards changed effective May 5, 2025. Household take-back guidance is not a substitute for healthcare-facility disposition requirements. This record system deliberately does not select a waste method or assert compliance from an acknowledgement.


## Corrections to unsupplied-allowance closure decisions

Assigned pharmacists may request correction of an erroneously recorded closure, retaining the exact original decision, reason, authority evidence, requester and time. A pending request holds new fills and new closures. A different assigned pharmacist applies or rejects it with separate evidence; the requesting actor cannot self-review. Approval excludes only that original closure from quantity accounting, reopening the exact remainder under the same original/refill allowance. Stock, reservations, completed supplies and financial records are never restored or edited. Rejection preserves the closure and releases the correction hold.

A request is eligible only on an active, unexpired, noncontrolled/noncompounded prescription, with no open fill, later noncancelled supply or unknown legacy allowance. Cancelled later reservations remain in history and do not consume authorization. Both request and application verify the locked prescription ledger; new evidence or a stopped/expired record can invalidate application, while independent rejection remains available. Duplicate/conflicting retries, scope/revocation and transactional audit failures are covered. Original closures and all applied/rejected requests remain visible. A subsequently justified closure creates a new decision rather than overwriting or resurrecting an older one.

This is a documentary correction of a closure, not a new prescription, clinical approval, early refill permission or payer adjustment. Corrections after later supply, cancelled/completed dispensing errors and operational professional acceptance remain outstanding. The UI exposes original and corrected history and holds new-fill controls while review is pending. A location-scoped worklist filter and row marker expose prescriptions awaiting review, including stopped records needing rejection. Synthetic-only acceptance is recorded in project evidence.


## Correcting an erroneous internal recall notice

A pharmacist with an active organization pharmacy assignment can request correction of an internal notice entered in error, retaining the original scope/source, correction reason, source evidence, actor and time. The organization hold remains active while pending. A different assigned pharmacist independently applies or rejects it. Exact retries bind actor, payload and notice version; a stale review can be rejected but cannot silently apply. Rejected and applied evidence is retained with paginated history. Register any verified replacement notice before requesting withdrawal of an erroneous scope; the software does not determine the external source's validity.

Application marks only this internal entry withdrawn and atomically quarantines every currently matching organization receipt, including other locations and receipts added while review was pending. Receipt-level recalls remain recalled, overlapping active notices still hold stock, and transferred receipts retain custody checks. Each receipt receives a zero-quantity audit event and a new version, invalidating stale count/disposition proposals. No balance, reservation, fill, invoice, payment or patient follow-up is edited. Other organizations and nonmatching stock are unaffected. Any failed inventory audit write rolls back the full correction, all quarantines and the independent decision. New supplier receipts are no longer held by this erroneous entry; other active notices still apply. Transfer arrivals retain their normal quarantine controls.

Withdrawal does not release existing stock. A pharmacist assigned to each location must separately review and record stock release, subject to remaining recall/custody/count/disposition/expiry controls. Historical matching receipts and fills remain visible, with original follow-up and source evidence retained. The register filters active, pending correction and withdrawn entries; detail screens clearly distinguish an active hold from historical matching. Technician access remains read-only.

This corrects internal data; it is not regulatory recall termination, external notice authentication, automatic scope replacement or clinical clearance. Verified source procedures, regulator/manufacturer changes, patient outreach, recall closure and responsible-pharmacist acceptance remain required before operational use. All acceptance work is synthetic/local only.

## Retained pickup and delivery recipient evidence (synthetic preview)

Recording completed pickup or delivery now requires an explicit patient/representative selection, recipient name, identity-check evidence, counseling outcome and its supporting reference, and pharmacist confirmation of actual receipt. Representatives additionally require a relationship and evidence of authority to receive for the patient. Delivery requires a pharmacy-staff or tracked-carrier method plus the carrier/staff and confirmed-recipient receipt reference. Dispatch or an unattended package is not treated as confirmed receipt. The form asks for references and checks performed, not raw identity-document numbers or copies. These fields retain human verification; the software does not authenticate the recipient or determine legal authority.

Handover must be on or after retained final preparation and within the existing current-label dates. Source, product, patient, stock, recall/custody and clinical-review checks remain mandatory. Missing preparation evidence cannot be silently grandfathered into a new handover. The retained fulfillment binds the prepared label, recipient evidence, recording pharmacist and server timestamp. All writes and the inventory deduction roll back together on an audit failure. Completed records cannot be overwritten; a stale retry cannot deduct twice. No historical recipient evidence is inferred or backfilled. The detail screen exposes retained evidence separately from claim records.

This is synthetic end-to-end acceptance only. Physical label/barcode validation, identity/representative procedures, delivery dispatch/failed-delivery custody, signatures, completed-record corrections and responsible-pharmacist operating acceptance remain outstanding. No delivery service is contacted, no patient notification is sent and no payment is collected. Controlled/compounded dispensing and production pharmacy access remain disabled.


## Package and label code verification (synthetic preview)

New pharmacist product revisions require the exact source-package GTIN (8, 12, 13 or 14 digits), including leading zeros and a valid check digit. The pharmacist must verify the package-to-product/NDC/lot/expiry relationship from its actual source. Check digits detect some transcription errors; they do not authenticate a product, registration or clinical suitability. There is no inferred NDC conversion, zero-padding equivalence, GS1 application-identifier parsing or automatic package-size conversion. Unsupported barcodes require a separately designed and accepted workflow; they are not silently normalized. Previous product revisions receive null package codes, with no inferred verification.

Each new label proof retains a unique, opaque internal FMTL code in its snapshot and row, plus an embedded Code 128 SVG and readable code. Existing documents and hashes are not re-rendered. Retrieval checks the code-to-snapshot binding as well as the document/snapshot hashes. The code contains no patient, medication or prescription identity. SVG is generated locally through the locked Picqer dependency; no barcode service or patient-data transmission occurs. Historical labels without codes remain available for audit, but new preparation needs a newly retained supported label and verified package code.

Final preparation compares the exact package code, receipt lot and current label code with the selected stock/product/label. Handover requires a fresh entry of that prepared label's code. Wrong, foreign or superseded labels fail closed. A changed product or source still requires fresh review/label evidence under the existing rules. Inputs distinguish a reported keyboard-scanner capture from manual comparison; manual entry requires a retained reason. These are operator-reported methods, not device attestation. Forms do not prefill expected codes. Both checks retain the actor, time, label hash and input method; preparation also binds the product and receipt. Any failed validation or audit write rolls back inventory and fill changes. Completed evidence is not overwritten.

Engineering validation covers code generation/independent image decoding, mismatches and leading zeros, current-label binding, manual-entry acknowledgement, retained preparation/handover evidence and rollback. Real scanner configuration, print size/contrast/quiet-zone quality, package/container association, GS1 DataMatrix/serialization, damaged/missing codes and responsible-pharmacist procedures remain operational acceptance requirements. Production pharmacy and controlled/compounded final dispensing remain disabled.

References checked September 27, 2026: [GS1 check-digit calculation](https://www.gs1.org/services/how-calculate-check-digit-manually) and [Picqer barcode renderer](https://github.com/picqer/php-barcode-generator). Dependency/version/license details are retained in PHARMACY-CODE-DEPENDENCIES.md.


## Completed handover documentary addenda

Assigned pharmacists can request an append-only correction or additional evidence for a collected/delivered fill. Supported sections are recipient, identity checks, representative authority, counseling, delivery and completion reference. The proposal retains statement, reason, evidence, author and server time; a different currently assigned pharmacist accepts or rejects it with separate evidence. Pending/rejected statements are visibly distinct from accepted addenda. All versions remain beside the unchanged original handover and can be paged through. Subsequent corrections require another retained addendum, never an edit or deletion.

Request identifiers are bound to actor/payload. The creation token binds the current completion and addendum ledger; acceptance rechecks the original completion identity. Stale creation, changed source, conflicting retries, revoked assignments and foreign records fail closed. Claim-only updates do not alter source identity. Request/review and prescription audit events share the organization write transaction; audit failure rolls back the addendum or decision. Historical fills receive no invented structured recipient evidence.

This is documentary correction only. It neither changes a completed handover, quantity, stock, label, invoice or payment nor establishes that a missed delivery actually occurred. Medication/quantity discrepancies, actual failed delivery, patient-safety incidents, controlled reporting corrections and external payer/carrier corrections require their separate operational workflows. Synthetic-only restrictions and human professional acceptance remain.

Pending handover addenda are discoverable in the prescription worklist. Assigned pharmacy staff can view all pending requests; pharmacists can select only requests by other authors for independent review. Counts aggregate all completed fills, including older fills beneath a later cancelled fill, without duplicating prescription rows. Discontinued prescriptions remain discoverable. Links point to the affected fill. Review decisions clear the pending counts; authors still see their own pending requests in the all-pending view. Organization/site/expiry boundaries and search/location filters apply. Biller/admin roles receive no handover-addendum counts or review filters, and technicians cannot use the independent-review filter.


## Historical label record copies

Assigned pharmacy staff can retrieve a marked record copy of an intact retained label and record its simulated copy/print evidence, including after completion, discontinuation, expiry or subsequent source changes. The copy derives from retained document bytes rather than current patient/product records, displays RECORD COPY / NOT A DISPENSING LABEL with the original hash, and hides the internal barcode in screen and print styles. Original proof bytes remain unchanged and separately retrievable. This is historical documentation, not a replacement container label, revised directions or renewed dispensing authority.

Print history retains an explicit dispensing_label or record_copy purpose and the rendered-document SHA-256 for new records. Existing print records remain classified as dispensing evidence because that was their original creation path; no historical document hash or actual printer output is inferred. Old exact-request retries remain valid. Record-copy entries never count toward the current label's dispensing print count or the preparation/handover print prerequisite. Dispensing print creation continues to require current intact source, pharmacist approval and usable stock. Copy access retains site/organization/role and integrity checks. Each new print requires its own actor-bound request, copies, date, reason, evidence and acknowledgement; audit failure rolls back insertion.

No printer command or external transmission is sent. Physical printer acceptance, operational container relabeling after handover, clinical label corrections and professional operating procedures remain outstanding.


## Prescriber-authorized amendments before first supply

An assigned pharmacist can retain corrections to strength, dosage form, directions, quantity per allowance and authorized refills before any supply has been handed over. A retained private source document must belong to the same prescription and pass its file integrity check. The pharmacist records direct prescriber consultation identity/method/authorization evidence, consultation date, reason and acknowledgement. This records the professional decision; software does not authenticate the prescriber or establish legal authority. Current Michigan operating procedures and responsible-pharmacist acceptance remain required.

Every amendment preserves complete before/after prescription snapshots, source-document identity/hash, actor/time and a monotonically increasing revision. The original intake identity, dates, source reference and request hash remain unchanged. Existing fills carry their original prescription revision. All open fills must first be cancelled through the normal workflow; amendment does not release reservations, copy approvals, relabel medication or alter stock, invoices or payments. A new fill uses the amended quantities/revision and requires a fresh pharmacist review. No-op updates, stale source tokens, foreign source files, invalid dates, missing/corrupt files and conflicting actor/payload retries fail. Audit failure rolls back both revision and amendment. Scoped paginated history remains available to pharmacists and technicians; only pharmacists can apply an amendment.

Patient, medication identity, prescriber identity, written/expiry dates and quantity unit are not editable through this workflow. Discontinued/expired orders, any completed supply, retained manufacturing or allowance decisions, and controlled/compounded prescriptions require their separate new-order/replacement or dedicated workflow. This boundary avoids rewriting completed authorizations or extending expired authority. It does not complete controlled-substance amendments, prescription transfers, external prescribing transport, or clinical relabeling after supply.

### Patient demographic corrections

Assigned pharmacists can correct names, birth date, phone and address on an existing independent pharmacy patient chart after explicitly confirming the same person and retaining a reason and identity-evidence reference. Every correction retains complete before/after values, actor/time and the incremented shared patient version. Patient record number, organization, location links, intake identity, clinical evidence and existing prescription associations are not editable through this action. This is not record merging, wrong-patient reassignment or identity authentication.

Stale submissions, no-op changes, future birth dates, missing evidence/confirmation and unauthorized roles or locations are refused. Corrections and audit history are atomic. Increasing the patient version makes prior fill reviews stale and current label checks require fresh review; existing historical documents and completed records are not rewritten. A prepared fill requiring correction must follow the existing cancellation/new-review workflow. UI validation retains entered details while the current chart refreshes. Operational identity-correction procedures and responsible-pharmacist acceptance remain required; all testing is synthetic.
