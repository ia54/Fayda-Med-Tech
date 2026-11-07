<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ValidationIssue;
use Illuminate\Http\Request;

class ValidationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->organization_id, 403, 'Organization context required.');
        $scope = ValidationIssue::where('organization_id', $request->user()->organization_id)
            ->whereHas('invoice', fn ($invoice) => $invoice
                ->where('organization_id', $request->user()->organization_id)
                ->whereHas('case', fn ($case) => $case->where('organization_id', $request->user()->organization_id)));
        $issues = (clone $scope)->with(['invoice.case'])
            ->when($request->status, function($query, $status) {
                return $query->where('status', $status);
            })
            ->latest()
            ->paginate($request->get('per_page', 15));

        $stats = [
            'total' => (clone $scope)->count(),
            'high_priority' => (clone $scope)->whereIn('severity', ['high', 'critical'])->where('status', 'pending')->count(),
            'in_review' => (clone $scope)->where('status', 'in_review')->count(),
            'resolved_today' => (clone $scope)->where('status', 'resolved')->whereDate('updated_at', today())->count(),
        ];

        return response()->json([
            'status' => true,
            'message' => 'Validation issues retrieved successfully',
            'data' => [
                'issues' => $issues,
                'stats' => $stats
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        abort_unless($request->user()->organization_id, 403, 'Organization context required.');
        $issue = ValidationIssue::where('organization_id', $request->user()->organization_id)
            ->whereHas('invoice', fn ($invoice) => $invoice
                ->where('organization_id', $request->user()->organization_id)
                ->whereHas('case', fn ($case) => $case->where('organization_id', $request->user()->organization_id)))
            ->findOrFail($id);
        $issue->update($request->only(['status', 'description', 'severity']));

        return response()->json([
            'status' => true,
            'message' => 'Validation issue updated successfully',
            'data' => $issue
        ]);
    }
}
