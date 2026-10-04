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
        $query = ClaimAppeal::with(['invoice.case'])
            ->where('organization_id', $request->user()->organization_id);
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($query) use ($search) {
                $query->where('appeal_number', 'like', "%{$search}%")
                    ->orWhereHas('invoice', fn ($invoice) => $invoice->where('invoice_number', 'like', "%{$search}%"));
            });
        }
        $appeals = $query->latest()
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => true,
            'message' => 'Appeals retrieved successfully',
            'data' => $appeals
        ]);
    }

    /**
     * Prepare and save a template appeal draft for human review.
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

        // Local template only; no model or clinical verification is performed.
        $letterContent = $this->templateAppealLetter($invoice, $request->reason_category, $request->additional_details);

        // Save the appeal to the database
        $appeal = ClaimAppeal::create([
            'organization_id' => $request->user()->organization_id,
            'invoice_id' => $invoice->id,
            'appeal_number' => 'APP-' . strtoupper(Str::random(8)),
            'reason_category' => $request->reason_category,
            'content' => $letterContent,
            'status' => 'draft',
            'metadata' => [
                'generation_method' => 'template',
                'requires_human_review' => true,
                'additional_details' => $request->additional_details
            ]
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Appeal letter generated and saved successfully',
            'data' => $appeal
        ], 210);
    }

    private function templateAppealLetter($invoice, $category, $details)
    {
        $firmName = $invoice->organization->org_name ?? '[Organization name required]';
        $caseTitle = $invoice->case->title ?? 'N/A';
        
        return "
DRAFT TEMPLATE — HUMAN REVIEW REQUIRED — NOT SUBMITTED
RE: Review of Claim Denial
Invoice Number: {$invoice->invoice_number}
Patient/Case: {$caseTitle}
Denial Category: {$category}

To Whom It May Concern,

This draft concerns the recorded denial category {$category}. The reviewer must confirm the payer, denial notice, submission deadline and grounds for appeal before use.

Medical necessity, coding accuracy and clinical-guideline support have not been verified by this template. Add supporting records and an authorized reviewer’s determination before use.

User-provided context (requires verification): " . ($details ?? "No additional details provided.") . "

[Reviewer: add the requested action, supporting evidence, recipient and contact details.]

Sincerely,
Billing Department
{$firmName}
        ";
    }
}
