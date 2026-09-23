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

    private function validateAllocations(array $values): void
    {
        if (!isset($values['other_deductions'])) return;
        $deductions = CaseSettlement::cents($values['attorney_fees']) + CaseSettlement::cents($values['costs']) + CaseSettlement::cents($values['other_deductions']);
        if ($deductions > CaseSettlement::cents($values['settlement_amount'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['other_deductions' => 'Fees, costs and other deductions cannot exceed the settlement amount.']);
        }
    }

    /**
     * List all settlements for the organization.
     */
    public function index(Request $request)
    {
        $request->validate(['per_page' => 'sometimes|integer|min:1|max:100', 'page' => 'sometimes|integer|min:1', 'status' => 'sometimes|in:pending,completed,in-negotiation', 'include_history' => 'sometimes|boolean']);
        $query = $this->visibleSettlements($request)->with(['case:id,case_number,title', 'creator:id,first_name,last_name', 'correction:id,supersedes_id']);

        if (!$request->boolean('include_history')) $query->current();

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
            'request_id' => 'nullable|uuid',
            'settlement_amount' => 'required|numeric|min:0|max:9999999999999.99|decimal:0,2',
            'settlement_date' => 'required|date',
            'status' => 'required|in:pending,completed,in-negotiation',
            'notes' => 'nullable|string',
            'attorney_fees' => 'required_with:costs,other_deductions|numeric|min:0|max:9999999999999.99|decimal:0,2',
            'costs' => 'required_with:attorney_fees,other_deductions|numeric|min:0|max:9999999999999.99|decimal:0,2',
            'other_deductions' => 'required_with:attorney_fees,costs|numeric|min:0|max:9999999999999.99|decimal:0,2',
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

        $settlement = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $caseQuery) {
            $caseQuery->lockForUpdate()->findOrFail($request->case_id);
            $this->validateAllocations($request->all());
            $payload = $request->only(['case_id', 'settlement_date', 'status', 'notes']);
            $payload['case_id'] = (int) $request->case_id;
            $payload['notes'] = $request->notes ?? null;
            $payload['settlement_date'] = \Carbon\Carbon::parse($request->settlement_date)->toDateString();
            foreach (['settlement_amount', 'attorney_fees', 'costs', 'other_deductions'] as $field) {
                $payload[$field] = $request->has($field) ? CaseSettlement::cents($request->input($field)) : null;
            }
            $hash = hash('sha256', json_encode($payload));
            if ($request->request_id) {
                $existing = CaseSettlement::withTrashed()->where('organization_id', $request->user()->organization_id)
                    ->where('case_id', $request->case_id)->where('request_id', $request->request_id)->first();
                if ($existing) {
                    abort_if($existing->trashed() || $existing->created_by !== $request->user()->id || !hash_equals($existing->request_hash, $hash), 409, 'This save reference was already used. Review the existing settlement before creating another.');
                    return $existing->load(['case:id,case_number,title', 'creator:id,first_name,last_name']);
                }
            }
            $settlement = CaseSettlement::create([
                'organization_id' => $request->user()->organization_id,
                'created_by' => $request->user()->id,
                'request_id' => $request->request_id,
                'request_hash' => $request->request_id ? $hash : null,
                ...$request->only(['case_id', 'settlement_amount', 'settlement_date', 'status', 'notes', 'attorney_fees', 'costs', 'other_deductions'])
            ]);

            if ($settlement->status === 'completed') $this->refreshCaseValue($settlement);

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
        $settlement = $this->visibleSettlements(request())->with(['case', 'creator:id,first_name,last_name', 'correction:id,supersedes_id'])
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
            'attorney_fees' => 'required_with:costs,other_deductions|numeric|min:0|max:9999999999999.99|decimal:0,2',
            'costs' => 'required_with:attorney_fees,other_deductions|numeric|min:0|max:9999999999999.99|decimal:0,2',
            'other_deductions' => 'required_with:attorney_fees,costs|numeric|min:0|max:9999999999999.99|decimal:0,2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, &$settlement) {
            CaseModel::where('organization_id', $request->user()->organization_id)->lockForUpdate()->findOrFail($settlement->case_id);
            $settlement = $this->visibleSettlements($request)->lockForUpdate()->findOrFail($settlement->id);
            abort_if($settlement->status === 'completed', 409, 'Completed settlements require an auditable correction workflow.');
            $this->validateAllocations(array_replace($settlement->getAttributes(), $request->only(['settlement_amount', 'attorney_fees', 'costs', 'other_deductions'])));
            $settlement->update($request->only(['settlement_amount', 'settlement_date', 'status', 'notes', 'attorney_fees', 'costs', 'other_deductions']));

            if ($settlement->status === 'completed') $this->refreshCaseValue($settlement);

            $settlement->load(['case:id,case_number,title', 'creator:id,first_name,last_name']);

        });

        return response()->json([
            'status' => true,
            'message' => 'Settlement updated successfully',
            'data' => $settlement
        ]);
    }

    private function refreshCaseValue(CaseSettlement $settlement, bool $preserveStatus = false): void
    {
        $total = CaseSettlement::where('organization_id', $settlement->organization_id)
            ->where('case_id', $settlement->case_id)->current()->where('status', 'completed')
            ->get()->sum(fn ($record) => CaseSettlement::cents($record->settlement_amount));
        abort_if($total > 999999999999999, 422, 'Combined completed settlements exceed the supported case value.');
        CaseModel::where('organization_id', $settlement->organization_id)->whereKey($settlement->case_id)
            ->update(['total_case_value' => sprintf('%d.%02d', intdiv($total, 100), $total % 100), ...($preserveStatus ? [] : ['status' => 'Settlement'])]);
    }

    /** Append a replacement; the completed original remains unchanged. */
    public function correct(Request $request, $id)
    {
        abort_unless(in_array($request->user()->role, ['admin', 'firm_admin'], true), 403);
        $original = $this->visibleSettlements($request)->findOrFail($id);
        $values = $request->validate([
            'request_id' => 'required|uuid',
            'correction_reason' => 'required|string|min:5|max:2000',
            'settlement_amount' => 'required|numeric|min:0|max:9999999999999.99|decimal:0,2',
            'settlement_date' => 'required|date',
            'attorney_fees' => 'required|numeric|min:0|max:9999999999999.99|decimal:0,2',
            'costs' => 'required|numeric|min:0|max:9999999999999.99|decimal:0,2',
            'other_deductions' => 'required|numeric|min:0|max:9999999999999.99|decimal:0,2',
            'notes' => 'nullable|string',
        ]);
        $this->validateAllocations($values);
        $payload = $values;
        unset($payload['request_id']);
        $payload['notes'] = $payload['notes'] ?? null;
        $payload['settlement_date'] = \Carbon\Carbon::parse($payload['settlement_date'])->toDateString();
        $payload['supersedes_id'] = $original->id;
        foreach (['settlement_amount', 'attorney_fees', 'costs', 'other_deductions'] as $field) $payload[$field] = CaseSettlement::cents($payload[$field]);
        ksort($payload);
        $hash = hash('sha256', json_encode($payload));
        $replacement = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $original, $values, $hash) {
            CaseModel::where('organization_id', $original->organization_id)->lockForUpdate()->findOrFail($original->case_id);
            $original = $this->visibleSettlements($request)->lockForUpdate()->findOrFail($original->id);
            $existing = CaseSettlement::withTrashed()->where('organization_id', $original->organization_id)
                ->where('case_id', $original->case_id)->where('request_id', $values['request_id'])->first();
            if ($existing) {
                abort_if($existing->trashed() || $existing->created_by !== $request->user()->id || !hash_equals($existing->request_hash ?? '', $hash), 409, 'This save reference was already used for different details.');
                return $existing;
            }
            abort_unless($original->status === 'completed', 409, 'Only completed records can be corrected here.');
            abort_if($original->correction()->exists(), 409, 'This record already has a correction. Open the current record.');
            $replacement = CaseSettlement::create([
                ...$values,
                'organization_id' => $original->organization_id,
                'case_id' => $original->case_id,
                'created_by' => $request->user()->id,
                'status' => 'completed',
                'supersedes_id' => $original->id,
                'request_hash' => $hash,
            ]);
            $this->refreshCaseValue($replacement, true);
            $this->logTimeline($original->case_id, 'legal', 'Settlement Corrected',
                "Settlement #{$original->id} replaced by #{$replacement->id}; original preserved.",
                ['original_id' => $original->id, 'replacement_id' => $replacement->id, 'reason' => $values['correction_reason']]);
            return $replacement;
        });
        return response()->json(['status' => true, 'message' => 'Correction recorded; original preserved.', 'data' => $replacement], 201);
    }

    /**
     * Remove the specified settlement.
     */
    public function destroy($id)
    {
        $settlement = $this->visibleSettlements(request())
            ->findOrFail($id);

        \Illuminate\Support\Facades\DB::transaction(function () use ($settlement) {
            CaseModel::where('organization_id', request()->user()->organization_id)->lockForUpdate()->findOrFail($settlement->case_id);
            // Re-read after acquiring the same lock used by completion updates.
            $current = $this->visibleSettlements(request())->lockForUpdate()->findOrFail($settlement->id);
            abort_if($current->status === 'completed', 409, 'Completed settlements cannot be archived.');
            $current->delete();
        });

        return response()->json([
            'status' => true,
            'message' => 'Settlement deleted successfully'
        ]);
    }
}
