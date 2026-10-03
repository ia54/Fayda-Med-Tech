# Dependency readiness — September 27, 2026

This records current dependency evidence, not approval to deploy or process patient data. Re-run audits for the selected release and verify the actual host platform before cutover.

## Locked dependency findings

- API: Composer audit of the complete locked tree, including development dependencies, reports no known security advisories. `doctrine/annotations` remains abandoned through the Swagger tooling; this is an unresolved maintenance finding, not a clean-maintenance claim.
- Public website and API asset tooling: npm audit of both complete lockfiles reports zero known vulnerabilities.
- Admin: pnpm audit of the complete lockfile reports one low-severity advisory, with zero moderate, high or critical findings: Quill2.0.3, GHSA-v3m3-f69x-jf25 / CVE-2025-15056. The [reviewed advisory](https://github.com/advisories/GHSA-v3m3-f69x-jf25) lists no patched version, and the registry currently resolves latest Quill to2.0.3. No audit ignore was added.

The existing rich-text mitigation sanitizes editor input/output and every identified rendered-HTML sink using the shared DOMPurify allowlist. Four DOM-based tests check formatting, malicious markup/protocols, excluded embeds/attributes and identical policy across both frontends; these pass after clean locked dependency installation. This is evidence for those covered paths, not a claim that the Quill package itself is patched or that a dependency audit proves the whole application secure. Keep the advisory visible and reassess an upstream fix or editor replacement before operational acceptance.

## Removed unused dependencies

The record-only admin has no Stripe SDK imports. `stripe` and `@stripe/stripe-js` have been removed from its manifest, lockfile and installed dependency tree, without upgrading unrelated packages. This does not change recording of payments received elsewhere.

The API's unregistered legacy `AiOcrController` and unused `openai-php/client` dependency have been removed. The old handler called a fixed vision-preview model, lacked the current private-document access path, invented fallback confidence and returned raw provider exception text. No route referenced it. The lockfile also removes its now-unused `php-http/discovery` and `php-http/multipart-stream-builder`; the obsolete discovery-plugin allow entry is removed. No retained package version changed. Existing Google Vision OCR and human pharmacy review remain separate. GLM is still unconfigured and unverified; removing dead code is not completion of AI extraction.

## Continuous checks

Admin CI now runs the existing password-recovery, protected-document and report-export tests alongside session-isolation tests and TypeScript checks. All eight targeted tests pass locally. Production-origin Linux builds separately execute the shared rich-text security tests. API CI retains Composer audit, PHP request tests and MySQL/MariaDB schema/recovery coverage.

Local builds and registry audits do not verify server PHP extensions, Linux/Node/glibc compatibility, deployment credentials, production restore, provider processing terms or pharmacy operating acceptance. The authoritative remaining requirements are in [RELEASE-STATUS.md](RELEASE-STATUS.md). Raw before/after audits and dependency-install/validation logs are retained in the workspace audit evidence outside Git.
