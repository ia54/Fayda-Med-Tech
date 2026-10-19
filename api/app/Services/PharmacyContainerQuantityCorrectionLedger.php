<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Per-container recounts share the aggregate yield decision, lock and audit transaction. */
class PharmacyContainerQuantityCorrectionLedger
{
    public function retain(User $actor, int $executionId, array $input): int
    {
        return app(PharmacyYieldCorrectionLedger::class)->retainContainers($actor, $executionId, $input);
    }

    public function prepare(User $actor, int $executionId, array $input): array
    {
        $source = app(PharmacyContainerCustodyLedger::class)->context($actor, $executionId);
        abort_unless(hash_equals($input['source_hash'], app(PharmacyCompoundingIncident::class)->digest($source)), 409, 'Container recount source changed.');
        return [$source, app(PharmacyContainerQuantityCorrection::class)->project($this->quantities($source), $input['decision'])];
    }

    public function record(int $executionId, int $yieldId, array $source, array $projection): void
    {
        $digest = app(PharmacyCompoundingIncident::class);
        DB::table('pharmacy_container_quantity_corrections')->insert(['execution_id' => $executionId, 'yield_proposal_id' => $yieldId,
            'packaging_proposal_id' => $source['packaging_context']['packaging_proposal']['id'],
            'source_snapshot' => json_encode($source, JSON_THROW_ON_ERROR), 'source_hash' => $digest->digest($source),
            'proposal' => json_encode($projection, JSON_THROW_ON_ERROR), 'proposal_hash' => $digest->digest($projection), 'created_at' => now()]);
    }

    public function validateApplication(User $actor, object $yield): ?array
    {
        $row = DB::table('pharmacy_container_quantity_corrections')->where('yield_proposal_id', $yield->id)->first();
        if (! $row) {
            abort_if(app(PharmacyContainerCustodyLedger::class)->established($yield->execution_id), 409, 'Yield corrections require container-level reconciliation after custody is established.');
            return null;
        }
        $current = app(PharmacyContainerCustodyLedger::class)->context($actor, $yield->execution_id);
        abort_unless(hash_equals($row->source_hash, app(PharmacyCompoundingIncident::class)->digest($current)), 409, 'Container recount source changed; reject and replace.');
        [, $projection] = $this->checked($row, $yield);
        return $this->aggregate($projection);
    }

    /** Independent replay, without recursively asking for current container or aggregate balances. */
    public function checked(object $row, object $yield): array
    {
        $digest = app(PharmacyCompoundingIncident::class);
        $source = json_decode($row->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
        $projection = json_decode($row->proposal, true, 512, JSON_THROW_ON_ERROR);
        $snapshot = json_decode($yield->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
        $aggregate = json_decode($yield->proposal, true, 512, JSON_THROW_ON_ERROR);
        $support = json_decode($yield->correction_evidence, true, 512, JSON_THROW_ON_ERROR);
        abort_unless(hash_equals($row->source_hash, $digest->digest($source)) && hash_equals($row->proposal_hash, $digest->digest($projection))
            && (int) $row->yield_proposal_id === (int) $yield->id && (int) $row->execution_id === (int) $yield->execution_id
            && (int) $source['execution']['id'] === (int) $row->execution_id
            && (int) $source['packaging_context']['packaging_proposal']['id'] === (int) $row->packaging_proposal_id
            && (int) $source['execution']['version'] === (int) $yield->execution_version
            && hash_equals($yield->source_hash, $digest->digest($snapshot)) && hash_equals($yield->proposal_hash, $digest->digest($aggregate))
            && hash_equals($yield->correction_evidence_hash, $digest->digest($support)), 409, 'Container recount evidence integrity failed.');
        foreach (['execution', 'batch', 'addenda'] as $key) {
            abort_unless($digest->digest($source[$key]) === $digest->digest($snapshot[$key]), 409, 'Container and yield correction sources differ.');
        }
        $quantities = $this->quantities($source);
        $expected = ['original_yield' => $quantities['original_yield'], 'accounted_yield' => $quantities['recorded_yield'],
            'previously_disposed' => $quantities['previously_disposed'], 'held_output' => $quantities['held_output'], 'unit' => $quantities['unit']];
        foreach ($expected as $key => $value) {
            $matches = $key === 'unit' ? $snapshot['yield'][$key] === $value
                : PharmacyStock::milli($snapshot['yield'][$key]) === PharmacyStock::milli($value);
            abort_unless($matches, 409, 'Container and yield correction quantities differ.');
        }
        $checked = app(PharmacyContainerQuantityCorrection::class)->project($quantities, $this->input($projection));
        abort_unless(hash_equals($row->proposal_hash, $digest->digest($checked))
            && $digest->digest($this->aggregate($checked)) === $digest->digest($aggregate)
            && $digest->digest($support) === $digest->digest(array_intersect_key($checked, array_flip(['reason', 'measurement_evidence', 'source_evidence']))), 409, 'Container recount projection is inconsistent.');
        $applied = $yield->status === 'applied';
        $actorId = $applied ? $yield->reviewed_by : $yield->created_by;
        abort_unless(DB::table('pharmacy_compounding_events')->where('batch_id', $source['batch']['id'])
            ->where('action', $applied ? 'yield_correction_applied' : 'yield_correction_proposed')
            ->where('actor_id', $actorId)->where('details->proposal_id', $yield->id)->exists(), 409, 'Container recount audit is missing.');
        if ($applied) {
            abort_unless($yield->reviewed_at && trim((string) $yield->review_evidence) !== ''
                && (int) $yield->reviewed_by !== (int) $yield->created_by
                && (int) $yield->reviewed_by !== (int) $source['execution']['created_by']
                && ! in_array((int) $yield->reviewed_by, array_map(fn ($a) => (int) $a['created_by'], $source['addenda']), true), 409, 'Independent container recount review is missing.');
        }
        return [$source, $checked];
    }

    public function aggregate(array $projection): array
    {
        return array_intersect_key($projection, array_flip(['original_yield', 'previous_accounted_yield', 'corrected_yield', 'previously_disposed',
            'previous_held_output', 'corrected_held_output', 'change_direction', 'change_quantity', 'unit', 'ingredient_stock_delta', 'release_enabled']));
    }

    public function after(array $balance, array $projection): array
    {
        $amounts = array_column($projection['containers'], 'observed_quantity', 'identifier');
        foreach ($balance['containers'] as &$container) { $container['quantity'] = $amounts[$container['identifier']]; }
        unset($container);
        $balance['unpackaged_quantity'] = $projection['unpackaged']['observed_quantity'];
        $balance['held_output'] = $projection['corrected_held_output']; $balance['recorded_yield'] = $projection['corrected_yield'];
        $balance['packaged_quantity'] = PharmacyStock::decimal(PharmacyStock::milli($balance['held_output']) - PharmacyStock::milli($balance['unpackaged_quantity']));
        return $balance;
    }

    private function quantities(array $source): array
    {
        $record = json_decode($source['execution']['record'], true, 512, JSON_THROW_ON_ERROR);
        return ['original_yield' => $record['yield_quantity']] + $source['container_balance'];
    }

    private function input(array $projection): array
    {
        $keys = array_flip(['observed_quantity', 'reason', 'measurement_evidence', 'source_evidence']);
        return array_intersect_key($projection, array_flip(['unit', 'corrected_yield', 'reason', 'measurement_evidence', 'source_evidence']))
            + ['containers' => array_map(fn ($r) => ['identifier' => $r['identifier']] + array_intersect_key($r, $keys), $projection['containers']),
                'unpackaged' => array_intersect_key($projection['unpackaged'], $keys)];
    }
}
