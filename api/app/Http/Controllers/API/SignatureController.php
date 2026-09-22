<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\Signature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\DocuSignService;
use Throwable;

/**
 * @OA\Tag(
 *     name="Signatures",
 *     description="API Endpoints for Digital Signature Lifecycle Tracking"
 * )
 */
class SignatureController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/documents/{documentId}/signatures",
     *     summary="Get signature history",
     *     operationId="getSignatureHistory",
     *     tags={"Signatures"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="documentId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function historyByDocument(int $documentId): JsonResponse
    {
        $document = Document::visibleTo(auth()->user())->with(['signatures', 'signers'])->find($documentId);

        if (!$document) {
            return response()->json([
                'status' => false,
                'message' => 'Document not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Signature history retrieved successfully',
            'data' => [
                'document_id' => $document->id,
                'signature_status' => $document->signature_status,
                'signatures' => $document->signatures,
                'signers' => $document->signers,
            ],
        ]);
    }

    public function docusignWebhook(Request $request, DocuSignService $docuSignService): JsonResponse
    {
        $webhookSecret = (string) $docuSignService->getWebhookSecret();
        $incomingSignature = (string) $request->header('X-Docusign-Signature-1');
        $expectedSignature = base64_encode(hash_hmac('sha256', $request->getContent(), $webhookSecret, true));

        if ($webhookSecret === '' || $incomingSignature === '' || !hash_equals($expectedSignature, $incomingSignature)) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized webhook request',
            ], 401);
        }

        $payload = $request->all();
        $envelopeId = data_get($payload, 'data.envelopeId')
            ?: data_get($payload, 'envelopeId')
            ?: data_get($payload, 'envelopeSummary.envelopeId');

        if (!$envelopeId) {
            return response()->json([
                'status' => false,
                'message' => 'Envelope ID not found in webhook payload',
            ], 422);
        }

        $document = Document::where('docusign_envelope_id', $envelopeId)->first();

        if (!$document) {
            ApiLog::create([
                'provider' => 'docusign',
                'endpoint' => 'webhook',
                'request_method' => 'POST',
                'response_status' => 404,
                'status' => 'failed',
                'request_payload' => ['envelope_id' => $envelopeId],
                'error_message' => 'Document not found for envelope: ' . $envelopeId,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'No document found for given envelope',
            ], 404);
        }

        try {
            $eventStatus = strtolower((string) (data_get($payload, 'data.status') ?: data_get($payload, 'status') ?: 'pending'));
            $documentSignatureStatus = $this->mapEnvelopeStatus($eventStatus);

            DB::transaction(function () use ($document, $payload, $eventStatus, $documentSignatureStatus, $envelopeId) {
                $documentUpdate = [
                    'signature_status' => $documentSignatureStatus,
                ];

                if ($documentSignatureStatus === 'completed') {
                    $documentUpdate['document_status'] = 'signed';
                    $documentUpdate['signed_at'] = now();
                }

                $document->update($documentUpdate);

                $recipients = data_get($payload, 'data.recipients.signers', []);
                if (!is_array($recipients)) {
                    $recipients = [];
                }

                foreach ($recipients as $recipient) {
                    $email = data_get($recipient, 'email');
                    $recipientStatus = strtolower((string) data_get($recipient, 'status', 'pending'));
                    $signerStatus = $this->mapSignerStatus($recipientStatus);

                    $documentSigner = DocumentSigner::where('document_id', $document->id)
                        ->where('email', $email)
                        ->first();

                    if ($documentSigner) {
                        $documentSigner->update([
                            'status' => $signerStatus,
                            'docusign_recipient_id' => data_get($recipient, 'recipientId'),
                            'signed_at' => $signerStatus === 'signed' ? now() : $documentSigner->signed_at,
                        ]);
                    }

                    Signature::create([
                        'document_id' => $document->id,
                        'document_signer_id' => optional($documentSigner)->id,
                        'provider' => 'docusign',
                        'provider_envelope_id' => $envelopeId,
                        'provider_event' => $eventStatus,
                        'status' => $this->mapSignatureEventStatus($recipientStatus),
                        'signed_file_path' => data_get($payload, 'data.documents.0.uri'),
                        'signed_file_url' => data_get($payload, 'data.documents.0.url'),
                        'provider_payload' => ['status' => $recipientStatus],
                        'processed_at' => now(),
                    ]);
                }
            });

            ApiLog::create([
                'document_id' => $document->id,
                'provider' => 'docusign',
                'endpoint' => 'webhook',
                'request_method' => 'POST',
                'response_status' => 200,
                'status' => 'success',
                'request_payload' => ['envelope_id' => $envelopeId],
                'response_payload' => ['processed' => true, 'envelope_id' => $envelopeId],
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Webhook processed successfully',
                'data' => [
                    'document_id' => $document->id,
                    'signature_status' => $document->fresh()->signature_status,
                ],
            ], 200);
        } catch (Throwable $exception) {
            ApiLog::create([
                'document_id' => $document->id,
                'provider' => 'docusign',
                'endpoint' => 'webhook',
                'request_method' => 'POST',
                'response_status' => 500,
                'status' => 'failed',
                'request_payload' => ['envelope_id' => $envelopeId],
                'error_message' => $exception->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to process webhook',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    private function mapEnvelopeStatus(string $status): string
    {
        return match ($status) {
            'completed' => 'completed',
            'declined' => 'declined',
            'voided' => 'voided',
            default => 'pending',
        };
    }

    private function mapSignerStatus(string $status): string
    {
        return match ($status) {
            'completed' => 'signed',
            'declined' => 'declined',
            default => 'pending',
        };
    }

    private function mapSignatureEventStatus(string $status): string
    {
        return match ($status) {
            'completed' => 'completed',
            'declined' => 'declined',
            'voided' => 'failed',
            default => 'pending',
        };
    }
}
