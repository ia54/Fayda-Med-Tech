<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\{Invoice, ClaimAppeal, Payment};
use Illuminate\Http\Request;

class BillingAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'firm_admin', 'medical_biller', 'provider_staff'], true), 403);
        // Organization analytics always require context, including platform administrators.
        abort_unless($user->organization_id, 403);
        $org = $user->organization_id;
        $invoices = Invoice::where('organization_id', $org)
            ->whereHas('case', fn ($q) => $q->where('organization_id', $org));
        $appeals = ClaimAppeal::where('organization_id', $org)
            ->whereHas('invoice', fn ($q) => $q->where('organization_id', $org)
                ->whereHas('case', fn ($c) => $c->where('organization_id', $org)));
        $invoiceCount = (clone $invoices)->count();
        $appealCount = (clone $appeals)->count();
        $start = now()->startOfMonth();
        $end = $start->copy()->addMonth();
        $receiptCents = Payment::where('organization_id', $org)
            ->whereHas('invoice', fn ($q) => $q->where('organization_id', $org)
                ->whereHas('case', fn ($c) => $c->where('organization_id', $org)))
            ->where('payment_date', '>=', $start->toDateString())
            ->where('payment_date', '<', $end->toDateString())
            ->get(['amount'])->sum(fn ($p) => (int) round((float) $p->amount * 100));
        $reasons = (clone $appeals)->selectRaw('reason_category as reason, count(*) as count')
            ->groupBy('reason_category')->orderByDesc('count')->orderBy('reason_category')->get()
            ->map(fn ($row) => ['reason' => $row->reason, 'count' => (int) $row->count,
                'percentage' => $appealCount ? round($row->count / $appealCount * 100, 1) : null]);

        return response()->json(['status' => true, 'message' => 'Recorded billing analytics', 'data' => [
            'metrics' => [
                'denial_rate' => $invoiceCount ? round((clone $invoices)->where('status', 'denied')->count() / $invoiceCount * 100, 1) : null,
                'avg_process_time' => null,
                'appeal_success_rate' => $appealCount ? round((clone $appeals)->where('status', 'accepted')->count() / $appealCount * 100, 1) : null,
                'revenue_recovery' => $receiptCents / 100,
            ],
            'counts' => ['invoices' => $invoiceCount, 'appeals' => $appealCount],
            'denial_reasons' => $reasons, 'payer_trends' => [],
            'basis' => [
                'scope' => 'Current organization only; records with inconsistent case/invoice ownership are excluded.',
                'rates' => 'Current recorded invoice and appeal statuses across all dates; not verified insurer outcomes.',
                'receipts' => 'Net signed receipts by payment date in the current calendar month, including reversals. No funds moved.',
                'period_start' => $start->toDateString(), 'period_end_exclusive' => $end->toDateString(),
                'timezone' => config('app.timezone'),
                'unavailable' => 'Processing duration and payer performance are not available from verified recorded data.',
            ],
        ]]);
    }
}
