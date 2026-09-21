<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ValidationIssue;
use Illuminate\Http\Request;

class ValidationController extends Controller
{
    public function index(Request $request)
    {
        $issues = ValidationIssue::with(['invoice.case'])
            ->when($request->status, function($query, $status) {
                return $query->where('status', $status);
            })
            ->latest()
            ->paginate($request->get('per_page', 15));

        $stats = [
            'total' => ValidationIssue::count(),
            'high_priority' => ValidationIssue::whereIn('severity', ['high', 'critical'])->where('status', 'pending')->count(),
            'in_review' => ValidationIssue::where('status', 'in_review')->count(),
            'resolved_today' => ValidationIssue::where('status', 'resolved')->whereDate('updated_at', today())->count(),
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
        $issue = ValidationIssue::findOrFail($id);
        $issue->update($request->only(['status', 'description', 'severity']));

        return response()->json([
            'status' => true,
            'message' => 'Validation issue updated successfully',
            'data' => $issue
        ]);
    }
}
