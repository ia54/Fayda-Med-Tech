# FaydaMedTech internal pharmacy system

Decision recorded September 24, 2026. Pharmacy is the core product, not an optional provider portal. Build the pharmacy-management and dispensing system inside FaydaMedTech. Support multiple potential pharmacy locations, controlled substances, and compounded preparations. On September 25 the owner confirmed BOTH sterile and nonsterile compounding. The owner will confirm an AI account, most likely GLM; no account, endpoint, contractual approval or patient-data processing has been verified.

## Discontinuation follow-up worklist

The prescription queue can filter discontinued records that still have pending/ready fills or ingredient reservations. Counts include all open fills and distinct reserved worksheets, not just the latest fill or number of ingredient lines. Tenant and location access apply before results are returned. Detail links lead to the exact fill or worksheet. Explicit cancellation/release removes the resolved item from this worklist without deleting history or claiming a physical return/disposal. Consumed ingredients and quarantined output require their own follow-up; this filter is not a complete recall or manufacturing exception queue.

## Retained replacement prescription links

An assigned pharmacist can link a separately received, later prescription to a discontinued original. Both must have the same patient/accident episode and pharmacy location. The replacement cannot be discontinued or already assigned as another original's replacement. Each original has at most one successor; each successor has at most one predecessor, and increasing record IDs prevent cycles. Later replacements can form a navigable retained chain.

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
- Per-fill pharmacist review checks and final product/label verification attestation. These record a professional review; they do not provide an automated clinical decision or generate a validated dispensing label.
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
