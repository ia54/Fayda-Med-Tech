<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentUploadRequest;
use App\Http\Requests\SignerAssignmentRequest;
use App\Http\Requests\SignatureProcessRequest;
use App\Models\ApiLog;
use App\Models\CaseModel;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\Signature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Services\DocuSignService;
use App\Traits\LogsTimeline;
use DocuSign\eSign\Api\EnvelopesApi;
use DocuSign\eSign\Model\Document as DocuSignDocument;
use DocuSign\eSign\Model\EnvelopeDefinition;
use DocuSign\eSign\Model\Signer;
use DocuSign\eSign\Model\Tabs;
use DocuSign\eSign\Model\SignHere;
use Throwable;

/**
 * @OA\Tag(
 *     name="Documents",
 *     description="API Endpoints for Case Document Management and Digital Signatures"
 * )
 */
class DocumentController extends Controller
{
    use LogsTimeline;
    /**
     * @OA\Get(
     *     path="/api/documents",
     *     summary="List all documents",
     *     description="Retrieve a paginated list of uploaded documents, filterable by status",
     *     operationId="getDocuments",
     *     tags={"Documents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $perPage = max(1, min(100, (int) $request->get('per_page', 10)));

            $query = Document::visibleTo(auth()->user())->with(['signers', 'ocrResults']);

            $documents = $query->when($request->filled('document_status'), function ($query) use ($request) {
                    return $query->where('document_status', $request->document_status);
                })
                ->when($request->filled('signature_status'), function ($query) use ($request) {
                    return $query->where('signature_status', $request->signature_status);
                })
                ->when($request->filled('ocr_status'), function ($query) use ($request) {
                    return $query->where('ocr_status', $request->ocr_status);
                })
                ->when($request->filled('case_id'), function ($query) use ($request) {
                    return $query->where('metadata->case_id', $request->case_id);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'status' => true,
                'message' => 'Documents retrieved successfully',
                'data' => [
                    'documents' => $documents->items(),
                    'pagination' => [
                        'current_page' => $documents->currentPage(),
                        'per_page' => $documents->perPage(),
                        'total' => $documents->total(),
                        'last_page' => $documents->lastPage(),
                    ],
                ],
            ], 200);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve documents',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/documents",
     *     summary="Upload a document",
     *     operationId="uploadDocument",
     *     tags={"Documents"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file", "title"},
     *                 @OA\Property(property="file", type="string", format="binary"),
     *                 @OA\Property(property="title", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Document uploaded")
     * )
     */
    public function store(DocumentUploadRequest $request): JsonResponse
    {
        abort_unless($request->user()->organization_id, 422, 'An organization is required for uploads.');
        if ($caseId = data_get($request->metadata, 'case_id')) {
            $case = CaseModel::where('organization_id', $request->user()->organization_id);
            if (in_array($request->user()->role, ['client', 'attorney'], true)) {
                $case->whereHas('parties', fn ($q) => $q->where('user_id', $request->user()->id));
            }
            abort_unless($case->whereKey($caseId)->exists(), 404, 'Case not found');
        }
        try {
            $file = $request->file('file');
            $path = $file->store('uploads', 'documents');

            $document = Document::create([
                'uploaded_by' => $request->user()->id,
                'organization_id' => $request->user()->organization_id,
                'case_id' => data_get($request->metadata, 'case_id'),
                'storage_disk' => 'documents',
                'title' => $request->title,
                'original_name' => $file->getClientOriginalName(),
                'filename' => $file->hashName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'path' => $path,
                'url' => null,
                'metadata' => $request->metadata,
            ]);

            // Log to timeline if associated with a case
            $caseId = $request->metadata['case_id'] ?? null;
            if ($caseId) {
                $this->logTimeline(
                    (int) $caseId,
                    'document',
                    'Document Uploaded',
                    "A new document '{$document->title}' has been uploaded to the case.",
                    ['document_id' => $document->id, 'category' => $request->metadata['category'] ?? 'General']
                );
            }

            return response()->json([
                'status' => true,
                'message' => 'Document uploaded successfully',
                'data' => $document,
            ], 201);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to upload document',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $document = Document::visibleTo(auth()->user())->with(['signers', 'signatures', 'ocrResults'])->find($id);

        if (!$document) {
            return response()->json([
                'status' => false,
                'message' => 'Document not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Document retrieved successfully',
            'data' => $document,
        ], 200);
    }

    public function eligibleSigners(int $id): JsonResponse
    {
        $document = Document::visibleTo(auth()->user())->findOrFail($id);
        $users = \App\Models\User::where('organization_id', $document->organization_id)->where('status', 'active')
            ->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email']);
        return response()->json(['status' => true, 'data' => $users]);
    }

    public function assignSigners(SignerAssignmentRequest $request, int $id): JsonResponse
    {
        $document = Document::visibleTo(auth()->user())->find($id);

        if (!$document) {
            return response()->json([
                'status' => false,
                'message' => 'Document not found',
            ], 404);
        }

        abort_unless($document->signature_status === 'not_sent', 409, 'Signer assignments are locked after signing begins.');
        foreach ($request->signers as $signer) {
            if (!empty($signer['user_id'])) {
                $user = \App\Models\User::where('organization_id', $document->organization_id)->find($signer['user_id']);
                abort_unless($user && strcasecmp($user->email, $signer['email']) === 0, 422, 'Signer account must match the email and organization.');
            }
        }

        try {
            DB::transaction(function () use ($document, $request) {
                foreach ($request->signers as $index => $signer) {
                    DocumentSigner::updateOrCreate(
                        [
                            'document_id' => $document->id,
                            'email' => $signer['email'],
                        ],
                        [
                            'user_id' => $signer['user_id'] ?? null,
                            'name' => $signer['name'],
                            'signing_order' => $signer['signing_order'] ?? ($index + 1),
                            'status' => 'pending',
                        ]
                    );
                }
            });

            return response()->json([
                'status' => true,
                'message' => 'Signers assigned successfully',
                'data' => $document->load('signers'),
            ], 200);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to assign signers',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    public function sendForSignature(SignatureProcessRequest $request, int $id, DocuSignService $docuSignService): JsonResponse
    {
        $document = Document::visibleTo(auth()->user())->with('signers')->find($id);

        if (!$document) {
            return response()->json([
                'status' => false,
                'message' => 'Document not found',
            ], 404);
        }

        if ($document->signers->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No signers assigned for this document',
            ], 422);
        }

        if (!Storage::disk($document->disk())->exists($document->path)) {
            return response()->json([
                'status' => false,
                'message' => 'Document file not found in storage',
            ], 404);
        }

        try {
            $token = $docuSignService->getAccessToken();
            $accountId = $docuSignService->getAccountId();
            $baseUri = $docuSignService->getBaseUri();

            if (!$baseUri || !$accountId || !$token) {
                return response()->json([
                    'status' => false,
                    'message' => 'DocuSign configuration is incomplete after authentication attempt',
                ], 500);
            }
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'DocuSign authentication failed: ' . $e->getMessage(),
            ], 500);
        }

        $recipients = [];
        foreach ($document->signers->sortBy('signing_order')->values() as $index => $signer) {
            $recipients[] = [
                'email' => $signer->email,
                'name' => $signer->name,
                'recipientId' => (string) ($index + 1),
                'routingOrder' => (string) $signer->signing_order,
                'tabs' => [
                    'signHereTabs' => [
                        [
                            'anchorString' => '/sn1/',
                            'anchorUnits' => 'pixels',
                            'anchorXOffset' => '20',
                            'anchorYOffset' => '10',
                        ],
                    ],
                ],
            ];
        }

        $payload = [
            'emailSubject' => $request->email_subject ?: 'Please sign this document',
            'emailBlurb' => $request->email_message ?: 'A document requires your signature.',
            'documents' => [
                [
                    'documentBase64' => base64_encode(Storage::disk($document->disk())->get($document->path)),
                    'name' => $document->original_name,
                    'fileExtension' => pathinfo($document->original_name, PATHINFO_EXTENSION) ?: 'pdf',
                    'documentId' => '1',
                ],
            ],
            'recipients' => [
                'signers' => $recipients,
            ],
            'status' => 'sent',
        ];

        try {
            $endpoint = $baseUri . '/restapi/v2.1/accounts/' . $accountId . '/envelopes';

            $response = Http::withToken($token)
                ->acceptJson()
                ->post($endpoint, $payload);

            $this->storeApiLog($document->id, 'docusign', $endpoint, 'POST', $payload, $response->json(), $response->status(), $response->successful() ? 'success' : 'failed');

            if (!$response->successful()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to send document to DocuSign',
                    'error' => $response->json(),
                ], 500);
            }

