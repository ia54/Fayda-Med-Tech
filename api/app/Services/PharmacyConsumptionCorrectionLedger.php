<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Synthetic correction ledger; no public route or release authorization. */
class PharmacyConsumptionCorrectionLedger
{
    public function apply(User $actor, int $proposalId, string $evidence): void
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        abort_unless($actor->role === 'pharmacist' && $actor->status === 'active' && $actor->organization_id, 403);
        validator(['evidence' => $evidence], ['evidence' => 'required|string|max:5000'])->validate();
        DB::transaction(function () use ($actor, $proposalId, $evidence) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $proposal = DB::table('pharmacy_consumption_corrections')->where('id', $proposalId)->lockForUpdate()->first();
            abort_unless($proposal, 404);
            $execution = DB::table('pharmacy_batch_executions')->where('id', $proposal->execution_id)->first();
            abort_unless($execution, 404);
            $batch = DB::table('pharmacy_batch_worksheets')->where('id', $execution->batch_id)->where('organization_id', $actor->organization_id)->first();
            abort_unless($batch, 404);
            app(PharmacyAccess::class)->requireLocation($actor, $batch->location_id);
            abort_if((int) $actor->id === (int) $proposal->created_by || (int) $actor->id === (int) $execution->created_by
                || DB::table('pharmacy_execution_addenda')->where('execution_id', $execution->id)->where('created_by', $actor->id)->exists(), 422, 'An independent pharmacist must review this correction.');
            if ($proposal->status !== 'pending') {
                abort_unless($proposal->status === 'applied' && (int) $proposal->reviewed_by === (int) $actor->id && $proposal->review_evidence === $evidence, 409, 'A different decision is already retained.');
                return;
            }
            $author = User::find($proposal->created_by);
            abort_unless($author && $author->status === 'active' && $author->role === 'pharmacist' && (int) $author->organization_id === (int) $actor->organization_id, 409, 'The proposal author is no longer authorized.');
            app(PharmacyAccess::class)->requireLocation($author, $batch->location_id);
            $digest = app(PharmacyCompoundingIncident::class);
            $snapshot = json_decode($proposal->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $projection = json_decode($proposal->proposal, true, 512, JSON_THROW_ON_ERROR);
            $support = json_decode($proposal->correction_evidence, true, 512, JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($proposal->source_hash, $digest->digest($snapshot)) && hash_equals($proposal->proposal_hash, $digest->digest($projection))
                && hash_equals($proposal->correction_evidence_hash, $digest->digest($support)), 409, 'Correction evidence integrity changed.');
            $current = app(PharmacyConsumptionContext::class)->inspect($actor, $execution->id, $proposal->ingredient_key);
            abort_unless(hash_equals($proposal->source_hash, $digest->digest($current)), 409, 'Receipt or affected batch evidence changed; reject and replace the proposal.');
            $lot = $current['receipt'];
            abort_if(DB::table('pharmacy_ingredient_counts')->where('ingredient_lot_id', $lot['id'])->where('status', 'pending')->exists(), 409, 'Resolve the pending physical count first.');
            abort_if(DB::table('pharmacy_compounding_incident_lines as l')->join('pharmacy_compounding_incidents as i', 'i.id', '=', 'l.incident_id')
                ->where('l.ingredient_lot_id', $lot['id'])->where('i.status', '<>', 'reconciled')->exists(), 409, 'Resolve existing ingredient incidents first.');
            $source = ['original_consumed' => $current['original_ingredient']['quantity'], 'accounted_consumed' => $current['accounted_consumed'],
                'on_hand' => $lot['on_hand'], 'reserved' => $lot['reserved'], 'unit' => $lot['quantity_unit']];
            $checked = app(PharmacyConsumptionCorrection::class)->project($source, ['corrected_consumed' => $projection['corrected_consumed'],
                'observed_on_hand' => $projection['corrected_on_hand'], 'unit' => $projection['unit']] + $support);
            abort_unless(hash_equals($digest->digest($checked), $digest->digest($projection)), 409, 'Correction accounting changed.');
            DB::table('pharmacy_ingredient_lots')->where('id', $lot['id'])->update(['on_hand' => $checked['corrected_on_hand'],
                'status' => $lot['status'] === 'recalled' ? 'recalled' : 'quarantined', 'version' => $lot['version'] + 1, 'updated_at' => now()]);
            foreach ($current['affected_batches'] as $affected) {
                if ($affected['execution']) {
                    $x = (array) $affected['execution'];
                    DB::table('pharmacy_batch_executions')->where('id', $x['id'])->update(['version' => $x['version'] + 1,
                        'status' => $x['status'] === 'rejected' ? 'rejected' : 'quarantined', 'updated_at' => now()]);
                }
            }
            DB::table('pharmacy_ingredient_events')->insert(['ingredient_lot_id' => $lot['id'], 'batch_id' => $batch->id, 'actor_id' => $actor->id,
                'action' => 'consumption_correction_applied', 'quantity' => $checked['stock_change_quantity'],
                'details' => json_encode(['proposal_id' => $proposalId, 'direction' => $checked['stock_direction'], 'release_enabled' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            DB::table('pharmacy_consumption_corrections')->where('id', $proposalId)->update(['status' => 'applied',
                'reviewed_by' => $actor->id, 'review_evidence' => $evidence, 'reviewed_at' => now()]);
            DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch->formulation_id, 'batch_id' => $batch->id,
                'actor_id' => $actor->id, 'action' => 'consumption_correction_applied',
                'details' => json_encode(['proposal_id' => $proposalId, 'execution_id' => $execution->id, 'affected_batch_ids' => array_column($current['affected_batches'], 'batch_id'),
                    'affected_execution_ids' => array_values(array_filter(array_map(fn ($b) => $b['execution'] ? (int) ((array) $b['execution'])['id'] : null, $current['affected_batches']))), 'stock_adjusted' => true, 'release_enabled' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);
        });
    }

    public function reject(User $actor, int $proposalId, string $evidence): void
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        abort_unless($actor->role === 'pharmacist' && $actor->status === 'active' && $actor->organization_id, 403);
        validator(['evidence' => $evidence], ['evidence' => 'required|string|max:5000'])->validate();
        DB::transaction(function () use ($actor, $proposalId, $evidence) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $proposal = DB::table('pharmacy_consumption_corrections')->where('id', $proposalId)->lockForUpdate()->first();
            abort_unless($proposal, 404);
            $execution = DB::table('pharmacy_batch_executions')->where('id', $proposal->execution_id)->first();
            abort_unless($execution, 404);
            $batch = DB::table('pharmacy_batch_worksheets')->where('id', $execution->batch_id)->where('organization_id', $actor->organization_id)->first();
            abort_unless($batch, 404);
            app(PharmacyAccess::class)->requireLocation($actor, $batch->location_id);
            abort_if((int) $actor->id === (int) $proposal->created_by || (int) $actor->id === (int) $execution->created_by
                || DB::table('pharmacy_execution_addenda')->where('execution_id', $execution->id)->where('created_by', $actor->id)->exists(), 422, 'An independent pharmacist must review this correction.');
            if ($proposal->status !== 'pending') {
                abort_unless($proposal->status === 'rejected' && (int) $proposal->reviewed_by === (int) $actor->id && $proposal->review_evidence === $evidence, 409, 'A different decision is already retained.');
                return;
            }
            // Rejection remains possible after source evidence changes; it never applies the projection.
            DB::table('pharmacy_consumption_corrections')->where('id', $proposalId)->update(['status' => 'rejected',
                'reviewed_by' => $actor->id, 'review_evidence' => $evidence, 'reviewed_at' => now()]);
            DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch->formulation_id, 'batch_id' => $batch->id,
                'actor_id' => $actor->id, 'action' => 'consumption_correction_rejected',
                'details' => json_encode(['proposal_id' => $proposalId, 'execution_id' => $execution->id, 'stock_adjusted' => false, 'release_enabled' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);
        });
    }

    public function retain(User $actor, int $executionId, string $ingredientKey, array $data): int
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        abort_unless($actor->role === 'pharmacist' && $actor->status === 'active' && $actor->organization_id, 403);
        $rules = ['request_id' => 'required|uuid', 'version' => 'required|integer|min:1', 'receipt_version' => 'required|integer|min:1',
            'unit' => 'required|in:mg,g,mL,each,capsule,tablet'];
        foreach (['corrected_consumed', 'observed_on_hand'] as $field) { $rules[$field] = 'required|numeric|min:0|max:999999.999|decimal:0,3'; }
        foreach (['reason', 'measurement_evidence', 'source_evidence', 'receipt_count_evidence'] as $field) { $rules[$field] = 'required|string|max:5000'; }
        $data = validator($data, $rules)->validate();
        return DB::transaction(function () use ($actor, $executionId, $ingredientKey, $data) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $snapshot = app(PharmacyConsumptionContext::class)->inspect($actor, $executionId, $ingredientKey);
            $digest = app(PharmacyCompoundingIncident::class);
            $hash = $digest->digest(['ingredient_key' => $ingredientKey, 'data' => $data]);
            $old = DB::table('pharmacy_consumption_corrections')->where('execution_id', $executionId)->where('request_id', $data['request_id'])->first();
            if ($old) {
                abort_unless((int) $old->created_by === (int) $actor->id && hash_equals($old->request_hash, $hash), 409, 'This identifier belongs to a different correction.');
                return (int) $old->id;
            }
            $execution = $snapshot['execution']; $receipt = $snapshot['receipt']; $batch = $snapshot['batch'];
            abort_unless((int) $execution['version'] === $data['version'] && (int) $receipt['version'] === $data['receipt_version'], 409, 'Execution or receipt changed. Refresh the correction evidence.');
            abort_if(DB::table('pharmacy_consumption_corrections')->where('ingredient_lot_id', $receipt['id'])->where('status', 'pending')->exists(), 409, 'A correction for this receipt already awaits review.');
            $source = ['original_consumed' => $snapshot['original_ingredient']['quantity'], 'accounted_consumed' => $snapshot['accounted_consumed'],
                'on_hand' => $receipt['on_hand'], 'reserved' => $receipt['reserved'], 'unit' => $receipt['quantity_unit']];
            $projection = app(PharmacyConsumptionCorrection::class)->project($source, $data);
            $evidence = array_intersect_key($data, array_flip(['reason', 'measurement_evidence', 'source_evidence', 'receipt_count_evidence']));
            $id = DB::table('pharmacy_consumption_corrections')->insertGetId([
                'execution_id' => $executionId, 'ingredient_lot_id' => $receipt['id'], 'ingredient_key' => $ingredientKey,
                'created_by' => $actor->id, 'request_id' => $data['request_id'], 'request_hash' => $hash,
                'execution_version' => $execution['version'], 'source_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'source_hash' => $digest->digest($snapshot),
                'proposal' => json_encode($projection, JSON_THROW_ON_ERROR), 'proposal_hash' => $digest->digest($projection),
                'correction_evidence' => json_encode($evidence, JSON_THROW_ON_ERROR), 'correction_evidence_hash' => $digest->digest($evidence), 'created_at' => now(),
            ]);
            DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch['formulation_id'], 'batch_id' => $batch['id'], 'actor_id' => $actor->id,
                'action' => 'consumption_correction_proposed', 'details' => json_encode(['proposal_id' => $id, 'execution_id' => $executionId,
                    'ingredient_lot_id' => $receipt['id'], 'stock_adjusted' => false, 'release_enabled' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            return (int) $id;
        });
    }
}
