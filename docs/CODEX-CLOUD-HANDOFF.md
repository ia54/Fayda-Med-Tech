# FaydaMedTech cloud development handoff

Prepared October 19, 2026 at the owner's request to continue development while the local computer is offline. This document prepares cloud work; it is not evidence that a cloud environment or task is running.

## Source and scope

Repository: `ia54/Fayda-Med-Tech`. Continue from `codex/audit-foundation`, including scope commit `721a861826d8d21da580ebb2b16ee45f0d19ae6d` and this handoff. Do not start from main or replace the existing work. Review draft PR #1 and current repository instructions before editing. Preserve unrelated work and use a new `codex/` branch based on this candidate for independent cloud changes.

The owner requires a multi-location pharmacy platform covering controlled medications and BOTH sterile and nonsterile compounding. Hazardous preparation is a separate classification. Record payments only. GLM is a possible future AI provider, not an approved or configured connection.

Only synthetic development is authorized here. Do not connect to production, attempt SSH/CyberPanel, probe hosting ports, use historical credentials, enable actual dispensing, collect payments, send messages, or transmit patient information to providers. Do not import the local `.runtime`, credentials, databases, or environment files. Production access, provider onboarding and Dr. Ghadeh's per-site operational acceptance remain separate dependencies.

## Environment preparation

Use a Linux cloud environment with PHP 8.3 and Composer 2, Node 22 with Corepack/pnpm, and Python 3. Match the checked-in CI workflows and lockfiles. PHP requires mbstring, dom, pdo_sqlite, curl, zip, bcmath, intl and sodium. Install dependencies without changing locks:

```sh
cd api
composer install --no-interaction --prefer-dist --no-progress --no-scripts --no-plugins
cd ../admin-panel
corepack pnpm install --frozen-lockfile
```

Do not copy production environment configuration. `api/phpunit.xml` forces testing, an in-memory SQLite database, array mail/session/cache and a synthetic application key. Test fixtures create their own synthetic records. MySQL/MariaDB integration uses disposable services configured in `.github/workflows/backend-checks.yml`; SQLite results do not substitute for those results.

## First cloud task

1. Confirm repository/branch/commit and that commands execute on a cloud machine without a local-host executor. Record the cloud task URL and runtime versions.
2. Read `docs/BUILD-COMPLETION.md`, `docs/PHARMACY-ACCEPTANCE-20261018.md`, `docs/PHARMACY-SYSTEM.md`, `docs/PHARMACY-QUALITY-RELEASE.md` and `deploy/production/PROVIDER-OPERATIONS-REGISTER.md`. Reconcile old outstanding lists against actual current code before adding work.
3. Complete the pending synthetic dispatch-recovery acceptance with a reproducible cloud fixture. Local-only pending state cannot be resumed in cloud: create equivalent fresh synthetic actors and records. Preserve the original six-unit loss, independently accept recovery of 2.125 then 3.875 units, verify separate quarantined receipts, original available stock 40, cancelled original fill, no invoices and zero loss remaining. Test stale/duplicate/over-recovery and unauthorized review. Do not claim browser verification if only API tests run.
4. Continue the build-completion worklist, prioritizing compounded output reservation/handover rehearsal and shared-role access/financial invariants. Maintain actual clinical release gates. Existing implemented workflows should be repaired or completed, not duplicated.
5. Run relevant focused tests after changes, then applicable full CI once the candidate is ready. Retain exact command results and remaining browser/operational limits. Commit and publish a reviewable branch/PR; never merge or deploy automatically.

Useful checks from the repository root:

```sh
(cd api && php vendor/bin/phpunit --colors=never)
(cd api && php tests/Security/role-access-smoke.php && php tests/Security/route-boundaries-smoke.php && php tests/Security/cors-config-smoke.php)
(cd admin-panel && corepack pnpm test:pharmacy && corepack pnpm run typecheck)
```

Read the other workflow checks for affected sessions, recovery, documents, reports and web packaging. Do not repeatedly run the entire suite for documentation changes. Provider or location decisions must remain explicit unknowns rather than guessed defaults.

## Evidence boundaries

Application candidate `6d3ef7a` had previously reported hosted backend/admin/web results; inspect their source-bound evidence before relying on them. Subsequent commits through `721a861` reconcile documentation only. The local focused sterile/hazard evidence regression passed 1 test / 15 assertions on October 19. None of these facts establishes cloud execution, production deployment or clinical acceptance.

Cloud tasks may finish, exhaust usage or require input; this handoff does not promise indefinite unattended development. Save progress and report concrete blockers rather than claiming background work continues after a task has ended.
