<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Synthetic reviewed yield corrections. No release, ingredient movement or public route. */
class PharmacyYieldCorrectionLedger
{
    public function decide(User $actor, int $proposalId, string $decision, string $evidence): void
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        abort_unless($actor->role === 'pharmacist' && $actor->status === 'active' && $actor->organization_id, 403);
        validator(compact('decision', 'evidence'), ['decision' => 'required|in:applied,rejected', 'evidence' => 'required|string|max:5000'])->validate();
        DB::transaction(function () use ($actor, $proposalId, $decision, $evidence) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $proposal = DB::table('pharmacy_yield_correction_proposals')->where('id', $proposalId)->lockForUpdate()->first();
            abort_unless($proposal, 404);
            $execution = DB::table('pharmacy_batch_executions')->where('id', $proposal->execution_id)->lockForUpdate()->first();
            abort_unless($execution, 404);
            $batch = DB::table('pharmacy_batch_worksheets')->where('id', $execution->batch_id)->where('organization_id', $actor->organization_id)->first();
            abort_unless($batch, 404);
            app(PharmacyAccess::class)->requireLocation($actor, $batch->location_id);
            abort_if((int) $actor->id === (int) $proposal->created_by || (int) $actor->id === (int) $execution->created_by
                || DB::table('pharmacy_execution_addenda')->where('execution_id', $execution->id)->where('created_by', $actor->id)->exists(), 422, 'An independent pharmacist must review yield corrections.');
            if ($proposal->status !== 'pending') {
                abort_unless($proposal->status === $decision && (int) $proposal->reviewed_by === (int) $actor->id && $proposal->review_evidence === $evidence, 409, 'A different yield correction decision is already retained.');

                return;
            }
            if ($decision === 'applied') {
                abort_if(app(PharmacyContainerCustodyLedger::class)->established($execution->id), 409, 'Yield corrections require container-level reconciliation after custody is established.');
                $author = User::find($proposal->created_by);
                abort_unless($author && $author->role === 'pharmacist' && $author->status === 'active' && (int) $author->organization_id === (int) $actor->organization_id, 409, 'The proposal author is no longer authorized.');
                app(PharmacyAccess::class)->requireLocation($author, $batch->location_id);
                $digest = app(PharmacyCompoundingIncident::class);
                $snapshot = json_decode($proposal->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
                $projection = json_decode($proposal->proposal, true, 512, JSON_THROW_ON_ERROR);
                abort_unless(hash_equals($proposal->source_hash, $digest->digest($snapshot)) && hash_equals($proposal->proposal_hash, $digest->digest($projection)), 409, 'Yield correction evidence integrity changed.');
                $custody = app(PharmacyExecutionCustodyLedger::class)->summary($actor, $execution->id);
                $record = json_decode($execution->record, true, 512, JSON_THROW_ON_ERROR);
                $source = ['original_yield' => $record['yield_quantity'], 'accounted_yield' => $custody['recorded_yield'],
                    'previously_disposed' => $custody['previously_disposed'], 'held_output' => $custody['held_output'], 'unit' => $custody['unit']];
                $current = ['execution' => (array) $execution, 'batch' => (array) $batch, 'yield' => $source,
                    'addenda' => DB::table('pharmacy_execution_addenda')->where('execution_id', $execution->id)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()];
                abort_unless((int) $execution->version === (int) $proposal->execution_version && hash_equals($proposal->source_hash, $digest->digest($current)), 409, 'Execution evidence changed; reject and replace this proposal.');
                $correctionEvidence = json_decode($proposal->correction_evidence, true, 512, JSON_THROW_ON_ERROR);
                abort_unless(hash_equals($proposal->correction_evidence_hash, $digest->digest($correctionEvidence)), 409, 'Correction supporting evidence changed.');
                $checked = app(PharmacyYieldCorrection::class)->project($source, ['corrected_yield' => $projection['corrected_yield'],
                    'observed_held' => $projection['corrected_held_output'], 'unit' => $projection['unit']] + $correctionEvidence);
                abort_unless(hash_equals($digest->digest($checked), $digest->digest($projection)), 422, 'The correction does not match its retained evidence.');
                DB::table('pharmacy_batch_executions')->where('id', $execution->id)->update(['version' => $execution->version + 1,
                    'status' => $execution->status === 'rejected' ? 'rejected' : 'quarantined', 'updated_at' => now()]);
            }
            DB::table('pharmacy_yield_correction_proposals')->where('id', $proposalId)->update(['status' => $decision,
                'reviewed_by' => $actor->id, 'review_evidence' => $evidence, 'reviewed_at' => now()]);
            DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch->formulation_id, 'batch_id' => $batch->id,
                'actor_id' => $actor->id, 'action' => 'yield_correction_'.$decision,
                'details' => json_encode(['proposal_id' => $proposalId, 'execution_id' => $execution->id, 'ingredient_stock_adjusted' => false, 'release_enabled' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);
        });
    }

