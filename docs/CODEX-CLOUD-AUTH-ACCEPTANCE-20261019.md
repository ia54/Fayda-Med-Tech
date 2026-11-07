# Cloud authentication browser acceptance — October 19, 2026

Continued from PR #2 head `797359e` on `codex/cloud-pharmacy-acceptance`. The final Chromium journey runs against clean source `a84538f09211f0cb2d73fdfb6a7faffa8e815dcd`, using fresh isolated synthetic accounts with **zero seeded access or refresh tokens**. This extends the earlier [compounded/shared-role evidence](CODEX-CLOUD-BROWSER-ACCEPTANCE-20261019.md); those earlier browser source pins remain historical and were not relabeled as runs of this middleware change.

## Repair

`CheckTokenExpiration` previously marked an expired access record revoked while rejecting the request. The refresh endpoint correctly refuses explicitly revoked access records, so that side effect also invalidated a still-valid linked refresh credential. The new request-level regression failed before the repair (refresh returned 401 instead of 200).

Expiry now denies access without changing explicit revocation state. Refresh rotation, logout, password reset and inactivity revocation retain their existing behavior. The regression verifies expired access rejection, successful one-use refresh, rejected refresh replay, rejected old access, successful replacement access and inability to restore the logged-out session. No route, schema, dependency lock or clinical/financial authorization changed.

## Browser evidence

Chromium **154.0.8037.57** and Playwright Core **1.56.1** execute actual forms against the local admin/API. The fixture starts with no authenticated session; the shared helper only seeds persisted authentication when the fixture actually supplies a user/token. All browser destinations except the two loopback origins are aborted, including attempted Vercel analytics. API probes initiated by the browser are retained separately from UI actions in the [evidence index](evidence/CLOUD-AUTH-BROWSER-20261019.json).

The completed journey verifies:

- Unauthenticated protected navigation redirects to login; incorrect password is rejected.
- Mandatory administrator MFA enrollment issues no browser session from password alone. The UI shows a setup key, rejects an incorrect authenticator code, accepts the real synthetic TOTP, and requires recovery-code retention confirmation before entering the dashboard.
- UI logout revokes both server access and refresh credentials. An unused recovery code signs in once; reuse fails and a different unused code works. Eight hashed recovery codes remain after the two successful recoveries.
- Controlled server-side access expiry produces a browser refresh, rotates the refresh credential, rejects reuse of the old credential and retains access to the intended workspace. Revoked refresh plus expired access clears local authentication and redirects to login.
- Both unknown and known synthetic addresses receive the generic recovery-request UI. The reset link is issued by the isolated fixture, **not delivered by email**. Its form changes the password, strips the token/email query after success and invalidates the old access/refresh credentials. The old password and reuse of the reset link fail; the new password succeeds.
- A controlled past persisted browser expiry deadline blocks protected rendering and redirects to login. This is distinct from the server-expiry mutation; no one-hour wall-clock or distributed inactivity claim is made.
- Login fits an emulated **390×844** viewport without horizontal overflow. Desktop and mobile-login screenshots are retained. This does not establish full mobile journeys or physical-device acceptance.

Invoices, payments, pharmacy stock/events/fills and settlements retain their initial database hashes. The final run has no API HTTP 5xx responses. No clinical release, dispensing, insurer submission, payment movement, external email or production access occurred.

## Exact checks

| Command | Result |
| --- | --- |
| `cd api && php vendor/bin/phpunit --colors=never` | **454 tests / 9,263 assertions passed**, 4m00.856s |
| `php vendor/bin/phpunit --colors=never tests/Feature/TokenLifecycleTest.php tests/Feature/MfaTest.php tests/Feature/PasswordRecoveryTest.php` | **23 tests / 221 assertions passed** |
| Same three files with `-c phpunit.mysql.xml` in disposable **MySQL 8.0.46** | **23 tests / 221 assertions passed**, 31.726s |
| Same focused MySQL configuration in disposable **MariaDB 10.11.19** | **23 tests / 221 assertions passed**, 16.679s |
| `cd admin-panel && corepack pnpm test:session` | **2 tests passed** |
| `corepack pnpm test:recovery` | **2 tests passed** |
| Explicit auth seed filter `test_seed_cloud_auth_browser_fixture` | **1 test / 9 assertions passed** |

The full SQLite rerun was justified by the global authentication middleware repair. The SQL runs are focused authentication checks, not reruns or replacements of the historical full MySQL/MariaDB financial suites. These are cloud-local results, not verified new-head hosted Actions results.

## Retention and reproduction

The guarded seed is `api/tests/Browser/CloudAuthFixtureTest.php`; the browser runner is `admin-panel/tests/browser/auth-session.mjs`. It requires explicit opt-in, testing/in-memory SQLite, a new directory under `/workspace/.cloud-tools`, and the existing known synthetic Passport public-key fingerprint before using its matching private key. It creates a personal client and a fixture reset link, never pre-authenticated sessions. Setup keys, passwords, reset tokens and recovery codes are not printed or committed; failure captures clear inputs and recovery-code display first.

From the repository root in this prepared cloud snapshot:

```sh
source /workspace/.cloud-tools/env.sh
/workspace/.cloud-tools/run-auth-seed.sh /workspace/.cloud-tools/auth-browser-NEW
```

Run browser fixtures sequentially because they share the isolated local API port. The final data is `/workspace/.cloud-tools/auth-browser-final-pinned/auth.sqlite`; its database/result/source-manifest and artifact hashes are in the committed evidence index. The review archive `/workspace/.cloud-tools/evidence/cloud-auth-browser-a84538f.tar.gz` is **1,429,849 bytes**, SHA-256 **`b35f05921345ec6ce2e498755f8ad3e89391648aea13d28fb857d0bf98f1e52d`**. It includes the database, full result/source manifest, final screenshots, seed/browser output and test logs. It excludes session JSON and signing-key files; retained credential records are synthetic hashes/encrypted synthetic data. Earlier failed/debug fixtures remain outside Git and are not represented as acceptance passes.

After acceptance, the local API was restored to the separate empty synthetic development database: FAQs returned 200/empty, and unauthenticated pharmacy returned 401. Both disposable SQL services were stopped again. No production or historical database/configuration was read.

## Remaining limits

External email delivery, multi-tab/overlapping-navigation refresh races, sustained wall-clock inactivity, full mobile/accessibility/device journeys, physical pharmacy equipment, per-site clinical/operational decisions and provider/production integration remain separate acceptance. Sterile and nonsterile product scope remains confirmed; actual release stays unavailable pending the required decisions.

The environment draft is saved separately after this report is committed. Saving does not publish it. The owner last reported Publishing for an earlier attempt and subsequently was unsure of its final status; neither successful snapshot publication nor a fresh-task restore is established. The latest source/start configuration requires user Review/Save and Publish. This live task still runs the original configuration version, and its GitHub API requests are blocked despite the saved custom domain. Native Git push updates the same PR branch, but latest-head hosted check status and PR-description updates cannot be asserted from this runtime.
