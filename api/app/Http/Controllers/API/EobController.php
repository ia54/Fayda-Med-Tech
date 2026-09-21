<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Eob;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EobController extends Controller
{
    /**
     * List EOBs with filters and aggregate stats.
     */
    public function index(Request $request)
    {
        $orgId = $request->user()->organization_id;
        $query = Eob::with(['case:id,title,case_number', 'uploader:id,first_name,last_name'])
            ->where('organization_id', $orgId);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->payer_name) {
            $query->where('payer_name', 'like', "%{$request->payer_name}%");
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('patient_name', 'like', "%{$search}%")
                  ->orWhere('payer_name', 'like', "%{$search}%")
                  ->orWhere('provider_name', 'like', "%{$search}%");
            });
        }

        $eobs = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => true,
            'message' => 'EOBs retrieved successfully',
            'data' => $eobs
        ]);
    }

    /**
     * Get aggregate EOB stats for the dashboard cards.
     */
    public function stats(Request $request)
    {
        $orgId = $request->user()->organization_id;

        $totalProcessed = Eob::where('organization_id', $orgId)
            ->whereIn('status', ['processed', 'matched'])
            ->count();

        $pendingReview = Eob::where('organization_id', $orgId)
            ->where('status', 'pending')
            ->count();

        $avgConfidence = Eob::where('organization_id', $orgId)
            ->whereNotNull('ai_confidence')
            ->avg('ai_confidence') ?? 0;

        $totalPaidAmount = Eob::where('organization_id', $orgId)
            ->sum('paid_amount');

        return response()->json([
            'status' => true,
            'message' => 'EOB stats retrieved',
            'data' => [
                'total_processed' => $totalProcessed,
                'pending_review' => $pendingReview,
                'avg_confidence' => round((float) $avgConfidence, 1),
                'total_paid_amount' => (float) $totalPaidAmount,
            ]
        ]);
    }

    /**
     * Store a new EOB record.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'case_id' => 'nullable|exists:cases,id',
            'document_id' => 'nullable|exists:documents,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'provider_name' => 'required|string|max:255',
            'patient_name' => 'required|string|max:255',
            'payer_name' => 'required|string|max:255',
            'billed_amount' => 'required|numeric|min:0',
            'allowed_amount' => 'nullable|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'patient_responsibility' => 'nullable|numeric|min:0',
            'service_date' => 'nullable|date',
            'eob_date' => 'nullable|date',
            'ai_confidence' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:pending,processed,matched,rejected',
            'extracted_data' => 'nullable|json',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $eob = Eob::create([
            'organization_id' => $request->user()->organization_id,
            'uploaded_by' => $request->user()->id,
            ...$request->only([
                'case_id', 'document_id', 'invoice_id', 'provider_name',
                'patient_name', 'payer_name', 'billed_amount', 'allowed_amount',
                'paid_amount', 'patient_responsibility', 'service_date', 'eob_date',
                'ai_confidence', 'status', 'extracted_data', 'notes'
            ])
        ]);

        $eob->load(['case:id,title,case_number', 'uploader:id,first_name,last_name']);

        return response()->json([
            'status' => true,
            'message' => 'EOB created successfully',
            'data' => $eob
        ], 201);
    }

    /**
     * Display the specified EOB.
     */
    public function show($id)
    {
        $eob = Eob::with(['case', 'document', 'invoice', 'uploader:id,first_name,last_name'])
            ->where('organization_id', request()->user()->organization_id)
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'EOB retrieved successfully',
            'data' => $eob
        ]);
    }

    /**
     * Update the specified EOB.
     */
    public function update(Request $request, $id)
    {
        $eob = Eob::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'case_id' => 'nullable|exists:cases,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'provider_name' => 'sometimes|required|string|max:255',
            'patient_name' => 'sometimes|required|string|max:255',
            'payer_name' => 'sometimes|required|string|max:255',
            'billed_amount' => 'sometimes|numeric|min:0',
            'allowed_amount' => 'nullable|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'patient_responsibility' => 'nullable|numeric|min:0',
            'service_date' => 'nullable|date',
            'eob_date' => 'nullable|date',
            'ai_confidence' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:pending,processed,matched,rejected',
            'extracted_data' => 'nullable|json',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $eob->update($request->only([
            'case_id', 'invoice_id', 'provider_name', 'patient_name',
            'payer_name', 'billed_amount', 'allowed_amount', 'paid_amount',
            'patient_responsibility', 'service_date', 'eob_date',
            'ai_confidence', 'status', 'extracted_data', 'notes'
        ]));

        return response()->json([
            'status' => true,
            'message' => 'EOB updated successfully',
            'data' => $eob
        ]);
    }

    /**
     * Delete EOB record.
     */
    public function destroy($id)
    {
        $eob = Eob::where('organization_id', request()->user()->organization_id)
            ->findOrFail($id);

        $eob->delete();

        return response()->json([
            'status' => true,
            'message' => 'EOB deleted successfully'
        ]);
    }
}
