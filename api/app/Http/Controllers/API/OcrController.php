<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\OcrProcessRequest;
use App\Models\ApiLog;
use App\Models\Document;
use App\Models\OcrResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * @OA\Tag(
 *     name="OCR",
 *     description="API Endpoints for Automated Document Text Extraction"
 * )
 */
class OcrController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/documents/{documentId}/ocr",
     *     summary="Run OCR on document",
     *     description="Process a PDF document through Google Vision to extract text content",
     *     operationId="processOcr",
     *     tags={"OCR"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="documentId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="OCR completed successfully")
     * )
     */
    public function process(OcrProcessRequest $request, int $documentId): JsonResponse
    {
        $document = Document::find($documentId);

        if (! $document) {
            return response()->json([
                'status' => false,
                'message' => 'Document not found',
            ], 404);
        }
        
        // 1. Check if file exists in storage
        if (! Storage::disk('public')->exists($document->path)) {
            return response()->json([
                'status' => false,
                'message' => 'Document file not found in storage',
            ], 404);
        }

        // 2. Restrict processing to PDFs ONLY
        if ($document->mime_type !== 'application/pdf') {
            return response()->json([
                'status' => false,
                'message' => 'OCR processing is restricted to PDF files only. Received type: ' . $document->mime_type,
            ], 422); // 422 Unprocessable Entity
        }

        $apiKey = getApiCredential('google_vision', 'API_KEY');
        
        // Fallback: If not found under google_vision/API_KEY, try general 'ocr' or just 'google_vision' with any name
        if (!$apiKey) {
            $fallback = \App\Models\ApiCredential::withoutGlobalScopes()
                ->where(function($q) {
                    $q->where('provider', 'google_vision')
                      ->orWhere('provider', 'ocr');
                })
                ->where('is_active', true)
                ->first();
            
            if ($fallback) {
                $apiKey = $fallback->key;
            }
        }
        
        if (! $apiKey) {
            return response()->json([
                'status' => false,
                'message' => 'Google Vision API key is missing. Please configure it in Integrations.',
            ], 500);
        }

        $ocrResult = OcrResult::create([
            'document_id' => $document->id,
            'processed_by' => optional($request->user())->id,
            'provider' => 'google_vision',
            'status' => 'processing',
        ]);

        $document->update(['ocr_status' => 'processing']);

        $featureType = $request->feature_type ?: 'DOCUMENT_TEXT_DETECTION';
        $languageHints = $request->language_hints ?: [];
        
        $absolutePath = storage_path('app/public/' . ltrim($document->path, '/'));
        
        if (!file_exists($absolutePath)) {
             $ocrResult->update(['status' => 'failed']);
             $document->update(['ocr_status' => 'failed']);

            return response()->json([
                'status' => false,
                'message' => 'Document file not accessible for OCR processing.',
            ], 500);
        }

        $pdfContent = file_get_contents($absolutePath);
        $pdfBase64 = base64_encode($pdfContent);
        
        // Estimate page count for chunking (since synchronous limit is 5 pages)
        $pageCount = preg_match_all("/\/Page\W/s", $pdfContent, $dummy) ?: 1;
        $maxPagesPerRequest = 5;
        $chunks = ceil($pageCount / $maxPagesPerRequest);
        
        $fullAnnotation = '';
        $fullResponse = [];

        try {
            $endpoint = "https://vision.googleapis.com/v1/files:annotate";

            for ($i = 0; $i < $chunks; $i++) {
                $startPage = ($i * $maxPagesPerRequest) + 1;
                $endPage = min(($i + 1) * $maxPagesPerRequest, $pageCount);
                
                $pages = range($startPage, $endPage);

                $payload = [
                    'requests' => [
                        [
                            'inputConfig' => [
                                'content' => $pdfBase64,
                                'mimeType' => 'application/pdf',
                            ],
                            'features' => [
                                ['type' => $featureType],
                            ],
                            'pages' => $pages,
                        ]
                    ]
                ];

                $response = Http::withHeaders([
                    'X-Goog-Api-Key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->timeout(120)
                ->post($endpoint, $payload);

                if (!$response->successful()) {
                    // If one chunk fails, we still try to return what we have or fail
                    break; 
                }

                $responseBody = $response->json();
                $fullResponse[] = $responseBody;

                $fileResponses = data_get($responseBody, 'responses.0.responses', []);
                foreach ($fileResponses as $res) {
                    $text = data_get($res, 'fullTextAnnotation.text') 
                        ?: data_get($res, 'textAnnotations.0.description', '');
                    $fullAnnotation .= $text . "\n";
                }
            }

            $fullAnnotation = trim($fullAnnotation);
            
            if (empty($fullAnnotation)) {
                throw new Throwable("No text extracted from any pages.");
            }

            $ocrResult->update([
                'status' => 'processed',
                'extracted_text' => $fullAnnotation,
                'full_response' => ['chunks' => $fullResponse],
                'metadata' => [
                    'feature_type' => $featureType,
                    'language_hints' => $languageHints,
                    'page_count' => $pageCount,
                ],
                'processed_at' => now(),
            ]);

            $document->update(['ocr_status' => 'processed']);

            return response()->json([
                'status' => true,
                'message' => 'OCR processed successfully for ' . $pageCount . ' pages.',
                'data' => [
                    'document_id' => $document->id,
                    'ocr_result_id' => $ocrResult->id,
                    'ocr_status' => 'processed',
                    'extracted_text' => $fullAnnotation,
                    'page_count' => $pageCount,
                ],
            ], 200);

        } catch (Throwable $exception) {
            $ocrResult->update([
                'status' => 'failed',
                'metadata' => ['error' => $exception->getMessage()],
            ]);

            $document->update(['ocr_status' => 'failed']);

            return response()->json([
                'status' => false,
                'message' => 'OCR processing failed',
                'error' => $exception->getMessage(),
            ], 500);
        }

    }

    /**
     * @OA\Get(
     *     path="/api/documents/{documentId}/ocr/latest",
     *     summary="Get latest OCR result",
     *     description="Retrieve the most recent text extraction result for a specific document",
     *     operationId="getLatestOcr",
     *     tags={"OCR"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="documentId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Document or result not found")
     * )
     */
    public function latest(int $documentId): JsonResponse
    {
        $document = Document::find($documentId);

        if (! $document) {
            return response()->json([
                'status' => false,
                'message' => 'Document not found',
            ], 404);
        }

        $result = OcrResult::where('document_id', $documentId)->latest()->first();

        return response()->json([
            'status' => true,
            'message' => $result ? 'OCR result retrieved successfully' : 'No OCR result found for document',
            'data' => $result,
        ], 200);
    }
}