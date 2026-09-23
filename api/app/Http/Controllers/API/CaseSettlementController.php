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

    private function visibleSettlements(Request $request)
    {
        abort_unless($request->user()->organization_id, 403);
        return CaseSettlement::where('organization_id', $request->user()->organization_id)
            ->whereHas('case', function ($query) use ($request) {
                if ($request->user()->role === 'attorney') $query->assignedToAttorney($request->user()->id);
            });
    }

    /**
     * List all settlements for the organization.
     */
    public function index(Request $request)
    {
        $query = $this->visibleSettlements($request)->with(['case:id,case_number,title', 'creator:id,first_name,last_name']);

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

        $settlements = $query->orderBy('settlement_date', 'desc')->orderByDesc('id')
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
            'case_id' => 'required|integer',
            'settlement_amount' => 'required|numeric|min:0|max:9999999999999.99|decimal:0,2',
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

        abort_unless($request->user()->organization_id, 403);
        $caseQuery = CaseModel::where('organization_id', $request->user()->organization_id);
        if ($request->user()->role === 'attorney') $caseQuery->assignedToAttorney($request->user()->id);
        $caseQuery->findOrFail($request->case_id);

        $settlement = \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
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

            return $settlement;
        });

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
        $settlement = $this->visibleSettlements(request())->with(['case', 'creator:id,first_name,last_name'])
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
        $settlement = $this->visibleSettlements($request)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'settlement_amount' => 'sometimes|required|numeric|min:0|max:9999999999999.99|decimal:0,2',
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

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $settlement) {
            abort_if($settlement->status === 'completed', 409, 'Completed settlements require an auditable correction workflow.');
            $settlement->update($request->only(['settlement_amount', 'settlement_date', 'status', 'notes']));

            if ($settlement->status === 'completed') {
                CaseModel::where('id', $settlement->case_id)->update([
                    'total_case_value' => $settlement->settlement_amount,
                    'status' => 'Settlement'
                ]);
            }

            $settlement->load(['case:id,case_number,title', 'creator:id,first_name,last_name']);

        });

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
        $settlement = $this->visibleSettlements(request())
            ->findOrFail($id);

        abort_if($settlement->status === 'completed', 409, 'Completed settlements cannot be archived.');
        $settlement->delete();

        return response()->json([
            'status' => true,
            'message' => 'Settlement deleted successfully'
        ]);
    }
}
