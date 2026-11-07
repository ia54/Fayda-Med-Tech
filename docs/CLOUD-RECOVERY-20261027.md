# Recovery continuation — October 27, 2026

Restored from GitHub branch `codex/cloud-recovery-20261023` at `1034e41a9ae9780b133e43e492637a2444b979e2`, preserving the verified `4570e385692e6aa6d68ac2b60ee27b7c6a6760c5` ancestor. Normal `git fetch origin` and an explicit branch fetch succeeded through inherited networking. No old runtime cache, historical SSH credentials, local-computer tools, provider credentials or production data were used. No AGENTS.md was present in the inspected checkout/workspace.

Environment display name was saved as **FaydaMedTech Recovery 20261027**. This does not establish privacy settings, publication, or restoration in a new task.

## Toolchain and repeatable commands

`deploy/cloud/install-recovery.sh` reconstructs Node 22.23.3 (official SHA-256 verification), packageManager-pinned pnpm 11.19.0, PHP 8.3.36 and Composer 2.10.3 (digest-pinned official container bases). The PHP build installs the CI-required extensions. It preserves inherited proxy routing, mounts a trusted CA bundle and keeps TLS and package-signature checks enabled. Dependency manifests and lockfiles are unchanged.

The script stores only nonsecret helpers below `/workspace/.cloud-tools`; dependencies are retained in ignored paths. Docker images, daemon state and running processes must be checked on each new runtime. No worktree is needed: cloud tasks already run in isolation.

- Install/refresh: `bash /workspace/Fayda-Med-Tech/deploy/cloud/install-recovery.sh`.
- Activate: `source /workspace/.cloud-tools/env.sh`.
- Start admin: `bash /workspace/Fayda-Med-Tech/deploy/cloud/start-admin.sh`.
- Start a fresh disposable API fixture: `bash /workspace/Fayda-Med-Tech/deploy/cloud/start-synthetic.sh /workspace/.cloud-tools/<new-unique-directory>`.
- Browser source acceptance: from `admin-panel`, set `FAYDAMED_BROWSER_FIXTURE_DIR` to that fixture and run `node tests/browser/prescription-source.mjs`.

Each fixture exports fresh seeded MFA-attested sessions; this does not verify login or MFA. Its API has private synthetic storage, a loopback published port and a Laravel HTTP-client stray-request guard. Stop only its recorded container after preserving evidence. Never publish sessions, keys or database exports.

## Network and dependency findings

Locked Composer archive installation was denied at `api.github.com`; a Git-source install uses the supported HTTPS proxy with the mounted Git CA. Composer advisory lookup at `packagist.org/api/security-advisories/` was also denied. Both exact host additions were saved in the configuration draft; neither a saved draft nor the runtime's unknown policy-state observation establishes enforcement. GitHub PR API access returned Forbidden. No token was requested merely because GitHub API access differed from Git fetch.

[Current pnpm audit](evidence/recovery-20261027/pnpm-audit.json) reports two critical, three high and one low advisory across Next.js, DOMPurify and Zod. This is a fresh registry result, superseding the earlier one-advisory observation only for this audit run. The local braces patch remains locked and its three regression checks passed. The audit is not clean; passing regression tests do not dispose of these findings or establish exploitability. Dependency upgrades and residual-risk disposition remain separate candidate-review work.

## Validation record

- Admin locked pnpm install: passed, 556 packages, packageManager 11.19.0.
- Public locked npm install: passed, 305 packages.
- Admin protected-document tests: 2 passed; pharmacy tests: 13 passed; session tests: 5 passed; patched-dependency tests: 3 passed; TypeScript check passed.
- Public rich-text security tests: 4 passed; Vite production build passed with its existing large-chunk warning.
- Chromium 154.0.8037.57 rendered the local admin login with a password field. This proves browser startup, not authentication.
- Full historical pharmacy, MySQL/MariaDB, packaging and completed pickup/delivery suites were not repeated without a relevant change.

- PHP locked source install: passed, 142 packages; `composer check-platform-reqs` passed. Source installs emitted existing classmap ambiguity warnings in Flysystem and the Pint development tree; the application provider was selected first. No declarations or locks were edited to suppress them.
- Focused backend: **3 tests / 117 assertions passed** (ordinary workflow, independent patient-chart boundaries/review invalidation, private immutable location-scoped source files).
- Fresh ordinary browser fixture: **1 test / 19 assertions passed**. The first API startup used an internal Docker network whose port was not published by this runtime. That failed connectivity attempt is not an application defect or acceptance pass. The corrected helper uses the supported default bridge, loopback publication and a Laravel HTTP-client stray-request guard. Inherited proxy configuration stays intact; no provider credentials are passed.
- [Fresh browser result](evidence/recovery-20261027/prescription-source-result.json): Chromium 154.0.8037.57 uploaded a 77-byte synthetic PDF, reloaded the retained source, retrieved it using the authenticated application action, and saved a keyboard-initiated browser download. SHA-256 `56d3c3138205d882a3499a87511ce49c1895f1d5a332d9b51700c9783471c792` matches uploaded bytes, database hash and [downloaded artifact](evidence/recovery-20261027/downloaded-SYN-prescription.pdf).
- Source listing and file probes: biller 403; foreign organization 404; unassigned site 404; anonymous file 401. Exactly one source record and one source audit event persisted. Stock, fills, invoices, payments and compounded execution snapshots were unchanged; no observed API response was 5xx. The attempted analytics host was blocked by the browser runner.
- Required-field validation focused the invalid reference field on keyboard submission. Keyboard retrieval/download and 390px/320px overflow checks passed. The [320px screenshot](evidence/recovery-20261027/prescription-source-mobile.png) was inspected. No reproducible application UX defect was established, so application code is unchanged. This is limited workflow coverage, not complete accessibility acceptance.
- The reusable install helper was executed successfully; the acceptance API was stopped after evidence preservation. Startup helpers were then exercised again with a new disposable development fixture and a restarted admin UI. Old fixture/session files were not reused.

[Evidence hashes and sizes](evidence/recovery-20261027/index.json) cover retained artifacts. The browser result records baseline HEAD plus the working-tree additions and complete application source hashes; its runner hash binds the new check. Sessions, keys and database exports remain outside Git.

## Remaining review and publication

The environment configuration draft captures installation/startup instructions and exact required egress additions. Saving is not application of network policy or publication. The platform tools expose no privacy selector; **Only me** visibility must be checked in environment settings before publishing. No new-task restoration is claimed.

Review the fresh dependency advisories and choose upgrade/risk-disposition scope before candidate approval. Composer audit and GitHub PR API access remain blocked by current egress; saved domain additions require review/save in environment settings. Normal Git reads work independently. Hosted browser-to-disk proof remains separate; no hosted access, merge, deployment, provider transaction, real payment or clinical release is authorized or claimed. Controlled and compound release stays disabled.
