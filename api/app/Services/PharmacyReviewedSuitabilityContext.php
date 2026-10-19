<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Current documentary prerequisites for labels, never clinical or dispensing authority. */
class PharmacyReviewedSuitabilityContext
{
    public function inspect(User $actor, int $executionId): array
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        abort_unless($actor->role === 'pharmacist' && $actor->status === 'active' && $actor->organization_id, 403);
        return DB::transaction(function () use ($actor, $executionId) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $current = app(PharmacyCurrentContainerReviewContext::class)->inspect($actor, $executionId);
            $execution = $current['custody']['execution']; $batch = $current['custody']['batch'];
            $p = DB::table('pharmacy_container_suitability')->where('execution_id', $executionId)->orderByDesc('id')->first();
            abort_unless($p && $p->status === 'reviewed', 409, 'The latest suitability findings require independent review; earlier findings cannot be reused.');
            $digest = app(PharmacyCompoundingIncident::class);
            $source = json_decode($p->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $proposal = json_decode($p->proposal, true, 512, JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($p->source_hash, $digest->digest($source)) && hash_equals($p->proposal_hash, $digest->digest($proposal)), 409, 'Suitability evidence integrity failed.');
            abort_unless(hash_equals($p->source_hash, $digest->digest($current)) && (int) $p->dating_proposal_id === (int) $current['dating']['dating_proposal']['id'], 409, 'Suitability source evidence changed.');
            foreach ([$p->created_by, $p->reviewed_by] as $id) {
                $staff = User::find($id);
                abort_unless($staff && $staff->role === 'pharmacist' && $staff->status === 'active' && (int) $staff->organization_id === (int) $actor->organization_id, 409, 'Suitability contributor is no longer authorized.');
                app(PharmacyAccess::class)->requireLocation($staff, $batch['location_id']);
            }
            abort_unless($p->reviewed_at && trim((string) $p->review_evidence) !== '' && (int) $p->reviewed_by !== (int) $p->created_by
                && (int) $p->reviewed_by !== (int) $execution['created_by']
                && ! DB::table('pharmacy_execution_addenda')->where('execution_id', $executionId)->where('created_by', $p->reviewed_by)->exists(), 409, 'Independent suitability review is required.');
            foreach (['proposed' => $p->created_by, 'reviewed' => $p->reviewed_by] as $action => $id) {
                abort_unless(DB::table('pharmacy_compounding_events')->where('batch_id', $batch['id'])->where('action', 'container_suitability_'.$action)
                    ->where('actor_id', $id)->where('details->proposal_id', $p->id)->exists(), 409, 'Suitability audit evidence is missing.');
            }
            $checked = app(PharmacyContainerSuitabilityEvidence::class)->inspect($source, array_column($proposal['containers'], 'assessment'));
            abort_unless(hash_equals($p->proposal_hash, $digest->digest($checked)) && ! $checked['requires_follow_up'], 409, 'Unresolved suitability findings prevent label prerequisites.');
            return ['suitability_proposal' => (array) $p, 'current_container_evidence' => $current,
                'clinical_suitability_verified' => false, 'release_enabled' => false];
        });
    }
}
