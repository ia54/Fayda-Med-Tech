<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use App\Services\PharmacyExecutionCustodyLedger;
use App\Services\PharmacyYieldCorrectionLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PharmacyYieldCorrectionController extends Controller
{
    public function worklist(Request $r)
    {
        $actor = $r->user();
        abort_unless(in_array($actor?->role, ['pharmacist', 'pharmacy_technician'], true) && $actor->status === 'active' && $actor->organization_id, 403);
        $data = $r->validate(['page' => 'nullable|integer|min:1', 'location_id' => 'nullable|integer|min:1', 'reviewable' => 'nullable|boolean']);
        $query = DB::table('pharmacy_yield_correction_proposals as p')
            ->join('pharmacy_batch_executions as e', 'e.id', '=', 'p.execution_id')
            ->join('pharmacy_batch_worksheets as b', 'b.id', '=', 'e.batch_id')
            ->where('b.organization_id', $actor->organization_id)->where('p.status', 'pending');
        app(PharmacyAccess::class)->scope($query, $actor, 'b.location_id');
        if (! empty($data['location_id'])) {
            app(PharmacyAccess::class)->requireLocation($actor, $data['location_id']);
            $query->where('b.location_id', $data['location_id']);
        }
        if ($r->boolean('reviewable')) {
            abort_unless($actor->role === 'pharmacist', 403);
            $query->where('p.created_by', '<>', $actor->id)->where('e.created_by', '<>', $actor->id)
                ->whereNotExists(function ($q) use ($actor) {
                    $q->selectRaw('1')->from('pharmacy_execution_addenda as a')->whereColumn('a.execution_id', 'e.id')->where('a.created_by', $actor->id);
                });
        }

        return response()->json(['data' => $query->orderBy('p.created_at')->orderBy('p.id')->paginate(20,
            ['p.id', 'p.execution_id', 'p.created_by', 'p.created_at', 'b.id as batch_id', 'b.batch_number', 'b.location_id']), 'release_enabled' => false]);
    }

    public function history(Request $r, int $executionId)
    {
        abort_unless(in_array($r->user()?->role, ['pharmacist', 'pharmacy_technician'], true) && $r->user()->status === 'active' && $r->user()->organization_id, 403);
        $r->validate(['page' => 'nullable|integer|min:1']);
        $execution = DB::table('pharmacy_batch_executions')->find($executionId);
        abort_unless($execution, 404);
        $batch = DB::table('pharmacy_batch_worksheets')->where('id', $execution->batch_id)->where('organization_id', $r->user()->organization_id)->first();
        abort_unless($batch, 404);
        app(PharmacyAccess::class)->requireLocation($r->user(), $batch->location_id);
        $rows = DB::table('pharmacy_yield_correction_proposals')->where('execution_id', $executionId)->orderByDesc('id')->paginate(20,
            ['id', 'created_by', 'execution_version', 'proposal', 'correction_evidence', 'status', 'reviewed_by', 'review_evidence', 'reviewed_at', 'created_at']);
        $rows->getCollection()->transform(function ($row) {
            $row->correction_evidence = json_decode($row->correction_evidence, true, 512, JSON_THROW_ON_ERROR);
            $row->container_correction_id = DB::table('pharmacy_container_quantity_corrections')->where('yield_proposal_id', $row->id)->value('id');
            $row->proposal = json_decode($row->proposal, true, 512, JSON_THROW_ON_ERROR);

            return $row;
        });

        return response()->json(['container_established' => app(\App\Services\PharmacyContainerCustodyLedger::class)->established($executionId), 'data' => $rows, 'execution_version' => $execution->version,
            'balance' => app(PharmacyExecutionCustodyLedger::class)->summary($r->user(), $executionId),
            'pending' => DB::table('pharmacy_yield_correction_proposals')->where('execution_id', $executionId)->where('status', 'pending')->exists(),
            'custody_pending' => DB::table('pharmacy_execution_custody_proposals')->where('execution_id', $executionId)->where('status', 'pending')->exists(),
            'release_enabled' => false]);
    }

    public function store(Request $r, int $executionId)
    {
        return response()->json(['data' => ['id' => app(PharmacyYieldCorrectionLedger::class)->retain($r->user(), $executionId, $r->all())]], 201);
    }

    public function decide(Request $r, int $proposalId)
    {
        if ($linked = DB::table('pharmacy_container_quantity_corrections')->where('yield_proposal_id', $proposalId)->first()) {
            abort_unless($r->user()?->role === 'pharmacist' && $r->user()->status === 'active' && $r->user()->organization_id, 403);
            $execution = DB::table('pharmacy_batch_executions')->find($linked->execution_id);
            $batch = $execution ? DB::table('pharmacy_batch_worksheets')->where('id', $execution->batch_id)->where('organization_id', $r->user()->organization_id)->first() : null;
            abort_unless($batch, 404);
            app(PharmacyAccess::class)->requireLocation($r->user(), $batch->location_id);
            abort(409, 'Review individual container quantities through container corrections.');
        }
        $data = $r->validate(['decision' => 'required|in:applied,rejected', 'evidence' => 'required|string|max:5000']);
        app(PharmacyYieldCorrectionLedger::class)->decide($r->user(), $proposalId, $data['decision'], $data['evidence']);

        return response()->json(['data' => ['id' => $proposalId, 'release_enabled' => false]]);
    }
}
