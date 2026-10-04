<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Fresh packaging prerequisite for subsequent eligibility checks, never release authority. */
class PharmacyReviewedPackagingContext
{
    public function inspect(User $actor, int $executionId): array
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        abort_unless($actor->role === 'pharmacist' && $actor->status === 'active' && $actor->organization_id, 403);
        return DB::transaction(function () use ($actor, $executionId) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $dating = app(PharmacyReviewedBeyondUseContext::class)->inspect($actor, $executionId);
            $batch = $dating['quality']['source']['batch'];
            $execution = $dating['quality']['source']['execution'];
            $p = DB::table('pharmacy_packaging_proposals')->where('execution_id', $executionId)->orderByDesc('id')->first();
            abort_unless($p && $p->status === 'reviewed', 409, 'The latest packaging proposal must be independently reviewed. Earlier proposals cannot be reused.');
            $digest = app(PharmacyCompoundingIncident::class);
            $source = json_decode($p->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $proposal = json_decode($p->proposal, true, 512, JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($p->source_hash, $digest->digest($source)) && hash_equals($p->proposal_hash, $digest->digest($proposal)), 409, 'Packaging evidence integrity failed.');
            abort_unless(hash_equals($p->source_hash, $digest->digest($dating)) && (int) $p->dating_proposal_id === (int) $dating['dating_proposal']['id'], 409, 'Packaging source evidence changed.');
            foreach ([$p->created_by, $p->reviewed_by] as $userId) {
                $staff = User::find($userId);
                abort_unless($staff && $staff->role === 'pharmacist' && $staff->status === 'active' && (int) $staff->organization_id === (int) $actor->organization_id, 409, 'Packaging author or reviewer is no longer authorized.');
                app(PharmacyAccess::class)->requireLocation($staff, $batch['location_id']);
            }
            abort_unless($p->reviewed_at && trim((string) $p->review_evidence) !== '' && (int) $p->reviewed_by !== (int) $p->created_by
                && (int) $p->reviewed_by !== (int) $execution['created_by']
                && ! DB::table('pharmacy_execution_addenda')->where('execution_id', $executionId)->where('created_by', $p->reviewed_by)->exists(), 409, 'Independent packaging review is required.');
            abort_unless(DB::table('pharmacy_compounding_events')->where('batch_id', $batch['id'])->where('action', 'packaging_reviewed')
                ->where('actor_id', $p->reviewed_by)->where('details->proposal_id', $p->id)->exists(), 409, 'Packaging review audit is missing.');
            $input = ['unit' => $proposal['unit'], 'unpackaged_quantity' => $proposal['unpackaged_quantity'], 'evidence' => $proposal['evidence'],
                'containers' => array_map(fn ($r) => array_intersect_key($r, array_flip(['identifier', 'quantity', 'container_reference', 'storage_reference'])), $proposal['containers'])];
            $checked = app(PharmacyFinishedPackaging::class)->project($dating['output_balance'], $input);
            abort_unless(hash_equals($p->proposal_hash, $digest->digest($checked)), 409, 'Packaging projection changed.');
            $identities = [];
            foreach ($checked['containers'] as $container) {
                $identity = DB::table('pharmacy_container_identities')->where('organization_id', $actor->organization_id)
                    ->where('execution_id', $executionId)->where('identifier', $container['identifier'])->first();
                abort_unless($identity, 409, 'Container identity binding is missing.');
                $first = DB::table('pharmacy_packaging_proposals')->where('id', $identity->first_proposal_id)->where('execution_id', $executionId)->first();
                abort_unless($first && (int) $first->id <= (int) $p->id, 409, 'Container origin evidence is missing.');
                $original = json_decode($first->proposal, true, 512, JSON_THROW_ON_ERROR);
                abort_unless(hash_equals($first->proposal_hash, $digest->digest($original))
                    && in_array($container['identifier'], array_column($original['containers'] ?? [], 'identifier'), true), 409, 'Container origin evidence is inconsistent.');
                $identities[] = (array) $identity;
            }
            return ['packaging_proposal' => (array) $p, 'dating' => $dating, 'container_identities' => $identities,
                'packaging' => $checked, 'release_enabled' => false];
        });
    }
}
