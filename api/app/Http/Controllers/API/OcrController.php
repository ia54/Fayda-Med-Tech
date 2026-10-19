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
        $document = Document::visibleTo(auth()->user())->find($documentId);

        if (! $document) {
            return response()->json([
                'status' => false,
                'message' => 'Document not found',
            ], 404);
        }
        
        // 1. Check if file exists in storage
        if (! Storage::disk($document->disk())->exists($document->path)) {
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
        
        if (! $apiKey) {
            return response()->json([
                'status' => false,
                'message' => 'Google Vision API key is missing. Please configure it in Integrations.',
            ], 500);
        }

        $ocrResult = OcrResult::create([
            'document_id' => $document->id,
            'organization_id' => $document->organization_id,
            'processed_by' => optional($request->user())->id,
            'provider' => 'google_vision',
            'status' => 'processing',
        ]);

        $document->update(['ocr_status' => 'processing']);

        $featureType = $request->feature_type ?: 'DOCUMENT_TEXT_DETECTION';
        $languageHints = $request->language_hints ?: [];
        
        $absolutePath = Storage::disk($document->disk())->path($document->path);
        
        if (!file_exists($absolutePath)) {
             $ocrResult->update(['status' => 'failed']);
             $document->update(['ocr_status' => 'failed']);

            return response()->json([
                'status' => false,
                'message' => 'Document file not accessible for OCR processing.',
            ], 500);
        }

        $fullAnnotation = '';
        $fullResponse = [];
        $pageCount = null;
        $nextPage = 1;

        try {
            $pdfContent = file_get_contents($absolutePath);
            if ($pdfContent === false || $pdfContent === '') {
                throw new \RuntimeException('Document could not be read.');
            }
            $pdfBase64 = base64_encode($pdfContent);
            // The provider reports totalPages. PDF object scanning misses compressed pages.
            do {
                $pages = $pageCount === null ? [1] : range($nextPage, min($nextPage + 4, $pageCount));
                $response = Http::withHeaders([
                    'X-Goog-Api-Key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])->connectTimeout(10)->timeout(120)->post('https://vision.googleapis.com/v1/files:annotate', [
                    'requests' => [[
                        'inputConfig' => ['content' => $pdfBase64, 'mimeType' => 'application/pdf'],
                        'features' => [['type' => $featureType]],
                        'imageContext' => ['languageHints' => $languageHints],
                        'pages' => $pages,
                    ]],
                ]);
                if (! $response->successful()) {
                    throw new \RuntimeException('OCR provider request failed.');
                }
                $body = $response->json();
                $file = data_get($body, 'responses.0');
                $reportedPages = data_get($file, 'totalPages');
                if (! is_int($reportedPages) || $reportedPages < 1 || $reportedPages > 100
                    || ($pageCount !== null && $pageCount !== $reportedPages)
                    || data_get($body, 'error') || data_get($file, 'error')) {
                    throw new \RuntimeException('OCR provider did not return a supported complete document.');
                }
                $pageCount = $reportedPages;
                $responses = data_get($file, 'responses');
                if (! is_array($responses) || count($responses) !== count($pages)) {
                    throw new \RuntimeException('OCR provider omitted pages.');
                }
                foreach ($responses as $index => $page) {
                    if (data_get($page, 'error') || data_get($page, 'context.pageNumber') !== $pages[$index]) {
                        throw new \RuntimeException('OCR page failed or was returned out of order.');
                    }
                    $text = data_get($page, 'fullTextAnnotation.text') ?: data_get($page, 'textAnnotations.0.description', '');
                    if (! is_string($text)) throw new \RuntimeException('Invalid OCR text response.');
                    $fullAnnotation .= $text . "\n";
                }
                $fullResponse[] = $body;
                $nextPage = end($pages) + 1;
            } while ($nextPage <= $pageCount);

            $fullAnnotation = trim($fullAnnotation);
            
            if (empty($fullAnnotation)) {
                throw new \RuntimeException("No text extracted from any pages.");
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
                'metadata' => ['error' => 'OCR did not complete all pages. Retry or contact support.'],
            ]);

            $document->update(['ocr_status' => 'failed']);

            return response()->json([
                'status' => false,
                'message' => 'OCR processing failed',
                'error' => 'OCR did not complete all pages. Documents over 100 pages require a separate processing workflow.',
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
        $document = Document::visibleTo(auth()->user())->find($documentId);

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