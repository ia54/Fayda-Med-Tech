<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * PDF Section 16: GDPR Compliance Tools
 */
class GdprController extends Controller
{
    /**
     * Export all user data (GDPR data portability)
     */
    public function exportUserData(Request $request, $userId)
    {
        $request->user()->isSuperAdmin() || $request->user()->isFirmAdmin();
        $user = User::with(['documents', 'cases', 'invoices', 'payments'])
            ->where('organization_id', $request->user()->organization_id)
            ->findOrFail($userId);

        $exportData = [
            'user_info' => $user->toArray(),
            'documents' => $user->documents()->get(),
            'cases' => $user->cases()->get(),
            'invoices' => $user->invoices()->get(),
            'payments' => $user->payments()->get(),
            'audit_logs' => $user->auditLogs()->get(),
            'exported_at' => now()->toIso8601String(),
            'exported_by' => $request->user()->email,
        ];

        return response()->json([
            'status' => true,
            'message' => 'User data exported successfully',
            'data' => $exportData
        ]);
    }

    /**
     * Delete all user data (GDPR right to erasure)
     * This is a soft delete - data is anonymized, not permanently removed
     */
    public function deleteUserData(Request $request, $userId)
    {
        $request->user()->isSuperAdmin() || $request->user()->isFirmAdmin();
        
        if (!config('app.gdpr_enable_soft_delete', true)) {
            return response()->json([
                'status' => false,
                'message' => 'GDPR soft delete is disabled'
            ], 403);
        }

        $user = User::where('organization_id', $request->user()->organization_id)
            ->findOrFail($userId);

        // Anonymize user data
        $user->update([
            'first_name' => 'Deleted',
            'last_name' => 'User',
            'email' => 'deleted_' . $user->id . '@anonymized.com',
            'ssn' => null,
            'dob' => null,
            'phone' => null,
            'address' => null,
            'status' => 'deleted',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'User data has been anonymized (GDPR deletion)'
        ]);
    }

    /**
     * Get audit trail for a specific user (PHI access tracking)
     */
    public function userAuditTrail(Request $request, $userId)
    {
        $request->user()->isSuperAdmin() || $request->user()->isFirmAdmin();
        
        $logs = \App\Models\AuditLog::where('user_id', $userId)
            ->orWhere('metadata->user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 50));

        return response()->json([
            'status' => true,
            'message' => 'Audit trail retrieved successfully',
            'data' => $logs
        ]);
    }
}
