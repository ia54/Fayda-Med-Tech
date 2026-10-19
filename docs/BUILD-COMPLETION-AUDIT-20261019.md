# Build audit and finish plan — October 19, 2026

Audited source: **`c6d0db736440cb4646d57f2103eaaaedad4213ba`**, PR #2, `codex/cloud-pharmacy-acceptance`. Owner decision during this audit: **synthetic pilot first, then the complete product**. Controlled medications, multi-location operation and both sterile and nonsterile compounding remain in the full product. Hazardous handling and each site's actual capabilities require explicit decisions.

## Decision summary

| Finish line | Remaining engineering work | Delivery interpretation |
| --- | ---: | --- |
| Facilitated synthetic pilot | **7.75–17.75 engineer-days**, budget **8–18** | Roughly **2–4 working weeks with one dedicated experienced engineer**, excluding owner/network waiting. A repeatable synthetic walkthrough with truthful data and explicit limitations; no production access needed. |
| Complete full product, additional after pilot | **123–241 engineer-days** before reserve; **153.75–301.25 with 25% reserve**, budget **154–302** | A substantial remaining program involving clinical rules, real release/custody, provider interfaces and operational acceptance. Vendor, clinical and hosting waiting is separate and currently unquoted. |
| Production activation | Gated by full-product acceptance, recoverable deployment and explicit owner/professional approval | No launch date can be committed until site/provider/certification/access lead times are known. |

An engineer-day means **8 focused engineering hours**, including relevant implementation, tests, repair and evidence. It is an effort unit, not an estimate of autonomous assistant wall-clock speed. Ranges are an inspection-based planning baseline, not fixed-price quotes. They assume reuse of existing code and one primary interface per provider category. The pilot includes a 1–2 day defect/retest allowance; the full-product total applies a separate 25% reserve once, without adding the pilot effort again.

The lowest-effort path is to finish the existing pilot workflow, remove unsupported/invented displays, repair access defects and avoid new integrations or new feature modules before the demonstration. Cloud publication is a separate support gate; a recorded/facilitated current-runtime pilot can proceed without production hosting. Current-head security/CI verification must still be obtained through permitted access or independently verified hosted results.

## Audit method and evidence limits

This is a source-and-evidence completion audit. It reconciles current source, route guards, service and test inventories, the handoff/worklist, release records, professional/provider dependencies and the three cloud acceptance reports. Inventory: **129 admin page source files, 59 pharmacy service files, 137 migrations, 29 feature-test files and 269 pharmacy test methods**. These are inventory counts and provide no code-completion denominator.

New work executed for this audit: one isolated in-memory analytics diagnostic (**1 test / 7 assertions**, deliberately confirming an unsafe current behavior), fresh admin/public dependency audits, and an attempted locked Composer audit. No broad suite was rerun for this planning-only change. The latest existing source-bound results remain **454 tests / 9,263 assertions**, focused authentication **23 tests / 221 assertions on each of SQLite/MySQL/MariaDB**, selected browser acceptance and verified source packaging. They do not establish untested screens, current external advisory clearance, production suitability or clinical acceptance.

The [evidence index](evidence/BUILD-AUDIT-20261019.json) records inspected-source hashes, advisory details and raw artifact hashes. [The diagnostic](evidence/BUILD-AUDIT-20261019-analytics-diagnostic.txt) retains the exact synthetic reproduction and output. The external evidence archive is `/workspace/.cloud-tools/build-audit/build-completion-audit-20261019.tar.gz`; its digest is recorded in the index. No PHI, production credentials, hosting access, live dispensing, external provider calls, messages or funds movement were used. This is not an exhaustive penetration test, clinical validation or legal/compliance opinion.

## What is already implemented and worth preserving

