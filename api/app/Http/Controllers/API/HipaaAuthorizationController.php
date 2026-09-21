<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\HipaaAuthorization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HipaaAuthorizationController extends Controller
{
    public function index(Request $request)
    {
        $query = HipaaAuthorization::with(['user', 'case', 'signer']);
        $user = $request->user();

        if ($user->role === 'client') {
            $query->where('user_id', $user->id);
        }

        if ($request->has('user_id')) $query->where('user_id', $request->user_id);
        if ($request->has('case_id')) $query->where('case_id', $request->case_id);
        if ($request->has('status')) $query->where('status', $request->status);

        return response()->json([
            'status' => true,
            'data' => $query->latest()->paginate($request->get('per_page', 15)),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'case_id' => 'nullable|exists:cases,id',
            'authorization_type' => 'required|in:hipaa_release,medical_records,treatment_consent,disclosure',
            'status' => 'required|in:pending,signed,expired,revoked',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:issue_date',
            'recipient_name' => 'nullable|string|max:255',
            'purpose' => 'nullable|string',
            'restrictions' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $auth = HipaaAuthorization::create(array_merge(
            $validator->validated(),
            ['organization_id' => $request->user()->organization_id]
        ));

        return response()->json([
            'status' => true,
            'message' => 'Authorization created successfully',
            'data' => $auth->load(['user', 'case']),
        ], 201);
    }

    public function show($id)
    {
        return response()->json([
            'status' => true,
            'data' => HipaaAuthorization::with(['user', 'case', 'signer'])->findOrFail($id),
        ]);
    }

    public function update(Request $request, $id)
    {
        $auth = HipaaAuthorization::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|required|in:pending,signed,expired,revoked',
            'expiry_date' => 'nullable|date',
            'recipient_name' => 'nullable|string|max:255',
            'purpose' => 'nullable|string',
            'restrictions' => 'nullable|string',
            'signed_document_path' => 'nullable|string',
            'signed_by' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        if (in_array('signed', [$data['status'] ?? null, $auth->status])) {
            $data['signed_at'] = now();
        }
        $auth->update($data);

        return response()->json(['status' => true, 'message' => 'Authorization updated', 'data' => $auth->fresh()->load(['user', 'case'])]);
    }

    public function destroy($id)
    {
        HipaaAuthorization::findOrFail($id)->delete();
        return response()->json(['status' => true, 'message' => 'Authorization deleted']);
    }
}