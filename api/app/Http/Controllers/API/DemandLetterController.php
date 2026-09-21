<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DemandLetter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Traits\LogsTimeline;

class DemandLetterController extends Controller
{
    use LogsTimeline;
    /**
     * List demand letters with filters.
     */
    public function index(Request $request)
    {
        $query = DemandLetter::with(['case:id,title,case_number,status', 'creator:id,first_name,last_name'])
            ->where('organization_id', $request->user()->organization_id);

        if ($request->case_id) {
            $query->where('case_id', $request->case_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('recipient_name', 'like', "%{$search}%")
                  ->orWhere('recipient_company', 'like', "%{$search}%")
                  ->orWhereHas('case', function ($cq) use ($search) {
                      $cq->where('title', 'like', "%{$search}%")
                         ->orWhere('case_number', 'like', "%{$search}%");
                  });
            });
        }

        $demandLetters = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => true,
            'message' => 'Demand letters retrieved successfully',
            'data' => $demandLetters
        ]);
    }

    /**
     * Store a new demand letter.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'case_id' => 'required|exists:cases,id',
            'recipient_name' => 'required|string|max:255',
            'recipient_company' => 'nullable|string|max:255',
            'recipient_address' => 'nullable|string|max:500',
            'demand_amount' => 'required|numeric|min:0',
            'content' => 'nullable|string',
            'status' => 'nullable|in:draft,sent,accepted,rejected,countered',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $demandLetter = DemandLetter::create([
            'organization_id' => $request->user()->organization_id,
            'created_by' => $request->user()->id,
            ...$request->only([
                'case_id', 'recipient_name', 'recipient_company',
                'recipient_address', 'demand_amount', 'content', 'status', 'notes'
            ])
        ]);

        $demandLetter->load(['case:id,title,case_number', 'creator:id,first_name,last_name']);

        // Log to timeline
        $this->logTimeline(
            $demandLetter->case_id,
            'legal',
            'Demand Letter Generated',
            "A formal demand for ${$demandLetter->demand_amount} has been generated for {$demandLetter->recipient_name}.",
            ['amount' => $demandLetter->demand_amount, 'recipient' => $demandLetter->recipient_name]
        );

        return response()->json([
            'status' => true,
            'message' => 'Demand letter created successfully',
            'data' => $demandLetter
        ], 201);
    }

    /**
     * Display the specified demand letter.
     */
    public function show($id)
    {
        $demandLetter = DemandLetter::with(['case', 'creator:id,first_name,last_name'])
            ->where('organization_id', request()->user()->organization_id)
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Demand letter retrieved successfully',
            'data' => $demandLetter
        ]);
    }

    /**
     * Update the specified demand letter.
     */
    public function update(Request $request, $id)
    {
        $demandLetter = DemandLetter::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'recipient_name' => 'sometimes|required|string|max:255',
            'recipient_company' => 'nullable|string|max:255',
            'recipient_address' => 'nullable|string|max:500',
            'demand_amount' => 'sometimes|required|numeric|min:0',
            'content' => 'nullable|string',
            'status' => 'nullable|in:draft,sent,accepted,rejected,countered',
            'sent_at' => 'nullable|date',
            'response_date' => 'nullable|date',
            'response_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Auto-set sent_at when status changes to sent
        $data = $request->only([
            'recipient_name', 'recipient_company', 'recipient_address',
            'demand_amount', 'content', 'status', 'sent_at',
            'response_date', 'response_amount', 'notes'
        ]);

        if (isset($data['status']) && $data['status'] === 'sent' && !$demandLetter->sent_at) {
            $data['sent_at'] = now();
        }

        $demandLetter->update($data);
        $demandLetter->load(['case:id,title,case_number', 'creator:id,first_name,last_name']);

        return response()->json([
            'status' => true,
            'message' => 'Demand letter updated successfully',
            'data' => $demandLetter
        ]);
    }

    /**
     * Remove the specified demand letter (only drafts can be deleted).
     */
    public function destroy($id)
    {
        $demandLetter = DemandLetter::where('organization_id', request()->user()->organization_id)
            ->findOrFail($id);

        if ($demandLetter->status !== 'draft') {
            return response()->json([
                'status' => false,
                'message' => 'Only draft demand letters can be deleted'
            ], 403);
        }

        $demandLetter->delete();

        return response()->json([
            'status' => true,
            'message' => 'Demand letter deleted successfully'
        ]);
    }
}
