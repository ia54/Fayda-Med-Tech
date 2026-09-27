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
| Migration and release rehearsal | Branch CI covers SQLite, MySQL 8 and MariaDB 10.11 plus synthetic upgrades/restore. The exact production schema/data and host permissions still require scoped read-only inspection and rehearsal. Preserve all existing keys and records. |
| Production builds and cutover | Build with the three real origins; existing local admin build targets localhost. Run production configuration checks and controlled rollback procedure in LAUNCH.md. No DNS changes or new ports. |
| Identity and account recovery | Synthetic MFA/session/reset checks exist. Owner-controlled production sign-in and actual reset/invitation mail delivery or accepted operator recovery remain to be demonstrated. |
| Shared platform acceptance | Synthetic financial, document, case, legal/report and role workflows have recorded acceptance. Verify the final release on the actual HTTPS origins and retained production configuration before reopening writes. Payments remain record-only. |
| Optional external services | Real signing delivery/archive, OCR and AI processing are not verified. Keep unconfigured functionality unavailable. GLM is a candidate only; do not send patient data without approved account/hosting/processing terms. |
| Real pharmacy operating scope | Need responsible pharmacist acceptance, actual location/staff credentials, supported controlled schedules, sterile/nonsterile/hazardous scope, catalogue/unit conventions and operating procedures. Engineering fixtures do not verify these facts. |
| Remaining pharmacy software | Operational prescription amendments/transfers/partial fills; clinical reference integration; validated labels/barcode/handover; schedule-specific controlled handling and reporting; stock transfers/returns/destruction; organization-wide recall response; manufacturing deviations/quality/BUD/finished-product release; actual PIP payer transport and reconciliation. See PHARMACY-SYSTEM.md for boundaries. |
| Pharmacy integrations and acceptance | Verify approved clinical/prescribing/reporting/payer connections as applicable, labelled test outputs, real processing contracts and professional acceptance. Do not enable controlled/compounded dispensing on the strength of documentary preview screens. |
| Operational reliability | Demonstrate production backup restore, worker/scheduler recovery, private-file access controls, monitoring, incident procedures and record-retention acceptance. |

## Implemented pharmacy foundation

Multi-location access, independent patient charts, intake, stock reservation and recorded handover/billing; private original prescription evidence; discontinuation and retained replacement links; formulation/worksheet review; ingredient receipt/quarantine/reservation; exact-quantity execution and independent documentary review; append-only addenda; independent ingredient-count reconciliation; receipt-specific ingredient and medication recall holds with allocation/fill tracing. None of these entries claims the remaining operational workflows above are complete.

## Working order

1. Finish and verify each in-flight safety/operations change; retain exact source and test evidence.
2. Close remaining software gaps against explicit acceptance cases, without enabling unsupported operations.
3. Once authorized server access is restored, perform the read-only host/configuration and backup checks while development continues.
4. Prepare the reviewed release and production-shaped rehearsal. Escalate only decisions/access that require the owner or responsible professional.
5. Cut over only the authorized applications after their actual launch requirements pass. Record deployed commit, migration/backup/rollback evidence and real HTTPS acceptance.

Local preview credentials, OAuth keys, databases and patient-like fixtures stay outside Git. No real prescribing, dispensing, payments, messages or patient-data exports are authorized by this checklist.
