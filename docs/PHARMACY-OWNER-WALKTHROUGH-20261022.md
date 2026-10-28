# Pharmacy owner walkthrough — October 22, 2026

Pharmacy is the central demonstration. This guide covers the private GoDaddy synthetic pilot, not a clinical launch. Email-code sign-in and the records below were verified in Chrome on October 22. The deployed frontend package is `115ddef`; subsequent source changes require a new deployment before they can be demonstrated.

## Ten-minute owner demonstration

| Step | Open | What the owner can see | Verified boundary |
| --- | --- | --- | --- |
| 1 | Pharmacy Operations | Three prescriptions linked to one patient, accident case and pharmacy | Ordinary fill pending; controlled and compounded records remain intake only |
| 2 | Patient records → Synthetic Demo Patient | Location enrollment, retained history and clinical chart | Allergies and medications remain unknown; no clinical review invented |
| 3 | Open SYN-RX-001 | Prescription, patient/case linkage, original-source section and actionable missing-record checks | AI is unconfigured; checks are deterministic, not interaction screening |
| 4 | Fills and billing on that prescription | Pending pharmacist review, reservation, label-proof requirements and action history | No approval, label print, dispensing, handover or claim submission completed |
| 5 | Locations and inventory | SYN-LOT-001: 100 on hand, 10 reserved, 90 unreserved | Synthetic stock; product verification outstanding |
| 6 | Worklist → select SYNTHETIC — Michigan Demo Pharmacy → Site readiness | Outstanding credentials, interfaces, physical acceptance and release decisions | Operational release disabled for all four displayed categories |
| 7 | Worklist controlled and compound rows | Dedicated classifications and linked intake records | These rows demonstrate intake, not complete controlled or compounded workflows |

Use fictional records only. Do not tick professional-review checkboxes merely to move the demonstration forward. An owner can review the screens without granting clinical acceptance.

## Exact demonstration records

- Patient 2: SYN-PAT-001, Synthetic Demo Patient.
- Accident case reference: SYN-PIP-001.
- Prescription 4: SYN-RX-001, ordinary training tablet; pending fill.
- Prescription 5: SYN-RX-002, controlled intake.
- Prescription 6: SYN-RX-003, nonsterile compound intake.
- Stock receipt 2: SYN-LOT-001; 100/10/90 on-hand/reserved/unreserved.
- One assigned pharmacist; no technician or additional role accounts created for this demonstration.

Browser evidence is retained in the workspace `launch/backend-preflight-20261022/pharmacy-demo-evidence.json` and the worklist, inventory and readiness PNGs under `launch/`. These are observations of this dataset, not general acceptance of every pharmacy feature.

## Next delivery sequence

1. **Presentation and navigation:** simplify worklist follow-up filters and preserve explicit accessible filter names. Validate the source, package a new preview build, then verify in Chrome. Source completion alone is not deployment.
2. **Evidence rehearsal:** exercise synthetic source attachment, persistence and retrieval on the hosted pilot. Record results before advancing the ordinary-fill rehearsal; professional-review decisions remain explicit.
3. **Hosted resilience:** verify current session denial boundaries, backup and restoration in an isolated destination, plus upload/download persistence. Do not overwrite the demonstrated database to test restoration.
4. **Provider decisions:** select prescription exchange, licensed clinical drug data, payer route, MAPS reporting mechanism and AI provider. No account/provider selection is established by a code adapter.
5. **Pharmacy acceptance:** Dr. Ghadeh approves site and professional credentials, controlled/compounding scope, SOPs and physical label/scanner workflow. Keep unresolved items visible.
6. **Clinical hosting and release:** select the operational host and required agreements, verify integration behavior and recovery, and obtain site-specific professional release acceptance before real patient use.

The six shared-platform role journeys have historical local evidence; they have not been freshly reverified in this one-account hosted pharmacy pilot. Payment scope remains recording receipts only, not collecting money.

## Hosted source-document check

October 22: SYN-SOURCE-001 was uploaded to prescription 4, retained after a full page reload, and recorded as a source-document-added history event at 22:56:06. Authenticated retrieval exposed a download link. A saved browser download and byte comparison remain unverified; browser policy prevented inspection of Chrome download history. Review and fulfillment remain pending, and claims remain not prepared. The fictional PDF contains no clinical directions or prescriber authorization.

October 23: the follow-up filter change in `9f67e00` was deployed to the private preview and browser-verified. Pending-stage filtering selected SYN-RX-001 and clearing filters restored all three prescriptions. Server-side source retrieval returned all 1,989 original bytes with matching SHA-256; browser-to-disk download integrity remains unverified.

## Separate cloud ordinary-workflow walkthrough — October 23

The hosted demonstration records above remain unchanged. A fresh, disposable cloud dataset at `9e2468b` now has [ordinary pharmacy browser evidence](CLOUD-RECOVERY-ACCEPTANCE-20261023.md). To review that evidence, follow clinical-chart review → verified stock product → reservation and pharmacist review → label proof and simulated print → final manual code comparison → pickup, or dispatch and confirmed delivery → site-assigned biller → recorded receipt. Screenshots and retained database hashes cover both paths, with exactly one stock deduction per completed fill.

This is an evidence walkthrough for Dr. Ghadeh, not a request to repeat simulated professional attestations on the hosted demonstration patient. Seeded sessions do not establish login/MFA acceptance. Synthetic submission records are internal rehearsal entries only; no payer was contacted and no funds moved. Physical labels/scanners, provider integrations and site-specific professional release remain separate decisions. Review the evidence before deciding what to demonstrate next; no new hosted deployment is implied.


## October 28 dependency and source-file follow-up

[The current security report](SECURITY-REMEDIATION-20261028.md) records patched web dependencies and a fresh source-file browser check against the production standalone build. Inspect the retained 320px screenshot and downloaded synthetic PDF/hash proof: upload, reload, keyboard retrieval/download and unauthorized-role/site checks passed. No stock, fill, invoice or payment changes occurred. This advances the disposable cloud walkthrough only; the historical hosted download, real sign-in/MFA, devices and professional acceptance remain unverified. No owner acceptance or hosted deployment is inferred.
