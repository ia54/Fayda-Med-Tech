<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * Display a listing of the payments.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Payment::with(['invoice.case']);

        // Client scoping: only see payments for invoices on their cases
        if ($user->role === 'client') {
            $query->whereHas('invoice.case.parties', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        if ($request->has('invoice_id')) {
            $query->where('invoice_id', $request->invoice_id);
        }

        $payments = $query->latest()->paginate($request->get('per_page', 15));

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
        $validator = Validator::make($request->all(), [
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'transaction_id' => 'nullable|string',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $invoice = Invoice::findOrFail($request->invoice_id);

        // Client validation: verify the client is a party on the invoice's case
        $user = $request->user();
        if ($user->role === 'client') {
            $isParty = $invoice->case && $invoice->case->parties()
                ->where('user_id', $user->id)
                ->exists();
            if (!$isParty) {
                return response()->json([
                    'status' => false,
                    'message' => 'You are not authorized to make payments on this invoice'
                ], 403);
            }
        }

        $payment = Payment::create([
            'organization_id' => $request->user()->organization_id,
            'invoice_id' => $request->invoice_id,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'transaction_id' => $request->transaction_id,
            'payment_date' => $request->payment_date,
            'notes' => $request->notes,
        ]);

        // Update invoice status if fully paid
        $totalPaid = $invoice->payments()->sum('amount');
        if ($totalPaid >= $invoice->amount) {
            $invoice->update([
                'status' => 'paid',
                'paid_at' => $request->payment_date
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Payment recorded successfully',
            'data' => $payment
        ], 201);
    }

    /**
     * Display the specified payment.
     */
    public function show($id)
    {
        $payment = Payment::with(['invoice.case'])->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Payment details retrieved successfully',
            'data' => $payment
        ]);
    }

    /**
     * Remove the specified payment from storage.
     */
    public function destroy($id)
    {
        $payment = Payment::findOrFail($id);
        $invoice = $payment->invoice;
        
        $payment->delete();

        // Re-evaluate invoice status
        $totalPaid = $invoice->payments()->sum('amount');
        if ($totalPaid < $invoice->amount) {
            $invoice->update([
                'status' => 'sent', // Or whatever appropriate status
                'paid_at' => null
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Payment deleted successfully'
        ]);
    }
}
