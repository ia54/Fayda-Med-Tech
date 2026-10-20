# Release preparation and owner pilot acceptance — October 20, 2026

## Decision and scope

Ibrahim accepted the **synthetic pilot** on **2026-10-20 at 08:05:46 UTC**: “Accept pilot for MedTech and let’s prep for release now”. This is owner acceptance of the synthetic pilot and authorization for release preparation. It is not residual-security-risk acceptance, clinical/site acceptance, production deployment authorization, expanded persistent access or authority to enable real dispensing. Controlled and compounded real dispensing remain disabled. No real patient data, external messages, provider calls or funds movement were used.

At 08:09:53 UTC Ibrahim requested verification and a recorded demo. The resulting recording is for owner review; it has not been sent to partners or publicly shared.

## Source reconciliation and hosted evidence

The saved environment started clean at `74d4c9f`. No AGENTS.md was found in the workspace/repository; the existing cloud handoff and release/provider guidance were reviewed. The local head was verified as an ancestor of PR #2 and fast-forwarded to `ce839f40d36656c147166d63c88689071ee40617`, then branch `codex/release-prep-20261020` was created. No reset or removal of existing work occurred.

GitHub was queried directly on October 20. PRs [#1](https://github.com/ia54/Fayda-Med-Tech/pull/1) and [#2](https://github.com/ia54/Fayda-Med-Tech/pull/2) remain OPEN and DRAFT. All six PR #2 checks passed at the exact head:

- [Backend](https://github.com/ia54/Fayda-Med-Tech/actions/runs/39307195215): PHP 8.3, MySQL 8, MariaDB 10.11 and pinned source package.
- [Admin](https://github.com/ia54/Fayda-Med-Tech/actions/runs/39307195686): session checks.
- [Production-origin web candidate](https://github.com/ia54/Fayda-Med-Tech/actions/runs/39307195226): Linux assets.

The historical full suite remains **458 tests / 9,339 assertions** and five browser replays across eight roles. Those counts are not newly rerun full-suite results. This preparation changes documentation and a manual recording harness, not application behavior.

## Fresh-environment verification

The saved runtime, synthetic Passport-key fingerprint and retained dependencies were available in this independent task. The reusable installer completed Composer installation/platform checks, frozen pnpm install, npm ci and existing synthetic migrations. The prior services were stopped; processes had to be started explicitly. A fresh guarded `CloudPilotFixtureTest` seed passed **1 test / 19 assertions**, and the current optimized admin build generated **120 routes**, including successful TypeScript checks.

The `pilot-records.mjs` Chromium replay passed: actual EOB/appeal UI operations, retained error/retry behavior, private PDF upload/preview/download, legacy fallback, anonymous/foreign-client denial, revoked assignment denial, five mobile keyboard/layout checks, and unchanged payments/stock/fills. No external hosts were contacted. This replay uses pre-authenticated synthetic sessions; it does not repeat login/MFA acceptance. Admin session (5), recovery (2), documents (2), reports (2) and pharmacy (13) tests passed. Shared rich-text security tests (4), release/source-packaging Python tests (18) and web-asset tests (6) passed. Public-site npm audit reports zero vulnerabilities. Both disposable database engines passed 141-table restore and private-file byte comparison; see the recovery rehearsal report.

Local UI: `http://127.0.0.1:3000`; synthetic API: `http://127.0.0.1:8000`. Both are bound to loopback. These are verified internal runtime addresses, **not partner-accessible URLs**. The viewing method is an owner-reviewed downloadable recording or a facilitated screen share. No public listener, tunnel, new account, credentials or sharing grant was created. Process lifetime is not guaranteed after the task ends.

Fresh-task restoration of this saved snapshot is verified. **Publication of environment draft 12 remains unverified**: this task did not establish its published revision or invoke Publish. If the environment UI requires owner-only Publish, Ibrahim must review and publish the selected configuration; this successful restore does not prove draft 12 was published.

## Dependency disposition

Current locked Composer audit: **zero security advisories**, `doctrine/annotations` still abandoned. Current admin audit: **braces 3.0.3 high**, development lint chain, GHSA-vfj7-8cjw-p6xm; **Quill 2.0.3 low**, GHSA-v3m3-f69x-jf25. Registry queries still return braces 3.0.3 and Quill 2.0.3 as latest. The advertised braces >=3.0.4 fix is unavailable, and no Quill patch is published. No unavailable override, advisory ignore, package substitution or unreviewed risk acceptance was added. Existing DOMPurify mitigation is retained; it is not a package fix. Replacement of the editor or lint tooling would require a separately tested change rather than an unrelated release-preparation upgrade.

The dated pilot gate snapshot is now **11/12 evidence gates met**: G12 has the existing final replay, exact hosted checks and dated owner acceptance. G10 dependency disposition remains open. This is not a product-completion percentage or permission to release. Publication and operational gates remain separate.

## Remaining release inputs

| Required input/evidence | Responsible owner | Current state |
| --- | --- | --- |
| Review and approve exact candidate; review draft PRs | Ibrahim/reviewer | Pending, no merge performed |
| Fix or explicit authorized disposition of residual advisories | Security/release owner | Pending; not accepted by agent |
| Approved authenticated production access and independently verified host identity | Hosting administrator | Pending; no host probing/authentication attempted |
| Current host inventory, safe isolated restore target, backup/recovery owner and RPO/RTO | Hosting administrator + Ibrahim | Pending |
| Existing provider names, account owners and site coverage | Dr. Ghadeh + billing lead | Pending |
| Prescribing, clinical reference, MAPS, payer, AI/OCR, signatures and mail test acceptance | Responsible owners/providers | Unverified; not portrayed as connected |
| Site capabilities, sterile/nonsterile/hazardous procedures, devices, clinical UAT/training | Dr. Ghadeh + site leads | Pending |
| Separate production cutover and any dispensing enablement authorization | Applicable owner/professional | Not granted by synthetic pilot acceptance |

See [migration/recovery plan](../deploy/production/RECOVERY-REHEARSAL-20261020.md), [provider register](../deploy/production/PROVIDER-OPERATIONS-REGISTER.md) and the [remaining development backlog](BUILD-FINISH-TABLES-20261019.md). There is no calendar delivery promise: vendor, host and clinical inputs remain unconfirmed.

## Recorded demo

See [owner-review demo and facilitated agenda](PARTNER-DEMO-20261020.md). The MP4 is a separate private Library deliverable, not a public application endpoint.
