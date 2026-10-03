# Provider and operations closure register

Updated September 30, 2026. Planning and evidence register; no accounts purchased, providers connected, or clinical operations approved.

Dr. Ghadeh is the user-identified pharmacy/co-owner and proposed pharmacy operations approver. Her acceptance and authority for each operating location remain to be confirmed. Abe owns software/provider commercial decisions. Existing account inventory is awaiting Dr. Ghadeh; “unknown” does not mean no account exists.

| Dependency | Accountable party | Current position | Required closure evidence | Client demonstration |
| --- | --- | --- | --- | --- |
| Locations and operating scope | Dr. Ghadeh + owner | Multiple locations, controlled and compounded medications requested; site details unconfirmed | Location roster, responsible staff, approved scope and procedures per location; pharmacist acceptance of ordinary and restricted workflows | Synthetic locations only |
| Prescription receipt / electronic prescribing | Dr. Ghadeh + selected provider | Existing account/network unknown | Contract and supported interface, credentials through secure configuration, test prescription receipt with patient/prescriber matching, cancellation/change handling and duplicate prevention | No network receipt; synthetic source documents |
| Clinical medication data | Dr. Ghadeh + owner | Licensed reference source unknown | Supported catalog and clinical screening coverage, update process, license, pharmacist review of matching, interaction/allergy warnings and unavailable-service behavior | No claim of clinical screening |
| Michigan MAPS | Dr. Ghadeh + reporting provider | Account, submission route and query access unverified | Confirm dispenser submission and query arrangements separately; accepted test reports, acknowledgments, corrections, failure queue and assigned follow-up owner | No submissions or production queries |
| Controlled medications | Dr. Ghadeh | Operational approval outstanding | Approved controls, role separation, custody, reporting and exception acceptance for each location | Final dispensing remains blocked |
| Compounding | Dr. Ghadeh + compounding lead | Sterile/nonsterile scope requested; facility/process acceptance missing | Approved formulations and records, quality/release procedures, expiry assignment, environmental/sterility requirements as applicable, independent release acceptance | No finished-product release |
| PIP and payer transport | Dr. Ghadeh + billing lead | Payer/provider accounts and transport unknown | Payer list, required documents, supported submission method, test acknowledgments/rejections and reconciliation ownership | Record invoices and payments only |
| AI/OCR | Abe + Dr. Ghadeh + selected provider | GLM likely; hosting/account/processing terms undecided | Approved data processing and deployment arrangement, securely configured account, source-linked quality evaluation and pharmacist review; timeout/failure acceptance | Retained synthetic extraction review only; no live model |
| Mail, MFA and recovery | Abe + Codex | Production sending and recovery unverified | Approved sender, delivery evidence, role/MFA recovery acceptance and support ownership | Existing synthetic local identity flow; no external mail |
| Document signatures | Abe + operational owner | DocuSign configuration support exists; account and delivery unverified | Account/terms, template and recipient ownership, delivery and signed-document webhook verification | External signing excluded |
| Hosting and recovery | Hosting administrator + Codex | Verified authenticated access outstanding | Authenticated host identity, inventory, compatible runtime, scoped backup and demonstrated restore, rollback rehearsal | Local demonstration can proceed; hosted pilot cannot yet |
| Physical pharmacy operations | Dr. Ghadeh + site leads | Hardware and operating acceptance outstanding | Printer/scanner models, physical label and scan checks, handover, recalls, reconciliation and incident procedures | Screen demonstration only |
| Support and release ownership | Abe + Dr. Ghadeh + hosting administrator | Named on-call/support responsibilities unconfirmed | Release approver, incident contact, backup owner, escalation and recovery acceptance | Facilitated demo with feedback log |

## Questions for existing providers before selecting replacements

First obtain provider names and account owners from Dr. Ghadeh. Then establish: supported interfaces; test environment and synthetic-data policy; onboarding/certification requirements; account and location coverage; data processing terms; cost and lead time; failure/retry/duplicate behavior; acknowledgment and correction support; service support contact. An integration is complete only after its acceptance evidence exists. Configuration or a contract alone is insufficient.

## Order of work

1. Confirm existing providers and site operating owners with Dr. Ghadeh; prepare any missing vendor decisions for Abe. Do not procure duplicates.
2. Close host access and recovery with the hosting administrator in parallel with local pilot preparation.
3. Freeze and refresh the synthetic shared-platform pilot, log client feedback, and keep provider-dependent actions excluded.
4. Implement and validate full pharmacy dependencies and remaining workflow gaps. The pilot does not replace any full-scope gate.
