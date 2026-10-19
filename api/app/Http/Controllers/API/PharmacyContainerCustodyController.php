<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use App\Services\PharmacyCompoundingIncident;
use App\Services\PharmacyContainerCustodyLedger;
use App\Services\PharmacyExecutionCustodyLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PharmacyContainerCustodyController extends Controller
{
    private function scope(Request $r, int $executionId): void
    {
        abort_unless(in_array($r->user()?->role, ['pharmacist', 'pharmacy_technician'], true) && $r->user()->status === 'active' && $r->user()->organization_id, 403);
        $e = DB::table('pharmacy_batch_executions')->find($executionId);
        abort_unless($e, 404);
        $b = DB::table('pharmacy_batch_worksheets')->where('id', $e->batch_id)->where('organization_id', $r->user()->organization_id)->first();
        abort_unless($b, 404);
        app(PharmacyAccess::class)->requireLocation($r->user(), $b->location_id);
    }

    public function context(Request $r, int $executionId)
    {
        $this->scope($r, $executionId);
        $source = app(PharmacyContainerCustodyLedger::class)->context($r->user(), $executionId);
        return response()->json(['data' => ['execution_id' => $executionId, 'execution_version' => $source['execution']['version'],
            'packaging_proposal_id' => $source['packaging_context']['packaging_proposal']['id'],
            'source_hash' => app(PharmacyCompoundingIncident::class)->digest($source), 'balance' => $source['container_balance'],
            'historical_evidence_only' => true], 'release_enabled' => false]);
    }

    public function history(Request $r, int $executionId)
    {
        $this->scope($r, $executionId);
        $r->validate(['page' => 'nullable|integer|min:1']);
        $rows = DB::table('pharmacy_container_custody_records as c')->join('pharmacy_execution_custody_proposals as o', 'o.id', '=', 'c.output_proposal_id')
            ->where('c.execution_id', $executionId)->where('o.execution_id', $executionId)->orderByDesc('c.id')->paginate(20,
                ['o.id', 'c.id as container_record_id', 'c.execution_id', 'c.packaging_proposal_id', 'o.created_by', 'o.status', 'o.reviewed_by', 'o.reviewed_at', 'o.created_at']);
        return response()->json(['data' => $rows, 'pending' => DB::table('pharmacy_execution_custody_proposals')->where('execution_id', $executionId)->where('status', 'pending')->exists()
                || DB::table('pharmacy_yield_correction_proposals')->where('execution_id', $executionId)->where('status', 'pending')->exists()
                || DB::table('pharmacy_container_repackaging')->where('execution_id', $executionId)->where('status', 'pending')->exists(),
            'release_enabled' => false]);
    }

    private function record(Request $r, int $proposalId): array
    {
        $c = DB::table('pharmacy_container_custody_records')->where('output_proposal_id', $proposalId)->first();
        abort_unless($c, 404);
        $this->scope($r, $c->execution_id);
        $o = DB::table('pharmacy_execution_custody_proposals')->where('id', $proposalId)->where('execution_id', $c->execution_id)->first();
        abort_unless($o, 409, 'Linked output evidence is missing.');
        return [$c, $o];
    }

    public function show(Request $r, int $proposalId)
    {
        [$c, $o] = $this->record($r, $proposalId);
        $p = json_decode($c->proposal, true, 512, JSON_THROW_ON_ERROR);
        $source = json_decode($c->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
        $digest = app(PharmacyCompoundingIncident::class);
        abort_unless(hash_equals($c->proposal_hash, $digest->digest($p)) && hash_equals($c->source_hash, $digest->digest($source)), 409, 'Container custody evidence integrity failed.');
        $data = array_intersect_key((array) $o, array_flip(['id', 'execution_id', 'execution_version', 'created_by', 'status', 'reviewed_by', 'review_evidence', 'reviewed_at', 'created_at']));
        $data += ['container_record_id' => $c->id, 'packaging_proposal_id' => $c->packaging_proposal_id, 'proposal' => $p];
        return response()->json(['data' => $data, 'release_enabled' => false]);
    }

    public function store(Request $r, int $executionId)
    {
        $this->scope($r, $executionId);
        $id = app(PharmacyContainerCustodyLedger::class)->retain($r->user(), $executionId, $r->all());
        return $this->show($r, $id)->setStatusCode(201);
    }

    public function decide(Request $r, int $proposalId)
    {
        $this->record($r, $proposalId);
        $d = $r->validate(['decision' => 'required|in:applied,rejected', 'evidence' => 'required|string|max:5000']);
        app(PharmacyExecutionCustodyLedger::class)->decide($r->user(), $proposalId, $d['decision'], $d['evidence']);
        return response()->json(['data' => ['id' => $proposalId, 'status' => $d['decision']], 'release_enabled' => false]);
    }
}
