<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Current source evidence for a synthetic quality record; never grants product release. */
class PharmacyBatchQualityContext
{
    public function inspect(User $actor, int $executionId, int $protocolId): array
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        abort_unless($actor->role === 'pharmacist' && $actor->status === 'active' && $actor->organization_id, 403);
        return DB::transaction(function () use ($actor, $executionId, $protocolId) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $execution = DB::table('pharmacy_batch_executions')->where('id', $executionId)->lockForUpdate()->first();
            abort_unless($execution, 404);
            $batch = DB::table('pharmacy_batch_worksheets')->where('id', $execution->batch_id)->where('organization_id', $actor->organization_id)->first();
            abort_unless($batch, 404);
            app(PharmacyAccess::class)->requireLocation($actor, $batch->location_id);
            $protocol = DB::table('pharmacy_quality_protocols')->where('id', $protocolId)->where('organization_id', $actor->organization_id)
                ->where('location_id', $batch->location_id)->where('formulation_id', $batch->formulation_id)->first();
            abort_unless($protocol, 404);
            abort_unless($protocol->status === 'reviewed', 422, 'Use a currently reviewed quality protocol for this location and formulation.');
            $digest = app(PharmacyCompoundingIncident::class);
            $record = json_decode($protocol->record, true, 512, JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($protocol->record_hash, $digest->digest($record)), 409, 'Quality protocol integrity failed.');
            $formula = DB::table('pharmacy_formulations')->where('id', $batch->formulation_id)->where('organization_id', $actor->organization_id)->first();
            abort_unless($formula && $formula->status === 'reviewed' && hash_equals($protocol->formulation_hash, $digest->digest((array) $formula)), 409, 'Formulation evidence changed.');
            $review = DB::table('pharmacy_quality_protocol_events')->where('protocol_id', $protocolId)->where('version', $protocol->version)->where('action', 'reviewed')->first();
            abort_unless($review && (int) $review->actor_id !== (int) $protocol->created_by && trim($review->evidence) !== '', 409, 'Independent protocol review evidence is missing.');
            $allocations = DB::table('pharmacy_ingredient_allocations')->where('batch_id', $batch->id)->orderBy('id')->get();
            $ingredients = [];
            foreach ($allocations as $allocation) {
                abort_unless($allocation->status === 'consumed', 409, 'Execution ingredient history requires reconciliation.');
                $ingredients[] = app(PharmacyConsumptionContext::class)->inspect($actor, $executionId, $allocation->ingredient_key);
            }
            $executionRecord = json_decode($execution->record, true, 512, JSON_THROW_ON_ERROR);
            $keys = array_column($executionRecord['ingredients'] ?? [], 'key');
            $allocationKeys = $allocations->pluck('ingredient_key')->all();
            sort($keys); sort($allocationKeys);
            abort_unless(count($keys) > 0 && $keys === $allocationKeys, 409, 'Execution ingredient lineage is incomplete.');
            $rx = DB::table('pharmacy_prescriptions')->where('id', $batch->prescription_id)->where('organization_id', $actor->organization_id)->where('location_id', $batch->location_id)->first();
            abort_unless($rx, 409, 'Prescription scope requires reconciliation.');
            return ['execution' => (array) $execution, 'batch' => (array) $batch, 'prescription' => (array) $rx,
                'formulation' => (array) $formula, 'protocol' => (array) $protocol, 'protocol_record' => $record,
                'protocol_review' => (array) $review,
                'addenda' => DB::table('pharmacy_execution_addenda')->where('execution_id', $executionId)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
                'output_custody' => app(PharmacyExecutionCustodyLedger::class)->summary($actor, $executionId),
                'ingredients' => $ingredients, 'batch_hold' => $digest->holdsBatch($actor->organization_id, $batch->id),
                'pending_yield_corrections' => DB::table('pharmacy_yield_correction_proposals')->where('execution_id', $executionId)->where('status', 'pending')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
                'pending_output_custody' => DB::table('pharmacy_execution_custody_proposals')->where('execution_id', $executionId)->where('status', 'pending')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
                'release_enabled' => false];
        });
    }
}
