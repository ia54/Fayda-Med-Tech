<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** Retained documentary quality observations and review; never releases medication. */
class PharmacyBatchQualityLedger
{
    private function actor(User $actor): void
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        abort_unless($actor->role === 'pharmacist' && $actor->status === 'active' && $actor->organization_id, 403);
    }

    private function scope(User $actor, int $executionId): array
    {
        $e = DB::table('pharmacy_batch_executions')->where('id', $executionId)->lockForUpdate()->first();
        abort_unless($e, 404);
        $b = DB::table('pharmacy_batch_worksheets')->where('id', $e->batch_id)->where('organization_id', $actor->organization_id)->first();
        abort_unless($b, 404);
        app(PharmacyAccess::class)->requireLocation($actor, $b->location_id);
        return [$e, $b];
    }

    public function retain(User $actor, int $executionId, int $protocolId, array $input): int
    {
        $this->actor($actor);
        $d = Validator::make($input, ['request_id' => 'required|uuid', 'source_hash' => 'required|string|regex:/^[a-f0-9]{64}$/',
            'previous_id' => 'present|nullable|integer|min:1', 'results' => 'required|array|min:1|max:100',
            'results.*' => 'required|array:key,outcome,observation,evidence_reference', 'results.*.key' => 'required|string|max:40',
            'results.*.outcome' => 'required|in:pass,fail,not_assessed', 'results.*.observation' => 'required|string|max:5000',
            'results.*.evidence_reference' => 'required|string|max:5000', 'evidence' => 'required|string|max:5000'])->validate();
        abort_if(trim($d['evidence']) === '', 422);
        return DB::transaction(function () use ($actor, $executionId, $protocolId, $d) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            [, $batch] = $this->scope($actor, $executionId);
            $digest = app(PharmacyCompoundingIncident::class);
            $requestHash = $digest->digest(['protocol_id' => $protocolId, 'input' => $d]);
            $retry = DB::table('pharmacy_batch_quality_records')->where('execution_id', $executionId)->where('request_id', $d['request_id'])->first();
            if ($retry) {
                abort_unless((int) $retry->created_by === (int) $actor->id && hash_equals($retry->request_hash, $requestHash), 409, 'Request belongs to different quality evidence.');
                return (int) $retry->id;
            }
            $last = DB::table('pharmacy_batch_quality_records')->where('execution_id', $executionId)->orderByDesc('id')->first();
            abort_unless(($last ? (int) $last->id : null) === ($d['previous_id'] === null ? null : (int) $d['previous_id']), 409, 'Quality history changed.');
            abort_if($last && $last->status === 'pending', 409, 'Review or reject the pending record before recording a replacement.');
            $source = app(PharmacyBatchQualityContext::class)->inspect($actor, $executionId, $protocolId);
            abort_unless(hash_equals($d['source_hash'], $digest->digest($source)), 409, 'Batch evidence changed. Refresh and compare it before recording results.');
            $results = app(PharmacyQualityEvidence::class)->inspect($source['protocol_record'], $d['results']);
            $id = DB::table('pharmacy_batch_quality_records')->insertGetId(['execution_id' => $executionId, 'protocol_id' => $protocolId,
                'previous_id' => $d['previous_id'], 'created_by' => $actor->id, 'request_id' => $d['request_id'], 'request_hash' => $requestHash,
                'source_snapshot' => json_encode($source, JSON_THROW_ON_ERROR), 'source_hash' => $d['source_hash'],
                'results' => json_encode($results, JSON_THROW_ON_ERROR), 'results_hash' => $digest->digest($results),
                'evidence' => $d['evidence'], 'evidence_hash' => hash('sha256', $d['evidence']), 'status' => 'pending', 'created_at' => now()]);
            $this->event($actor, $batch, $id, 'quality_results_recorded');
            return $id;
        });
    }

    public function decide(User $actor, int $id, string $decision, string $evidence): void
    {
        $this->actor($actor);
        abort_unless(in_array($decision, ['reviewed', 'rejected'], true) && trim($evidence) !== '' && strlen($evidence) <= 5000, 422);
        DB::transaction(function () use ($actor, $id, $decision, $evidence) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $p = DB::table('pharmacy_batch_quality_records')->where('id', $id)->lockForUpdate()->first();
            abort_unless($p, 404);
            [$execution, $batch] = $this->scope($actor, $p->execution_id);
            abort_if((int) $actor->id === (int) $p->created_by || (int) $actor->id === (int) $execution->created_by
                || DB::table('pharmacy_execution_addenda')->where('execution_id', $execution->id)->where('created_by', $actor->id)->exists(), 422, 'A pharmacist independent of these results and preparation must review.');
            if ($p->status !== 'pending') {
                abort_unless($p->status === $decision && (int) $p->reviewed_by === (int) $actor->id && $p->review_evidence === $evidence, 409);
                return;
            }
            if ($decision === 'reviewed') {
                $author = User::find($p->created_by);
                abort_unless($author && (int) $author->organization_id === (int) $actor->organization_id && $author->role === 'pharmacist' && $author->status === 'active', 409, 'Result author is no longer authorized.');
                app(PharmacyAccess::class)->requireLocation($author, $batch->location_id);
                $digest = app(PharmacyCompoundingIncident::class);
                $source = json_decode($p->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
                $results = json_decode($p->results, true, 512, JSON_THROW_ON_ERROR);
                abort_unless(hash_equals($p->source_hash, $digest->digest($source)) && hash_equals($p->results_hash, $digest->digest($results)) && hash_equals($p->evidence_hash, hash('sha256', $p->evidence)), 409, 'Retained quality evidence integrity failed.');
                $current = app(PharmacyBatchQualityContext::class)->inspect($actor, $p->execution_id, $p->protocol_id);
                abort_unless(hash_equals($p->source_hash, $digest->digest($current)), 409, 'Batch evidence changed. Reject and replace this result record.');
                $checked = app(PharmacyQualityEvidence::class)->inspect($current['protocol_record'], array_column($results['evidence'], 'result'));
                abort_unless(hash_equals($p->results_hash, $digest->digest($checked)), 409, 'Quality result projection changed.');
                // Review retains failed/unassessed observations too. It does not approve quality or clear any hold.
            }
            DB::table('pharmacy_batch_quality_records')->where('id', $id)->update(['status' => $decision, 'reviewed_by' => $actor->id,
                'review_evidence' => $evidence, 'reviewed_at' => now()]);
            $this->event($actor, $batch, $id, 'quality_results_'.$decision);
        });
    }

    private function event(User $actor, object $batch, int $id, string $action): void
    {
        DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch->formulation_id, 'batch_id' => $batch->id,
            'actor_id' => $actor->id, 'action' => $action, 'details' => json_encode(['quality_record_id' => $id, 'release_enabled' => false, 'stock_adjusted' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
