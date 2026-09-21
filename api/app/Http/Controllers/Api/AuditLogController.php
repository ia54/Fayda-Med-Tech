<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Audit Logs",
 *     description="API Endpoints for System Activity Monitoring"
 * )
 */
class AuditLogController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/audit-logs",
     *     summary="List system audit logs",
     *     description="Retrieve a paginated list of all system events, filterable by organization, user, and date",
     *     operationId="getAuditLogs",
     *     tags={"Audit Logs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="organization_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request)
    {
        $query = AuditLog::with(['user', 'organization'])->latest();
        $authUser = $request->user();

        // Enforce Tenant Isolation
        if ($authUser->role !== 'admin') {
            $query->where('organization_id', $authUser->organization_id);
        } else {
            // Only Super Admin can filter by any organization
            if ($request->organization_id) {
                $query->where('organization_id', $request->organization_id);
            }
        }

        // Search by event or user name
        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('event', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by event
        if ($request->event) {
            $query->where('event', $request->event);
        }

        // Filter by date range
        if ($request->from_date) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->to_date) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $logs = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'status' => true,
            'message' => 'Audit logs retrieved successfully',
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/audit-logs/{id}",
     *     summary="Get audit log details",
     *     operationId="getAuditLogById",
     *     tags={"Audit Logs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function show($id)
    {
        $log = AuditLog::with(['user', 'organization', 'auditable'])->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Audit log details retrieved successfully',
            'data' => $log
        ]);
    }
}
