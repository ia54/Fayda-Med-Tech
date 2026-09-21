<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MedicalHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MedicalHistoryController extends Controller
{
    public function index(Request $request)
    {
        $query = MedicalHistory::with(['user', 'case']);
        $user = $request->user();

        if ($user->role === 'client') {
            $query->where('user_id', $user->id);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->has('case_id')) {
            $query->where('case_id', $request->case_id);
        }
        if ($request->has('record_type')) {
            $query->where('record_type', $request->record_type);
        }

        $records = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => true,
            'message' => 'Medical histories retrieved successfully',
            'data' => $records,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'case_id' => 'nullable|exists:cases,id',
            'record_date' => 'required|date',
            'provider_name' => 'nullable|string|max:255',
            'diagnosis' => 'nullable|string|max:255',
            'treatment_description' => 'nullable|string',
            'medications' => 'nullable|string',
            'notes' => 'nullable|string',
            'attachments' => 'nullable|array',
            'record_type' => 'required|in:pre_existing,treatment,surgery,medication,imaging,other',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $record = MedicalHistory::create(array_merge(
            $validator->validated(),
            ['organization_id' => $request->user()->organization_id]
        ));

        return response()->json([
            'status' => true,
            'message' => 'Medical history created successfully',
            'data' => $record->load(['user', 'case']),
        ], 201);
    }

    public function show($id)
    {
        $record = MedicalHistory::with(['user', 'case'])->findOrFail($id);
        return response()->json(['status' => true, 'data' => $record]);
    }

    public function update(Request $request, $id)
    {
        $record = MedicalHistory::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'record_date' => 'sometimes|required|date',
            'provider_name' => 'nullable|string|max:255',
            'diagnosis' => 'nullable|string|max:255',
            'treatment_description' => 'nullable|string',
            'medications' => 'nullable|string',
            'notes' => 'nullable|string',
            'attachments' => 'nullable|array',
            'record_type' => 'sometimes|required|in:pre_existing,treatment,surgery,medication,imaging,other',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $record->update($validator->validated());

        return response()->json([
            'status' => true,
            'message' => 'Medical history updated successfully',
            'data' => $record->load(['user', 'case']),
        ]);
    }

    public function destroy($id)
    {
        MedicalHistory::findOrFail($id)->delete();
        return response()->json(['status' => true, 'message' => 'Medical history deleted successfully']);
    }
}