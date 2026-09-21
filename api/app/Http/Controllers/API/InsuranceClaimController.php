<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\InsuranceClaim;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InsuranceClaimController extends Controller
{
    /**
     * List insurance claims
     */
    public function index(Request $request)
    {
        $query = InsuranceClaim::with(['insuranceCompany', 'case', 'adjuster'])
            ->where('organization_id', $request->user()->organization_id);

        if ($request->case_id) {
            $query->where('case_id', $request->case_id);
        }

        if ($request->status) {
            $query->where('claim_status', $request->status);
        }

        if ($request->insurance_company_id) {
            $query->where('insurance_company_id', $request->insurance_company_id);
        }

        $claims = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => true,
            'message' => 'Insurance claims retrieved successfully',
            'data' => $claims
        ]);
    }

    /**
     * Store a new insurance claim
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'insurance_company_id' => 'required|exists:insurance_companies,id',
            'case_id' => 'required|exists:cases,id',
            'claim_number' => 'required|string|max:100',
            'coverage_type' => 'required|in:liability,pip,medpay,uninsured_motorist',
            'coverage_limit' => 'nullable|numeric|min:0',
            'claim_status' => 'nullable|in:open,pending,settled,denied',
            'demand_amount' => 'nullable|numeric|min:0',
            'settlement_offer' => 'nullable|numeric|min:0',
            'final_settlement' => 'nullable|numeric|min:0',
            'adjuster_id' => 'nullable|exists:insurance_adjusters,id',
            'adjuster_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $claim = InsuranceClaim::create([
            'organization_id' => $request->user()->organization_id,
            ...$request->only(['insurance_company_id', 'case_id', 'claim_number', 'coverage_type', 'coverage_limit', 'claim_status', 'demand_amount', 'settlement_offer', 'final_settlement', 'adjuster_id', 'adjuster_notes'])
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Insurance claim created successfully',
            'data' => $claim
        ], 201);
    }

    /**
     * Display the specified insurance claim.
     */
    public function show($id)
    {
        $claim = InsuranceClaim::with(['insuranceCompany', 'case', 'adjuster'])
            ->where('organization_id', request()->user()->organization_id)
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Insurance claim retrieved successfully',
            'data' => $claim
        ]);
    }

    /**
     * Update the specified insurance claim.
     */
    public function update(Request $request, $id)
    {
        $claim = InsuranceClaim::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'insurance_company_id' => 'sometimes|required|exists:insurance_companies,id',
            'case_id' => 'sometimes|required|exists:cases,id',
            'claim_number' => 'sometimes|required|string|max:100',
            'coverage_type' => 'sometimes|required|in:liability,pip,medpay,uninsured_motorist',
            'coverage_limit' => 'nullable|numeric|min:0',
            'claim_status' => 'nullable|in:open,pending,settled,denied',
            'demand_amount' => 'nullable|numeric|min:0',
            'settlement_offer' => 'nullable|numeric|min:0',
            'final_settlement' => 'nullable|numeric|min:0',
            'adjuster_id' => 'nullable|exists:insurance_adjusters,id',
            'adjuster_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $claim->update($request->only(['insurance_company_id', 'case_id', 'claim_number', 'coverage_type', 'coverage_limit', 'claim_status', 'demand_amount', 'settlement_offer', 'final_settlement', 'adjuster_id', 'adjuster_notes']));

        return response()->json([
            'status' => true,
            'message' => 'Insurance claim updated successfully',
            'data' => $claim
        ]);
    }
}
