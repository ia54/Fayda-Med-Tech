<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\CaseModel;
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
        abort_if($user->role !== 'admin' && !$user->organization_id, 403);
        $request->validate(['search' => 'nullable|string|max:200', 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:100']);
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

        if ($request->filled('search')) {
            $term = '%' . $request->string('search')->trim() . '%';
            $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'like', $term)
                    ->orWhere('metadata->patient_name', 'like', $term)
                    ->orWhereHas('case', function ($cases) use ($term) {
                        $cases->where('title', 'like', $term)->orWhere('case_number', 'like', $term);
                    });
            });
        }
        $invoices = $query->latest()->orderByDesc('id')->paginate($request->get('per_page', 15));

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
        $user = $request->user();
        abort_if($user->role !== 'admin' && !$user->organization_id, 403);
        $caseQuery = CaseModel::query();
        if ($user->role !== 'admin') {
            $caseQuery->where('organization_id', $user->organization_id);
        }
        $case = is_scalar($request->input('case_id')) ? $caseQuery->find($request->input('case_id')) : null;
        $validator = Validator::make($request->all(), [
            'case_id' => ['required', 'integer', function ($attribute, $value, $fail) use ($case) {
                if (!$case || !$case->organization_id) {
                    $fail('Select an available case in your organization.');
                }
            }],
            'amount' => 'required|numeric|min:0',
            'due_date' => 'nullable|date',
            'status' => $user->role === 'provider_staff' ? 'nullable|in:draft,sent' : 'nullable|in:draft,sent,paid,denied,voided',
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
            'organization_id' => $case->organization_id,
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
        abort_if($user->role !== 'admin' && !$user->organization_id, 403);
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

    /** Providers can edit their organization's drafts, or send them for internal review. */
    public function updateProviderDraft(Request $request, $id)
    {
        abort_unless($request->user()->organization_id, 403);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'status' => 'required|in:draft,sent',
            'metadata' => 'required|array:patient_name,service_date,payer,cpt_codes,diagnosis_codes,notes',
            'metadata.patient_name' => 'required|string|max:255',
            'metadata.service_date' => 'required|date_format:Y-m-d',
            'metadata.payer' => 'nullable|string|max:255',
            'metadata.cpt_codes' => 'nullable|string|max:1000',
            'metadata.diagnosis_codes' => 'nullable|string|max:1000',
            'metadata.notes' => 'nullable|string|max:10000',
            'organization_id' => 'prohibited',
            'case_id' => 'prohibited',
            'paid_at' => 'prohibited',
        ]);
        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $id, $data) {
            $invoice = Invoice::where('organization_id', $request->user()->organization_id)->lockForUpdate()->findOrFail($id);
            abort_unless($invoice->status === 'draft', 409, 'Only draft records can be changed by a provider.');
            $invoice->update([
                'amount' => $data['amount'],
                'status' => $data['status'],
                'metadata' => array_merge($invoice->metadata ?? [], $data['metadata']),
            ]);
            return response()->json(['status' => true, 'message' => 'Billing record saved for internal use. No insurer submission was made.', 'data' => $invoice]);
        });
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
