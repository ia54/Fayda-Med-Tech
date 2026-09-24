# FaydaMedTech internal pharmacy system

Decision recorded September 24, 2026. Pharmacy is the core product, not an optional provider portal. Build the pharmacy-management and dispensing system inside FaydaMedTech. Support multiple potential pharmacy locations, controlled substances, and compounded preparations. The owner will confirm an AI account, most likely GLM; no account, endpoint, contractual approval or patient-data processing has been verified.

## Current implementation boundary

**Development preview with synthetic records only. Not cleared for real dispensing.** The pharmacy API rejects requests outside local/testing environments. Existing production workflows are not replaced. Controlled and compounded prescriptions can be received and held, but final dispensing is deliberately unavailable until the required workflows are built and validated. This is an engineering release boundary, not a statement that these medication types are outside the product scope.

Implemented in this branch:

- Dedicated pharmacist and pharmacy-technician roles; pharmacist-only clinical approval/final verification, biller-only invoice preparation. Platform administration does not imply pharmacist authority. Only platform administrators can grant pharmacy roles.
- Locations belonging to an organization, with address and license reference. A stored reference is not verified licensure. Staff location assignments and verified credentials are still outstanding.
- Accident/patient-linked prescription intake, source reference, directions, quantity/unit, expiration, authorized refill count, controlled and compounded classifications.
- Separate coverage, clinical review, fulfillment and billing states. Coverage verification is not dispensing permission or a promise of payment.
- Location-specific stock receipts, lot/expiration tracking, reservations, quarantine and exact fractional quantity accounting. Stock is deducted once at handover; cancellation releases its reservation. Availability and expiration are checked again at final preparation and handover.
- Per-fill pharmacist review checks and final product/label verification attestation. These record a professional review; they do not provide an automated clinical decision or generate a validated dispensing label.
- One retained invoice linkage per fulfilled fill, evidence-backed external submission records, payer issue classification, and the existing payment-recording ledger. No payment collection or payer transmission.
- Version checks, duplicate-request protection and action history. Operational records are not deleted on migration rollback.
- Deterministic, source-referenced missing-information checks. No generative model connection and no patient data sent to an AI service.

## Product structure

Organization → licensed pharmacy location → authorized staff and stock.

Patient → accident/PIP episode → prescription → fill → reserved stock → pharmacist release → handover → claim → payer response → recorded receipt/reconciliation.

Compounding adds a versioned formulation and a production batch between prescription and fill. Controlled-substance handling adds schedule-specific rules, custody, reporting and reconciliation. A prescription can require both sets of controls.

The current intake links existing case-party/client records. A true pharmacy patient record must become its own entity; patients must not need portal login accounts to receive care. Keep a stable link to existing case/client identifiers rather than merging patients by email.

## Required work before pharmacy operational launch

| Area | Next implementation | Acceptance evidence |
| --- | --- | --- |
| Patient records | Patient identity/DOB, contacts, authorized representatives, allergies, current medications, clinical history and source dates; distinguish unknown from none | Duplicate/mismatch review, restricted updates, documented clinical review and patient history |
| Pharmacy locations | Verified license/PIC/controlled-substance credentials, staff assignments and location switching; pharmacy-specific Rx numbering and settings | A staff member cannot operate at an unassigned location; verification expires visibly |
| Prescriptions | Original record storage, prescriber identity/authority verification, e-prescribing receipt, corrections, transfers, discontinuation, renewals and partial fills | Original and amended history retained; refill and transfer edge cases tested |
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

Do not treat a compounded preparation as a product with one NDC. Ingredient-level records, formula versions and batch provenance must be represented explicitly. Sterile/nonsterile and hazardous preparation requirements determine the production controls. The owner has confirmed both controlled substances and compounding; the responsible pharmacist must identify the actual service types and validate procedures.

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

Still needed from pharmacy operations: location roster and credentials; controlled schedules; sterile/nonsterile/hazardous compounding scope and formulas; stock catalogue and unit conventions; target payers/e-prescribing/reporting arrangements. These do not prevent building the shared foundation.

Still needed for AI: confirmed provider account and hosting/processing approval. Do not assume a GLM model name establishes suitable patient-data handling.
