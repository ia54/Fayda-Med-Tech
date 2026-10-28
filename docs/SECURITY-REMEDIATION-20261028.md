# Dependency remediation — October 28, 2026

Continued from `a0189ad6cc4e48e6466206ee6b2a21055cefdfad` on `codex/cloud-recovery-20261023`; normal fetch confirmed that remote tip before edits. Source, lockfile integrity, CA trust and the existing braces mitigation were preserved. No network policy, production service, provider integration, payment or clinical-release setting was changed.

## Advisory dispositions

The [19-entry disposition record](evidence/security-20261028/advisory-dispositions.json) includes each official GitHub Advisory Database URL, affected/patched version text, retrieved-page hash and installed fix. Official pages were read through the existing allowed GitHub HTTPS route. No audit exclusions, severity filters, renamed versions, verification bypass or force-upgrade were used.

| Package / affected version | Fixed version | Disposition |
| --- | --- | --- |
| Next.js 15.5.27 | **15.5.28**, with **Sharp 0.35.5** | GHSA-2464-fhr4-w9xw (critical image-optimization RCE) and GHSA-p3m5-59mp-jv5r (high Server Actions DoS) fixed by the supported Next 15 patch. eslint-config-next moves to the matching patch. Images were already unoptimized and no `use server` directive was found in application/components, but those observations do not substitute for upgrading. |
| DOMPurify 3.4.16, both apps | **3.4.17** | GHSA-cg2g-xh64-v4q7 (critical shadow-root/template bypass), GHSA-mrv2-3pqr-j5jr (high detached-tree IN_PLACE hooks), GHSA-c6gr-qvv4-5v3f (low XHTML/SMIL bypass). The existing final-sink string sanitizer does not enable these DOM modes; the underlying dependency is upgraded regardless. |
| Zod 3.25.76 | **4.6.6** | GHSA-mrhx-c7xc-m3w5 (high resource exhaustion). The advisory lists no patched Zod 3 version, so a major migration is required. Resolver 5.2.2 supports Zod 4 while preserving the existing React Hook Form version. |
| Vite 7.3.6, public development toolchain | **7.3.7** | GHSA-698v-4r3x-3vcv, GHSA-r825-w8mm-5pxw, GHSA-587h-7wmj-h5pm, GHSA-9jrq-w75r-8gcw, GHSA-rq7h-c2jc-7f22. Includes editor argument handling and development-server file/middleware boundaries; the same-major patch fixes the listed ranges. |
| undici 8.11.2, jsdom development dependency | **8.12.3** | Eight entries, including GHSA-4qv8-2p9j-2685, GHSA-f72v-6w45-q472, GHSA-xjj6-f33q-929c, GHSA-4w7w-5xrc-9h26, GHSA-2m5v-pch6-74hx and GHSA-qrxm-fqhp-jwvm; see the complete linked record for all IDs. The supported `npm update undici` resolves the existing compatible range above the patched 8.12.2 floor, without adding a direct runtime dependency or override. |

Fresh [admin audit](evidence/security-20261028/admin-audit.json) and [public-site audit](evidence/security-20261028/frontend-audit-final.json) both exit **0 with zero reported advisories**. The intermediate public audit was taken after the DOMPurify update and exposed the additional Vite/undici findings; it is not an untouched pre-change baseline. Audit counts are time-bound registry observations, not a guarantee that all security defects are absent.

The braces 3.0.3 depth-64 patch and pnpm patchedDependencies registration are byte-for-byte unchanged. Its three regression checks still pass. A currently empty registry audit does not invalidate the historical advisory or justify removing that mitigation.

## Zod compatibility changes

- Preserve organization/user/testimonial error messages using Zod 4's supported `error` option.
- Give CaseForm distinct schema input/output types, retaining its existing default status without weakening types or assertions.
- Declare the two existing CMS dynamic lists in their schemas. Skip those list fields in the generic multipart loop so the existing explicit JSON serialization sends each list once.
- Add executable validation/resolver regression tests to the existing CI `test:dependencies` command. They cover accepted input, required/type/password rejection, field-level error mapping, optional update passwords, enum/boolean validation, edited list values and invalid entries. The collection regression verifies a 10,000-element oversized constrained array fails before element validation while an in-bounds array still validates.

## Validation executed

- Frozen admin install and public `npm ci`: passed with the new locks. No unrelated dependency refresh; the public lock retains existing platform metadata.
- Admin typecheck: passed. Initial migration errors were corrected rather than suppressed.
- Admin dependency tests: **7 passed** (3 braces, 4 validation/migration tests); session tests: **5 passed**; private-document tests: **2 passed**.
- Public rich-text security tests: **4 passed**; Vite production build: passed, retaining its existing chunk-size warning.
- Next.js 15.5.28 production build: passed, **120/120 pages generated**, with type checking enabled. Existing configuration skips lint during build; no lint pass is claimed. Existing Node environment-proxy warnings are retained in logs.
- Fresh synthetic seed: **1 test / 19 assertions passed**. The production browser check used the supported standalone server with copied generated static/public assets. An initial `next start` invocation warned about standalone output and was replaced before acceptance; its Ready message is not counted as application validation.
- [Production browser result](evidence/security-20261028/prescription-source-result.json): upload → reload → authenticated keyboard download matched all **77 bytes**, SHA-256 `56d3c3138205d882a3499a87511ce49c1895f1d5a332d9b51700c9783471c792`. Required-field keyboard validation, 390px/320px overflow and download checks passed. Source list/file probes: biller 403, foreign organization 404, unassigned site 404, anonymous download 401. One source/audit event persisted; stock/fills/invoices/payments/compounded-execution snapshots stayed unchanged. No API 5xx was observed. This is seeded-session synthetic evidence, not login/MFA, hosted, physical-device or clinical acceptance.

The [candidate file hashes](evidence/security-20261028/candidate-files.json) bind changed code/manifests/locks/test bytes; browser evidence records the base HEAD plus working-tree changes and application hashes. [Evidence hashes and sizes](evidence/security-20261028/index.json), sanitized logs and official advisory metadata are retained; sessions, private keys and database exports are excluded. Two disposable Composer download-cache mirrors were removed to free build space after verifying installed vendors did not reference them; installed packages were retained. No full historical PHP/MySQL/pharmacy suites were repeated for these web-only dependency/schema changes.

## Remaining gates

The owner reports **Only me**, unrestricted internet off, no secrets, and a saved, unpublished draft approving the existing package-manager preset plus exactly `api.github.com` and `packagist.org`. The [runtime observation](evidence/security-20261028/network-observation.json) still shows spec revision 1 with the old restricted policy enforced and neither custom host present. GitHub API access is denied; Composer audit exits **100** on a proxy CONNECT 403. **Composer advisory status remains unknown**, not clean. Normal Git fetch works separately.

No supported network apply/reload/restart control is exposed. Exact-commit draft preparation is separate from applying the approved network policy and from publication. The user must use the environment publication workflow for the prepared version, then verify the connected runtime and retry those operations; no fresh-task restoration is claimed here. Privacy approval need not be repeated.

Hosted source-download proof, owner walkthrough acceptance, physical devices, provider choices and site-specific clinical/operational sign-offs remain separate. Controlled/compound release stays disabled. No merge, production deployment, real PHI, provider call, real payment or dispensing occurred.
