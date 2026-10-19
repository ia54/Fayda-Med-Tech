<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Historical packaging provenance for custody findings, never current clinical eligibility. */
class PharmacyPackagingCustodyAnchor
{
    public function inspect(User $actor, int $executionId): array
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        abort_unless($actor->role === 'pharmacist' && $actor->status === 'active' && $actor->organization_id, 403);
        return DB::transaction(function () use ($actor, $executionId) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $execution = DB::table('pharmacy_batch_executions')->where('id', $executionId)->lockForUpdate()->first();
            abort_unless($execution, 404);
            $batch = DB::table('pharmacy_batch_worksheets')->where('id', $execution->batch_id)->where('organization_id', $actor->organization_id)->first();
            abort_unless($batch, 404);
            app(PharmacyAccess::class)->requireLocation($actor, $batch->location_id);
            $p = DB::table('pharmacy_packaging_proposals')->where('execution_id', $executionId)->orderByDesc('id')->first();
            abort_unless($p && $p->status === 'reviewed', 409, 'The latest packaging proposal must be independently reviewed. Earlier proposals cannot be reused.');
            $digest = app(PharmacyCompoundingIncident::class);
            $source = json_decode($p->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $proposal = json_decode($p->proposal, true, 512, JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($p->source_hash, $digest->digest($source)) && hash_equals($p->proposal_hash, $digest->digest($proposal)), 409, 'Packaging evidence integrity failed.');
            $dating = $source;
            abort_unless((int) $p->dating_proposal_id === (int) $dating['dating_proposal']['id']
                && (int) $dating['quality']['source']['execution']['id'] === $executionId
                && (int) $dating['quality']['source']['batch']['id'] === (int) $batch->id, 409, 'Historical packaging lineage is inconsistent.');
            abort_unless($p->reviewed_at && trim((string) $p->review_evidence) !== '' && (int) $p->reviewed_by !== (int) $p->created_by
                && (int) $p->reviewed_by !== (int) $execution->created_by
                && ! in_array((int) $p->reviewed_by, array_map(fn ($a) => (int) $a['created_by'], $dating['quality']['source']['addenda']), true), 409, 'Independent packaging review is required.');
            abort_unless(DB::table('pharmacy_compounding_events')->where('batch_id', $batch->id)->where('action', 'packaging_reviewed')
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
                app(PharmacyContainerIdentifiers::class)->assertInitial($identity);
                $first = DB::table('pharmacy_packaging_proposals')->where('id', $identity->first_proposal_id)->where('execution_id', $executionId)->first();
                abort_unless($first && (int) $first->id <= (int) $p->id, 409, 'Container origin evidence is missing.');
                $original = json_decode($first->proposal, true, 512, JSON_THROW_ON_ERROR);
                abort_unless(hash_equals($first->proposal_hash, $digest->digest($original))
                    && in_array($container['identifier'], array_column($original['containers'] ?? [], 'identifier'), true), 409, 'Container origin evidence is inconsistent.');
                $identities[] = (array) $identity;
            }
            return ['packaging_proposal' => (array) $p, 'dating' => $dating, 'container_identities' => $identities,
                'packaging' => $checked, 'historical_evidence_only' => true, 'release_enabled' => false];
        });
    }
}