            $envelopeId = data_get($response->json(), 'envelopeId');

            DB::transaction(function () use ($document, $envelopeId) {
                $document->update([
                    'docusign_envelope_id' => $envelopeId,
                    'document_status' => 'sent_for_signature',
                    'signature_status' => 'pending',
                    'sent_at' => now(),
                ]);

                $document->signers()->update(['status' => 'sent']);

                Signature::create([
                    'document_id' => $document->id,
                    'provider' => 'docusign',
                    'provider_envelope_id' => $envelopeId,
                    'provider_event' => 'envelope_sent',
                    'status' => 'pending',
                    'provider_payload' => ['response' => $document->fresh()],
                    'processed_at' => now(),
                ]);
            });

            return response()->json([
                'status' => true,
                'message' => 'Document sent for signature successfully',
                'data' => [
                    'document_id' => $document->id,
                    'envelope_id' => $envelopeId,
                    'signature_status' => 'pending',
                ],
            ], 200);
        } catch (Throwable $exception) {
            $this->storeApiLog($document->id, 'docusign', 'envelopes', 'POST', $payload, null, 500, 'failed', $exception->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'DocuSign integration failed',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    public function signInApp(Request $request, int $id): JsonResponse
    {
        $document = Document::visibleTo(auth()->user())->with('signers')->find($id);

        if (!$document) {
            return response()->json([
                'status' => false,
                'message' => 'Document not found',
            ], 404);
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf|max:20480',
            'signer_email' => 'nullable|email',
        ]);
        // The caller cannot select another person's signing identity.
        abort_if($request->filled('signer_email') && strcasecmp($request->signer_email, $request->user()->email) !== 0, 403);
        abort_if($document->docusign_envelope_id, 409, 'Complete this request through DocuSign.');
        $path = null;
        try {
            DB::transaction(function () use ($document, $request, &$path) {
                $document = Document::visibleTo($request->user())->lockForUpdate()->findOrFail($document->id);
                $signer = $document->signers()->where('user_id', $request->user()->id)->lockForUpdate()->first();
                abort_unless($signer, 403, 'Your account is not an assigned signer.');
                abort_unless(in_array($signer->status, ['pending', 'sent'], true), 409, 'This signature has already been processed.');
                abort_if($document->signers()->where('signing_order', '<', $signer->signing_order)->where('status', '!=', 'signed')->exists(), 409, 'An earlier signer must complete their signature first.');
                $file = $request->file('file');
                $path = $file->store('signed', 'documents');
                $signer->update(['status' => 'signed', 'signed_at' => now()]);
                $allSigned = !$document->signers()->where('status', '!=', 'signed')->exists();
                $document->update([
                    'document_status' => $allSigned ? 'signed' : 'sent_for_signature',
                    'signature_status' => $allSigned ? 'completed' : 'pending',
                    'signed_at' => $allSigned ? now() : null,
                ]);
                Signature::create([
                    'organization_id' => $document->organization_id,
                    'document_id' => $document->id,
                    'document_signer_id' => $signer->id,
                    'provider' => 'in-app',
                    'provider_event' => 'signed',
                    'status' => 'completed',
                    'signed_file_path' => $path,
                    'provider_payload' => [
                        'actor_id' => $request->user()->id,
                        'sha256' => hash_file('sha256', $file->getRealPath()),
                        'original_sha256' => hash('sha256', Storage::disk($document->disk())->get($document->path)),
                    ],
                    'processed_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            if ($path) { Storage::disk('documents')->delete($path); }
            throw $exception;
        }
        return response()->json([
            'status' => true,
            'message' => 'Document signed successfully',
            'data' => $document->fresh(['signers', 'signatures']),
        ]);
    }

    public function signatureStatus(int $id): JsonResponse
    {
        $document = Document::visibleTo(auth()->user())->with(['signers', 'signatures'])->find($id);

        if (!$document) {
            return response()->json([
                'status' => false,
                'message' => 'Document not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Signature status retrieved successfully',
            'data' => [
                'document_id' => $document->id,
                'document_status' => $document->document_status,
                'signature_status' => $document->signature_status,
                'envelope_id' => $document->docusign_envelope_id,
                'signers' => $document->signers,
                'signature_events' => $document->signatures,
            ],
        ], 200);
    }

    public function preview(int $id)
    {
        $document = Document::visibleTo(auth()->user())->find($id);

        if (!$document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        $signed = $document->signatures()->where('provider', 'in-app')->where('status', 'completed')
            ->whereNotNull('signed_file_path')->latest('id')->first();
        $disk = Storage::disk($signed ? 'documents' : $document->disk());
        $filePath = $signed ? $signed->signed_file_path : $document->path;
        abort_unless($disk->exists($filePath), 404, 'File not found in storage');
        $mime = $signed ? 'application/pdf' : $document->mime_type;
        $disposition = in_array($mime, ['application/pdf', 'image/png', 'image/jpeg'], true) ? 'inline' : 'attachment';
        return $disk->response($filePath, $document->original_name, [
            'Content-Type' => $mime ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
        ], $disposition);
    }

    public function destroy(int $id): JsonResponse
    {
        $document = Document::visibleTo(auth()->user())->find($id);

        if (!$document) {
            return response()->json([
                'status' => false,
                'message' => 'Document not found',
            ], 404);
        }

        try {
            $document->delete();

            return response()->json([
                'status' => true,
                'message' => 'Document archived successfully',
            ], 200);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete document',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    public function assignCategories(Request $request, int $id): JsonResponse
    {
        $document = Document::visibleTo(auth()->user())->with('categories')->find($id);
        if (!$document) {
            return response()->json(['status' => false, 'message' => 'Document not found'], 404);
        }

        $request->validate([
            'category_ids' => 'required|array',
            'category_ids.*' => ['integer', \Illuminate\Validation\Rule::exists('document_categories', 'id')->where('organization_id', $document->organization_id)],
        ]);

        $document->categories()->sync($request->category_ids);

        return response()->json([
            'status' => true,
            'message' => 'Categories assigned successfully',
            'data' => $document->fresh(['categories']),
        ]);
    }

    private function storeApiLog(?int $documentId, string $provider, string $endpoint, string $method, ?array $requestPayload, $responsePayload, ?int $responseStatus, string $status, ?string $errorMessage = null): void
    {
        ApiLog::create([
            'document_id' => $documentId,
            'provider' => $provider,
            'endpoint' => $endpoint,
            'request_method' => $method,
            'response_status' => $responseStatus,
            'status' => $status,
            'request_payload' => ['document_id' => $documentId],
            'response_payload' => ['status' => $responseStatus],
            'error_message' => $errorMessage,
        ]);
    }
}
