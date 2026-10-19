<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use App\Services\PharmacyReviewedQualityContext;
use App\Services\PharmacyBeyondUseLedger;
use App\Services\PharmacyCompoundingIncident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PharmacyBeyondUseController extends Controller
{
    private function execution(Request $r, int $id): object
    {
        abort_unless(in_array($r->user()?->role, ['pharmacist', 'pharmacy_technician'], true)
            && $r->user()->status === 'active' && $r->user()->organization_id, 403);
        $e = DB::table('pharmacy_batch_executions')->where('id', $id)->first();
        abort_unless($e, 404);
        $b = DB::table('pharmacy_batch_worksheets')->where('id', $e->batch_id)->where('organization_id', $r->user()->organization_id)->first();
        abort_unless($b, 404);
        app(PharmacyAccess::class)->requireLocation($r->user(), $b->location_id);
        return $e;
    }

    public function context(Request $r, int $executionId)
    {
        $this->execution($r, $executionId);
        $source = app(PharmacyReviewedQualityContext::class)->inspect($r->user(), $executionId);
        $execution = json_decode($source['source']['execution']['record'], true, 512, JSON_THROW_ON_ERROR);
        return response()->json(['data' => ['execution_id' => $executionId, 'quality_record_id' => $source['quality_record']['id'],
            'source_hash' => app(PharmacyCompoundingIncident::class)->digest($source), 'prepared_on' => $execution['prepared_on']],
            'release_enabled' => false, 'clinical_limits_verified' => false]);
    }

    public function history(Request $r, int $executionId)
    {
        $this->execution($r, $executionId);
        $r->validate(['page' => 'nullable|integer|min:1']);
        return response()->json(['data' => DB::table('pharmacy_beyond_use_proposals')->where('execution_id', $executionId)->orderByDesc('id')
            ->paginate(20, ['id', 'execution_id', 'quality_record_id', 'previous_id', 'created_by', 'status', 'reviewed_by', 'created_at', 'reviewed_at']), 'release_enabled' => false]);
    }

    private function record(Request $r, int $id): object
    {
        $p = DB::table('pharmacy_beyond_use_proposals')->where('id', $id)->first();
        abort_unless($p, 404);
        $this->execution($r, $p->execution_id);
        return $p;
    }

    public function show(Request $r, int $id)
    {
        $p = $this->record($r, $id);
        $proposal = json_decode($p->proposal, true, 512, JSON_THROW_ON_ERROR);
        $source = json_decode($p->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
        $digest = app(PharmacyCompoundingIncident::class);
        abort_unless(hash_equals($p->proposal_hash, $digest->digest($proposal)) && hash_equals($p->source_hash, $digest->digest($source)), 409, 'Retained dating evidence integrity failed.');
        $data = array_intersect_key((array) $p, array_flip(['id', 'execution_id', 'quality_record_id', 'previous_id', 'created_by', 'status',
            'reviewed_by', 'review_evidence', 'created_at', 'reviewed_at']));
        $data['proposal'] = $proposal;
        $data['elapsed'] = new \DateTimeImmutable($proposal['proposed_bud_at_utc']) <= now()->toDateTimeImmutable();
        return response()->json(['data' => $data, 'release_enabled' => false, 'clinical_limits_verified' => false]);
    }

    public function store(Request $r, int $executionId)
    {
        $this->execution($r, $executionId);
        $id = app(PharmacyBeyondUseLedger::class)->retain($r->user(), $executionId, $r->all());
        return $this->show($r, $id)->setStatusCode(201);
    }

    public function decide(Request $r, int $id)
    {
        $this->record($r, $id);
        $d = $r->validate(['decision' => 'required|in:reviewed,rejected', 'evidence' => 'required|string|max:5000']);
        app(PharmacyBeyondUseLedger::class)->decide($r->user(), $id, $d['decision'], $d['evidence']);
        return response()->json(['data' => ['id' => $id, 'status' => $d['decision']], 'release_enabled' => false]);
    }
}
