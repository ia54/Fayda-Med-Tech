# Authoritative continuation handoff — 23 October 2026

Continue FaydaMedTech development in a cloud coding environment so execution does not depend on the owner's Mac. Repository: ia54/Fayda-Med-Tech. Starting branch: codex/pilot-gateway. Latest development commit before this handoff: 3787f84.

Read applicable AGENTS.md instructions and the existing CODEX-CLOUD-HANDOFF.md. This dated handoff supersedes older progress claims where they conflict. Preserve existing branding, data history and unrelated work. Work through actionable development and verification tasks; ask only for genuinely necessary owner decisions. Do not claim unattended execution beyond the actual running task.

## Verified baseline

- Private GoDaddy synthetic pilot frontend: https://z1izdz2qau.preview.c36.airoapp.ai . Backend: pilot-api.faydamed.tech, private gateway. No real PHI or clinical launch authorized.
- Follow-up-filter UI from 9f67e00 is deployed and browser verified. Pending filtering returns SYN-RX-001; resetting returns three prescriptions.
- Source-document controller returned 1,989 bytes with SHA-256 b646e6e60661a4570ba83f9b04c8120e4bbab18790cc536e966430309406efd7. Browser-to-disk download verification remains incomplete.
- Hosted in-memory SQLite HTTP-kernel rehearsals passed: 48 assertions for dispensing through recorded payments; 37 for patient-chart boundaries and review invalidation. They used injected synthetic identities, not real browser login or hardware. The corresponding PHPUnit tests were extended, but PHPUnit was not run because development dependencies were unavailable locally/on the pilot host.
- Read docs/PHARMACY-PAYMENT-REHEARSAL-20261023.md and docs/PHARMACY-CHART-REHEARSAL-20261023.md for precise scope.
- Chrome patient chart readback confirmed unknown allergy/medication status and no review date; saved pilot records were not changed by rehearsals.
- GoDaddy preview sleep stalled once; a single private-preview restart restored it. Shared cPanel file usage was 247,804/250,000; do not remove unrelated files.

## Next work, in order

1. Install repository-pinned development dependencies in the isolated cloud environment. Use disposable SQLite and existing cloud/browser fixtures; inspect package manifests, CI and test safeguards before running. Run the two extended PharmacyWorkflowTest methods through PHPUnit and address actual failures.
2. Run the existing cloud browser acceptance setup and extend the ordinary pharmacy journey through clinical-chart review, product verification, label preparation, collection/delivery, medical-biller handoff and recorded payment. Use explicitly synthetic fixtures. Verify persistence, role/location boundaries, audit history and inventory only once. Retain screenshots and exact test results.
3. Fix reproducible UX defects discovered by those checks; preserve branded UI and distinguish incomplete/unknown states from reviewed evidence. Avoid duplicate implementation of existing workflows.
4. Prepare an updated Dr. Ghadeh owner acceptance walkthrough. Clinical approval, physical devices, provider integrations and production readiness are separate gates; never convert simulated checks into real approvals.
5. Commit source and evidence on an appropriate codex/ branch and prepare/update a draft PR. Do not overwrite concurrent branch work; fetch current remote state first.

## Boundaries and dependencies

- Payments are recording-only, never collection. No real funds, prescriptions, patients, payer transmissions or MAPS submissions.
- Controlled and compounded dispensing remains disabled. Multiple locations must preserve isolation and access assignments.
- AI provider is not selected/configured; GLM was only a possible choice. No patient-data transmission to an AI provider. Current assistant is rules-only.
- Dr. Ghadeh is the pharmacist/co-owner for operational acceptance. Do not contact anyone without explicit instruction.
- Existing production domains faydamed.tech, admin.faydamed.tech, api.faydamed.tech remain unchanged. Do not retry old SSH access, old IPs, proxies or server credentials from conversation history.
- Cloud does not inherit local Chrome/GoDaddy login sessions. Continue independent coding/tests if deployment access is unavailable. Never request passwords in chat or embed credentials in code.
- Owner's existing pending synthetic records are for demonstration; use disposable fixtures for simulated clinical approvals.

## Evidence portability

Source, regression tests and reports are in Git. Original terminal screenshots and standalone rehearsal scripts under the Mac workspace launch/ are not automatically available in cloud. Do not cite them as inspected; reproduce the tests and save new cloud evidence. The reports accurately distinguish previous hosted-harness evidence from fresh PHPUnit/browser verification.
