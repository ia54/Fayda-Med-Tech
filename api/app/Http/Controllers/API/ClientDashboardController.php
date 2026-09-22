<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ClientDashboardController extends Controller
{
    /**
     * Get client-specific dashboard data (personal cases, documents, etc.)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            // 1. Get cases where the user is a party (Client/Plaintiff)
            $cases = CaseModel::whereHas('parties', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })->get();

            // 2. Stats
            $activeCase = $cases->whereIn('status', ['active', 'open', 'Active', 'pending_settlement'])->first();
            $pendingTasksCount = DB::table('case_tasks')
                ->whereIn('case_id', $cases->pluck('id'))
                ->where('assigned_to', $user->id)
                ->where('status', 'pending')
                ->count();

            // 3. Pending Signatures count
            $pendingSignaturesCount = Document::visibleTo($user)->whereHas('signers', function($q) use ($user) {
                $q->where(function($sq) use ($user) {
                    $sq->where('user_id', $user->id)
                       ;
                })->where('status', 'pending');
            })->count();

            // 4. Recent Documents (Related to their cases)
            $caseIds = $cases->pluck('id')->toArray();
            $documents = Document::visibleTo($user)->where(function($q) use ($user, $caseIds) {
                $q->whereHas('signers', function($sq) use ($user) {
                    $sq->where('user_id', $user->id)
                       ;
                })->orWhereIn('metadata->case_id', $caseIds);
            })
                ->latest()
                ->limit(5)
                ->get()
                ->map(function($doc) {
                    return [
                        'id' => $doc->id,
                        'name' => $doc->original_name,
                        'status' => $doc->document_status,
                        'date' => $doc->created_at->format('M d, Y'),
                        'action_required' => $doc->document_status === 'pending_signature' 
                            || $doc->signature_status === 'pending'
                    ];
                });

            // 5. Billing Summary (Related to their cases)
            $totalBilled = Invoice::whereIn('case_id', $caseIds)->sum('amount');
            $paidAmount = Invoice::whereIn('case_id', $caseIds)
                ->where('status', 'paid')
                ->sum('amount');
            
            $recentInvoices = Invoice::whereIn('case_id', $caseIds)
                ->with('case')
                ->latest()
                ->limit(5)
                ->get()
                ->map(function($inv) {
                    return [
                        'id' => $inv->id,
                        'invoice_number' => $inv->invoice_number,
                        'amount' => '$' . number_format($inv->amount, 2),
                        'status' => $inv->status,
                        'due_date' => $inv->due_date ? $inv->due_date->format('M d, Y') : 'N/A',
                        'case_title' => optional($inv->case)->title ?? 'N/A'
                    ];
                });

            return response()->json([
                'status' => true,
                'message' => 'Client dashboard data retrieved successfully',
                'data' => [
                    'user' => [
                        'name' => $user->full_name,
                        'email' => $user->email,
                    ],
                    'case_summary' => $activeCase ? [
                        'id' => $activeCase->id,
                        'title' => $activeCase->title,
                        'number' => $activeCase->case_number,
                        'status' => ucfirst($activeCase->status),
                        'last_update' => $activeCase->updated_at->diffForHumans(),
                    ] : null,
                    'stats' => [
                        'pending_tasks' => $pendingTasksCount,
                        'pending_signatures' => $pendingSignaturesCount,
                        'total_documents' => $documents->count(),
                        'billing_summary' => [
                            'total' => '$' . number_format($totalBilled, 2),
                            'paid' => '$' . number_format($paidAmount, 2),
                        ]
                    ],
                    'recent_documents' => $documents,
                    'recent_invoices' => $recentInvoices,
                    'all_cases' => $cases->map(function($c) {
                        return [
                            'id' => $c->id,
                            'title' => $c->title,
                            'case_number' => $c->case_number,
                            'status' => ucfirst($c->status),
                            'accident_date' => $c->accident_date ? $c->accident_date->format('M d, Y') : null,
                            'value' => '$' . number_format($c->total_case_value ?? 0, 2)
                        ];
                    })
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve client data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get documents pending the client's signature.
     */
    public function pendingSignatures(Request $request)
    {
        try {
            $user = $request->user();

            $documents = Document::visibleTo($user)->with(['signers'])
                ->whereHas('signers', function($q) use ($user) {
                    $q->where(function($sq) use ($user) {
                        $sq->where('user_id', $user->id)
                           ;
                    })->whereIn('status', ['pending', 'sent']);
                })
                ->latest()
                ->paginate(15);

            return response()->json([
                'status' => true,
                'message' => 'Pending signatures retrieved successfully',
                'data' => $documents
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve pending signatures',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the client's own profile data.
     */
    public function profile(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'status' => true,
            'message' => 'Profile retrieved successfully',
            'data' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'organization' => $user->organization?->name ?? null,
                'created_at' => $user->created_at,
            ]
        ]);
    }

    /**
     * Update the client's own profile.
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $user->update($request->only(['first_name', 'last_name', 'phone']));

        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully',
            'data' => $user->fresh()
        ]);
    }
}
