# Patient-chart rehearsal — 23 October 2026

The isolated hosted HTTP-kernel rehearsal passed **37 assertions** at 17:06:23 UTC, using SQLite `:memory:` and an injected synthetic identity. It did not modify saved pilot records or real account permissions.

Extended the existing patient-chart regression test to cover a changed medication history between approval and preparation. Preparation is rejected until a fresh pharmacist review retains the new chart version. The existing post-preparation allergy-change scenario then confirms collection is rejected and no stock is dispensed.

Also exercised chart creation/retry identity, absence of a newly created portal user, unknown allergy status, approval blocking before clinical review, technician/biller role restrictions, stale chart updates, assistant stale-review flags, and loss of access after the fixture's location assignment is disabled.

Evidence: `launch/rehearse-pharmacy-chart-20261023.php` and `launch/pharmacy-chart-rehearsal-20261023.png` in the parent workspace. The repository PHPUnit test is extended; the standalone equivalent ran against hosted application code. PHPUnit itself was not available. This does not prove browser login, physical dispensing, real clinical acceptance, or provider connectivity.

The prior dispensing-to-payment rehearsal remains a separate 48-assertion result; do not count these as 85 unique scenarios or a release percentage.

Git verification initially encountered iCloud-offloaded pack files. Downloading the two existing packs restored access; `git diff --check` passed. No reset or pack deletion was performed. These changes extend test coverage; no new application deployment is claimed.
