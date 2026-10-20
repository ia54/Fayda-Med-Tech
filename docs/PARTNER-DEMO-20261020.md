# Partner demonstration — October 20, 2026

**Ready for an owner-reviewed, guided synthetic demo. Not ready for production or unrestricted partner access.**

The fresh environment restored successfully and passed the representative pilot-records Chromium replay. The video records actual implemented screens using existing synthetic fixture accounts. Sessions are pre-authenticated: the recording does not demonstrate login/MFA. No real patients, live provider connections, payer submissions, money movement or actual dispensing appear.

## Scope update after owner clarification

The owner requested the core MedTech workflows and then clarified that foundational pharmacy must be included as the operational heart. The revised walkthrough includes ordinary patient/prescription, stock and retained preparation/handover records plus assigned cases, provider draft/submission for internal review, biller review, record-only receipts/corrections, settlements, CSV, documents and EOB/appeal workflows. Advanced controlled/compounding preview screens are excluded. Pharmacy history is explicitly seeded; no automatic pharmacy-to-finance integration is claimed. The initial 3:36 recording below is superseded when the revised export is delivered.

## Initial recorded walkthrough (superseded scope)

The captioned recording is **3 minutes 36 seconds**, intentionally shorter than the proposed 8–12 minute target. It shows EOB creation, search, explicit review and reload; retained appeal history and local draft generation; private synthetic PDF preview; pharmacy scope/exclusions; and analytics calculation basis. It ends with remaining development and release gates. Pharmacy is an overview here, not a newly recorded end-to-end dispensing or compounding demonstration. The existing five-replay evidence separately covers compounded handover rehearsal and eight roles.

The pharmacy fixture's worklist is empty; no active prescription is implied. External provider connections are unconfigured in the recording, not simulated successful API calls. Seeded appeal status is explicitly distinguished from actual payer transmission. Captions and title cards are explanatory overlays; application results are real browser footage.

The initial PDF clip blocked Chromium's built-in viewer resources. A separate real-browser retake with only the built-in PDF resources allowed replaces that clip in the owner-review export. No result or application screen was fabricated. A first retake reached the visible PDF but timed out waiting for network-idle; the final capture uses a bounded render wait. Raw takes and editing metadata remain outside Git. Secrets, test session files, databases, signing keys and environment internals are excluded from deliverables.

## Live viewing/access

The verified UI address is `http://127.0.0.1:3000` **inside this cloud runtime only**. API is loopback `http://127.0.0.1:8000`. No owner preview gateway or partner-accessible URL has been verified. Use the downloadable owner-review video; after review the owner can choose a facilitated screen share. Do not send partners the loopback address as a working link. No public exposure, sharing changes or account creation occurred. Runtime services may stop when this task ends, and fixture sessions expire.

## 10–15 minute facilitated meeting

| Time | Show/discuss |
| --- | --- |
| 0–1 min | Synthetic pilot scope, dated owner acceptance and explicit exclusions |
| 1–5 min | Play the recorded actual UI walkthrough |
| 5–7 min | Discuss the separate five-replay/eight-role evidence, including synthetic compounding rehearsal; no physical/site acceptance implied |
| 7–10 min | Remaining engineering: provider adapters, controlled rules, real compounded release/supply, devices, operational resilience |
| 10–12 min | Hosting/recovery, residual advisories, clinical/site decisions, named owners |
| 12–15 min | Collect feedback and select the next acceptance cases; avoid promising dates until inputs are confirmed |

Ibrahim reviews the recording before any partner delivery. Dr. Ghadeh/site leads own clinical and site acceptance; hosting administrator owns approved host access/recovery inputs. See [current readiness evidence](RELEASE-PREPARATION-20261020.md) and [remaining development table](BUILD-FINISH-TABLES-20261019.md).

## Export verification

H.264 MP4, 1440×1100, 4,428,606 bytes. Full FFmpeg decode passed; Chromium playback started with no media error. Representative exported frames were visually reviewed. Loopback-server seeking was not established; it is not claimed as a verified player feature. The private Library recording is linked in the task delivery; [export metadata and SHA-256](evidence/release-20261020/video-export.json) bind the file to this evidence.
