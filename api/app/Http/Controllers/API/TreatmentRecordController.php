<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TreatmentRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TreatmentRecordController extends Controller
{
    /**
     * List treatment records
     */
    public function index(Request $request)
    {
        $query = TreatmentRecord::with(['case', 'provider', 'patient'])
            ->where('organization_id', $request->user()->organization_id);

        if ($request->case_id) {
            $query->where('case_id', $request->case_id);
        }

        if ($request->provider_id) {
            $query->where('provider_id', $request->provider_id);
        }

        $records = $query->orderBy('treatment_date', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => true,
            'message' => 'Treatment records retrieved successfully',
            'data' => $records
        ]);
    }

    /**
     * Store a new treatment record
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'case_id' => 'required|exists:cases,id',
            'provider_id' => 'nullable|exists:providers,id',
            'patient_id' => 'nullable|exists:users,id',
            'treatment_date' => 'required|date',
            'treatment_type' => 'nullable|string|max:255',
            'diagnosis_codes' => 'nullable|array',
            'procedure_codes' => 'nullable|array',
            'notes' => 'nullable|string',
            'mmi_status' => 'nullable|in:pending,reached,exceeded',
            'disability_rating' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $record = TreatmentRecord::create([
            'organization_id' => $request->user()->organization_id,
            ...$request->only(['case_id', 'provider_id', 'patient_id', 'treatment_date', 'treatment_type', 'diagnosis_codes', 'procedure_codes', 'notes', 'mmi_status', 'disability_rating'])
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Treatment record created successfully',
            'data' => $record
        ], 201);
    }

    /**
     * Display the specified treatment record.
     */
    public function show($id)
    {
        $record = TreatmentRecord::with(['case', 'provider', 'patient'])
            ->where('organization_id', request()->user()->organization_id)
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Treatment record retrieved successfully',
            'data' => $record
        ]);
    }

    /**
     * Update the specified treatment record.
     */
    public function update(Request $request, $id)
    {
        $record = TreatmentRecord::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'case_id' => 'sometimes|required|exists:cases,id',
            'provider_id' => 'nullable|exists:providers,id',
            'patient_id' => 'nullable|exists:users,id',
            'treatment_date' => 'sometimes|required|date',
            'treatment_type' => 'nullable|string|max:255',
            'diagnosis_codes' => 'nullable|array',
            'procedure_codes' => 'nullable|array',
            'notes' => 'nullable|string',
            'mmi_status' => 'nullable|in:pending,reached,exceeded',
            'disability_rating' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $record->update($request->only(['case_id', 'provider_id', 'patient_id', 'treatment_date', 'treatment_type', 'diagnosis_codes', 'procedure_codes', 'notes', 'mmi_status', 'disability_rating']));

        return response()->json([
            'status' => true,
            'message' => 'Treatment record updated successfully',
            'data' => $record
        ]);
    }
}
