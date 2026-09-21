<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MedicalRecordRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MedicalRecordRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = MedicalRecordRequest::with(['user', 'case', 'requester', 'provider']);

        if ($request->has('case_id')) $query->where('case_id', $request->case_id);
        if ($request->has('user_id')) $query->where('user_id', $request->user_id);
        if ($request->has('status')) $query->where('status', $request->status);

        return response()->json([
            'status' => true,
            'data' => $query->latest()->paginate($request->get('per_page', 15)),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'case_id' => 'nullable|exists:cases,id',
            'user_id' => 'required|exists:users,id',
            'provider_id' => 'nullable|exists:providers,id',
            'provider_name' => 'required|string|max:255',
            'provider_fax' => 'nullable|string|max:50',
            'provider_email' => 'nullable|email|max:255',
            'provider_address' => 'nullable|string',
            'request_date' => 'required|date',
            'followup_date' => 'nullable|date|after_or_equal:request_date',
            'records_requested' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $data = array_merge($validator->validated(), [
            'organization_id' => $request->user()->organization_id,
            'requested_by' => $request->user()->id,
        ]);

        $record = MedicalRecordRequest::create($data);

        return response()->json([
            'status' => true,
            'message' => 'Medical record request created',
            'data' => $record->load(['user', 'case', 'requester', 'provider']),
        ], 201);
    }

    public function show($id)
    {
        return response()->json([
            'status' => true,
            'data' => MedicalRecordRequest::with(['user', 'case', 'requester', 'provider'])->findOrFail($id),
        ]);
    }

    public function update(Request $request, $id)
    {
        $record = MedicalRecordRequest::findOrFail($id);
        $record->update($request->only([
            'status', 'followup_date', 'notes', 'authorization_form_path', 'received_document_ids', 'provider_name', 'provider_fax', 'provider_email', 'provider_address'
        ]));
        return response()->json(['status' => true, 'message' => 'Updated', 'data' => $record->fresh()->load(['user', 'case'])]);
    }

    public function destroy($id)
    {
        MedicalRecordRequest::findOrFail($id)->delete();
        return response()->json(['status' => true, 'message' => 'Deleted']);
    }
}