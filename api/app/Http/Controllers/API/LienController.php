<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Lien;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Traits\LogsTimeline;

class LienController extends Controller
{
    use LogsTimeline;
    /**
     * List liens
     */
    public function index(Request $request)
    {
        $query = Lien::with(['case', 'provider'])
            ->where('organization_id', $request->user()->organization_id);

        if ($request->case_id) {
            $query->where('case_id', $request->case_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->lien_type) {
            $query->where('lien_type', $request->lien_type);
        }

        $liens = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => true,
            'message' => 'Liens retrieved successfully',
            'data' => $liens
        ]);
    }

    /**
     * Store a new lien
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'case_id' => 'required|exists:cases,id',
            'provider_id' => 'nullable|exists:providers,id',
            'lien_type' => 'required|in:medical,attorney,government_medicare,government_medicaid,health_insurance',
            'amount' => 'required|numeric|min:0',
            'status' => 'nullable|in:pending,negotiated,settled,released',
            'negotiated_amount' => 'nullable|numeric|min:0',
            'reduction_amount' => 'nullable|numeric|min:0',
            'payoff_date' => 'nullable|date',
            'release_document_url' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $lien = Lien::create([
            'organization_id' => $request->user()->organization_id,
            ...$request->only(['case_id', 'provider_id', 'lien_type', 'amount', 'status', 'negotiated_amount', 'reduction_amount', 'payoff_date', 'release_document_url', 'notes'])
        ]);

        $this->logTimeline(
            (int) $lien->case_id,
            'legal',
            'Lien Added',
            "A new {$lien->lien_type} lien of ${$lien->amount} has been added.",
            ['lien_id' => $lien->id, 'type' => $lien->lien_type]
        );

        return response()->json([
            'status' => true,
            'message' => 'Lien created successfully',
            'data' => $lien
        ], 201);
    }

    /**
     * Display the specified lien.
     */
    public function show($id)
    {
        $lien = Lien::with(['case', 'provider'])
            ->where('organization_id', request()->user()->organization_id)
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Lien retrieved successfully',
            'data' => $lien
        ]);
    }

    /**
     * Update the specified lien.
     */
    public function update(Request $request, $id)
    {
        $lien = Lien::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'case_id' => 'sometimes|required|exists:cases,id',
            'provider_id' => 'nullable|exists:providers,id',
            'lien_type' => 'sometimes|required|in:medical,attorney,government_medicare,government_medicaid,health_insurance',
            'amount' => 'sometimes|required|numeric|min:0',
            'status' => 'nullable|in:pending,negotiated,settled,released',
            'negotiated_amount' => 'nullable|numeric|min:0',
            'reduction_amount' => 'nullable|numeric|min:0',
            'payoff_date' => 'nullable|date',
            'release_document_url' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $lien->update($request->only(['case_id', 'provider_id', 'lien_type', 'amount', 'status', 'negotiated_amount', 'reduction_amount', 'payoff_date', 'release_document_url', 'notes']));

        $this->logTimeline(
            (int) $lien->case_id,
            'legal',
            'Lien Updated',
            "Lien status changed to '{$lien->status}' with current amount ${$lien->amount}.",
            ['lien_id' => $lien->id, 'status' => $lien->status]
        );

        return response()->json([
            'status' => true,
            'message' => 'Lien updated successfully',
            'data' => $lien
        ]);
    }

    /**
     * Remove the specified lien.
     */
    public function destroy($id)
    {
        $lien = Lien::where('organization_id', request()->user()->organization_id)
            ->findOrFail($id);

        $lien->delete();

        return response()->json([
            'status' => true,
            'message' => 'Lien deleted successfully'
        ]);
    }
}
