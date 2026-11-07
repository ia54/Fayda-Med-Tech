# Signing release and recovery checks

The backend now validates the DocuSign account and API origin, uses demo or production OAuth as appropriate, and selects credentials for the document's organization. A configured account ID is required; the first account returned by the provider is never selected implicitly. Private-key environment values are wired into cached configuration. Authentication and send requests have timeouts. Provider diagnostics and document bodies are not returned to users or copied into signing logs.

## Required acceptance before enabling real signing

- Confirm the intended account, environment, integration-key consent, private key and account-specific API origin with the account owner. Validate the configured organization credential scope. Do not use patient documents for acceptance.
- Configure JSON Connect envelope events with an envelope summary and recipients, and HMAC signing using the matching organization/system webhook secret. This implementation verifies `X-Docusign-Signature-1`; rotating multiple simultaneous keys is not yet supported. Recipient-only events are acknowledged without changing envelope state. Unknown-envelope events are rejected, permitting provider retry after the envelope is linked.
- Templates must have separate `/sn1/`, `/sn2/`, etc. signature anchors in signer order. Missing anchors are an error, not permission to omit a required signature.
- Test a synthetic envelope end to end: creation, recipient delivery, signer order, completion, decline, duplicate webhook and delayed events. Completed/declined/voided envelope states and signed/declined/failed recipient states cannot be reversed by a late event.
- **Completed PDF and completion-certificate retrieval/private archival are implemented and synthetically tested.** Opening a completed envelope downloads both artifacts from the authenticated account after checking its envelope ID and completion status. Both files must pass MIME, size and basic PDF completeness checks before a single archive record becomes visible. Their SHA-256 digests are checked on reuse; corrupt or missing files are refetched. Preview never falls back to the original upload after signing completes. This is authenticated provider retrieval, not independent cryptographic PDF-signature validation. Actual provider delivery, artifact acceptance and rendered browser acceptance remain required.

## Private artifact retrieval

The existing authenticated preview route returns the signed PDF for a completed DocuSign envelope. The new `GET /api/documents/{id}/completion-certificate` route (also available under `/api/client/documents/`) returns its certificate. Both retain the document tenant and role visibility rules. Files are on the private documents disk, with no public URL; responses use private/no-store, nosniff and restrictive content-security headers. CORS exposes only the artifact type and certificate-availability headers used by the shared preview controls.

Retrieval is on demand and synchronous, with provider timeouts, no redirects, a 25 MiB PDF limit and a 5 MiB certificate limit. Files are streamed with an enforced byte limit before being stored. Artifacts remain cached locally for subsequent authorized downloads, including provider outages. Archiving does not send signature requests or emails. Background archival of unopened completed envelopes and larger-file support are not implemented. Include this private signed-file tree in backup/restore verification. Check PDF/certificate switching and downloads at desktop and mobile widths before launch.

## Ambiguous dispatch recovery

Migration `2026_09_24_000001_add_signing_dispatch_guard` adds a persistent per-document dispatch ID. The claim is saved before the external request and included as the DocuSign `transactionId`. Concurrent/repeated sends, signer edits and in-app signing are blocked once that claim exists. A timeout, malformed success, provider error or failure to save the response leaves the claim in place. There is deliberately no automatic clearing/retry endpoint.

Migration `2026_09_24_000002_add_signing_dispatch_snapshot` adds private dispatch evidence: organization, account, API origin, creation time, source/name hashes and a recipient fingerprint. The evidence and guard are saved under the document lock before sending; the actual payload uses those same bytes and signers. Evidence is excluded from API serialization. Existing claims are not retroactively certified.

A trusted server operator can run:

```sh
php artisan signing:reconcile DOCUMENT_ID
```

This is read-only by default. It uses the saved transaction ID to find exactly one envelope in the original account/environment, then verifies its identity, document name/ID and exact signer identities/orders against the saved evidence. It checks that local source bytes and signers have not changed. Output excludes recipients, file content and credentials.

After reviewing the returned envelope ID, explicitly link it:

```sh
php artisan signing:reconcile DOCUMENT_ID --apply --envelope=EXACT_ENVELOPE_ID --actor=ADMIN_USER_ID --reason='Internal recovery ticket reference'
```

Apply repeats provider verification, requires an active super-admin or same-organization firm admin, rechecks evidence under a row lock, and atomically links the envelope, updates signing state and records the actor/reason. The actor is attribution for the trusted shell operator, not a substitute for server-access authentication. Repeating the command for the same linked envelope is a no-op. Keep audit reasons free of patient or credential details.

No recovery command creates, resends or voids an envelope, and none clears the dispatch guard. Missing/changed evidence, wrong recipients, uncertain results, account changes or provider errors leave the record unresolved. Legacy claims without snapshots require manual investigation. The provider's transaction lookup retention is seven days; an empty result is not proof of non-delivery, especially after expiry. Conclusively failed sends still require a separately reviewed operational decision; there is no force-reset option.

Configure Connect acknowledgement/retry behavior so a callback that arrives before local linking is retried. Actual provider recovery acceptance remains pending; synthetic tests cover read-only inspection, explicit apply, identity mismatches, actor permissions, changed local state, repeat apply and transaction rollback.

Back up the database before production migration. Do not roll back this column while unresolved claims exist: that would remove duplicate-send protection. The migration is additive and does not send messages or alter existing envelope IDs.

## Sources

- [DocuSign authentication and account/base-URI discovery](https://www.docusign.com/blog/developers/demystifying-docusign-authentication)
- [Transaction ID lookup and seven-day retention](https://www.docusign.com/blog/developers/common-api-tasks-use-transactionid-to-find-the-envelope-you-created)
- [Anchor configuration](https://docusign.github.io/docusign-esign-node-client/model_InitialHere.js.html)

Artifact API reference: [DocuSign document downloads](https://www.docusign.com/blog/developers/dsdev-common-api-tasks-downloading-documents).
