<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Fresh documentary input for container suitability review; never release authority. */
class PharmacyCurrentContainerReviewContext
{
    public function inspect(User $actor, int $executionId): array
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        abort_unless($actor->role === 'pharmacist' && $actor->status === 'active' && $actor->organization_id, 403);
        return DB::transaction(function () use ($actor, $executionId) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $dating = app(PharmacyReviewedBeyondUseContext::class)->inspect($actor, $executionId);
            $custody = app(PharmacyContainerCustodyLedger::class)->context($actor, $executionId);
            $digest = app(PharmacyCompoundingIncident::class);
            abort_unless($digest->digest($dating['quality']['source']['execution']) === $digest->digest($custody['execution']), 409, 'Container and dating execution evidence differ.');
            $balance = $custody['container_balance'];
            foreach (['recorded_yield', 'previously_disposed', 'held_output'] as $key) {
                abort_unless(PharmacyStock::milli($balance[$key]) === PharmacyStock::milli($dating['output_balance'][$key]), 409, 'Container and dating quantities differ.');
            }
            abort_unless($balance['unit'] === $dating['output_balance']['unit'], 409, 'Container and dating units differ.');
            $containers = []; $empty = [];
            foreach ($balance['containers'] as $container) {
                if (PharmacyStock::milli($container['quantity']) === 0) { $empty[] = $container; }
                else { $containers[] = $container; }
            }
            return ['dating' => $dating, 'custody' => $custody, 'containers_for_review' => $containers,
                'empty_container_history' => $empty, 'unpackaged_quantity' => $balance['unpackaged_quantity'],
                'container_suitability_verified' => false, 'clinical_limits_verified' => false,
                'release_enabled' => false];
        });
    }
}
