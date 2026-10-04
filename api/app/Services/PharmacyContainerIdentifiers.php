<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Permanent organization-scoped identifiers across initial packaging and repackaging. */
class PharmacyContainerIdentifiers
{
    public function reserveInitial(object $identity): void
    {
        $this->lock((int) $identity->organization_id);
        $prior = $this->find((int) $identity->organization_id, $identity->identifier);
        if ($prior) {
            $this->assertInitial($identity);
            return;
        }
        DB::table('pharmacy_container_identifier_reservations')->insert(['organization_id' => $identity->organization_id,
            'execution_id' => $identity->execution_id, 'identifier' => $identity->identifier, 'initial_identity_id' => $identity->id,
            'repackaging_id' => null, 'created_at' => $identity->created_at]);
    }

    public function assertInitial(object $identity): void
    {
        $row = $this->find((int) $identity->organization_id, $identity->identifier);
        abort_unless($row && (int) $row->execution_id === (int) $identity->execution_id
            && (int) $row->initial_identity_id === (int) $identity->id && $row->repackaging_id === null,
            409, 'Container identifier reservation does not match its original packaging.');
    }

    public function reserveRepackaging(int $organizationId, int $executionId, int $proposalId): void
    {
        $this->lock($organizationId);
        $proposal = DB::table('pharmacy_container_repackaging')->where('id', $proposalId)->where('execution_id', $executionId)->first();
        $execution = DB::table('pharmacy_batch_executions')->find($executionId);
        $batch = $execution ? DB::table('pharmacy_batch_worksheets')->where('id', $execution->batch_id)->where('organization_id', $organizationId)->first() : null;
        abort_unless($proposal && $proposal->status === 'pending' && $batch, 409, 'Container reservation requires a retained repackaging proposal in this organization.');
        $projection = json_decode($proposal->proposal, true, 512, JSON_THROW_ON_ERROR);
        abort_unless(hash_equals($proposal->proposal_hash, app(PharmacyCompoundingIncident::class)->digest($projection))
            && is_array($projection['containers'] ?? null) && array_is_list($projection['containers']), 409, 'Repackaging container evidence integrity failed.');
        $identifiers = [];
        foreach ($projection['containers'] as $container) {
            abort_unless(is_array($container) && is_bool($container['new_identity'] ?? null), 409, 'Repackaging identity origin is missing.');
            if ($container['new_identity']) { $identifiers[] = $container['identifier'] ?? null; }
        }
        abort_unless(array_is_list($identifiers) && count($identifiers) <= 100 && count(array_unique($identifiers, SORT_REGULAR)) === count($identifiers), 422);
        foreach ($identifiers as $identifier) {
            abort_unless(is_string($identifier) && preg_match('/^[A-Z0-9][A-Z0-9_-]{0,63}$/D', $identifier), 422);
            $prior = $this->find($organizationId, $identifier);
            if ($prior) {
                abort_unless((int) $prior->execution_id === $executionId && $prior->initial_identity_id === null
                    && (int) $prior->repackaging_id === $proposalId, 409, 'Container identifier is permanently reserved by earlier packaging evidence.');
                continue;
            }
            // Also fail on missing registry backfill instead of claiming an existing original identifier.
            abort_if(DB::table('pharmacy_container_identities')->where('organization_id', $organizationId)->where('identifier', $identifier)->exists(), 409, 'Original container identity is missing its reservation.');
            DB::table('pharmacy_container_identifier_reservations')->insert(['organization_id' => $organizationId,
                'execution_id' => $executionId, 'identifier' => $identifier, 'initial_identity_id' => null,
                'repackaging_id' => $proposalId, 'created_at' => now()]);
        }
    }

    private function find(int $organizationId, string $identifier): ?object
    {
        return DB::table('pharmacy_container_identifier_reservations')->where('organization_id', $organizationId)->where('identifier', $identifier)->first();
    }

    private function lock(int $organizationId): void
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        abort_unless(DB::connection()->transactionLevel() > 0, 409, 'Container reservations must share their proposal transaction.');
        abort_unless(DB::table('organizations')->where('id', $organizationId)->lockForUpdate()->first(), 404);
    }
}