- Tenant/role/site boundaries, private records, patient/location enrollment, case and document infrastructure, financial histories and record-only receipt corrections exist. Current targeted role/browser evidence and full request tests are retained.
- Ordinary prescription intake, source retention, nonrestricted authorization quantities, stock reservations, reviewed labels, preparation, dispatch, delivery exceptions, loss/recovery and separate patient-return custody have substantial implementation and synthetic tests. Several final browser/operational branches still need acceptance.
- Internal ordinary prescription transfers already preserve original records and exact remaining allowances. Rebuild only missing restricted/external paths and conflicted correction cases.
- Formulations, ingredient receipts/reservations, execution, incidents, yield/consumption corrections, quality protocols/results, pharmacist-supplied dating evidence, packaging/custody/repackaging, container suitability and label ledgers exist. Their documentary chain is substantial reusable work.
- Compounded reservation/handover is an **isolated rehearsal**. It deliberately creates no real inventory reservation, fill or release. The real operational lifecycle is still a major feature package.
- Real browser password/MFA enrollment, one-use recovery, logout, controlled refresh/expiry and fixture-link password reset now pass without seeded authenticated sessions. Earlier financial/compounded journeys retain their distinct source pins and seeded-authentication limits.
- Report totals, settlement corrections and an actual Chromium CSV download have retained evidence. This does not validate every analytics screen or every financial query.

## New findings and priority

| Finding | Evidence and impact | Priority / work package |
| --- | --- | --- |
| **Client analytics discloses unassigned financial aggregates** | A client with `/api/invoices` total **0** receives `/api/billing/analytics` denial rate **50%** for inaccessible organization invoices. Billing routes include clients; analytics lacks the client assignment restriction. Confirmed by fresh synthetic request reproduction. | **Pilot blocker, high. P01.** Repair the API permission/scope, not only the navigation. Add correctness regressions before considering it closed. |
| **Fabricated analytics and subscription receipts** | Analytics hardcodes **14.5 days**, fallback **78.4%**, and named payer claim counts. Firm Billing & Plans creates two **$299 Paid** transactions and future billing dates without recorded evidence. Analytics Filter/Export buttons lack handlers. The recovery query also filters month without year. | **Pilot blocker for exposed pages. P02.** Use source-backed values with a stated basis; show unavailable where data is absent; remove unsupported controls. No need to build commercial subscription collection. |
| **Fresh dependency advisories supersede old clean/low-only notes** | Admin audit: **3 high, 2 moderate, 3 low** advisory findings. Public audit: **1 high**, sharing source-map-js with admin. API advisory status is unknown because Composer's security-advisory request was blocked. Findings are package-advisory results; runtime exploitability needs assessment. | **P07 before candidate freeze.** Scoped patches and relevant build/security regression; residual findings require explicit disposition. |
| **Whole pharmacy subsystem remains preview-only** | `PharmacyPreviewOnly` returns 503 outside local/testing. Controlled/compounded fulfillment remains blocked. Handover rehearsal explicitly returns `release_enabled=false` and avoids stock/fill writes. | Correct safety boundary, **not a defect to remove wholesale**. F08–F19/F30 provide missing capability and accepted per-site enablement. |
| **Provider paths are incomplete or unaccepted** | No established e-prescribing, licensed screening, MAPS or payer transport acceptance. AI has validators/retained human review, not an approved dispatch/application pipeline. Google Vision/DocuSign support is not evidence of a configured accepted pharmacy connection. | F10–F13/F21–F25; contracts and sandbox/certification inputs first. Never procure replacements before inventorying existing accounts. |
| **Release/checklist statements are stale** | `launch-readiness.json` refers to `914ae750` and uncommitted 303-test work; current audited source is `c6d0db7` with 454-test evidence. Some pharmacy sections still call formula/ingredient/batch or label integration “next” despite existing services and later evidence. | Use this reconciled backlog for planning. Preserve historical source claims; do not recreate completed ledgers or count old gate statuses as current progress. |

### Dependency remediation candidates

Fresh registry results report patches for **DOMPurify 3.4.15 → ≥3.4.16**, **Next 15.5.26 → ≥15.5.27**, **source-map-js 1.2.1 → ≥1.2.2**, **sharp 0.35.4 → ≥0.35.5**, and **braces 3.0.3 → ≥3.0.4**. These are minimum versions reported by this audit, not an instruction to upgrade indiscriminately. Check compatibility and lockfile paths. Braces is in the lint toolchain; sharp is optional; source-map-js spans build/runtime dependency paths. Assess applicability rather than claiming every advisory is remotely exploitable. Quill 2.0.3 has a low advisory with no patched version reported; existing sanitization needs renewed verification and a recorded disposition. Do not suppress findings to turn checks green. Lockfiles were **unchanged** during this audit.

## Measurable progress baseline

