<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Documentary prerequisite for subsequent proposals, never clinical or dispensing authority. */
class PharmacyReviewedQualityContext
{
    public function inspect(User $actor, int $executionId): array
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        abort_unless($actor->role === 'pharmacist' && $actor->status === 'active' && $actor->organization_id, 403);
        return DB::transaction(function () use ($actor, $executionId) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $e = DB::table('pharmacy_batch_executions')->where('id', $executionId)->lockForUpdate()->first();
            abort_unless($e, 404);
            $batch = DB::table('pharmacy_batch_worksheets')->where('id', $e->batch_id)->where('organization_id', $actor->organization_id)->first();
            abort_unless($batch, 404);
            app(PharmacyAccess::class)->requireLocation($actor, $batch->location_id);
            $p = DB::table('pharmacy_batch_quality_records')->where('execution_id', $executionId)->orderByDesc('id')->first();
            abort_unless($p && $p->status === 'reviewed', 409, 'The latest quality record needs independent review; earlier records cannot be reused.');
            $digest = app(PharmacyCompoundingIncident::class);
            $source = json_decode($p->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $results = json_decode($p->results, true, 512, JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($p->source_hash, $digest->digest($source)) && hash_equals($p->results_hash, $digest->digest($results))
                && hash_equals($p->evidence_hash, hash('sha256', $p->evidence)), 409, 'Quality evidence integrity failed.');
            foreach ([$p->created_by, $p->reviewed_by] as $userId) {
                $staff = User::find($userId);
                abort_unless($staff && $staff->role === 'pharmacist' && $staff->status === 'active' && (int) $staff->organization_id === (int) $actor->organization_id, 409, 'Quality author or reviewer is no longer authorized.');
                app(PharmacyAccess::class)->requireLocation($staff, $batch->location_id);
            }
            abort_unless($p->reviewed_at && trim((string) $p->review_evidence) !== '' && (int) $p->reviewed_by !== (int) $p->created_by
                && (int) $p->reviewed_by !== (int) $e->created_by
                && ! DB::table('pharmacy_execution_addenda')->where('execution_id', $executionId)->where('created_by', $p->reviewed_by)->exists(), 409, 'Independent quality review is required.');
            abort_unless(DB::table('pharmacy_compounding_events')->where('batch_id', $batch->id)->where('action', 'quality_results_reviewed')
                ->where('actor_id', $p->reviewed_by)->where('details->quality_record_id', $p->id)->exists(), 409, 'Quality review audit is missing.');
            $current = app(PharmacyBatchQualityContext::class)->inspect($actor, $executionId, $p->protocol_id);
            abort_unless(hash_equals($p->source_hash, $digest->digest($current)), 409, 'Quality source evidence changed. Retain and review a replacement.');
            $checked = app(PharmacyQualityEvidence::class)->inspect($current['protocol_record'], array_column($results['evidence'], 'result'));
            abort_unless(hash_equals($p->results_hash, $digest->digest($checked)) && ! $checked['requires_follow_up'], 409, 'Failed or unassessed quality observations require resolution.');
            abort_if($current['batch_hold'] || count($current['pending_yield_corrections']) || count($current['pending_output_custody'])
                || count($current['pending_consumption_corrections']), 409, 'Resolve pending batch evidence before dating proposals.');
            foreach ($current['ingredients'] as $ingredient) {
                abort_if($ingredient['receipt']['status'] === 'recalled', 409, 'Recalled ingredient evidence blocks dating proposals.');
            }
            return ['quality_record' => (array) $p, 'source' => $current, 'clinical_quality_verified' => false, 'release_enabled' => false];
        });
    }
}
