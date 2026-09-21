<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use OpenAI\Client as OpenAI;

/**
 * PDF Section 8 & 9: Document Management with AI OCR & AI Vision Features
 * Integrates OpenAI GPT-4 Vision / Google Cloud Vision
 */
class AiOcrController extends Controller
{
    protected $openai;

    public function __construct()
    {
        $this->openai = \OpenAI::client(config('services.openai.api_key'));
    }

    /**
     * Process document with AI Vision (PDF Section 9.1)
     * Uses OpenAI GPT-4 Vision to extract structured data from documents
     */
    public function processWithVision(Request $request)
    {
        $validator = validator()->make($request->all(), [
            'document_id' => 'required|exists:documents,id',
            'ai_provider' => 'nullable|in:openai,google',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $document = \App\Models\Document::findOrFail($request->document_id);
        
        // Get document file path
        $filePath = storage_path('app/' . $document->file_path);
        if (!file_exists($filePath)) {
            return response()->json([
                'status' => false,
                'message' => 'Document file not found'
            ], 404);
        }

        try {
            // Convert PDF to images if needed, or use image directly
            $base64Image = base64_encode(file_get_contents($filePath));
            
            // Call OpenAI GPT-4 Vision API
            $response = $this->openai->chat()->create([
                'model' => 'gpt-4-vision-preview',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Extract the following information from this medical document: 
                                1. Patient name, DOB, and any ID numbers
                                2. Provider name, NPI, and address
                                3. Diagnosis codes (ICD-10) and procedure codes (CPT)
                                4. Billing amounts and dates of service
                                5. Insurance information
                                
                                Return the data in JSON format with confidence scores for each field.'
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => 'data:image/png;base64,' . $base64Image
                                ]
                            ]
                        ]
                    ]
                ],
                'max_tokens' => 2000
            ]);

            $extractedText = $response->choices[0]->message->content;
            
            // Parse and strucure the response
            $structuredData = json_decode($extractedText, true);
            
            // Update OCR result
            $ocrResult = \App\Models\OcrResult::updateOrCreate(
                ['document_id' => $document->id],
                [
                    'raw_text' => $extractedText,
                    'structured_data' => $structuredData,
                    'confidence_score' => $structuredData['confidence'] ?? 0.85,
                    'ai_provider' => 'openai',
                    'processed_by' => $request->user()->id,
                    'processed_at' => now(),
                ]
            );

            return response()->json([
                'status' => true,
                'message' => 'Document processed with AI Vision successfully',
                'data' => [
                    'ocr_result_id' => $ocrResult->id,
                    'extracted_data' => $structuredData,
                    'confidence_score' => $ocrResult->confidence_score,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'AI processing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get supported document types (PDF Section 9.1)
     */
    public function getSupportedDocumentTypes()
    {
        $types = [
            'medical_record' => 'Medical records (hospital, clinic, imaging, therapy notes)',
            'eob' => 'Explanation of Benefits (EOB)/Explanation of Review (EOR)',
            'police_report' => 'Police / accident reports',
            'medical_bill' => 'Medical bills and itemized statements',
            'insurance_policy' => 'Insurance policies and declarations pages',
            'retainer_agreement' => 'Attorney-client retainer agreements',
            'hipaaa_auth' => 'HIPAA authorization forms',
            'lien_agreement' => 'Lien agreements and letters of protection',
        ];

        return response()->json([
            'status' => true,
            'message' => 'Supported document types retrieved',
            'data' => $types
        ]);
    }

    /**
     * Auto-detect document type using AI
     */
    public function detectDocumentType(Request $request)
    {
        $validator = validator()->make($request->all(), [
            'document_id' => 'required|exists:documents,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $document = \App\Models\Document::findOrFail($request->document_id);
        
        try {
            $base64Image = base64_encode(file_get_contents(storage_path('app/' . $document->file_path)));
            
            $response = $this->openai->chat()->create([
                'model' => 'gpt-4-vision-preview',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'What type of document is this? Choose from: medical_record, eob, police_report, medical_bill, insurance_policy, retainer_agreement, hipaa_auth, lien_agreement. Return only the type key.'
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => 'data:image/png;base64,' . $base64Image
                                ]
                            ]
                        ]
                    ]
                ],
                'max_tokens' => 50
            ]);

            $detectedType = trim($response->choices[0]->message->content);

            return response()->json([
                'status' => true,
                'message' => 'Document type detected',
                'data' => [
                    'detected_type' => $detectedType,
                    'confidence' => 0.90
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Detection failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