The new pilot has **12 explicit evidence gates: 6 met at their retained source pins, 3 partial, 3 open**. That is **50% baseline checklist coverage**, with unequal work behind each gate; it does not mean half the code or half the remaining effort. Final replay and owner acceptance are separate gates. The exact definitions are in [the backlog JSON](BUILD-FINISH-BACKLOG-20261019.json).

| Status | Gates |
| --- | --- |
| Met baseline | G01 scope/exclusions; G02 synthetic runtime/locks; G03 pinned backend/source baseline; G04 real auth browser; G05 financial/settlement browser baseline; G06 report/CSV baseline |
| Partial | G07 current private-document lifecycle; G08 current EOB/appeal branches; G11 concurrency and full pilot responsive/accessibility checks |
| Open | G09 truthful displays and complete access sweep; G10 fresh dependency disposition; G12 one-candidate final replay and owner acceptance |

The old 20-gate launch tracker has 4 passed, 6 in progress and 10 blocked **at an older baseline**. It is not a valid current overall completion percentage. A full-product earned-effort percentage cannot be reconstructed from file counts or passing tests. Start tracking remaining effort, package acceptance and dated forecast changes from this audit instead. Keep failed or unexecuted acceptance cases open.

## Sized backlog and completion rules

The [full table](BUILD-FINISH-TABLES-20261019.md) lists all **43 remaining work packages**. The [CSV](BUILD-FINISH-BACKLOG-20261019.csv) and [JSON](BUILD-FINISH-BACKLOG-20261019.json) include ranges, dependencies, accountable parties, done criteria, source evidence, confidence and required decisions. They separate work already supported by code from missing implementation and acceptance. Dependencies form an acyclic graph; external waiting is identified rather than disguised as coding effort.

Pilot packages P01–P12 total **7.75–17.75 days**. Full-product packages F01–F31 add **123–241 days**, or **153.75–301.25** with the explicit reserve. The full-product number includes ordinary exception gaps, controlled authorization/custody/reporting, real compounded release, separate sterile/nonsterile/hazardous controls, devices, provider adapters, reliability, per-site UAT and safely gated deployment. It does not remove required sterile/nonsterile or controlled scope to make the plan appear shorter.

### Capacity scenarios

| Dedicated engineering capacity | Pilot workload | Additional full-product workload with reserve |
| --- | --- | --- |
| 1 engineer | ~2–4 working weeks | ~31–61 engineer working weeks |
| 2 engineers | Ideal workload floor ~1–2 weeks; plan ~1–3 for serial replay/review | ~16–31 team working weeks as an ideal capacity floor |
| 3 engineers | Little benefit below the serial acceptance/review path | ~11–21 team working weeks as an ideal capacity floor |

These are capacity calculations using five working days/week, not launch promises. Additional people do not compress pharmacist qualification, vendor certification, access recovery or site appointments. No team of that size is assumed to exist or has been engaged. Quote calendar dates only after actual capacity and external lead times are confirmed.

## Fastest practical execution order

1. **Close the pilot's newly found blockers first:** P01 analytics authorization, P02 fabricated displays and P07 dependency findings. Keep excluded integrations unavailable. Server-side access denial is mandatory even if a screen is removed from the demonstration.
2. **Run two independent engineering lanes if capacity exists.** Lane A handles access/data truth and dependency repair. Lane B prepares repeatable document/EOB/appeal/browser/session edge fixtures. Both merge at P06/P08 final responsive and role replay. Avoid parallel edits to the same shared auth or tenant policy.
3. **Freeze once for the pilot.** P08 → P09 → P11 is the closing sequence: one candidate, applicable CI/builds/archives, short owner walkthrough, explicit pass/fail feedback. Run focused checks during repair; broad suites only after material changes or at candidate freeze. Do not rebuild all 59 pharmacy services or add unrequested dashboards to make the demo larger.
4. **Resolve full-product inputs while pilot engineering proceeds.** Obtain the site/provider/device matrix, accepted clinical workflow cases, professional approvers and hosting recovery owner. Do not wait until after the demo to discover a vendor's certification queue. P10 cloud reuse/network readiness runs in parallel where supported.
5. **After pilot, build against approved contracts.** Site/rule/catalogue foundations feed clinical/eRx/MAPS, controlled custody, compounding release and payer lanes. Reuse existing reviewed ledgers. Use synthetic deterministic adapters first; external sandbox/production access requires separate authorization and approved processing arrangements.
6. **Integrate before full UAT.** Close supply/recall/custody and provider failure scenarios, device acceptance, concurrency/outage behavior and fail-closed site enablement. F28 host/restore preparation can begin once separately authorized, in parallel with feature work. F29 accepted end-to-end site UAT precedes F31 controlled cutover and final HTTPS checks. No release gate is cleared by an engineering checkbox alone.

