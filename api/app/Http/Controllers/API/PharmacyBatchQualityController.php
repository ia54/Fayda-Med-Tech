<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use App\Services\PharmacyBatchQualityContext;
use App\Services\PharmacyBatchQualityLedger;
use App\Services\PharmacyCompoundingIncident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PharmacyBatchQualityController extends Controller
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
        $d = $r->validate(['protocol_id' => 'required|integer|min:1']);
        $source = app(PharmacyBatchQualityContext::class)->inspect($r->user(), $executionId, $d['protocol_id']);
        return response()->json(['data' => ['execution_id' => $executionId, 'protocol_id' => $d['protocol_id'],
            'source_hash' => app(PharmacyCompoundingIncident::class)->digest($source), 'protocol_record' => $source['protocol_record'],
            'batch_hold' => $source['batch_hold'], 'output_custody' => $source['output_custody'],
            'pending_yield_count' => count($source['pending_yield_corrections']), 'pending_custody_count' => count($source['pending_output_custody'])],
            'release_enabled' => false, 'clinical_quality_verified' => false]);
    }

    public function history(Request $r, int $executionId)
    {
        $this->execution($r, $executionId);
        $r->validate(['page' => 'nullable|integer|min:1']);
        return response()->json(['data' => DB::table('pharmacy_batch_quality_records')->where('execution_id', $executionId)->orderByDesc('id')
            ->paginate(20, ['id', 'execution_id', 'protocol_id', 'previous_id', 'created_by', 'status', 'reviewed_by', 'created_at', 'reviewed_at']), 'release_enabled' => false]);
    }

    private function record(Request $r, int $id): object
    {
        $p = DB::table('pharmacy_batch_quality_records')->where('id', $id)->first();
        abort_unless($p, 404);
        $this->execution($r, $p->execution_id);
        return $p;
    }

    public function show(Request $r, int $id)
    {
        $p = $this->record($r, $id);
        $results = json_decode($p->results, true, 512, JSON_THROW_ON_ERROR);
        $source = json_decode($p->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
        $digest = app(PharmacyCompoundingIncident::class);
        abort_unless(hash_equals($p->results_hash, $digest->digest($results)) && hash_equals($p->source_hash, $digest->digest($source))
            && hash_equals($p->evidence_hash, hash('sha256', $p->evidence)), 409, 'Retained quality evidence integrity failed.');
        $data = array_intersect_key((array) $p, array_flip(['id', 'execution_id', 'protocol_id', 'previous_id', 'created_by', 'status',
            'evidence', 'reviewed_by', 'review_evidence', 'created_at', 'reviewed_at']));
        $data['results'] = $results;
        $data['protocol_record'] = $source['protocol_record'];
        return response()->json(['data' => $data, 'release_enabled' => false, 'clinical_quality_verified' => false]);
    }

    public function store(Request $r, int $executionId)
    {
        $this->execution($r, $executionId);
        $d = $r->validate(['protocol_id' => 'required|integer|min:1']);
        $id = app(PharmacyBatchQualityLedger::class)->retain($r->user(), $executionId, $d['protocol_id'], $r->all());
        return $this->show($r, $id)->setStatusCode(201);
    }

    public function decide(Request $r, int $id)
    {
        $this->record($r, $id);
        $d = $r->validate(['decision' => 'required|in:reviewed,rejected', 'evidence' => 'required|string|max:5000']);
        app(PharmacyBatchQualityLedger::class)->decide($r->user(), $id, $d['decision'], $d['evidence']);
        return response()->json(['data' => ['id' => $id, 'status' => $d['decision']], 'release_enabled' => false]);
    }
}