    public function retain(User $actor, int $executionId, array $data): int
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        abort_unless($actor->role === 'pharmacist' && $actor->status === 'active' && $actor->organization_id, 403);
        $rules = ['request_id' => 'required|uuid', 'version' => 'required|integer|min:1',
            'unit' => 'required|in:mg,g,mL,each,capsule,tablet',
            'reason' => 'required|string|max:5000', 'measurement_evidence' => 'required|string|max:5000',
            'source_evidence' => 'required|string|max:5000'];
        foreach (['corrected_yield', 'observed_held'] as $field) {
            $rules[$field] = 'required|numeric|min:0|max:999999.999|decimal:0,3';
        }
        $data = validator($data, $rules)->validate();

        return DB::transaction(function () use ($actor, $executionId, $data) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $execution = DB::table('pharmacy_batch_executions')->where('id', $executionId)->lockForUpdate()->first();
            abort_unless($execution, 404);
            $batch = DB::table('pharmacy_batch_worksheets')->where('id', $execution->batch_id)->where('organization_id', $actor->organization_id)->first();
            abort_unless($batch, 404);
            app(PharmacyAccess::class)->requireLocation($actor, $batch->location_id);
            $digest = app(PharmacyCompoundingIncident::class);
            $hash = $digest->digest($data);
            $old = DB::table('pharmacy_yield_correction_proposals')->where('execution_id', $executionId)->where('request_id', $data['request_id'])->first();
            if ($old) {
                abort_unless((int) $old->created_by === (int) $actor->id && hash_equals($old->request_hash, $hash), 409, 'This request identifier belongs to a different yield correction.');

                return (int) $old->id;
            }
            abort_if(app(PharmacyContainerCustodyLedger::class)->established($executionId), 409, 'Yield corrections require container-level reconciliation after custody is established.');
            abort_unless((int) $execution->version === $data['version'], 409, 'The execution evidence changed. Refresh before proposing a yield correction.');
            abort_if(DB::table('pharmacy_yield_correction_proposals')->where('execution_id', $executionId)->where('status', 'pending')->exists(), 409, 'An output yield correction is already awaiting review.');
            abort_if(DB::table('pharmacy_execution_custody_proposals')->where('execution_id', $executionId)->where('status', 'pending')->exists(), 409, 'Resolve pending output custody before proposing a yield correction.');
            $custody = app(PharmacyExecutionCustodyLedger::class)->summary($actor, $executionId);
            $record = json_decode($execution->record, true, 512, JSON_THROW_ON_ERROR);
            $source = ['original_yield' => $record['yield_quantity'], 'accounted_yield' => $custody['recorded_yield'],
                'previously_disposed' => $custody['previously_disposed'], 'held_output' => $custody['held_output'], 'unit' => $custody['unit']];
            $proposal = app(PharmacyYieldCorrection::class)->project($source, $data);
            $snapshot = ['execution' => (array) $execution, 'batch' => (array) $batch, 'yield' => $source,
                'addenda' => DB::table('pharmacy_execution_addenda')->where('execution_id', $executionId)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()];
            $id = DB::table('pharmacy_yield_correction_proposals')->insertGetId([
                'execution_id' => $executionId, 'created_by' => $actor->id, 'request_id' => $data['request_id'], 'request_hash' => $hash,
                'execution_version' => $execution->version, 'source_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'source_hash' => $digest->digest($snapshot), 'proposal' => json_encode($proposal, JSON_THROW_ON_ERROR),
                'proposal_hash' => $digest->digest($proposal),
                'correction_evidence_hash' => $digest->digest(array_intersect_key($data, array_flip(['reason', 'measurement_evidence', 'source_evidence']))), 'correction_evidence' => json_encode(array_intersect_key($data, array_flip(['reason', 'measurement_evidence', 'source_evidence'])), JSON_THROW_ON_ERROR), 'created_at' => now(),
            ]);
            DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch->formulation_id, 'batch_id' => $batch->id,
                'actor_id' => $actor->id, 'action' => 'yield_correction_proposed',
                'details' => json_encode(['proposal_id' => $id, 'execution_id' => $executionId, 'stock_adjusted' => false, 'release_enabled' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);

            return (int) $id;
        });
    }
}
