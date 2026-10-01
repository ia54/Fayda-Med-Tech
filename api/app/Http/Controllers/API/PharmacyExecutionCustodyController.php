<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use App\Services\PharmacyExecutionCustodyLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PharmacyExecutionCustodyController extends Controller
{
    public function history(Request $r, int $executionId)
    {
        abort_unless(in_array($r->user()?->role, ['pharmacist', 'pharmacy_technician'], true) && $r->user()->status === 'active' && $r->user()->organization_id, 403);
        $r->validate(['page' => 'nullable|integer|min:1']);
        $execution = DB::table('pharmacy_batch_executions')->find($executionId);
        abort_unless($execution, 404);
        $batch = DB::table('pharmacy_batch_worksheets')->where('id', $execution->batch_id)->where('organization_id', $r->user()->organization_id)->first();
        abort_unless($batch, 404);
        app(PharmacyAccess::class)->requireLocation($r->user(), $batch->location_id);
        $rows = DB::table('pharmacy_execution_custody_proposals')->where('execution_id', $executionId)->orderByDesc('id')->paginate(20,
            ['id', 'created_by', 'execution_version', 'proposal', 'evidence', 'status', 'reviewed_by', 'review_evidence', 'reviewed_at', 'created_at']);
        $rows->getCollection()->transform(function ($row) {
            $row->proposal = json_decode($row->proposal, true, 512, JSON_THROW_ON_ERROR);

            return $row;
        });

        return response()->json(['data' => $rows, 'execution_version' => $execution->version,
            'balance' => app(PharmacyExecutionCustodyLedger::class)->summary($r->user(), $executionId),
            'pending' => DB::table('pharmacy_execution_custody_proposals')->where('execution_id', $executionId)->where('status', 'pending')->exists(),
            'release_enabled' => false]);
    }

    public function store(Request $r, int $executionId)
    {
        return response()->json(['data' => ['id' => app(PharmacyExecutionCustodyLedger::class)->retain($r->user(), $executionId, $r->all())]], 201);
    }

    public function decide(Request $r, int $proposalId)
    {
        $data = $r->validate(['decision' => 'required|in:applied,rejected', 'evidence' => 'required|string|max:5000']);
        app(PharmacyExecutionCustodyLedger::class)->decide($r->user(), $proposalId, $data['decision'], $data['evidence']);

        return response()->json(['data' => ['id' => $proposalId, 'release_enabled' => false]]);
    }
}
