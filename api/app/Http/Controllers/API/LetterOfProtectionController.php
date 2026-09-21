<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LetterOfProtection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LetterOfProtectionController extends Controller
{
    /**
     * List Letters of Protection
     */
    public function index(Request $request)
    {
        $query = LetterOfProtection::with(['case', 'provider'])
            ->where('organization_id', $request->user()->organization_id);

        if ($request->case_id) {
            $query->where('case_id', $request->case_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $lops = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => true,
            'message' => 'Letters of Protection retrieved successfully',
            'data' => $lops
        ]);
    }

    /**
     * Store a new Letter of Protection
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'case_id' => 'required|exists:cases,id',
            'provider_id' => 'required|exists:providers,id',
            'lop_number' => 'nullable|string|max:100',
            'amount_covered' => 'nullable|numeric|min:0',
            'services_covered' => 'nullable|array',
            'expiry_date' => 'nullable|date',
            'status' => 'nullable|in:issued,accepted,expired,cancelled',
            'provider_acceptance_date' => 'nullable|date',
            'document_url' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $lop = LetterOfProtection::create([
            'organization_id' => $request->user()->organization_id,
            ...$request->only(['case_id', 'provider_id', 'lop_number', 'amount_covered', 'services_covered', 'expiry_date', 'status', 'provider_acceptance_date', 'document_url', 'notes'])
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Letter of Protection created successfully',
            'data' => $lop
        ], 201);
    }

    /**
     * Display the specified LOP.
     */
    public function show($id)
    {
        $lop = LetterOfProtection::with(['case', 'provider'])
            ->where('organization_id', request()->user()->organization_id)
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Letter of Protection retrieved successfully',
            'data' => $lop
        ]);
    }

    /**
     * Update the specified LOP.
     */
    public function update(Request $request, $id)
    {
        $lop = LetterOfProtection::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'case_id' => 'sometimes|required|exists:cases,id',
            'provider_id' => 'sometimes|required|exists:providers,id',
            'lop_number' => 'nullable|string|max:100',
            'amount_covered' => 'nullable|numeric|min:0',
            'services_covered' => 'nullable|array',
            'expiry_date' => 'nullable|date',
            'status' => 'nullable|in:issued,accepted,expired,cancelled',
            'provider_acceptance_date' => 'nullable|date',
            'document_url' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $lop->update($request->only(['case_id', 'provider_id', 'lop_number', 'amount_covered', 'services_covered', 'expiry_date', 'status', 'provider_acceptance_date', 'document_url', 'notes']));

        return response()->json([
            'status' => true,
            'message' => 'Letter of Protection updated successfully',
            'data' => $lop
        ]);
    }

    /**
     * Remove the specified LOP.
     */
    public function destroy($id)
    {
        $lop = LetterOfProtection::where('organization_id', request()->user()->organization_id)
            ->findOrFail($id);

        $lop->delete();

        return response()->json([
            'status' => true,
            'message' => 'Letter of Protection deleted successfully'
        ]);
    }
}
