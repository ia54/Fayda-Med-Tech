<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Lien;
use App\Models\Provider;
use App\Models\Document;
use App\Models\Signature;
use App\Models\OcrResult;
use App\Models\AuditLog;
use App\Models\CaseSettlement;
use App\Models\User;
use App\Models\InsuranceClaim;
use App\Models\ReportGeneration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportsController extends Controller
{
    /**
     * 1. Case Status Report - All cases by status, attorney, and date range
     */
    public function caseStatus(Request $request)
    {
        $query = $this->casesForReport($request)->with(['creator', 'parties']);

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('attorney_id')) {
            $query->whereHas('parties', fn($q) => $q->where('user_id', $request->attorney_id)->where('role_in_case', 'attorney'));
        }
        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to')) $query->whereDate('created_at', '<=', $request->date_to);

        $cases = $query->get();
        $summary = [
            'total' => $cases->count(),
            'by_status' => $cases->groupBy('status')->map->count(),
            'new_this_month' => $cases->where('created_at', '>=', now()->startOfMonth())->count(),
        ];

        $report = $this->storeReport($request, 'case_status', [
            'total_cases' => $summary['total'],
            'status_breakdown' => $summary['by_status'],
            'cases' => $cases,
        ]);

        return response()->json(['status' => true, 'data' => $report]);
    }

    /**
     * 2. Revenue by Period - Invoiced, collected, outstanding by month/quarter
     */
    public function revenueByPeriod(Request $request)
    {
        $invoiceQuery = $this->forOrganization(Invoice::class, $request);
        $data = $request->validate([
            'period' => 'sometimes|in:monthly,quarterly,yearly',
            'date_from' => 'sometimes|date_format:Y-m-d',
            'date_to' => 'sometimes|date_format:Y-m-d',
        ]);
        $dateFrom = $data['date_from'] ?? now()->subYear()->toDateString();
        $dateTo = $data['date_to'] ?? now()->toDateString();
        if ($dateFrom > $dateTo) {
            throw \Illuminate\Validation\ValidationException::withMessages(['date_to' => 'The end date must be on or after the start date.']);
        }
        $invoices = $invoiceQuery->whereDate('created_at', '>=', $dateFrom)->whereDate('created_at', '<=', $dateTo)->get();
        $periodPayments = $this->forOrganization(Payment::class, $request)
            ->whereDate('payment_date', '>=', $dateFrom)->whereDate('payment_date', '<=', $dateTo)->get();
        $cohortPayments = $this->forOrganization(Payment::class, $request)
            ->whereIn('invoice_id', $invoices->pluck('id'))->whereDate('payment_date', '<=', $dateTo)->get();
        $sumCents = fn ($records) => $records->sum(fn ($record) => (int) round((float) $record->amount * 100));
        $invoiced = $sumCents($invoices);
        $cohortCollected = $sumCents($cohortPayments);

        $report = $this->storeReport($request, 'revenue', [
            'basis' => 'Range totals, not grouped periods. Invoices use creation dates; receipts use recorded payment dates and include reversals. Outstanding is the balance of invoices created in this range after receipts dated through the end date. Includes all invoice statuses; this is not recognized accounting revenue.',
            'period' => $data['period'] ?? 'monthly',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'total_invoiced' => $invoiced / 100,
            'total_collected' => $sumCents($periodPayments) / 100,
            'collected_against_period_invoices' => $cohortCollected / 100,
            'total_outstanding' => ($invoiced - $cohortCollected) / 100,
            'invoice_count' => $invoices->count(),
            'payment_count' => $periodPayments->count(),
        ]);

        return response()->json(['status' => true, 'data' => $report]);
    }

    /**
     * 3. Insurance Aging - Outstanding balances by insurer and invoice age
     */
    public function insuranceAging(Request $request)
    {
        $invoices = $this->forOrganization(Invoice::class, $request)
            ->with(['payments' => fn ($query) => $query->where('organization_id', $request->user()->organization_id)])
            ->where('status', 'sent')->get();
        $aging = [];

        foreach ($invoices as $invoice) {
            // Signed receipt entries include reversals. Accumulate integer cents.
            $received = $invoice->payments->sum(fn ($payment) => (int) round((float) $payment->amount * 100));
            $balance = (int) round((float) $invoice->amount * 100) - $received;
            if ($balance <= 0) continue;
            $days = $invoice->created_at->copy()->startOfDay()->diffInDays(now()->startOfDay());
            $bucket = $days <= 30 ? '0-30' : ($days <= 60 ? '31-60' : ($days <= 90 ? '61-90' : '90+'));
            $insurer = $invoice->metadata['payer'] ?? 'Unknown';

            if (!isset($aging[$insurer])) {
                $aging[$insurer] = ['0-30' => 0, '31-60' => 0, '61-90' => 0, '90+' => 0, 'total' => 0];
            }
            $aging[$insurer][$bucket] += $balance;
            $aging[$insurer]['total'] += $balance;
        }

        foreach ($aging as &$buckets) {
            foreach ($buckets as &$cents) $cents = $cents / 100;
            unset($cents);
        }
        unset($buckets);
        $report = $this->storeReport($request, 'insurance_aging', [
            'basis' => 'Sent invoices with a positive balance after recorded receipts and reversals; calendar days since invoice creation.',
            'aging' => $aging,
        ]);
        return response()->json(['status' => true, 'data' => $report]);
    }

    /**
     * 4. Settlement Summary - Settled cases, gross settlement, net to client
     */
    public function settlementSummary(Request $request)
    {
        $this->captureCaseScope($request);
        abort_unless($request->user()->organization_id && in_array($request->user()->role, ['admin', 'firm_admin', 'attorney'], true), 403);
        $query = CaseSettlement::where('organization_id', $request->user()->organization_id)->current()->where('status', 'completed');
        if ($request->user()->role === 'attorney') $query->whereHas('case', fn ($q) => $q->assignedToAttorney($request->user()->id));
        $settlements = $query->get();
        $unknown = $settlements->whereNull('other_deductions')->count();
        $sum = fn ($field) => $settlements->sum(fn ($s) => CaseSettlement::cents($s->$field ?? 0)) / 100;
        $report = $this->storeReport($request, 'settlement', [
            'basis' => 'Current completed records; excludes pending and replaced records. Not cash receipts.',
            'total_settlements' => $settlements->count(),
            'total_gross_settlement' => $sum('settlement_amount'),
            'total_attorney_fees' => $sum('attorney_fees'),
            'total_costs' => $sum('costs'),
            'total_other_deductions' => $unknown ? null : $sum('other_deductions'),
            'unknown_allocation_count' => $unknown,
            'total_net_to_client' => $unknown ? null : $settlements->sum(fn ($s) => CaseSettlement::cents($s->net_to_client)) / 100,
        ]);
        return response()->json(['status' => true, 'data' => $report]);
    }

    /**
     * 5. Attorney Production - Cases closed and fees generated per attorney
     */
    public function attorneyProduction(Request $request)
    {
        $this->captureCaseScope($request);
        abort_unless($request->user()->organization_id && in_array($request->user()->role, ['admin', 'firm_admin', 'attorney'], true), 403);
        $attorneyQuery = User::where('organization_id', $request->user()->organization_id)->where('role', 'attorney');
        if ($request->user()->role === 'attorney') $attorneyQuery->whereKey($request->user()->id);
        $attorneys = $attorneyQuery->get();
        $production = [];

        foreach ($attorneys as $attorney) {
            $caseQuery = CaseModel::where('organization_id', $request->user()->organization_id)->assignedToAttorney($attorney->id);

            $totalCases = $caseQuery->count();
            $closedCases = (clone $caseQuery)->where('status', 'Closed')->count();

            $feesGenerated = Schema::hasColumn('case_settlements', 'attorney_fees')
                ? CaseSettlement::where('organization_id', $request->user()->organization_id)->current()->where('status', 'completed')->whereHas('case', fn($q) =>
                    $q->assignedToAttorney($attorney->id)
                  )->sum('attorney_fees')
                : 0;

            $production[] = [
                'attorney_id' => $attorney->id,
                'attorney_name' => $attorney->full_name,
                'total_cases' => $totalCases,
                'closed_cases' => $closedCases,
                'fees_generated' => $feesGenerated,
            ];
        }

        $report = $this->storeReport($request, 'attorney_production', ['production' => $production]);
        return response()->json(['status' => true, 'data' => $report]);
    }

    /**
     * 6. Provider Billing - Bills submitted per provider vs. payments received
     */
    public function providerBilling(Request $request)
    {
        $providers = $this->forOrganization(Provider::class, $request)->get();
        $billing = [];

        foreach ($providers as $provider) {
            $invoices = $this->forOrganization(Invoice::class, $request)->where('metadata->provider_id', $provider->id)->get();
            $payments = $this->forOrganization(Payment::class, $request)->whereIn('invoice_id', $invoices->pluck('id'))->get();
            $billed = round((float) $invoices->sum('amount'), 2);
            $received = round((float) $payments->sum('amount'), 2);

            $billing[] = [
                'provider_id' => $provider->id,
                'provider_name' => $provider->name,
                'total_billed' => $billed,
                'total_collected' => $received,
                'outstanding' => round($billed - $received, 2),
                'invoice_count' => $invoices->count(),
            ];
        }

        $report = $this->storeReport($request, 'provider_billing', ['billing' => $billing]);
        return response()->json(['status' => true, 'data' => $report]);
    }

    /**
     * 7. Lien Summary - Total liens vs. negotiated reductions per case
     */
    public function lienSummary(Request $request)
    {
        $liens = $this->forOrganization(Lien::class, $request)
            ->whereIn('case_id', $this->casesForReport($request)->select('id'));
        $totalLiensCount = (clone $liens)->count();
        $totalLiensAmount = (clone $liens)->sum('amount');
        $totalReductions = (clone $liens)->sum('reduction_amount');
        $totalOutstanding = $totalLiensAmount - $totalReductions;

        $report = $this->storeReport($request, 'lien_summary', [
            'total_liens' => $totalLiensCount,
            'total_lien_amount' => $totalLiensAmount,
            'total_negotiated_reductions' => $totalReductions,
            'total_outstanding' => $totalOutstanding,
        ]);
        return response()->json(['status' => true, 'data' => $report]);
    }

    /**
     * 8. OCR Processing Log - Documents processed, extraction accuracy, errors
     */
    public function ocrProcessingLog(Request $request)
    {
        $ocrResults = $this->forOrganization(OcrResult::class, $request)->with(['document'])->get();
        $total = $ocrResults->count();
        $successful = $ocrResults->where('status', 'completed')->count();
        $failed = $ocrResults->where('status', 'failed')->count();

        $report = $this->storeReport($request, 'ocr_log', [
            'total_processed' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'accuracy_rate' => $total > 0 ? round(($successful / $total) * 100, 1) . '%' : '0%',
            'results' => $ocrResults->take(50),
        ]);
        return response()->json(['status' => true, 'data' => $report]);
    }

    /**
     * 9. Signature Activity - Sent, completed, pending, expired signatures
     */
    public function signatureActivity(Request $request)
    {
        $signatures = $this->forOrganization(Signature::class, $request)->with(['document'])
            ->where(fn ($query) => $query->whereNull('provider_event')->orWhere('provider_event', '!=', 'documents_archived'))
            ->get();
        $report = $this->storeReport($request, 'signature_activity', [
            'total' => $signatures->count(),
            'completed' => $signatures->where('status', 'completed')->count(),
            'pending' => $signatures->where('status', 'pending')->count(),
            'expired' => $signatures->where('status', 'expired')->count(),
            'by_provider' => $signatures->groupBy('provider')->map->count(),
        ]);
        return response()->json(['status' => true, 'data' => $report]);
    }

    /**
     * 10. User Activity Log - Login history, actions, IP addresses per user
     */
    public function userActivityLog(Request $request)
    {
        $logs = $this->forOrganization(AuditLog::class, $request)->with(['user'])
            ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->user_id))
            ->latest()
            ->paginate($request->get('per_page', 50));

        return response()->json(['status' => true, 'data' => $logs]);
    }

    /**
     * 11. Document Audit Trail - Who viewed/edited/uploaded each document
     */
    public function documentAuditTrail(Request $request)
    {
        $logs = $this->forOrganization(AuditLog::class, $request)->where('auditable_type', 'App\Models\Document')
            ->with(['user'])
            ->when($request->filled('document_id'), fn($q) => $q->where('auditable_id', $request->document_id))
            ->latest()
            ->paginate($request->get('per_page', 50));

        return response()->json(['status' => true, 'data' => $logs]);
    }

    /**
     * 12. HIPAA Compliance Log - Access to protected health information
     */
    public function hipaaComplianceLog(Request $request)
    {
        $logs = $this->forOrganization(AuditLog::class, $request)->where('event', 'PHI_ACCESS')
            ->with(['user'])
            ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->user_id))
            ->latest()
            ->paginate($request->get('per_page', 50));

        return response()->json(['status' => true, 'data' => $logs]);
    }

    /**
     * 13. Collection Rate Report - Billed vs. collected by insurance and period
     */
    public function collectionRateReport(Request $request)
    {
        $totalBilled = $this->forOrganization(Invoice::class, $request)->sum('amount');
        $totalCollected = $this->forOrganization(Payment::class, $request)->sum('amount');
        $collectionRate = $totalBilled > 0 ? round(($totalCollected / $totalBilled) * 100, 1) : 0;

        $report = $this->storeReport($request, 'collection_rate', [
            'total_billed' => $totalBilled,
            'total_collected' => $totalCollected,
            'collection_rate' => $collectionRate . '%',
            'outstanding' => $totalBilled - $totalCollected,
            'invoice_count' => $this->forOrganization(Invoice::class, $request)->count(),
            'payment_count' => $this->forOrganization(Payment::class, $request)->count(),
        ]);
        return response()->json(['status' => true, 'data' => $report]);
    }

    /**
     * 14. Referral Source Report - Cases by referral source
     */
    public function referralSourceReport(Request $request)
    {
        $cases = $this->casesForReport($request)->get();
        $sources = [];

        foreach ($cases as $case) {
            $source = $case->metadata['referral_source'] ?? 'Direct';
            if (!isset($sources[$source])) $sources[$source] = 0;
            $sources[$source]++;
        }

        $report = $this->storeReport($request, 'referral_source', [
            'sources' => $sources,
            'total_cases' => $cases->count(),
        ]);
        return response()->json(['status' => true, 'data' => $report]);
    }

    /**
     * List all previously generated reports
     */
    public function reportHistory(Request $request)
    {
        $request->validate(['per_page' => 'sometimes|integer|min:1|max:100']);
        return response()->json([
            'status' => true,
            'data' => $this->visibleSavedReports($request)->with(['generator'])
                ->latest()
                ->paginate($request->get('per_page', 20)),
        ]);
    }

    /**
     * Show a specific generated report
     */
    public function showReport(Request $request, $id)
    {
        return response()->json([
            'status' => true,
            'data' => $this->visibleSavedReports($request)->with(['generator', 'definition'])->findOrFail($id),
        ]);
    }

    private function forOrganization(string $model, Request $request): \Illuminate\Database\Eloquent\Builder
    {
        abort_unless($request->user()->organization_id, 403);
        $this->captureCaseScope($request);
        $query = $model::query();
        return $query->where($query->getModel()->qualifyColumn('organization_id'), $request->user()->organization_id);
    }

    private function casesForReport(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = $this->forOrganization(CaseModel::class, $request);
        if ($request->user()->role === 'attorney') $query->assignedToAttorney($request->user()->id);
        return $query;
    }

    private function currentCaseScope(Request $request): string
    {
        $ids = CaseModel::where('organization_id', $request->user()->organization_id)
            ->assignedToAttorney($request->user()->id)->orderBy('id')->pluck('id');
        return hash('sha256', $ids->implode(','));
    }

    private function captureCaseScope(Request $request): void
    {
        if ($request->user()->role === 'attorney' && !$request->attributes->has('report_case_scope')) {
            // Capture before reading report data, not after assignments may change.
            $request->attributes->set('report_case_scope', $this->currentCaseScope($request));
        }
    }

    /** Saved snapshots must not bypass the scope used to generate them. */
    private function visibleSavedReports(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $user = $request->user();
        abort_unless($user->organization_id, 403);
        $query = ReportGeneration::query()->where('organization_id', $user->organization_id);
        if ($user->role === 'firm_admin') {
            // Platform-admin and legacy snapshots may contain a wider scope.
            $query->whereIn('parameters->_generated_role', ['firm_admin', 'attorney', 'medical_biller', 'provider_staff']);
        }
        if ($user->role === 'attorney') {
            $query->where('parameters->_case_scope', $this->currentCaseScope($request));
        }
        if (!in_array($user->role, ['admin', 'firm_admin'], true)) {
            $types = ['case_status', 'revenue', 'insurance_aging', 'provider_billing', 'lien_summary', 'ocr_log', 'signature_activity', 'collection_rate', 'referral_source'];
            if ($user->role === 'attorney') $types = array_merge($types, ['settlement', 'attorney_production']);
            // Legacy snapshots lack trustworthy generation-role evidence. Staff
            // can regenerate them through the currently authorized endpoint.
            $query->where('generated_by', $user->id)
                ->where('parameters->_generated_role', $user->role)
                ->whereIn('report_type', $types);
        }
        return $query;
    }

    /**
     * Store a generated report record
     */
    private function storeReport(Request $request, string $type, array $summary): ReportGeneration
    {
        $this->captureCaseScope($request);
        $scope = $request->user()->role === 'attorney' ? ['_case_scope' => $request->attributes->get('report_case_scope')] : [];
        return ReportGeneration::create([
            'organization_id' => $request->user()->organization_id,
            'generated_by' => $request->user()->id,
            'report_name' => ucwords(str_replace('_', ' ', $type)) . ' Report',
            'report_type' => $type,
            'parameters' => array_merge($request->except(['per_page', '_generated_role', '_case_scope']), ['_generated_role' => $request->user()->role], $scope),
            'format' => $request->get('format', 'json'),
            'status' => 'completed',
            'completed_at' => now(),
            'result_summary' => $summary,
        ]);
    }
}
