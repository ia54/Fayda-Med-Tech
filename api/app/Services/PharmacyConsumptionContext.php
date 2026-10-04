<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Scoped, read-only evidence for an ingredient correction; never authorizes application. */
class PharmacyConsumptionContext
{
    public function inspect(User $actor, int $executionId, string $ingredientKey): array
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        abort_unless($actor->role === 'pharmacist' && $actor->status === 'active' && $actor->organization_id, 403);
        return DB::transaction(function () use ($actor, $executionId, $ingredientKey) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $execution = DB::table('pharmacy_batch_executions')->where('id', $executionId)->lockForUpdate()->first();
            abort_unless($execution, 404);
            $batch = DB::table('pharmacy_batch_worksheets')->where('id', $execution->batch_id)->where('organization_id', $actor->organization_id)->first();
            abort_unless($batch, 404);
            app(PharmacyAccess::class)->requireLocation($actor, $batch->location_id);
            $allocation = DB::table('pharmacy_ingredient_allocations')->where('batch_id', $batch->id)->where('ingredient_key', $ingredientKey)->first();
            abort_unless($allocation && $allocation->status === 'consumed', 422, 'Select an ingredient consumed in this execution.');
            $lot = DB::table('pharmacy_ingredient_lots')->where('id', $allocation->ingredient_lot_id)->where('organization_id', $actor->organization_id)->where('location_id', $batch->location_id)->lockForUpdate()->first();
            abort_unless($lot, 404);
            $record = json_decode($execution->record, true, 512, JSON_THROW_ON_ERROR);
            $lines = array_values(array_filter($record['ingredients'] ?? [], fn ($line) => ($line['key'] ?? null) === $ingredientKey));
            abort_unless(count($lines) === 1 && ($lines[0]['unit'] ?? null) === $lot->quantity_unit
                && PharmacyStock::milli($lines[0]['quantity']) === PharmacyStock::milli($allocation->quantity), 409, 'Original ingredient evidence requires reconciliation.');
            $allocations = DB::table('pharmacy_ingredient_allocations')->where('ingredient_lot_id', $lot->id)->orderBy('id')->get();
            $reserved = 0;
            $affected = [];
            foreach ($allocations as $other) {
                $related = DB::table('pharmacy_batch_worksheets')->where('id', $other->batch_id)->first();
                abort_unless($related && (int) $related->organization_id === (int) $actor->organization_id && (int) $related->location_id === (int) $lot->location_id, 409, 'Ingredient allocation scope is inconsistent.');
                if ($other->status === 'reserved') { $reserved += PharmacyStock::milli($other->quantity); }
                $affected[$other->batch_id] = ['batch_id' => (int) $other->batch_id, 'version' => (int) $related->version,
                    'execution' => DB::table('pharmacy_batch_executions')->where('batch_id', $other->batch_id)->first()];
            }
            abort_unless($reserved === PharmacyStock::milli($lot->reserved), 409, 'Receipt reservations do not match retained allocations.');
            $accounted = PharmacyStock::decimal(PharmacyStock::milli($lines[0]['quantity']));
            $digest = app(PharmacyCompoundingIncident::class);
            $history = DB::table('pharmacy_consumption_corrections')->where('execution_id', $executionId)
                ->where('ingredient_key', $ingredientKey)->where('status', 'applied')->orderBy('execution_version')->get();
            $lastVersion = 0;
            foreach ($history as $prior) {
                $old = json_decode($prior->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
                $projection = json_decode($prior->proposal, true, 512, JSON_THROW_ON_ERROR);
                $evidence = json_decode($prior->correction_evidence, true, 512, JSON_THROW_ON_ERROR);
                abort_unless(hash_equals($prior->source_hash, $digest->digest($old)) && hash_equals($prior->proposal_hash, $digest->digest($projection))
                    && hash_equals($prior->correction_evidence_hash, $digest->digest($evidence))
                    && (int) $prior->ingredient_lot_id === (int) $lot->id && (int) ($old['execution']['id'] ?? 0) === $executionId
                    && (int) ($old['allocation']['id'] ?? 0) === (int) $allocation->id
                    && $prior->reviewed_by && (int) $prior->reviewed_by !== (int) $prior->created_by && (int) $prior->reviewed_by !== (int) $execution->created_by
                    && (int) $prior->execution_version > $lastVersion && (int) $prior->execution_version < (int) $execution->version
                    && ($old['accounted_consumed'] ?? null) === $accounted, 409, 'Prior consumption correction evidence is inconsistent.');
                $checked = app(PharmacyConsumptionCorrection::class)->project([
                    'original_consumed' => $lines[0]['quantity'], 'accounted_consumed' => $accounted,
                    'on_hand' => $old['receipt']['on_hand'], 'reserved' => $old['receipt']['reserved'], 'unit' => $lot->quantity_unit,
                ], ['corrected_consumed' => $projection['corrected_consumed'], 'observed_on_hand' => $projection['corrected_on_hand'], 'unit' => $projection['unit']] + $evidence);
                abort_unless(hash_equals($digest->digest($checked), $digest->digest($projection)), 409, 'Prior consumption accounting is inconsistent.');
                $accounted = $checked['corrected_consumed'];
                $lastVersion = (int) $prior->execution_version;
            }
            return ['execution' => (array) $execution, 'batch' => (array) $batch, 'allocation' => (array) $allocation,
                'accounted_consumed' => $accounted, 'applied_corrections' => $history->map(fn ($row) => (array) $row)->all(),
                'receipt' => (array) $lot, 'original_ingredient' => $lines[0],
                'receipt_allocations' => $allocations->map(fn ($row) => (array) $row)->all(), 'affected_batches' => array_values($affected),
                'receipt_events' => DB::table('pharmacy_ingredient_events')->where('ingredient_lot_id', $lot->id)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
                'application_enabled' => false, 'release_enabled' => false];
        });
    }
}
