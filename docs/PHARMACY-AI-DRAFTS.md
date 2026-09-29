# Pharmacy extraction drafts — implementation and remaining work

## Purpose and current boundary

Reduce repeated transcription while retaining pharmacist control of the original prescription, patient/case linkage, clinical review and every supply. A model result is an unverified suggestion; it cannot create or amend a prescription, change controlled classification, approve review, allocate stock, dispense or bill.

`PharmacyExtractionDraft` now constructs a text-only extraction request and validates a completed response. It is a pure service: no HTTP client, credentials, database writes, routes or queue dispatch. It does not connect GLM, perform OCR, read a source file or establish source authorization. The active pharmacy screens still correctly state that AI is unconfigured.

The candidate provider's official [chat interface](https://docs.z.ai/api-reference/llm/chat-completion) documents chat completion responses, while its [structured-output guide](https://docs.z.ai/guides/capabilities/struct-output) documents JSON mode and application-side schema validation. Reviewed September 29, 2026. These describe a technical interface, not approved patient-data handling, model accuracy, processing terms or clinical acceptance. No model/version/account is selected or configured by this increment.

## Implemented response contract

- Thirteen explicitly named fields retain literal patient/order/prescriber text; dates, numbers and units are not normalized. Unknown fields have no candidates. Different extracted values remain conflicting candidates instead of silently choosing one.
- Every candidate includes a one-based page and an exact contiguous quote in the supplied page transcription. Its value must be a substring of that quote. This verifies text correspondence only: it cannot prove correct field classification, complete extraction, accurate OCR, whole-token selection or correspondence with actual original-file bytes. Human comparison with the original remains essential.
- Only the versioned schema and allowed keys pass. Missing/extra fields, authority/confidence claims, invalid page references, invented quotes, unsupported types, duplicate candidates, excessive sizes/depth, invalid UTF-8 and invalid source-hash format fail. Exceptions contain no source/provider text.
- The returned draft is always `needs_human_review`. Original-file hash, transcription hash, response-content hash, page count and literal candidates support later provenance retention. The source hash is supplied by the future authorized file pipeline; the pure validator does not authenticate it.
- The chat envelope must contain exactly one completed assistant response with the configured model identity, no refusal/error and no tool calls. Truncated/foreign-model/multiple responses fail. Provider reasoning and raw errors are not retained.
- Document instructions remain untrusted text in a separate user message. No function/tool execution is available. Prompt wording is not a substitute for the application permission boundaries.

Synthetic unit validation: seven tests / 60 assertions passed, covering literal preservation, conflicts/unknowns, malformed/unsubstantiated evidence, size/shape limits, embedded instructions and incomplete/refused/tool/foreign-model responses. These tests do not evaluate any real model or provider.

## Required next implementation

1. Manual source transcriptions are now retained through scoped source-file endpoints. The original bytes must match the displayed hash. Ordered text (including blank page positions and surrounding whitespace), author, reference, hashes and prior transcription link are immutable. Corrections require the current latest ID; exact actor/payload retries retain one record and failed audit writes roll back. The source screen provides page entry and paginated history. It labels manual text as unverified and does not alter the prescription. This does not supply OCR: existing shared-platform Google Vision results cannot be assumed to cover pharmacy files. Provider transcription still requires its approved processing path.
2. Persist extraction attempts and drafts with source/transcription/version/model identities, requesting actor, timestamps and separate pending/succeeded/failed states. Failed or ambiguous requests must not silently retry or be represented as successful. A response never changes clinical records.
3. Expose a scoped human-review screen showing the original, page quote, suggested field and existing value. Retain each accept/reject/correct decision and explicit pharmacist comparison. Reject stale source/order/patient versions and roll back review evidence atomically on persistence failure.
4. Carry reviewed suggestions into a distinct intake/amendment form with normal authority, validation and confirmation. Human draft review is not clinical approval. Preserve rejected/raw suggestions and do not silently apply bulk changes.
5. Add a disabled-by-default provider adapter only after approved hosting/account, endpoint/model support, processing terms and deployment controls are identified. No arbitrary URL, tools, browser access, hidden retries or automatic routing to an alternative provider. Never send credentials or unrelated case/chart content in prompts.
6. Verify synthetic request/review/retry/failure/tenant/role/location/source-integrity cases and an end-to-end browser journey; then validate actual model quality, OCR fidelity and pharmacist acceptance before real use.

Hosted GLM versus self-hosted GLM, approved account and data-handling terms remain owner decisions. This work neither starts another listener nor widens production pharmacy availability.

Focused request and contract validation: nine tests / 103 assertions passed. TypeScript passed. The full backend suite passed 300 tests / 5,753 assertions; optimized admin build generated 120 pages. Synthetic migration preserved all 43 prior pharmacy tables. Chrome rejected blank text while preserving reason/confirmation, then retained an original transcription and a provenance correction. Both versions survived reload; read-back found two new transcription rows and two audit events, with all prior events and the other 42 pharmacy tables unchanged. The source form fitted a 390-pixel viewport without horizontal overflow. Current-head publication, database-engine CI and release packaging remain pending. No production or provider operation occurred.
