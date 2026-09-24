# Signing release and recovery checks

The backend now validates the DocuSign account and API origin, uses demo or production OAuth as appropriate, and selects credentials for the document's organization. A configured account ID is required; the first account returned by the provider is never selected implicitly. Private-key environment values are wired into cached configuration. Authentication and send requests have timeouts. Provider diagnostics and document bodies are not returned to users or copied into signing logs.

## Required acceptance before enabling real signing

- Confirm the intended account, environment, integration-key consent, private key and account-specific API origin with the account owner. Validate the configured organization credential scope. Do not use patient documents for acceptance.
- Configure JSON Connect envelope events with an envelope summary and recipients, and HMAC signing using the matching organization/system webhook secret. This implementation verifies `X-Docusign-Signature-1`; rotating multiple simultaneous keys is not yet supported. Recipient-only events are acknowledged without changing envelope state. Unknown-envelope events are rejected, permitting provider retry after the envelope is linked.
- Templates must have separate `/sn1/`, `/sn2/`, etc. signature anchors in signer order. Missing anchors are an error, not permission to omit a required signature.
- Test a synthetic envelope end to end: creation, recipient delivery, signer order, completion, decline, duplicate webhook and delayed events. Completed/declined/voided envelope states and signed/declined/failed recipient states cannot be reversed by a late event.
- **Completed PDF and completion-certificate retrieval/private archival are still outstanding.** The current preview serves the source document for DocuSign envelopes. A completed provider status does not prove that the downloaded local PDF contains those signatures. Do not advertise the signing workflow as release-ready until retrieval and browser acceptance are finished.

## Ambiguous dispatch recovery

Migration `2026_09_24_000001_add_signing_dispatch_guard` adds a persistent per-document dispatch ID. The claim is saved before the external request and included as the DocuSign `transactionId`. Concurrent/repeated sends, signer edits and in-app signing are blocked once that claim exists. A timeout, malformed success, provider error or failure to save the response leaves the claim in place. There is deliberately no automatic clearing/retry endpoint.

An operator must first preserve the local record and inspect the **same account and environment** at DocuSign using the transaction ID. The provider's transaction lookup retention is seven days; an empty result after expiry is not proof that no envelope exists. Confirm document and recipient identity before linking a found envelope and applying its authenticated state in a reviewed database transaction. If non-creation is conclusively established, correct the cause before a reviewed reset of the claim. Do not clear the guard simply because the UI reports a failure. Recovery tooling and its acceptance test remain outstanding; this is an operator gate, not a completed automated workflow.

Back up the database before production migration. Do not roll back this column while unresolved claims exist: that would remove duplicate-send protection. The migration is additive and does not send messages or alter existing envelope IDs.

## Sources

- [DocuSign authentication and account/base-URI discovery](https://www.docusign.com/blog/developers/demystifying-docusign-authentication)
- [Transaction ID lookup and seven-day retention](https://www.docusign.com/blog/developers/common-api-tasks-use-transactionid-to-find-the-envelope-you-created)
- [Anchor configuration](https://docusign.github.io/docusign-esign-node-client/model_InitialHere.js.html)
