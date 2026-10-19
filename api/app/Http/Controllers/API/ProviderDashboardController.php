<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\CaseModel;
use App\Models\ValidationIssue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProviderDashboardController extends Controller
{
    /**
     * Get provider-specific dashboard statistics and data.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $organizationId = $user->organization_id;

            // 1. Stats
            $totalClaims = Invoice::where('organization_id', $organizationId)->count();
            $totalRevenue = Payment::whereHas('invoice', function($q) use ($organizationId) {
                $q->where('organization_id', $organizationId);
            })->sum('amount');
            
            $pendingClaims = Invoice::where('organization_id', $organizationId)
                ->whereIn('status', ['draft', 'sent'])
                ->count();
            
            $paidCount = Invoice::where('organization_id', $organizationId)->where('status', 'paid')->count();
            $deniedCount = Invoice::where('organization_id', $organizationId)->where('status', 'denied')->count();
            $successRate = ($paidCount + $deniedCount) > 0 ? ($paidCount / ($paidCount + $deniedCount)) * 100 : 0;

            // 2. Recent Claims (Invoices)
            $recentClaims = Invoice::with(['case'])
                ->where('organization_id', $organizationId)
                ->latest()
                ->limit(5)
                ->get()
                ->map(function($invoice) {
                    return [
                        'id' => 'CLM-' . $invoice->invoice_number,
                        'patient' => $invoice->case ? $invoice->case->title : 'Unknown Patient',
                        'amount' => '$' . number_format($invoice->amount, 2),
                        'payer' => $invoice->metadata['payer'] ?? 'Not recorded',
                        'status' => ucfirst($invoice->status),
                        'date' => $invoice->created_at->format('Y-m-d'),
                    ];
                });

            // 3. Pending Tasks (Validation Issues)
            $pendingTasks = ValidationIssue::where('organization_id', $organizationId)
                ->where('status', 'pending')
                ->latest()
                ->limit(4)
                ->get()
                ->map(function($issue) {
                    return [
                        'id' => $issue->id,
                        'task' => $issue->description,
                        'priority' => ucfirst($issue->severity),
                        'dueDate' => $issue->created_at->addDays(2)->diffForHumans(),
                    ];
                });

            // Group by year and month; both local SQLite and production MySQL are supported.
            $monthExpression = DB::connection()->getDriverName() === 'sqlite'
                ? "strftime('%Y-%m', payment_date)"
                : "DATE_FORMAT(payment_date, '%Y-%m')";
            $monthlyRevenue = Payment::whereHas('invoice', function($q) use ($organizationId) {
                $q->where('organization_id', $organizationId);
            })
                ->selectRaw($monthExpression . ' as month_key, SUM(amount) as total')
                ->where('payment_date', '>=', now()->subMonths(6))
                ->groupByRaw($monthExpression)
                ->orderBy('month_key')
                ->get()
                ->map(fn ($row) => [
                    'month' => \Carbon\Carbon::createFromFormat('!Y-m', $row->month_key)->format('M Y'),
                    'total' => $row->total,
                ]);

            // 5. Status Distribution
            $statusDistribution = Invoice::where('organization_id', $organizationId)
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->map(function($item) use ($totalClaims) {
                    return [
                        'status' => ucfirst($item->status),
                        'percentage' => $totalClaims > 0 ? round(($item->count / $totalClaims) * 100) : 0,
                        'color' => $this->getStatusColor($item->status)
                    ];
                });

            return response()->json([
                'status' => true,
                'message' => 'Provider statistics retrieved successfully',
                'data' => [
                    'stats' => [
                        'total_claims' => $totalClaims,
                        'total_revenue' => '$' . number_format($totalRevenue, 2),
                        'pending_claims' => $pendingClaims,
                        'success_rate' => number_format($successRate, 1) . '%',
                    ],
                    'recent_claims' => $recentClaims,
                    'pending_tasks' => $pendingTasks,
                    'monthly_revenue' => $monthlyRevenue,
                    'status_distribution' => $statusDistribution
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve provider statistics',
                // Internal exception details must not be exposed to portal users.
            ], 500);
        }
    }

    private function getStatusColor($status) {
        switch ($status) {
            case 'paid': return 'bg-chart-2';
            case 'sent': return 'bg-primary';
            case 'denied': return 'bg-chart-4';
            case 'draft': return 'bg-chart-3';
            default: return 'bg-muted';
        }
    }
}
