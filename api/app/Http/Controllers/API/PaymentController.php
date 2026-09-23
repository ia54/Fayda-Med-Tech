<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * Display a listing of the payments.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        abort_if($user->role !== 'admin' && !$user->organization_id, 403);
        $request->validate(['search' => 'nullable|string|max:200', 'per_page' => 'nullable|integer|min:1|max:100', 'page' => 'nullable|integer|min:1']);
        $query = Payment::with(['invoice.case', 'reversal']);

        // Client scoping: only see payments for invoices on their cases
        if ($user->role === 'client') {
            $query->whereHas('invoice.case.parties', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        if ($request->has('invoice_id')) {
            $query->where('invoice_id', $request->invoice_id);
        }

        if ($request->filled('search')) {
            $term = '%' . $request->string('search')->trim() . '%';
            $query->where(fn ($q) => $q->where('transaction_id', 'like', $term)->orWhereHas('invoice', fn ($i) => $i->where('invoice_number', 'like', $term)));
        }
        $payments = $query->latest()->orderByDesc('id')->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => true,
            'message' => 'Payments retrieved successfully',
            'data' => $payments
        ]);
    }

    /**
     * Store a newly created payment in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'firm_admin', 'medical_biller'], true), 403);
        abort_if($user->role !== 'admin' && !$user->organization_id, 403);
        $data = $request->validate([
            'invoice_id' => 'required|integer',
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99', 'decimal:0,2'],
            'payment_method' => 'required|in:check,wire,credit_card,cash',
            'transaction_id' => 'required|string|max:255',
            'payment_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'notes' => 'nullable|string|max:5000',
        ]);
        return DB::transaction(function () use ($data, $user) {
            $invoice = Invoice::lockForUpdate()->findOrFail($data['invoice_id']);
            abort_unless($invoice->organization_id, 422, 'The invoice needs an organization.');
            abort_if($invoice->payments()->where('transaction_id', $data['transaction_id'])->exists(), 409, 'This reference has already been recorded for this invoice.');
            abort_unless(in_array($invoice->status, ['sent', 'denied'], true), 409, 'Payments can only be recorded against open invoices.');
            $paidCents = (int) round((float) $invoice->payments()->sum('amount') * 100);
            $invoiceCents = (int) round((float) $invoice->amount * 100);
            $incomingCents = (int) round((float) $data['amount'] * 100);
            abort_if($incomingCents > $invoiceCents - $paidCents, 422, 'Payment exceeds the outstanding balance.');
            $payment = Payment::create(array_merge($data, ['organization_id' => $invoice->organization_id, 'recorded_by' => $user->id]));
            if ($paidCents + $incomingCents === $invoiceCents) {
                $invoice->update(['status' => 'paid', 'paid_at' => $data['payment_date']]);
            }
            return response()->json(['status' => true, 'message' => 'Previously received payment recorded. No funds were moved.', 'data' => $payment], 201);
        });
    }

    /**
     * Display the specified payment.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        abort_if($user->role !== 'admin' && !$user->organization_id, 403);
        $query = Payment::with(['invoice.case', 'reversal']);
        if ($user->role === 'client') {
            $query->whereHas('invoice.case.parties', fn ($q) => $q->where('user_id', $user->id));
        }
        $payment = $query->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Payment details retrieved successfully',
            'data' => $payment
        ]);
    }

    /** Append a correcting entry; retain the original receipt and its reference. */
    public function reverse(Request $request, $id)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'firm_admin', 'medical_biller'], true), 403);
        abort_if($user->role !== 'admin' && !$user->organization_id, 403);
        $data = $request->validate(['reason' => 'required|string|max:5000']);
        $original = Payment::findOrFail($id);
        return DB::transaction(function () use ($original, $user, $data) {
            // Same lock order as recording a receipt: serialize changes on the invoice.
            $invoice = Invoice::lockForUpdate()->findOrFail($original->invoice_id);
            $payment = Payment::lockForUpdate()->findOrFail($original->id);
            abort_if($payment->reversal_of_id || (float) $payment->amount <= 0, 409, 'A correction entry cannot be reversed. Record a new receipt if needed.');
            abort_if($payment->reversal()->exists(), 409, 'This receipt has already been reversed.');
            $reversal = Payment::create([
                'organization_id' => $invoice->organization_id,
                'invoice_id' => $invoice->id,
                'amount' => '-' . $payment->amount,
                'payment_method' => $payment->payment_method,
                'transaction_id' => 'REV-' . \Illuminate\Support\Str::uuid(),
                'payment_date' => now()->toDateString(),
                'notes' => $data['reason'],
                'reversal_of_id' => $payment->id,
                'recorded_by' => $user->id,
            ]);
            if ($invoice->status === 'paid') {
                $invoice->update(['status' => 'sent', 'paid_at' => null]);
            }
            return response()->json(['status' => true, 'message' => 'Receipt reversed in the ledger. No refund or transfer was issued.', 'data' => $reversal], 201);
        });
    }

    /**
     * Remove the specified payment from storage.
     */
    public function destroy($id)
    {
        abort(409, 'Recorded payments cannot be deleted. A payment correction requires a reversal workflow.');
    }
}
