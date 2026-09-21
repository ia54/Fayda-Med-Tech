<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ClientPortalSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClientPortalSettingsController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'status' => true,
            'data' => ClientPortalSetting::with('user')
                ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->user_id))
                ->paginate($request->get('per_page', 20)),
        ]);
    }

    public function show($userId)
    {
        $settings = ClientPortalSetting::with('user')->where('user_id', $userId)->first();
        if (!$settings) {
            return response()->json(['status' => false, 'message' => 'Settings not found for this user'], 404);
        }
        return response()->json(['status' => true, 'data' => $settings]);
    }

    public function update(Request $request, $userId)
    {
        $settings = ClientPortalSetting::firstOrCreate(
            ['user_id' => $userId, 'organization_id' => $request->user()->organization_id],
            ['user_id' => $userId, 'organization_id' => $request->user()->organization_id]
        );

        $settings->update($request->only([
            'case_status_visible', 'documents_visible', 'invoices_visible',
            'payments_visible', 'signatures_visible', 'medical_history_visible',
            'allow_document_upload', 'allow_messaging', 'theme',
        ]));

        return response()->json([
            'status' => true,
            'message' => 'Portal settings updated',
            'data' => $settings->fresh()->load('user'),
        ]);
    }
}