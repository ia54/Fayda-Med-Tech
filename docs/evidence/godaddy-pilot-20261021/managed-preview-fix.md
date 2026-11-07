# Managed private preview startup — 2026-10-21

Owner-observed GoDaddy app z1izdz2qau invokes `npm run dev` and failed because that script was absent. The deployment-only manifest now provides it. The wrapper requires an integer platform-injected PORT in 1..65535, applies the existing verified braces guard, then invokes Next dev with `--hostname 0.0.0.0 --port PORT` and NODE_ENV=development. No fixed host port is introduced. Production build/start and dependency locks are unchanged. The CLI runs in the same process so platform signals reach Next directly.

Validation in the existing cloud runtime, Node 22.23.3:

- `node --test deploy/production/tests/managed_preview.test.mjs`: 3 tests passed, including eight invalid port cases and valid boundary ports.
- Existing extracted managed package with locked dependencies, overlaid with only the updated manifest and new wrapper: `PORT=3000 NEXT_PUBLIC_API_BASE_URL=https://api.pilot.invalid/api NEXT_PUBLIC_API_URL=https://api.pilot.invalid npm run dev` started Next 15.5.27, reported Network http://0.0.0.0:3000 and Ready in 2.4s. Existing owned cloud port 3000 was used only for rehearsal; server stopped afterward.
- Chromium 154 / Playwright: GET http://127.0.0.1:3000/auth/login returned 200, title Fayda Wellness Pharmacy Admin, 3 inputs, visible Sign In button. No credentials submitted or API/provider transaction attempted. An initial `/login` check correctly returned 404; the application route is `/auth/login`.

These are cloud startup/browser observations, not GoDaddy runtime acceptance. Owner must update the SAME isolated app and verify its logs/private preview. No upload, duplicate app, DNS or public deployment was performed here. The placeholder .invalid backend remains intentionally unusable. This development preview does not establish production or clinical readiness. No broad production rebuild was repeated for a development entrypoint-only change.
