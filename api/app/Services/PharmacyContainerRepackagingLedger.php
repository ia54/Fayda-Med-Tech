<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Retained repackaging findings; one independent decision updates quarantined container history. */
class PharmacyContainerRepackagingLedger
{
    private function actor(User $actor): void
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        abort_unless($actor->role === 'pharmacist' && $actor->status === 'active' && $actor->organization_id, 403);
    }

    private function scope(User $actor, int $executionId): array
    {
        DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
        $execution = DB::table('pharmacy_batch_executions')->where('id', $executionId)->lockForUpdate()->first();
        abort_unless($execution, 404);
        $batch = DB::table('pharmacy_batch_worksheets')->where('id', $execution->batch_id)->where('organization_id', $actor->organization_id)->first();
        abort_unless($batch, 404);
        app(PharmacyAccess::class)->requireLocation($actor, $batch->location_id);
        return [$execution, $batch];
    }

    public function retain(User $actor, int $executionId, array $input): int
    {
        $this->actor($actor);
        $d = validator($input, ['request_id' => 'required|uuid', 'source_hash' => 'required|string|regex:/^[a-f0-9]{64}$/', 'decision' => 'required|array'])->validate();
        return DB::transaction(function () use ($actor, $executionId, $d) {
            [$execution, $batch] = $this->scope($actor, $executionId);
            $digest = app(PharmacyCompoundingIncident::class); $hash = $digest->digest($d);
            $prior = DB::table('pharmacy_container_repackaging')->where('execution_id', $executionId)->where('request_id', $d['request_id'])->first();
            if ($prior) {
                abort_unless((int) $prior->created_by === (int) $actor->id && hash_equals($prior->request_hash, $hash), 409, 'Request belongs to different repackaging findings.');
                return (int) $prior->id;
            }
            $this->noConflicts($executionId);
            $source = app(PharmacyContainerCustodyLedger::class)->context($actor, $executionId);
            abort_unless(hash_equals($d['source_hash'], $digest->digest($source)), 409, 'Container evidence changed before repackaging.');
            $projection = app(PharmacyContainerRepackaging::class)->project($source['container_balance'], $d['decision']);
            $id = DB::table('pharmacy_container_repackaging')->insertGetId(['execution_id' => $executionId, 'created_by' => $actor->id,
                'request_id' => $d['request_id'], 'request_hash' => $hash, 'execution_version' => $execution->version,
                'source_snapshot' => json_encode($source, JSON_THROW_ON_ERROR), 'source_hash' => $digest->digest($source),
                'proposal' => json_encode($projection, JSON_THROW_ON_ERROR), 'proposal_hash' => $digest->digest($projection), 'created_at' => now()]);
            app(PharmacyContainerIdentifiers::class)->reserveRepackaging($actor->organization_id, $executionId, $id);
            $this->event($actor, $batch, $id, 'proposed');
            return (int) $id;
        });
    }

    public function decide(User $actor, int $id, string $decision, string $evidence): void
    {
        $this->actor($actor);
        abort_unless(in_array($decision, ['applied', 'rejected'], true) && trim($evidence) !== '' && strlen($evidence) <= 5000, 422);
        DB::transaction(function () use ($actor, $id, $decision, $evidence) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $row = DB::table('pharmacy_container_repackaging')->where('id', $id)->lockForUpdate()->first();
            abort_unless($row, 404);
            [$execution, $batch] = $this->scope($actor, $row->execution_id);
            abort_if((int) $actor->id === (int) $row->created_by || (int) $actor->id === (int) $execution->created_by
                || DB::table('pharmacy_execution_addenda')->where('execution_id', $execution->id)->where('created_by', $actor->id)->exists(), 422, 'Repackaging requires a pharmacist independent of these findings and preparation.');
            if ($row->status !== 'pending') {
                abort_unless($row->status === $decision && (int) $row->reviewed_by === (int) $actor->id && $row->review_evidence === $evidence, 409, 'A different repackaging decision is already retained.');
                return;
            }
            if ($decision === 'applied') {
                $this->noConflicts($execution->id, $id);
                $author = User::find($row->created_by);
                abort_unless($author && $author->status === 'active' && $author->role === 'pharmacist' && (int) $author->organization_id === (int) $actor->organization_id, 409, 'Repackaging author is no longer authorized.');
                app(PharmacyAccess::class)->requireLocation($author, $batch->location_id);
                $current = app(PharmacyContainerCustodyLedger::class)->context($actor, $execution->id);
                abort_unless((int) $execution->version === (int) $row->execution_version
                    && hash_equals($row->source_hash, app(PharmacyCompoundingIncident::class)->digest($current)), 409, 'Repackaging source changed; reject and replace.');
                $this->checked($row);
                DB::table('pharmacy_batch_executions')->where('id', $execution->id)->update(['version' => $execution->version + 1,
                    'status' => $execution->status === 'rejected' ? 'rejected' : 'quarantined', 'updated_at' => now()]);
            }
            DB::table('pharmacy_container_repackaging')->where('id', $id)->update(['status' => $decision, 'reviewed_by' => $actor->id,
                'review_evidence' => $evidence, 'reviewed_at' => now()]);
            $this->event($actor, $batch, $id, $decision);
        });
    }

    /** Verify retained evidence without recursively loading current balances. */
    public function checked(object $row): array
    {
        $source = json_decode($row->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
        $projection = json_decode($row->proposal, true, 512, JSON_THROW_ON_ERROR);
        $digest = app(PharmacyCompoundingIncident::class);
        abort_unless(hash_equals($row->source_hash, $digest->digest($source)) && hash_equals($row->proposal_hash, $digest->digest($projection))
            && (int) $source['execution']['id'] === (int) $row->execution_id
            && (int) $source['execution']['batch_id'] === (int) $source['batch']['id']
            && (int) $source['execution']['version'] === (int) $row->execution_version, 409, 'Repackaging evidence integrity failed.');
        $checked = app(PharmacyContainerRepackaging::class)->project($source['container_balance'], $this->input($projection));
        abort_unless(hash_equals($row->proposal_hash, $digest->digest($checked)), 409, 'Repackaging projection is inconsistent.');
        foreach ($checked['containers'] as $container) {
            $reservation = DB::table('pharmacy_container_identifier_reservations')->where('organization_id', $source['batch']['organization_id'])
                ->where('execution_id', $row->execution_id)->where('identifier', $container['identifier'])->first();
            abort_unless($reservation, 409, 'Repackaging container origin is missing.');
            if ($container['new_identity']) {
                abort_unless($reservation->initial_identity_id === null && (int) $reservation->repackaging_id === (int) $row->id, 409, 'Repackaging container origin changed.');
            } else {
                abort_unless(($reservation->initial_identity_id !== null) !== ($reservation->repackaging_id !== null), 409, 'Container origin is ambiguous.');
            }
        }
        $applied = $row->status === 'applied';
        abort_unless(DB::table('pharmacy_compounding_events')->where('batch_id', $source['batch']['id'])
            ->where('action', $applied ? 'container_repackaging_applied' : 'container_repackaging_proposed')
            ->where('actor_id', $applied ? $row->reviewed_by : $row->created_by)->where('details->proposal_id', $row->id)->exists(), 409, 'Repackaging audit is missing.');
        if ($applied) {
            abort_unless($row->reviewed_at && trim((string) $row->review_evidence) !== ''
                && (int) $row->reviewed_by !== (int) $row->created_by && (int) $row->reviewed_by !== (int) $source['execution']['created_by']
                && ! in_array((int) $row->reviewed_by, array_map(fn ($a) => (int) $a['created_by'], $source['addenda']), true), 409, 'Independent repackaging review is missing.');
        }
        return [$source, $checked];
    }

    public function after(array $balance, array $projection): array
    {
        $balance['containers'] = array_map(fn ($r) => array_intersect_key($r, array_flip(['identifier', 'quantity', 'container_reference', 'storage_reference', 'status'])), $projection['containers']);
        $balance['unpackaged_quantity'] = $projection['unpackaged']['quantity'];
        $balance['packaged_quantity'] = PharmacyStock::decimal(PharmacyStock::milli($balance['held_output']) - PharmacyStock::milli($balance['unpackaged_quantity']));
        return $balance;
    }

    private function input(array $p): array
    {
        return ['unit' => $p['unit'], 'new_containers' => array_values(array_map(fn ($r) => array_intersect_key($r, array_flip(['identifier', 'container_reference', 'storage_reference'])), array_filter($p['containers'], fn ($r) => $r['new_identity']))),
            'transfers' => $p['transfers'], 'observed_containers' => array_map(fn ($r) => ['identifier' => $r['identifier'], 'quantity' => $r['quantity'], 'evidence' => $r['observation_evidence']], $p['containers']),
            'observed_unpackaged' => ['quantity' => $p['unpackaged']['quantity'], 'evidence' => $p['unpackaged']['observation_evidence']],
            'process_evidence' => $p['process_evidence'], 'reconciliation_evidence' => $p['reconciliation_evidence']];
    }

    private function noConflicts(int $executionId, ?int $ownId = null): void
    {
        $pending = DB::table('pharmacy_container_repackaging')->where('execution_id', $executionId)->where('status', 'pending');
        if ($ownId !== null) { $pending->where('id', '<>', $ownId); }
        abort_if($pending->exists()
            || DB::table('pharmacy_execution_custody_proposals')->where('execution_id', $executionId)->where('status', 'pending')->exists()
            || DB::table('pharmacy_yield_correction_proposals')->where('execution_id', $executionId)->where('status', 'pending')->exists(), 409, 'Resolve pending repackaging, custody or yield corrections first.');
    }

    private function event(User $actor, object $batch, int $id, string $decision): void
    {
        DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch->formulation_id, 'batch_id' => $batch->id,
            'actor_id' => $actor->id, 'action' => 'container_repackaging_'.$decision,
            'details' => json_encode(['proposal_id' => $id, 'release_enabled' => false, 'stock_adjusted' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
