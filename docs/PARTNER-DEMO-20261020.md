# Partner demonstration — October 20, 2026

**Ready for an owner-reviewed, guided synthetic demo. Not ready for production or unrestricted partner access.**

The fresh environment restored successfully and passed the representative pilot-records Chromium replay. The video records actual implemented screens using existing synthetic fixture accounts. Sessions are pre-authenticated: the recording does not demonstrate login/MFA. No real patients, live provider connections, payer submissions, money movement or actual dispensing appear.

## Short recorded walkthrough

The captioned recording is approximately four minutes, intentionally shorter than the proposed 8–12 minute target. It shows EOB creation, search, explicit review and reload; retained appeal history and local draft generation; private synthetic PDF preview; pharmacy scope/exclusions; and analytics calculation basis. It ends with remaining development and release gates. Pharmacy is an overview here, not a newly recorded end-to-end dispensing or compounding demonstration. The existing five-replay evidence separately covers compounded handover rehearsal and eight roles.

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
