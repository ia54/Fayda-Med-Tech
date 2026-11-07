# Development branch rollout prerequisites

This branch has not been deployed to production. Use a synthetic staging environment before a controlled release.

1. Confirm the deployed PHP/MySQL versions, queue and storage configuration. Back up the database and both public/private document stores; verify restore access. Preserve the application encryption key, which protects enrolled MFA secrets.
2. Install the committed Composer lockfile. Rehearse all migrations against a representative, sanitized database. Fresh MySQL CI does not prove upgrade compatibility or rollback safety.
3. Provision writable private document storage outside the public web root. New uploads use the `documents` disk. Run `php artisan documents:privatize` to inspect legacy migration candidates. In staging, `--apply` copies and hash-verifies before updating references; `--apply --remove-public` removes the verified legacy public copy. Review the inventory and backup before removal. Existing public URLs remain exposed until the old copies and any hosting/CDN caches are addressed. Document storage migration rollback deliberately requires a retention plan.
4. Prepare administrators for authenticator enrollment and securely saved recovery codes. Admin and firm-admin accounts require MFA by default when no security settings exist. Verify the configured policy with every role. Do not delete enrollment secrets or rotate the application key as a routine rollback.
5. Expect users to sign in again: refresh tokens now store hashes, rotate once, and depend on their parent access token. Password reset and MFA changes revoke sessions. Alternative Passport OAuth routes are disabled; inventory external consumers before deployment.
6. Verify existing document signer assignments by account ID. Email alone no longer grants document access. Use explicit eligible account assignment for in-app signing and the external provider flow for external recipients. Archived documents retain their files.
7. Configure and test the DocuSign HMAC secret and exact raw-body signature delivery. Missing/invalid signatures are rejected. Full external delivery, duplicate-event handling and event ordering still need integration acceptance testing.
8. Run all six roles through their actual case, document, billing and settlement tasks with synthetic records. Test denied access, reload persistence, session expiry, retries and mobile navigation. Resolve the broader type-check/dependency backlog before a market-ready claim.

Keep release and recovery procedures environment-specific. Do not run destructive migrations or file removal against production merely because branch CI passes. If a release fails, restrict access and restore the reviewed application/data snapshot without silently reopening access controls.
