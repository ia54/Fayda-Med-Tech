## Remaining synthetic pilot work

| ID | Work | Engineer-days | Dependencies |
| --- | --- | ---: | --- |
| P01 | Close client analytics access leak | 0.5–1 | Start now |
| P02 | Remove fabricated financial/plan data and inactive controls | 0.5–1 | P01 |
| P03 | Refresh protected document lifecycle | 1–2 | Start now |
| P04 | Refresh manual EOB and appeal branches | 0.5–1.5 | Start now |
| P05 | Exercise session concurrency and remaining recovery edges | 1–2 | Start now |
| P06 | Target pilot mobile and keyboard/accessibility checks | 0.5–1.5 | P03, P04 |
| P07 | Remediate and verify current dependency findings | 1–3 | Start now |
| P08 | Replay final pilot roles and pharmacy preview | 0.5–1 | P01, P02, P03, P04, P05, P06, P07 |
| P09 | Freeze verified builds, CI, archives and fresh security evidence | 0.5–1 | P08 |
| P10 | Verify reusable cloud publication/restoration | 0.25–0.75 | Start now |
| P11 | Prepare demo, resettable fixture and owner acceptance | 0.5–1 | P09 |
| P12 | Pilot defect/retest allowance | 1–2 | P01, P02, P03, P04, P05, P06, P07 |

## Additional full-product work after pilot

| ID | Work | Engineer-days | Dependencies |
| --- | --- | ---: | --- |
| F01 | Freeze site, provider, workflow and acceptance contracts | 3–5 | P11 |
| F02 | Patient identity, professional credentials and per-site configuration gaps | 3–6 | F01 |
| F03 | Catalogue, package/unit conversions and source provenance | 3–6 | F01 |
| F04 | Ordinary prescription/correction/replacement/allowance conflicts | 3–6 | F01, F02 |
| F05 | Ordinary external and controlled prescription transfer/amendment paths | 4–8 | F04, F08, F10 |
| F06 | Inventory purchasing and loss/excess/return/destruction gaps | 4–8 | F01, F03 |
| F07 | Recall external scope changes, outreach and closure | 3–6 | F06 |
| F08 | Controlled schedule-specific authorization/rule engine | 5–10 | F01, F02 |
| F09 | Controlled inventory/custody and correction exceptions | 3–6 | F06, F08 |
| F10 | Approved electronic prescription receipt adapter | 6–12 | F01, F02 |
| F11 | Licensed clinical reference and screening adapter | 5–10 | F01, F03 |
| F12 | MAPS submission, acknowledgments, correction and outage handling | 6–12 | F01, F08, F09 |
| F13 | MAPS query access and review workflow | 3–6 | F01, F08 |
| F14 | Validate formulation/calculation/quality/BUD policy implementation | 4–8 | F01, F03 |
| F15 | Real compounded output reservation, release and supply lifecycle | 8–15 | F02, F03, F14, F16, F17 |
| F16 | Sterile-specific qualification and release evidence | 5–10 | F01, F14 |
| F17 | Nonsterile and hazardous process-specific controls | 3–6 | F01, F14 |
| F18 | Compounded amendments, transfers, returns and release exceptions | 4–8 | F15, F04 |
| F19 | Physical labels, scanner/printer and handover integration | 4–8 | F01, F03, F14 |
| F20 | Versioned PIP pricing/coverage/coordination and EOB rules | 3–6 | F01, F03 |
| F21 | Payer submission, acknowledgment and reconciliation adapters | 8–15 | F01, F20 |
| F22 | Approved AI adapter and reviewed suggestion application | 4–8 | F01, F02 |
| F23 | Pharmacy OCR source-fidelity pipeline and evaluation | 3–6 | F01, F22 |
| F24 | External signature provider acceptance | 2–4 | F01 |
| F25 | Production identity, mail and operator recovery | 2–4 | F01, F28 |
| F26 | Production privacy, access, audit/export and retention review | 4–8 | F01, F02, F08, F15 |
| F27 | Concurrency/load, workers, monitoring and outage recovery | 6–10 | F10, F12, F15, F21 |
| F28 | Host inventory, coordinated restore and migration/rollback rehearsal | 5–9 | P09 |
| F29 | Per-site full-workflow UAT, training and final acceptance support | 4–8 | F04, F05, F07, F09, F10, F11, F12, F13, F15, F18, F19, F20, F21, F22, F23, F24, F25, F26, F27, F28, F30 |
| F30 | Safe production/site enablement gates and configuration | 2–4 | F08, F11, F12, F15, F26 |
| F31 | Final candidate cutover, real-origin acceptance and rollback standby | 1–3 | F29 |

