# Pharmacy browser acceptance — October 18, 2026

Application candidate: `6d3ef7aa77fbcebf6624be6d1ef4f243bdb33b96`. All observations below use Chrome and isolated synthetic records on the existing localhost ports 8000 and 3102. Production-ready and deployed remain false.

| Exercise | Verified result | Limits |
| --- | --- | --- |
| Runtime preparation | Fresh 120-page admin build; copied SQLite migrated from 133 to 137 migrations; original pilot preserved | macOS offloaded files required pinned API source and lockfile dependencies in temporary storage |
| Pharmacist authentication | Both existing synthetic pharmacists completed password and MFA sign-in | Production identity/recovery not tested |
| Recall overview | 32 seeded fills across two pages; overview unaffected by filtering; completed filter resets to page one; revoked location removed from counts/rows and direct follow-up access | Seeded read-side fixture, not proof of recall follow-up creation or clinical acceptance |
| Unreadable transfer | Sender cancellation saved and reloaded; original damaged snapshot/evidence retained; hold cleared; stock/fills/receipts unchanged | Receiving-side rejection not yet browser-accepted |
| Patient return | Browser receipt of 1.125 units; other pharmacist applies 0.125 disposition; independent recount leaves 0.875 custody; original receipt remains 1.125 | Synthetic completed fill has no linked invoice; billed-return browser path not claimed |
| Financial and dispensing preservation | Before/after hashes matched for prescriptions, fills, stock, invoices and payments through return/recount sequence | Later explicitly seeded cancelled fill and synthetic discontinuation are separate test setup/actions |
| Older-return worklist | Newer cancelled fill and browser discontinuation do not hide custody; filtered worklist links to original fill 10 | Full disposition removing the last attention item remains to test |
| Dispatch loss | Browser exception and balanced four-returned/six-lost proposal; other pharmacist applies; original stock 40, quarantine 4, no reservation, cancelled fill and no invoice | Initial dispatched state prepared by existing in-memory integration fixture |
| Later recovery | 2.125 units independently reviewed into separate quarantine; remaining loss 3.875 | Second 3.875 recovery submitted but final independent review remains pending after browser-control timeouts |

Focused local regression: **16 tests / 454 assertions**, including patient-return scope/recount/history/audit rollback and dispatch custody/loss/recovery caps and independent review. This is separate from browser evidence. The existing source candidate's hosted full regression remains 452 tests / 9,234 assertions.

Workspace evidence: `audit-evidence/build-completion-20261018/acceptance-runtime-current.json`, `patient-return-readback.json`, `patient-return-browser.jpg`, `older-return-worklist.txt`, `dispatch-loss-applied.json`, and `local-custody-regression.log`. Private fixture databases and authentication material stay outside Git.

Four existing synthetic pharmacy source files were copied to isolated storage and verified against retained SHA256 values. Other private documents, other role journeys on this build, physical devices, external providers, approved pharmacy procedures and production recovery/deployment are not established by these results. No provider calls, actual medication, real patient records, payment collection or production server changes occurred.
