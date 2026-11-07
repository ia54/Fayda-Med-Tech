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
        $payload = $request->all();
        $envelopeId = data_get($payload, 'data.envelopeId') ?: data_get($payload, 'envelopeId') ?: data_get($payload, 'envelopeSummary.envelopeId');
        $document = is_string($envelopeId) && strlen($envelopeId) <= 128
            ? Document::withoutGlobalScopes()->whereNull('deleted_at')->where('docusign_envelope_id', $envelopeId)->first() : null;
        $secret = (string) $docuSignService->getWebhookSecret($document?->organization_id);
        $signature = (string) $request->header('X-Docusign-Signature-1');
        if (!$document || $secret === '' || $signature === '' || !hash_equals(base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true)), $signature)) {
            return response()->json(['status'=>false, 'message'=>'Unauthorized webhook request'], 401);
        }

        if (is_string($payload['event'] ?? null) && str_starts_with($payload['event'], 'recipient-')) {
            return response()->json(['status'=>true, 'message'=>'Recipient event ignored; envelope summary required'], 200);
        }

        // JSON Connect may place the summary inside data.envelopeSummary.
        $status = data_get($payload, 'data.envelopeSummary.status') ?: data_get($payload, 'data.status') ?: data_get($payload, 'status') ?: data_get($payload, 'envelopeSummary.status');
        if (!$status && is_string($payload['event'] ?? null) && str_starts_with($payload['event'], 'envelope-')) $status = substr($payload['event'], 9);
        if (!is_string($status) || !in_array(strtolower($status), ['created','sent','delivered','completed','declined','voided'], true)) {
            // Recipient-only and unknown events cannot establish envelope completion.
            return response()->json(['status'=>true, 'message'=>'Event ignored'], 200);
        }
        $status = strtolower($status);
        $eventKey = 'webhook:' . hash('sha256', $request->getContent());
        try {
            DB::transaction(function () use ($document, $payload, $status, $eventKey, $envelopeId) {
                $locked = Document::withoutGlobalScopes()->whereKey($document->id)->lockForUpdate()->firstOrFail();
                $history = Signature::withoutGlobalScopes()->where('document_id', $locked->id)->where('provider', 'docusign');
                if ((clone $history)->where('provider_event', $eventKey)->exists()) return;
                $next = $this->mapEnvelopeStatus($status);
                $terminal = in_array($locked->signature_status, ['completed','declined','voided'], true);
                if (!$terminal) {
                    $update = ['signature_status'=>$next];
                    if ($next === 'completed') $update += ['document_status'=>'signed', 'signed_at'=>$locked->signed_at ?: now()];
                    $locked->update($update);
                }
                // Conflicting or older events cannot undo a terminal envelope or signer.
                if (!$terminal || $locked->signature_status === $next) {
                    $recipients = data_get($payload, 'data.envelopeSummary.recipients.signers') ?? data_get($payload, 'data.recipients.signers', []);
                    foreach (is_array($recipients) ? $recipients : [] as $recipient) {
                        if (!is_array($recipient) || !is_string($recipient['email'] ?? null)) continue;
                        $recipientStatus = $recipient['status'] ?? null;
                        if (!in_array($recipientStatus, ['sent','delivered','completed','declined'], true)) continue;
                        $signer = DocumentSigner::withoutGlobalScopes()->where('document_id', $locked->id)->where('email', $recipient['email'])->first();
                        if (!$signer || in_array($signer->status, ['signed','declined','failed'], true)) continue;
                        $nextSigner = $recipientStatus === 'completed' ? 'signed' : ($recipientStatus === 'declined' ? 'declined' : 'sent');
                        $signer->update(['status'=>$nextSigner, 'signed_at'=>$nextSigner === 'signed' ? ($signer->signed_at ?: now()) : $signer->signed_at]);
                    }
                }
                Signature::create([
                    'organization_id'=>$locked->organization_id, 'document_id'=>$locked->id,
                    'provider'=>'docusign', 'provider_envelope_id'=>$envelopeId,
                    'provider_event'=>$eventKey, 'status'=>$this->mapSignatureEventStatus($locked->signature_status),
                    'provider_payload'=>['envelope_status'=>$status, 'ignored_transition'=>$terminal && $locked->signature_status !== $next],
                    'processed_at'=>now(),
                ]);
            });
            return response()->json(['status'=>true, 'message'=>'Webhook processed successfully', 'data'=>['document_id'=>$document->id, 'signature_status'=>$document->fresh()->signature_status]]);
        } catch (Throwable $exception) {
            // Avoid retaining raw provider payloads, recipient details or database errors.
            return response()->json(['status'=>false, 'message'=>'Failed to process signing update'], 500);
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
