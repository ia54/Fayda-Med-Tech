# Pharmacy Chrome verification — September 26, 2026

Synthetic local preview only, using the existing localhost ports 8000 and 3102. No production domains, data or processes changed. Fixture accounts use MFA and different location assignments; credentials, OAuth keys and SQLite data are outside the repository.

## Verified before corrections

- Pharmacist sign-in and authenticator verification through Chrome.
- Compounding list and reviewed worksheet rendering.
- Actual execution submission with explicitly synthetic measurement/process/personnel/equipment/quality references and a yield difference.
- Saved execution remains quarantined, requires a different pharmacist for document review, and marks allocation consumed.
- Ingredient inventory shows the expected 5 g → 3 g change and zero reservation. Database read-back confirms one execution and one consumption event.

## Findings and changes

- Switching accounts retained the prior account's RTK Query location cache in the UI. Backend location enforcement remained in place. All API cache data now clears atomically when account, organization or role changes, and RTK subscriptions/timers are reset. Late protected responses are discarded after an identity change. Refresh requests are isolated by refresh token and cannot restore a different account. Normal same-account access-token rotation preserves cache.
- A worksheet or ingredient record lost its selection on refresh. Formula, worksheet and ingredient records now have query-string URLs; back navigation and reload retain the selected record.
- Consumed allocations retained an explanation stating stock was unchanged. Copy now distinguishes reserved from consumed stock.
- Ingredient consumption history omitted the measurement/process evidence and showed an unlinked worksheet number. Those references are now visible and the worksheet reference is a link.

Two automated Redux/RTK tests verify identity changes, logout, stale in-flight fulfillment, organization/role changes and token rotation. A dedicated admin CI workflow runs those checks plus TypeScript validation. Final post-fix browser/build evidence is retained in the workspace audit-evidence/pharmacy-20260926 folder and PR description.

This walkthrough is not real-pharmacy acceptance. It does not establish clinical correctness, facility qualification, controlled-substance reporting, payer transport, BUD assignment or medication release. Operational gates in PHARMACY-SYSTEM.md and deploy/production/OUTSTANDING.md remain.
