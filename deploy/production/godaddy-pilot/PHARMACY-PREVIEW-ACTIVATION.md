# Isolated pharmacy preview activation

Prepared 22 October 2026. Not deployed or enabled by this document.

The hosted API runs APP_ENV=production, while PharmacyPreviewOnly previously
accepted local/testing only. The proposed exception requires all of:

- Explicit boolean SYNTHETIC_PILOT=true and SYNTHETIC_PHARMACY_PREVIEW=true.
- A server-side attribute set by the gateway only after its key and bootstrap checks.
- Debug off; MySQL localhost; database exactly faydamed_pilot.
- App URL exactly https://pilot-api.faydamed.tech.
- Array mail, synchronous queue and null broadcasting; existing outbound provider blocking.

Normal authentication, role, organization, location and workflow checks remain active.
No caller header or JSON field can supply the server-side attribute. The additional
flag defaults false. This is synthetic demonstration access, not clinical acceptance.

## Activation procedure

1. Obtain action-time approval for hosted pharmacy preview access and the initial
   account. Keep the original three production domains unchanged.
2. Back up the existing middleware, pilot config and public gateway entrypoint in
   the private pilot backup directory. Preserve the environment file and its 0600 mode.
3. Install the reviewed helper, middleware, config and gateway file. Lint all changed
   PHP files. Verify the existing unauthenticated/direct gateway denial checks.
4. Enable only SYNTHETIC_PHARMACY_PREVIEW in this pilot environment. Confirm effective
   configuration without printing secrets. Do not change APP_ENV or disable MFA.
5. Recheck empty users/organizations and exactly one personal-access client. Create
   only one clearly labelled SYNTHETIC organization and one active pharmacist account,
   pharmacist@example.invalid, with permissions=[] and the new organization ID.
   Stop on any pre-existing records; do not rerun generic seeders or reset the database.
6. Owner enters a fresh password privately and enrolls an authenticator through the
   normal login flow. Never print passwords, QR secrets, recovery codes or tokens.
7. Verify successful login, refresh/logout, pharmacy navigation, denied administrative
   routes, and location assignment boundaries. Create synthetic location/patient/stock
   fixtures only in separately scoped acceptance steps. Do not assert clinical readiness.

Rollback: set SYNTHETIC_PHARMACY_PREVIEW=false and restore the private file backups.
Revoke the demo user's sessions and mark it inactive if account access must be removed;
retain audit history. Do not remove unrelated data or reset the database.

## Verification recorded so far

29 standalone policy assertions passed; changed PHP files linted. Laravel integration
test added, but local runner stalled before output and was interrupted, so it is not
counted as passed. Hosted activation and authenticated workflow tests remain pending.
