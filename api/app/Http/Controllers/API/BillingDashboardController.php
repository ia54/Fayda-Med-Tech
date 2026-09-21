<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ClaimAppeal;
use App\Models\ValidationIssue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingDashboardController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $orgId = $user->organization_id;
            
            // Work Queue count (Draft/Denied invoices + Open validation issues)
            $workQueueCount = Invoice::where('organization_id', $orgId)
                              ->whereIn('status', ['draft', 'denied'])->count() + 
                              ValidationIssue::where('organization_id', $orgId)
                              ->where('status', 'open')->count();
            
            // Appeals count
            $appealsCount = ClaimAppeal::where('organization_id', $orgId)->count();
            
            // Claims Processed
            $claimsProcessedCount = Invoice::where('organization_id', $orgId)
                                    ->whereIn('status', ['sent', 'paid', 'denied'])->count();
            
            // Success Rate
            $paidCount = Invoice::where('organization_id', $orgId)->where('status', 'paid')->count();
            $deniedCount = Invoice::where('organization_id', $orgId)->where('status', 'denied')->count();
            $totalResolved = $paidCount + $deniedCount;
            $successRate = $totalResolved > 0 ? ($paidCount / $totalResolved) * 100 : 0;

            // Priority Items (Combined from Invoices and Validation Issues)
            $priorityInvoices = Invoice::with(['case'])
                ->where('organization_id', $orgId)
                ->where('status', 'denied')
                ->latest()
                ->limit(3)
                ->get()
                ->map(function ($invoice) {
                    return [
                        'id' => 'INV-' . $invoice->invoice_number,
                        'patient' => $invoice->case ? $invoice->case->title : 'Unknown Patient',
                        'issue' => 'Claim Denied by Payer',
                        'priority' => 'High',
                        'payer' => $invoice->metadata['payer'] ?? 'Blue Cross',
                        'amount' => '$' . number_format($invoice->amount, 2),
                        'assigned' => $invoice->updated_at->diffForHumans(),
                        'type' => 'Denial',
                    ];
                });

            $priorityValidation = ValidationIssue::latest()
                ->where('organization_id', $orgId)
                ->where('status', 'open')
                ->limit(2)
                ->get()
                ->map(function ($issue) {
                    return [
                        'id' => 'VAL-' . $issue->id,
                        'patient' => 'Bulk Processing',
                        'issue' => $issue->issue_description,
                        'priority' => ucfirst($issue->severity),
                        'payer' => 'N/A',
                        'amount' => 'N/A',
                        'assigned' => $issue->created_at->diffForHumans(),
                        'type' => 'Validation Error',
                    ];
                });

            $workQueuePreview = $priorityInvoices->concat($priorityValidation)->sortByDesc('assigned');

            // Recent Appeals
            $recentAppeals = ClaimAppeal::latest()
                ->where('organization_id', $orgId)
                ->limit(5)
                ->get()
                ->map(function($appeal) {
                    return [
                        'id' => 'APP-' . $appeal->id,
                        'claimId' => 'CLM-' . $appeal->invoice_id,
                        'patient' => 'Patient ' . $appeal->invoice_id,
                        'payer' => $appeal->payer_name ?? 'Insurance',
                        'reason' => $appeal->reason_category,
                        'status' => ucfirst($appeal->status),
                        'generated' => $appeal->created_at->format('Y-m-d'),
                        'aiAssisted' => true,
                    ];
                });

            // Denial Reasons
            $denialReasons = ClaimAppeal::select('reason_category', DB::raw('count(*) as count'))
                ->where('organization_id', $orgId)
                ->groupBy('reason_category')
                ->get()
                ->map(function($item) use ($appealsCount) {
                    return [
                        'reason' => $item->reason_category,
                        'percentage' => $appealsCount > 0 ? round(($item->count / $appealsCount) * 100) : 0
                    ];
                });

            // Payer Performance (Mocked data since we don't have enough distribution yet)
            $payerPerformance = [
                ['payer' => 'Medicare', 'rate' => 94.2, 'color' => 'bg-chart-2'],
                ['payer' => 'Blue Cross', 'rate' => 89.7, 'color' => 'bg-primary'],
                ['payer' => 'Aetna', 'rate' => 87.3, 'color' => 'bg-accent'],
                ['payer' => 'Cigna', 'rate' => 82.1, 'color' => 'bg-chart-3'],
            ];

            return response()->json([
                'status' => true,
                'message' => 'Billing operations data retrieved',
                'data' => [
                    'stats' => [
                        'work_queue' => $workQueueCount,
                        'appeals_generated' => $appealsCount,
                        'claims_processed' => $claimsProcessedCount,
                        'success_rate' => number_format($successRate, 1) . '%',
                    ],
                    'work_queue_preview' => $workQueuePreview->values(),
                    'recent_appeals' => $recentAppeals,
                    'denial_reasons' => $denialReasons,
                    'payer_performance' => $payerPerformance
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve billing data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
