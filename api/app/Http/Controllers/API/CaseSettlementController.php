<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CaseSettlement;
use App\Models\CaseModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Traits\LogsTimeline;

class CaseSettlementController extends Controller
{
    use LogsTimeline;
    /**
     * List all settlements for the organization.
     */
    public function index(Request $request)
    {
        $query = CaseSettlement::with(['case:id,case_number,title', 'creator:id,first_name,last_name'])
            ->where('organization_id', $request->user()->organization_id);

        if ($request->case_id) {
            $query->where('case_id', $request->case_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $search = $request->search;
            $query->whereHas('case', function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('case_number', 'like', "%{$search}%");
            });
        }

        $settlements = $query->orderBy('settlement_date', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => true,
            'message' => 'Settlements retrieved successfully',
            'data' => $settlements
        ]);
    }

    /**
     * Store a new settlement.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'case_id' => 'required|exists:cases,id',
            'settlement_amount' => 'required|numeric|min:0',
            'settlement_date' => 'required|date',
            'status' => 'required|in:pending,completed,in-negotiation',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $settlement = CaseSettlement::create([
            'organization_id' => $request->user()->organization_id,
            'created_by' => $request->user()->id,
            ...$request->only(['case_id', 'settlement_amount', 'settlement_date', 'status', 'notes'])
        ]);

        // If status is completed, update the case total_case_value
        if ($request->status === 'completed') {
            CaseModel::where('id', $request->case_id)->update([
                'total_case_value' => $request->settlement_amount,
                'status' => 'Settlement'
            ]);
        }

        $settlement->load(['case:id,case_number,title', 'creator:id,first_name,last_name']);

        // Log to timeline
        $this->logTimeline(
            $settlement->case_id,
            'milestone',
            'Settlement Recorded',
            "A settlement of ${$settlement->settlement_amount} has been recorded with status '{$settlement->status}'.",
            ['amount' => $settlement->settlement_amount, 'status' => $settlement->status]
        );

        return response()->json([
            'status' => true,
            'message' => 'Settlement recorded successfully',
            'data' => $settlement
        ], 201);
    }

    /**
     * Display the specified settlement.
     */
    public function show($id)
    {
        $settlement = CaseSettlement::with(['case', 'creator:id,first_name,last_name'])
            ->where('organization_id', request()->user()->organization_id)
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Settlement retrieved successfully',
            'data' => $settlement
        ]);
    }

    /**
     * Update the specified settlement.
     */
    public function update(Request $request, $id)
    {
        $settlement = CaseSettlement::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'settlement_amount' => 'sometimes|required|numeric|min:0',
            'settlement_date' => 'sometimes|required|date',
            'status' => 'sometimes|required|in:pending,completed,in-negotiation',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $settlement->update($request->only(['settlement_amount', 'settlement_date', 'status', 'notes']));

        if ($settlement->status === 'completed') {
            CaseModel::where('id', $settlement->case_id)->update([
                'total_case_value' => $settlement->settlement_amount,
                'status' => 'Settlement'
            ]);
        }

        $settlement->load(['case:id,case_number,title', 'creator:id,first_name,last_name']);

        return response()->json([
            'status' => true,
            'message' => 'Settlement updated successfully',
            'data' => $settlement
        ]);
    }

    /**
     * Remove the specified settlement.
     */
    public function destroy($id)
    {
        $settlement = CaseSettlement::where('organization_id', request()->user()->organization_id)
            ->findOrFail($id);

        $settlement->delete();

        return response()->json([
            'status' => true,
            'message' => 'Settlement deleted successfully'
        ]);
    }
}
