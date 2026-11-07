# Isolated pilot email sign-in

This opt-in path is limited to one configured pharmacist email in the synthetic
`faydamed_pilot` MySQL database on localhost, with debug disabled and the pilot API
URL. It does not change normal platform authentication or ordinary pilot mail.

Required server-only environment settings:

- `PILOT_EMAIL_LOGIN=true`
- `PILOT_LOGIN_EMAIL=<approved recipient>`
- `PILOT_LOGIN_FROM=<owned sender address>`
- `PILOT_LOGIN_MAIL_TRANSPORT=sendmail` (or separately configured SMTP)

Keep `MAIL_MAILER=array`. Only `PilotEmailCode` uses the dedicated named mailer.
The account email must match the approved recipient. Activation must invalidate
existing account challenges and tokens; never copy or print passwords or TOTP
secrets. Back up the account's prior MFA state privately before a switch.

Password verification precedes sending. Challenges expire in five minutes, allow
five attempts, and store an HMAC bound to the challenge token, recipient, and
application key. Resend requires another password sign-in after one minute and
invalidates the older challenge. Mail transport failure removes the challenge.
A successful code is consumed in the same transaction as token issuance. Account
security reports email mode and prevents TOTP recovery/disable actions for it.

Before marking this deployed, verify a real message reaches the approved inbox
and have the account owner enter the code themselves. An accepted sendmail call
alone is not proof of delivery. No production/clinical readiness is implied.

Rollback must restore the previous private account MFA state and configuration,
then revoke sessions/challenges. Simply turning off the flag may lock out an
account which has no enrolled authenticator. Do not remove MFA to restore access.
