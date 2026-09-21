<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\ClaimAppeal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AppealController extends Controller
{
    /**
     * Get list of appeals for the organization.
     */
    public function index(Request $request)
    {
        $appeals = ClaimAppeal::with(['invoice.case'])
            ->where('organization_id', $request->user()->organization_id)
            ->latest()
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => true,
            'message' => 'Appeals retrieved successfully',
            'data' => $appeals
        ]);
    }

    /**
     * Generate and save an AI-assisted appeal letter.
     */
    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invoice_id' => 'required|exists:invoices,id',
            'reason_category' => 'required|string',
            'additional_details' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $invoice = Invoice::with(['case'])->findOrFail($request->invoice_id);

        if ($invoice->status !== 'denied') {
            return response()->json([
                'status' => false,
                'message' => 'Appeals can only be generated for denied invoices.'
            ], 400);
        }

        // Mock AI Generation Logic
        $letterContent = $this->mockAppealLetter($invoice, $request->reason_category, $request->additional_details);

        // Save the appeal to the database
        $appeal = ClaimAppeal::create([
            'organization_id' => $request->user()->organization_id,
            'invoice_id' => $invoice->id,
            'appeal_number' => 'APP-' . strtoupper(Str::random(8)),
            'reason_category' => $request->reason_category,
            'content' => $letterContent,
            'status' => 'draft',
            'metadata' => [
                'additional_details' => $request->additional_details
            ]
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Appeal letter generated and saved successfully',
            'data' => $appeal
        ], 210);
    }

    private function mockAppealLetter($invoice, $category, $details)
    {
        $firmName = $invoice->organization->name ?? 'FaydaTech Legal';
        $caseTitle = $invoice->case->title ?? 'N/A';
        
        return "
RE: Formal Appeal of Claim Denial
Invoice Number: {$invoice->invoice_number}
Patient/Case: {$caseTitle}
Denial Category: {$category}

To Whom It May Concern,

This letter serves as a formal appeal regarding the denial of the above-referenced claim. Upon review of the denial notification, we believe the decision was made in error based on the following grounds related to {$category}.

Our records indicate that all services provided were medically necessary and documented in accordance with standard coding practices. Specifically:
- {$category} justification has been verified against clinical guidelines.
- Additional context: " . ($details ?? "No additional details provided.") . "

We request a thorough re-evaluation of this claim. If further documentation is required, please contact our billing department immediately.

Sincerely,
Billing Department
{$firmName}
        ";
    }
}
