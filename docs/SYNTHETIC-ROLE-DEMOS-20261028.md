# Actual platform role demonstrations — October 28, 2026

These are brief **silent, read-only recordings of the real built application**, not mock screens. The build used Next.js 15.5.28, application sources unchanged from `7bce3ca7a10b47ac88a61e0a8273250b7ea9dfbf`, build ID `wlAhq8PHITSqormb8Nn5B`, and Chromium 154.0.8037.57. Fresh synthetic accounts/sessions came from the existing guarded `CloudSharedRoleFixtureTest`; they are not hosted accounts and do not prove password login or MFA. No pharmacist privileges or case/site assignments were broadened.

| Role / existing fixture | Routes shown | Recording |
| --- | --- | --- |
| Provider staff / `provider@example.invalid` | Provider claims and documents | [Provider MP4](evidence/coordinated-recovery-20261028/videos/synthetic-provider.mp4) |
| Medical biller / `biller@example.invalid` | Billing invoices and analytics | [Biller MP4](evidence/coordinated-recovery-20261028/videos/synthetic-biller.mp4) |
| Attorney / `attorney@example.invalid` | Legal cases with assigned synthetic case | [Attorney MP4](evidence/coordinated-recovery-20261028/videos/synthetic-attorney.mp4) |
| Firm admin / `firm_admin@example.invalid` | Legal cases and billing/plans | [Firm-admin MP4](evidence/coordinated-recovery-20261028/videos/synthetic-firm_admin.mp4) |

The videos run approximately 4.6–8.6 seconds each, at 1280×900 and 5 fps. Chromium CDP supplied actual live rendered frames to system FFmpeg; MP4 conversion preserves those screens. No substitute UI, data overlay, narration, audio or invented workflow was added. Visible empty states and existing synthetic rows are the application's actual responses. This short tour does not establish every role's full workflow or operational usability.

All four roles returned 200 for case listing, 404 for the foreign organization's case, and 403 for pharmacy source access. Their contexts attempted **zero write requests**. The harness blocks writes and all external requests, and compares cases, invoices, payments, documents, pharmacy sources and fills before/after; all were unchanged. No provider request, payment, dispensing action or hosted account creation occurred. [Exact results](evidence/coordinated-recovery-20261028/role-demo-result.json) and [video metadata/hashes](evidence/coordinated-recovery-20261028/video-manifest.json) retain the observed scope.

## Run the same synthetic roles

Use the existing cloud checkout; no worktree or new public port is needed. Check the existing 3000/8000 listeners first; never stop unrelated services.

```sh
source /workspace/.cloud-tools/env.sh
cd /workspace/Fayda-Med-Tech
bash deploy/cloud/start-synthetic.sh /workspace/.cloud-tools/<new-unique-role-fixture> shared
# In another terminal, use the supported existing development startup:
bash deploy/cloud/start-admin.sh
# Then from admin-panel, record and verify the read-only role views:
FAYDAMED_BROWSER_FIXTURE_DIR=/workspace/.cloud-tools/<new-unique-role-fixture> \
  node tests/browser/role-demo.mjs
```

`ordinary` remains the default startup fixture. Optional `shared` selects the existing shared-role seed (1 test / 16 assertions), exports `shared.sqlite` and fresh role sessions, and otherwise uses the same protected storage, known synthetic signing-key provenance, mail/cache/session arrays and stray-HTTP guard. It refuses existing directories. Tokens are short-lived; regenerate rather than reuse historical sessions. Do not copy session files or keys into Git or share them as login credentials.

The recorded run used the existing **standalone production build**, not the development server. To reproduce that variant, build with local synthetic API origins, copy generated public/static assets into `.next/standalone` per Next.js instructions, and run `HOSTNAME=127.0.0.1 PORT=3000 node .next/standalone/server.js` instead of the development startup. This uses the same existing ports; it is not a deployment.

Interactive UI access is through an authorized browser executor inside the cloud runtime at the existing loopback application/API addresses. No public preview link or hosted access is created here. The supplied MP4s allow review without expanding network access. Stop only the recorded test container after recording; its database/storage fixtures remain outside the container, and `--rm` removes the stopped process layer.

The existing shared-role seed also has platform-admin, client, pharmacist and technician sessions. Only the four requested roles were recorded in this run. The older `shared-role.mjs` journey records synthetic financial transactions; it was deliberately **not run** under this read-only/no-payment scope. Controlled and compounded release remains disabled.
