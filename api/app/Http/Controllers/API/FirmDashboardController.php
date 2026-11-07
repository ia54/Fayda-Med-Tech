<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FirmDashboardController extends Controller
{
    /**
     * Get firm-specific dashboard statistics.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $organizationId = $user->organization_id;

            if (!$organizationId) {
                return response()->json([
                    'status' => false,
                    'message' => 'User is not associated with any organization.'
                ], 403);
            }

            // Case Statistics Query Builder
            $query = CaseModel::where('organization_id', $organizationId);
            
            // If user is an attorney, they only see their assigned cases
            if ($user->role === 'attorney') {
                $query->assignedToAttorney($user->id);
            }

            $activeCasesCount = (clone $query)->whereIn('status', ['Active', 'active', 'Intake', 'New', 'Demand'])->count();
            
            $totalRecovery = (clone $query)->whereIn('status', ['Settlement', 'settled', 'Closed', 'closed', 'pending_settlement'])
                ->sum('total_case_value');

            $pendingAmount = (clone $query)->whereIn('status', ['Active', 'active', 'Intake', 'New', 'Demand'])
                ->sum('total_case_value');

            $settlementsReadyCount = (clone $query)->whereIn('status', ['Settlement', 'pending_settlement'])->count();

            // Recent Activity from Audit Logs
            $auditQuery = AuditLog::where('organization_id', $organizationId)->with('user:id,first_name,last_name');

            // If user is an attorney, they only see activity related to their assigned cases
            if ($user->role === 'attorney') {
                $assignedCaseIds = (clone $query)->pluck('id');
                $auditQuery->where('auditable_type', CaseModel::class)
                    ->whereIn('auditable_id', $assignedCaseIds);
            }

            $recentActivity = $auditQuery->latest()
                ->limit(5)
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'user' => $log->user ? $log->user->first_name . ' ' . $log->user->last_name : 'System',
                        'event' => $log->event,
                        'description' => $log->description,
                        'timestamp' => $log->created_at ? $log->created_at->diffForHumans() : 'Just now',
                    ];
                });

            // Revenue Data for Chart (Last 6 months)
            $revenueData = collect(range(5, 0))->map(function($i) use ($query) {
                $date = now()->subMonths($i);
                $month = $date->format('M');
                $amount = (clone $query)->whereIn('status', ['Settlement', 'settled', 'Closed', 'closed', 'pending_settlement'])
                    ->whereMonth('updated_at', $date->month)
                    ->whereYear('updated_at', $date->year)
                    ->sum('total_case_value');
                
                return [
                    'month' => $month,
                    'amount' => (float) $amount
                ];
            });

            // Team Workload (Cases per attorney)
            $teamWorkload = DB::table('case_parties')
                ->join('cases', 'case_parties.case_id', '=', 'cases.id')
                ->join('users', 'case_parties.user_id', '=', 'users.id')
                ->where('cases.organization_id', $organizationId)
                ->whereNull('cases.deleted_at')
                ->whereIn('cases.status', ['Active', 'active', 'Intake', 'New', 'Demand'])
                ->when($user->role === 'attorney', fn ($q) => $q->where('users.id', $user->id))
                ->where('case_parties.role_in_case', 'attorney')
                ->select('users.first_name', 'users.last_name', DB::raw('count(distinct cases.id) as case_count'))
                ->groupBy('users.id', 'users.first_name', 'users.last_name')
                ->get()
                ->map(function($item) {
                    return [
                        'name' => $item->first_name . ' ' . $item->last_name,
                        'cases' => $item->case_count
                    ];
                });

            // For the frontend dummy data structure
            return response()->json([
                'status' => true,
                'message' => 'Dashboard statistics retrieved successfully',
                'data' => [
                    'stats' => [
                        'active_cases' => $activeCasesCount,
                        'total_recovery' => (float) $totalRecovery,
                        'pending_amount' => (float) $pendingAmount,
                        'settlements_ready' => $settlementsReadyCount,
                    ],
                    'recent_activity' => $recentActivity,
                    'revenue_data' => $revenueData,
                    'team_workload' => $teamWorkload,
                    'recovery_growth' => null,
                    'financial_basis' => 'Recorded case values, not collected payments or settlement disbursements',
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
