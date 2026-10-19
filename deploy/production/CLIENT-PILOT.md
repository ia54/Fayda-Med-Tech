# Shared-platform client pilot

Scope authorized September 30, 2026. Purpose: demonstrate the existing shared PI platform to the owners while full pharmacy development continues. This is a synthetic-data demonstration, not operational dispensing or production acceptance.

## Demonstration sequence

| Step | Role | Demonstrate | Acceptance evidence to refresh |
| --- | --- | --- | --- |
| 1 | Super Admin | Organization/user overview and correct totals | Role login/MFA, persisted counts, no invented uptime metrics |
| 2 | Provider | Open assigned case, create/edit invoice draft, submit to billing | Save/reload, correct amount, submitted editing restriction |
| 3 | Biller | Review invoice; record payment received elsewhere; reverse an error and record correction | Retained ledger, explanation, duplicate reference rejection, correct balance |
| 4 | Patient/client | View own case, invoice, recorded payments and authorized private document | Own-record scope, authenticated document access, staff-only notes absent |
| 5 | Attorney | View assigned case and financial information | Assignment boundary and supported case views |
| 6 | Firm Admin | Record settlement, preserve original on correction, view reports | Current versus replaced totals and saved report result |
| 7 | Pharmacist, optional separate preview | Show location-scoped intake and retained source-linked review | Clearly label preview-only; no provider call or final restricted dispensing |

Prior evidence: workspace audit-evidence/role-browser-20260924/acceptance.md, financial-browser-20260924/acceptance.md and legal-browser-20260924/acceptance.md. Those observations are historical, not a fresh pilot pass. CSV generation is tested but browser-to-disk persistence was not established. Mobile acceptance is also outstanding.

## Preparation and acceptance

- Pin the demonstrated commit and record exact running build, database and fixture identifiers.
- Preserve the existing pharmacy synthetic database. Locate the historical shared-role fixture or prepare an isolated synthetic dataset without overwriting it.
- Use existing authorized localhost ports 8000 and 3102 only. Switching their runtime requires preserving and documenting the current configuration; do not start new listeners.
- Refresh all six role journeys in Chrome, including persistence, denied access and recorded-payment correction. Store dated evidence and issues; do not mark a failed or unexecuted step complete.
- Demonstrate to the owner with a visible synthetic-data explanation, no real patient records, credentials, external provider transmissions or payment collection.
- Record feedback as defect, missing feature, or operational decision, with an owner and verification criterion.
- A remotely hosted pilot additionally requires authenticated host access, backups/restore, origin/security checks and controlled deployment within the three authorized domains. A local walkthrough does not satisfy those requirements.

## Excluded from pilot claims

Live e-prescribing, drug interaction screening, MAPS reporting, payer submissions, external signatures, production email/recovery, model-connected extraction, controlled/compounded dispensing and physical pharmacy acceptance. These remain in the full mission; they are not represented as completed by this demonstration.

## September 30 acceptance snapshot

Evidence is in the workspace `audit-evidence/client-pilot-20260930/acceptance.md`; this local increment remains unpublished.

| Journey | Current result | Remaining acceptance |
| --- | --- | --- |
| Shared authentication | Six roles passed real HTTP password/MFA and logout revocation | Super Admin password/MFA/dashboard totals verified; organization missing-date display correction awaits rebuild |
| Provider to biller | Draft persisted, submitted editing restricted, biller reviewed | Final published-build/origin verification |
| Recorded payments | Receipt, reversal, corrected receipts, duplicate prevention and patient reconciliation verified | Production acceptance; no collection enabled |
| Private document | Authenticated bytes match stored hash; anonymous denied; browser text-file fallback shown | PDF inline rendering and browser download persistence not claimed |
| Attorney and firm admin | Settlement completion, linked correction, preserved original and current-only report totals verified | Final published-build/origin verification |
| Manual EOB | Create, review, filter and reject verified; payment ledger unchanged | Inline save-error and successful retry verified after rebuilt fix; list-load failure branch not yet browser-tested |
| Appeal template | Backend verifies template disclosure, saved draft, tenant isolation and no external calls | Chrome generation, retained content viewing, invoice search and status filtering verified; multi-page and load-failure browser branches not claimed |
| Pharmacy | Existing synthetic preview foundation only | All external/physical/professional operating gates remain open |

This table records specific observations, not blanket feature or production approval. The full launch denominator remains unchanged.
