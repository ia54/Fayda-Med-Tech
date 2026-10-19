<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use App\Services\PharmacyCompoundingIncident;
use App\Services\PharmacyContainerCustodyLedger;
use App\Services\PharmacyContainerRepackagingLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PharmacyContainerRepackagingController extends Controller
{
    public function worklist(Request $r)
    {
        $actor = $r->user();
        abort_unless(in_array($actor?->role, ['pharmacist', 'pharmacy_technician'], true) && $actor->status === 'active' && $actor->organization_id, 403);
        $data = $r->validate(['page' => 'nullable|integer|min:1', 'location_id' => 'nullable|integer|min:1', 'reviewable' => 'nullable|boolean']);
        $query = DB::table('pharmacy_container_repackaging as p')
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

    private function scope(Request $r, int $executionId): void
    {
        abort_unless(in_array($r->user()?->role, ['pharmacist', 'pharmacy_technician'], true) && $r->user()->status === 'active' && $r->user()->organization_id, 403);
        $e = DB::table('pharmacy_batch_executions')->find($executionId);
        $b = $e ? DB::table('pharmacy_batch_worksheets')->where('id', $e->batch_id)->where('organization_id', $r->user()->organization_id)->first() : null;
        abort_unless($b, 404);
        app(PharmacyAccess::class)->requireLocation($r->user(), $b->location_id);
    }

    public function context(Request $r, int $executionId)
    {
        $this->scope($r, $executionId);
        $source = app(PharmacyContainerCustodyLedger::class)->context($r->user(), $executionId);
        return response()->json(['data' => ['execution_id' => $executionId, 'execution_version' => $source['execution']['version'],
            'source_hash' => app(PharmacyCompoundingIncident::class)->digest($source), 'balance' => $source['container_balance'],
            'historical_evidence_only' => true], 'release_enabled' => false]);
    }

    public function history(Request $r, int $executionId)
    {
        $this->scope($r, $executionId); $r->validate(['page' => 'nullable|integer|min:1']);
        $rows = DB::table('pharmacy_container_repackaging')->where('execution_id', $executionId)->orderByDesc('id')->paginate(20,
            ['id', 'execution_id', 'execution_version', 'created_by', 'status', 'reviewed_by', 'reviewed_at', 'created_at']);
        $pending = false;
        foreach (['pharmacy_container_repackaging', 'pharmacy_execution_custody_proposals', 'pharmacy_yield_correction_proposals'] as $table) {
            $pending = $pending || DB::table($table)->where('execution_id', $executionId)->where('status', 'pending')->exists();
        }
        return response()->json(['data' => $rows, 'pending_output_change' => $pending, 'release_enabled' => false]);
    }

    private function record(Request $r, int $id): object
    {
        $p = DB::table('pharmacy_container_repackaging')->find($id); abort_unless($p, 404);
        $this->scope($r, $p->execution_id);
        return $p;
    }

    public function show(Request $r, int $id)
    {
        $p = $this->record($r, $id); $digest = app(PharmacyCompoundingIncident::class);
        $source = json_decode($p->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
        $projection = json_decode($p->proposal, true, 512, JSON_THROW_ON_ERROR);
        abort_unless(hash_equals($p->source_hash, $digest->digest($source)) && hash_equals($p->proposal_hash, $digest->digest($projection)), 409, 'Repackaging evidence integrity failed.');
        $data = array_intersect_key((array) $p, array_flip(['id', 'execution_id', 'execution_version', 'created_by', 'status', 'reviewed_by', 'review_evidence', 'reviewed_at', 'created_at']));
        return response()->json(['data' => $data + ['proposal' => $projection], 'release_enabled' => false]);
    }

    public function store(Request $r, int $executionId)
    {
        $this->scope($r, $executionId);
        $id = app(PharmacyContainerRepackagingLedger::class)->retain($r->user(), $executionId, $r->all());
        return $this->show($r, $id)->setStatusCode(201);
    }

    public function decide(Request $r, int $id)
    {
        $this->record($r, $id);
        $d = $r->validate(['decision' => 'required|in:applied,rejected', 'evidence' => 'required|string|max:5000']);
        app(PharmacyContainerRepackagingLedger::class)->decide($r->user(), $id, $d['decision'], $d['evidence']);
        return response()->json(['data' => ['id' => $id, 'status' => $d['decision']], 'release_enabled' => false]);
    }
}
