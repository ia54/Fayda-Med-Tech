# Conditional launch checklist — October 27, 2026

Reconciled October 27, 2026 against the October 22–23 evidence below. All planning dates use America/New_York; source timestamps retain their stated timezone. The former October 20 status is historical, not the current engineering baseline. This checklist supplements BUILD-FINISH-BACKLOG-20261019.json; it does not replace full-product work or promise a launch date. No merge, deployment, clinical approval or real payment is authorized by this document.

## Evidence baseline and scope

- [Authoritative October 23 handoff](CLOUD-CONTINUATION-20261023.md): a private synthetic GoDaddy frontend and private backend gateway were operating in the reported pilot; follow-up filtering was browser-verified at `9f67e00`. These are historical observations, not an October 27 health check.
- [Owner walkthrough](PHARMACY-OWNER-WALKTHROUGH-20261022.md): email-code sign-in and synthetic patient/prescription/inventory screens were observed October 22. Source attachment persisted after reload; October 23 server retrieval matched all 1,989 bytes and SHA-256 `b646e6e60661a4570ba83f9b04c8120e4bbab18790cc536e966430309406efd7`. Browser-to-disk hash proof remains outstanding. The hosted ordinary fill remains pending in that report; controlled and compound records demonstrate intake only.
- [Cloud recovery acceptance](CLOUD-RECOVERY-ACCEPTANCE-20261023.md) and [evidence index](evidence/CLOUD-ORDINARY-20261023.json): the two extended PHPUnit tests passed 2 tests / 86 assertions; the pharmacy class passed 269 tests / 7,520 assertions; admin pharmacy tests passed 13 and typecheck passed. The clean `9e2468b1985c56a9e5a259326db82b35ac357317` disposable browser fixture completed pickup and dispatch/delivery through site-assigned billing and recorded receipts. Stock 50→30, zero reserved, two dispense events/invoices/receipts and retained audits were checked. Seeded sessions, simulated print and internal synthetic submission entries do not establish login/MFA, hardware, payer or clinical acceptance. The hosted demonstration dataset was unchanged.
- Draft [PR #5](https://github.com/ia54/Fayda-Med-Tech/pull/5) candidate `4570e385692e6aa6d68ac2b60ee27b7c6a6760c5`: six hosted CI jobs were inspected October 27 and all concluded SUCCESS. [Backend run](https://github.com/ia54/Fayda-Med-Tech/actions/runs/40002159315): PHP 8.3 regression tests (including successful locked Composer audit), pinned source release package, MySQL 8 schema/finance/recovery and MariaDB 10.11 schema/finance/recovery. [Admin run](https://github.com/ia54/Fayda-Med-Tech/actions/runs/40002159309): session-isolation. [Web run](https://github.com/ia54/Fayda-Med-Tech/actions/runs/40002159329): Linux production-origin asset candidate. These successes belong to that SHA; this documentation update creates a later head whose checks must be inspected separately.
- PR #5 records a pinned source archive at `4570e38`: 1,259 files / 137 migrations, SHA-256 `591a268e2fd8007e0126df636f36948e1d819ab1271c5acdf613e655edcefa39`, `production_ready=false`. Source packaging and disposable CI restore rehearsals do not establish a current hosted backup or a GoDaddy restore.
- October 27 original-runtime check: cloud shell exists, runtime reports running/connected and restricted network policy enforced, but normal `git fetch origin` fails connecting to inherited `proxy:8080`. GitHub connector reads work; dependency/browser access is not restored. No fresh application tests or browser journey ran during this checklist reconciliation. The separate successful setup runtime is reported deleted by the owner; its success does not establish reusable environment restoration.

- October 27 new-runtime restoration: [recovery report](CLOUD-RECOVERY-20261027.md) and [evidence index](evidence/recovery-20261027/index.json) record normal Git fetch, locked Node/pnpm/Composer installation, PHP platform validation, 3 focused backend tests / 117 assertions, and fresh synthetic prescription upload/reload/authenticated browser download with exact 77-byte/hash proof. Source-specific role/site/anonymous denials and 390px/320px keyboard checks passed. This is disposable cloud evidence, not hosted proof, login/MFA acceptance, publication or clinical release. Current pnpm audit reports two critical, three high and one low finding; Composer audit is blocked by current network policy.

## Synthetic-pilot engineering blocker register

The October 20–27 target dates were conditional planning dates, not completed gates.

| Gate / accountable owner | Established evidence | Remaining blocker / next action |
| --- | --- | --- |
| Candidate validation — engineering | Six successful jobs at full SHA above; prior pharmacy and browser results retain their own source bindings | Inspect checks at any later head. Do not repeat the 269-test class or both completed journeys without a relevant change/failure. |
| Dependency security — engineering / owner | Historical candidate Composer audit passed; fresh braces mitigation tests passed; October 27 pnpm audit reports 2 critical / 3 high / 1 low across Next.js, DOMPurify and Zod | Review upgrade/risk disposition before candidate approval. Current Composer audit is network-blocked; historical green CI does not clear fresh findings. Preserve the locked braces mitigation until reviewed. |
| Cloud continuation — platform / owner | New recovery environment supports normal Git, locked dependencies, focused PHP/admin tests and Chromium; see October 27 recovery report | Review/save installation/startup and exact egress additions; confirm Only me visibility and publish. Fresh-task restoration remains unverified. The old-runtime failure remains historical and was not repaired by this new setup. |
| Source documents — engineering | Historical hosted upload/reload/server hash; fresh disposable cloud upload → reload → authenticated browser download matched all 77 bytes and SHA-256, with scoped denial probes and retained source hashes/screenshot | Hosted browser-to-disk proof still needs authorized current hosting access; cloud results do not establish it. |
| Targeted usability — engineering | Prior pickup/delivery persistence; new source workflow passed required-field keyboard focus, keyboard retrieval/download and 390px/320px overflow checks | Expand owner/device/accessibility acceptance as separately scoped; this limited source workflow is not a complete accessibility audit. No reproducible UX defect was established. |
| Packaging — engineering | Pinned source and asset checks, synthetic MySQL/MariaDB restore jobs succeeded | Keep archive immutable and source-bound. These packages remain source-only, not deployment authorization. |
| Owner walkthrough/demo — owner / Dr. Ghadeh | Updated evidence walkthrough exists; no acceptance disposition is recorded | Review current versus planned features and record pass/fail or accepted limitations. Any demo video must label synthetic fixtures, simulated hardware and unverified gates truthfully; no completed video is claimed here. |

## Hosting and recovery gates

| Gate / accountable owner | Historical evidence | Remaining blocker / next action |
| --- | --- | --- |
| Private synthetic runtime — owner / hosting operator | October 22 login and October 23 private frontend/backend gateway/filter evidence supersede “backend .invalid” and “no backend routing/provisioning evidence” as current status | Obtain authorized current nonsecret health, TLS/origin, private-storage denial, access and quota evidence. No current GoDaddy health check was performed in this reconciliation. |
| Hosted backup/recovery — operator / security / engineering | Disposable SQL/private-file rehearsals and CI recovery passed; not proof of hosted recovery | Supply actual isolated synthetic backup/restore, retention/recovery objectives, monitoring and rollback evidence. Never restore over the demonstrated database or infer backup health from packaging. |
| Capacity and release operation — operator / owner | October 23 handoff reports cPanel 247,804/250,000 entries, superseding October 21 quota for that observation | Recheck current capacity before any authorized deployment; remove no unrelated files. Scope decision, rollback readiness and explicit go/no-go remain required. |

Owner acceptance is separate from engineering and hosting evidence. The prior hosted email-code sign-in observation and seeded cloud sessions do not provide complete current authentication/session acceptance. No conditional date automatically authorizes a synthetic launch.

## Separate real clinical and vendor gates

These remain closed. Synthetic pilot acceptance cannot authorize patient data, provider transactions, live dispensing or financial movement. Sterile and nonsterile compounding are both confirmed product scope; neither has per-site clinical acceptance.

| Due / gate | Accountable owner | Required acceptance evidence | Blocker | Next action |
| --- | --- | --- | --- | --- |
| Oct 24–25 / hosting and data | Hosting operator / security owner | Verified supported runtime, private object/file storage, least-privilege access, approved secrets/services, encrypted backup and isolated restore, retention/recovery objectives, monitoring and rollback | No verified hosting/data-plane evidence; no production access authorized | Supply nonsecret design/evidence and authorize a separately scoped deployment only after review |
| Before any clinical use / sites | Dr. Ghadeh / licensed site professional / owner | Named sites and jurisdictions, facility and professional license verification, site privileges, workflow and schedule-specific policies, lawful catalogue/formulary/units | Site capabilities, jurisdictions and professional acceptance outstanding; recorded license text or assignment rows are insufficient | Provide site matrix and accountable sign-offs; map requirements without inventing clinical policy |
| Before provider transactions / interfaces | Owner / contracted providers / engineering | Named e-prescribing, drug-reference, PDMP/MAPS and payer vendors; contracts, nonsecret interface specifications, approved sandbox integration and vendor certification | Vendor selection/contracts/interfaces and acceptance absent | Obtain specifications; build against synthetic contracts; no external provider calls under current authorization |
| Before compounding / clinical acceptance | Dr. Ghadeh / site professional | Separate sterile and nonsterile facility/process acceptance, formulation/quality/BUD approvals and hazardous handling classification, qualified personnel and applicable monitoring | Both workflows remain preview-only; no approved site-specific clinical matrix | Collect professional requirements and acceptance, then implement/verify scoped contracts |
| Before dispensing / physical and financial acceptance | Site professional / operations / finance | Physical label/printer/scanner and handover validation; approved inventory/reconciliation, claim/payment service configuration and financial controls | Browser/API tests cannot establish device, clinical, provider or financial acceptance | Perform supervised acceptance only under separate authorization; keep release gates false |
| Oct 26–27 / live go-no-go | Owner / clinical / hosting / security owners | All applicable above evidence plus source-bound candidate, approvals and operational runbooks | Full-product backlog remains substantial; no live release authorized | Explicitly defer live operations unless every applicable gate passes; never substitute the synthetic pilot decision |



## Historical hosting notes — retained without updating their dates

The October 21 and October 22 entries below record what was known then. Their backend/provisioning unknowns and next-action rows are superseded by the evidence baseline and current registers above where they conflict. They do not describe current health or prove restored backups.

## October 21 split-hosting evidence

Owner confirmed managed Node included with Deluxe, EMA uses1/10 slots (nine free), latest cPanel usage235,306/250,000. Primary prep is managed Node22 admin + cPanel PHP/MySQL/API/static; see deploy/production/GODADDY-SPLIT-PILOT.md and its footprint JSON. Single cPanel release fits11,565/14,694 entries; two extracted releases do not fit. This does not pass hosting/readiness gates: terms/app creation, isolated preview origins without DNS change, managed quota accounting/runtime, actual PHP/MySQL/private storage and rollback proof, synthetic user/MFA provisioning and owner acceptance still required. No old-server, DNS, EMA, live-data or clinical changes occurred.

## October 22 isolated backend preparation

Owner-observed managed app z1izdz2qau is Healthy/InfraOK/SiteOK and Chrome rendered `/auth/login` using source5192aba archive SHA25670a3d1b7bb08a0cc642e448beb752a6bedff7f0d32512ce9f70f0da9f5fd47d1. This supersedes the preceding managed-app startup unknown only; no backend or clinical gate passes from this result.

| Item | Owner | Acceptance evidence | Blocker | Next action |
| --- | --- | --- | --- | --- |
| Existing managed preview startup | Owner, observed | Healthy/InfraOK/SiteOK and Chrome login from exact5192aba ZIP | Backend still .invalid | Retain app, no duplicate deployment |
| Native backend packaging | Engineering / local operator | Committed staging helper, locked production Composer recipe, repeated archive hashes and readonly verification | Local native PHP/Composer availability not yet verified | Follow deploy/production/GODADDY-BACKEND-HANDOFF.md; use existing immutable bed4caa artifact if rebuilding is unnecessary |
| Private backend routing | Owner / hosting | Not yet verified | No confirmed protected upstream reachable from managed Node | Inspect cPanel isolated routing/TLS/access restrictions before proxy implementation; do not expose public endpoint |
| Host/private-storage/MySQL acceptance | Owner / hosting, engineering tooling | CLI-only readonly probe and tests; no host execution claimed | Actual web PHP, least privilege DB, private HTTP denial, current quota | Run approved read-only checks on selected isolated namespace |
| Rollback / full browser acceptance | Owner + engineering | Archive verifier creates zero extracted entries; historical role tests remain source-bound | DB/private-file restore and integrated hosted login/MFA/documents untested | Rehearse restore and selected synthetic journeys only after isolated backend gates |

