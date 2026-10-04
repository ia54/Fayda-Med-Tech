<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Container findings share one atomic disposition with the existing output ledger. */
class PharmacyContainerCustodyLedger
{
    public function established(int $executionId): bool
    {
        return DB::table('pharmacy_container_custody_records as c')
            ->join('pharmacy_execution_custody_proposals as o', 'o.id', '=', 'c.output_proposal_id')
            ->where('c.execution_id', $executionId)->whereIn('o.status', ['pending', 'applied'])->exists();
    }

    public function context(User $actor, int $executionId): array
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
            $aggregate = app(PharmacyExecutionCustodyLedger::class)->summary($actor, $executionId);
            $rows = DB::table('pharmacy_container_custody_records as c')
                ->join('pharmacy_execution_custody_proposals as o', 'o.id', '=', 'c.output_proposal_id')
                ->where('c.execution_id', $executionId)->where('o.status', 'applied')->orderBy('o.execution_version')->select('c.*')->get();
            $digest = app(PharmacyCompoundingIncident::class);
            $anchor = null; $balance = null; $previous = null;
            foreach ($rows as $row) {
                [$source, $projection] = $this->evidence($row);
                if ($anchor === null) {
                    $anchor = $source['packaging_context'];
                    $balance = $anchor['packaging'];
                }
                abort_unless($digest->digest($source['packaging_context']) === $digest->digest($anchor)
                    && $source['previous_record_id'] === $previous
                    && $digest->digest($source['container_balance']) === $digest->digest($balance), 409, 'Container custody ancestry is inconsistent.');
                $checked = app(PharmacyContainerCustody::class)->project($balance, $this->input($projection));
                abort_unless(hash_equals($row->proposal_hash, $digest->digest($checked)) && $checked['accounting_complete'], 409, 'Applied container accounting is inconsistent.');
                $output = DB::table('pharmacy_execution_custody_proposals')->find($row->output_proposal_id);
                $this->matchOutput($output, $row, $checked);
                $balance = $this->after($balance, $checked); $previous = (int) $row->id;
            }
            if ($anchor === null) {
                $anchor = app(PharmacyPackagingCustodyAnchor::class)->inspect($actor, $executionId);
                $balance = $anchor['packaging'];
            } else {
                // Established physical custody remains recordable after expiry or clinical holds.
                // Its original packaging evidence is retained; it is never silently replaced.
                $p = DB::table('pharmacy_packaging_proposals')->find($anchor['packaging_proposal']['id']);
                abort_unless($p && $digest->digest((array) $p) === $digest->digest($anchor['packaging_proposal']), 409, 'Established packaging evidence changed.');
                foreach ($anchor['container_identities'] as $identity) {
                    $current = DB::table('pharmacy_container_identities')->find($identity['id']);
                    abort_unless($current && $digest->digest((array) $current) === $digest->digest($identity), 409, 'Established container identity changed.');
                }
            }
            foreach (['recorded_yield', 'previously_disposed', 'held_output', 'unit'] as $key) {
                $matches = $key === 'unit' ? $balance[$key] === $aggregate[$key]
                    : PharmacyStock::milli($balance[$key]) === PharmacyStock::milli($aggregate[$key]);
                abort_unless($matches, 409, 'Container and aggregate output balances diverged.');
            }
            return ['execution' => (array) $execution, 'batch' => (array) $batch,
                'addenda' => DB::table('pharmacy_execution_addenda')->where('execution_id', $executionId)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
                'packaging_context' => $anchor, 'previous_record_id' => $previous, 'container_balance' => $balance];
        });
    }

    public function retain(User $actor, int $executionId, array $input): int
    {
        $d = validator($input, ['request_id' => 'required|uuid', 'source_hash' => 'required|string|regex:/^[a-f0-9]{64}$/', 'decision' => 'required|array'])->validate();
        // The output ledger owns authorization, locking, idempotency and the single audit/decision.
        return app(PharmacyExecutionCustodyLedger::class)->retainContainers($actor, $executionId, $d);
    }

    public function prepare(User $actor, int $executionId, array $input): array
    {
        $source = $this->context($actor, $executionId);
        abort_unless(hash_equals($input['source_hash'], app(PharmacyCompoundingIncident::class)->digest($source)), 409, 'Container custody source changed.');
        $projection = app(PharmacyContainerCustody::class)->project($source['container_balance'], $input['decision']);
        return [$source, $projection];
    }

    public function record(int $executionId, int $outputId, array $source, array $projection): void
    {
        $digest = app(PharmacyCompoundingIncident::class);
        DB::table('pharmacy_container_custody_records')->insert(['execution_id' => $executionId, 'output_proposal_id' => $outputId,
            'packaging_proposal_id' => $source['packaging_context']['packaging_proposal']['id'],
            'source_snapshot' => json_encode($source, JSON_THROW_ON_ERROR), 'source_hash' => $digest->digest($source),
            'proposal' => json_encode($projection, JSON_THROW_ON_ERROR), 'proposal_hash' => $digest->digest($projection), 'created_at' => now()]);
    }

    public function validateApplication(User $actor, object $output): void
    {
        $row = DB::table('pharmacy_container_custody_records')->where('output_proposal_id', $output->id)->first();
        if (! $row) {
            abort_if($this->established($output->execution_id), 409, 'Use container custody to reconcile established container balances.');
            return;
        }
        [$source, $projection] = $this->evidence($row);
        $digest = app(PharmacyCompoundingIncident::class);
        $current = $this->context($actor, $output->execution_id);
        abort_unless(hash_equals($row->source_hash, $digest->digest($current)), 409, 'Container custody source changed; reject and replace.');
        $checked = app(PharmacyContainerCustody::class)->project($source['container_balance'], $this->input($projection));
        abort_unless(hash_equals($row->proposal_hash, $digest->digest($checked)) && $checked['accounting_complete'], 422, 'Resolve unaccounted container quantities before applying disposition.');
        $this->matchOutput($output, $row, $checked);
    }

    private function evidence(object $row): array
    {
        $source = json_decode($row->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
        $projection = json_decode($row->proposal, true, 512, JSON_THROW_ON_ERROR);
        $digest = app(PharmacyCompoundingIncident::class);
        abort_unless(hash_equals($row->source_hash, $digest->digest($source)) && hash_equals($row->proposal_hash, $digest->digest($projection))
            && (int) $source['execution']['id'] === (int) $row->execution_id
            && (int) $source['packaging_context']['packaging_proposal']['id'] === (int) $row->packaging_proposal_id, 409, 'Container custody evidence integrity failed.');
        return [$source, $projection];
    }

    private function input(array $projection): array
    {
        $keys = array_flip(['retained_quarantined', 'disposed_output', 'unaccounted_output', 'evidence']);
        return ['unit' => $projection['unit'], 'evidence' => $projection['evidence'],
            'containers' => array_map(fn ($r) => ['identifier' => $r['identifier']] + array_intersect_key($r, $keys), $projection['containers']),
            'unpackaged' => array_intersect_key($projection['unpackaged'], $keys)];
    }

    private function matchOutput(object $output, object $row, array $projection): void
    {
        $p = json_decode($output->proposal, true, 512, JSON_THROW_ON_ERROR);
        $digest = app(PharmacyCompoundingIncident::class);
        $aggregateSource = json_decode($output->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
        $containerSource = json_decode($row->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
        abort_unless(hash_equals($output->source_hash, $digest->digest($aggregateSource))
            && (int) $output->execution_version === (int) $containerSource['execution']['version'], 409, 'Linked disposition source is inconsistent.');
        foreach (['execution', 'batch', 'addenda'] as $key) {
            abort_unless($digest->digest($aggregateSource[$key]) === $digest->digest($containerSource[$key]), 409, 'Container and aggregate evidence differ.');
        }
        $action = $output->status === 'applied' ? 'execution_custody_applied' : 'execution_custody_proposed';
        $actorId = $output->status === 'applied' ? $output->reviewed_by : $output->created_by;
        abort_unless(DB::table('pharmacy_compounding_events')->where('batch_id', $containerSource['batch']['id'])
            ->where('action', $action)->where('actor_id', $actorId)->where('details->proposal_id', $output->id)->exists(), 409, 'Container disposition audit is missing.');
        if ($output->status === 'applied') {
            abort_unless($output->reviewed_at && trim((string) $output->review_evidence) !== '', 409, 'Container disposition review evidence is missing.');
        }

        abort_unless((int) $output->execution_id === (int) $row->execution_id && hash_equals($output->proposal_hash, $digest->digest($p)), 409, 'Linked aggregate custody is inconsistent.');
        foreach (['recorded_yield', 'previously_disposed', 'disposed_output', 'total_disposed', 'retained_quarantined', 'unaccounted_output', 'unit', 'accounting_complete', 'ingredient_stock_delta', 'release_enabled'] as $key) {
            abort_unless($p[$key] === $projection[$key], 409, 'Container quantities do not match the linked aggregate disposition.');
        }
    }

    private function after(array $source, array $projection): array
    {
        $amounts = array_column($projection['containers'], 'retained_quarantined', 'identifier');
        foreach ($source['containers'] as &$container) { $container['quantity'] = $amounts[$container['identifier']]; }
        unset($container);
        $source['unpackaged_quantity'] = $projection['unpackaged']['retained_quarantined'];
        $source['held_output'] = $projection['retained_quarantined']; $source['previously_disposed'] = $projection['total_disposed'];
        $source['packaged_quantity'] = PharmacyStock::decimal(PharmacyStock::milli($source['held_output']) - PharmacyStock::milli($source['unpackaged_quantity']));
        return $source;
    }
}