### Immediate pilot queue

| Order | Package | Concrete next result |
| --- | --- | --- |
| 1 | P01 | Failing correctness test for client analytics; scoped/denied API and regression |
| 2 | P02 | Source-backed/unavailable analytics and subscription display; no invented receipts or inert actions |
| 3, parallel | P07 | Minimal reviewed dependency patches, current advisories and targeted rich-text/build checks |
| 4 | P03/P04/P05 | Missing document, EOB/appeal and concurrency/recovery cases with fresh synthetic evidence |
| 5 | P06/P08 | Pilot viewport/keyboard and all-role replay on the integrated candidate |
| 6 | P09/P11 | Exact source/check/build package and owner walkthrough/acceptance |

## Owner and professional inputs needed now

Names/references are enough for planning; no passwords, keys or PHI belong in the intake.

| Input | Accountable party | Why it affects the forecast |
| --- | --- | --- |
| Actual locations, jurisdictions, PIC/site leads, controlled schedules, sterile/nonsterile capability and hazardous scope | Dr. Ghadeh + site leads | Site count and applicable processes change rules, configuration, physical testing and UAT effort |
| Existing eRx, drug reference, MAPS submission/query, payer, OCR/AI and signing providers/account owners | Dr. Ghadeh + billing lead; Abe for commercial/AI decisions | Prevent duplicate procurement; obtain real interfaces, sandboxes, certification lead times and coverage |
| Approved procedures, formulations, calculations, QC/BUD references, labels and acceptance scenarios | Responsible pharmacist/compounding lead | Defines executable clinical requirements; coding cannot choose unknown thresholds or release authority |
| Printer/scanner/container/label formats and expected daily volumes | Site leads | Device adapters, performance test volume and per-site acceptance are otherwise unsized |
| Pilot attendee/feedback owner; support, security, retention, backup/RPO/RTO and release owners | Abe + pharmacist + operations | Determines acceptance turnaround and operational requirements |
| Authenticated hosting recovery and permitted staging/deployment plan | Hosting administrator + owner | Required for production inventory/restore/real-origin acceptance; not requested or accessed in this audit |

## Forecast uncertainty and change control

- Full-product estimates have **low confidence** until F01 closes. Assumptions are one main prescribing interface, clinical reference, MAPS submit path plus separate query, payer transport, AI/OCR path and initial device family. Multiple payers behind one supported transport differ from multiple proprietary transports.
- The actual number of sites is unknown. F29 includes one representative acceptance cycle and engineering support, not unlimited site rollouts. Site/jurisdiction/device expansion must be added explicitly after inventory.
- Procurement, provider certification, facility/personnel qualification, licensed standards, formal independent legal/security/compliance reviews, professional testing labor, hardware, hosting and service costs are **not quoted** in engineer-days. Their calendar waiting is unknown, not zero.
- P12 and the full-product reserve cover ordinary discovery/retest, not new jurisdictions, rewritten workflows, unavailable interfaces or changed product scope. Re-estimate those changes rather than consume a hidden buffer.
- On package closure, record accepted commit, exact tests/browser/site evidence, approver, actual effort and remaining estimate. Reforecast weekly and after provider/site decisions. Track pilot and full product independently so a polished demo cannot be reported as pharmacy launch completion.

## Environment and publication status

Runtime execution is verified; reusable draft revision 10 was saved for `c6d0db7`. Final filesystem publication and independent fresh-task restoration remain unverified. The current runtime reports its original configuration version. Saved custom domains include GitHub API and Packagist, but this runtime still blocks those requests. The owner previously clicked Publish and could not confirm its final state. User Review/Save and Publish remain separate from saving future draft updates. No production credentials or hosting access are needed to execute the pilot repair queue.
