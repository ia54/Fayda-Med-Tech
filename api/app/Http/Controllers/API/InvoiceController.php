<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the invoices.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Invoice::with(['case']);

        // Filter for Client Role
        if ($user->role === 'client') {
            $query->whereHas('case.parties', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('case_id')) {
            $query->where('case_id', $request->case_id);
        }

        $invoices = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => true,
            'message' => 'Invoices retrieved successfully',
            'data' => $invoices
        ]);
    }

    /**
     * Store a newly created invoice in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'case_id' => 'required|exists:cases,id',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'nullable|date',
            'status' => 'nullable|in:draft,sent,paid,denied,voided',
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $invoice = Invoice::create([
            'organization_id' => $request->user()->organization_id,
            'case_id' => $request->case_id,
            'invoice_number' => 'INV-' . strtoupper(bin2hex(random_bytes(4))),
            'amount' => $request->amount,
            'status' => $request->status ?? 'draft',
            'due_date' => $request->due_date,
            'notes' => $request->notes,
            'metadata' => $request->metadata,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Invoice created successfully',
            'data' => $invoice
        ], 201);
    }

    /**
     * Display the specified invoice.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $query = Invoice::with(['case', 'payments']);

        if ($user->role === 'client') {
            $query->whereHas('case.parties', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        $invoice = $query->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Invoice details retrieved successfully',
            'data' => $invoice
        ]);
    }

    /**
     * Update the specified invoice in storage.
     */
    public function update(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'amount' => 'sometimes|required|numeric|min:0',
            'status' => 'sometimes|required|in:draft,sent,paid,denied,voided',
            'due_date' => 'nullable|date',
            'paid_at' => 'nullable|date',
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $invoice->update($validator->validated());

        return response()->json([
            'status' => true,
            'message' => 'Invoice updated successfully',
            'data' => $invoice
        ]);
    }

    /**
     * Remove the specified invoice from storage.
     */
    public function destroy($id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->delete();

        return response()->json([
            'status' => true,
            'message' => 'Invoice deleted successfully'
        ]);
    }
}
