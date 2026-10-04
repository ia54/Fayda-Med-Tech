<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Append-only synthetic proofs. Saving a proof never authorizes printing for dispensing. */
class PharmacyFinishedContainerLabelLedger
{
    public function current(User $actor, object $proof): array
    {
        $this->checked($proof);
        $current = app(PharmacyFinishedContainerLabelContext::class)->inspect($actor, $proof->execution_id, $proof->container_identifier);
        $latest = DB::table('pharmacy_container_label_proofs')->where('execution_id', $proof->execution_id)->where('container_identifier', $proof->container_identifier)->max('revision');
        abort_unless((int) $latest === (int) $proof->revision && hash_equals($proof->source_hash, app(PharmacyCompoundingIncident::class)->digest($current)), 409, 'Proof is superseded or its source changed. Retain a new proof before use.');
        return $current;
    }

    public function checked(object $proof): array
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        $source = json_decode($proof->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
        $digest = app(PharmacyCompoundingIncident::class);
        abort_unless(hash_equals($proof->source_hash, $digest->digest($source))
            && hash_equals($proof->document_hash, hash('sha256', $proof->document)), 409, 'Retained proof integrity failed.');
        abort_unless((int) $source['reviewed_suitability']['current_container_evidence']['custody']['execution']['id'] === (int) $proof->execution_id
            && $source['container']['identifier'] === $proof->container_identifier, 409, 'Retained proof source binding differs.');
        abort_unless(($source['synthetic_only'] ?? null) === true && ($source['release_enabled'] ?? null) === false, 409, 'Retained proof authority is invalid.');
        $batch = $source['reviewed_suitability']['current_container_evidence']['custody']['batch'];
        abort_unless(DB::table('pharmacy_compounding_events')->where('batch_id', $batch['id'])->where('actor_id', $proof->created_by)
            ->where('action', 'container_label_proof_retained')->where('details->label_id', $proof->id)->exists(), 409, 'Retained proof audit is missing.');
        return $source;
    }

    public function retain(User $actor, int $executionId, array $input): int
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        abort_unless($actor->role === 'pharmacist' && $actor->status === 'active' && $actor->organization_id, 403);
        $d = validator($input, ['request_id' => 'required|uuid', 'identifier' => 'required|string|max:64',
            'previous_id' => 'present|nullable|integer|min:1', 'source_hash' => 'required|string|regex:/^[a-f0-9]{64}$/'])->validate();
        return DB::transaction(function () use ($actor, $executionId, $d) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $execution = DB::table('pharmacy_batch_executions')->where('id', $executionId)->lockForUpdate()->first();
            $batch = $execution ? DB::table('pharmacy_batch_worksheets')->where('id', $execution->batch_id)->where('organization_id', $actor->organization_id)->first() : null;
            abort_unless($batch, 404);
            app(PharmacyAccess::class)->requireLocation($actor, $batch->location_id);
            $digest = app(PharmacyCompoundingIncident::class);$requestHash = $digest->digest($d);
            $retry = DB::table('pharmacy_container_label_proofs')->where('execution_id', $executionId)->where('request_id', $d['request_id'])->first();
            if ($retry) {
                abort_unless((int) $retry->created_by === (int) $actor->id && hash_equals($retry->request_hash, $requestHash), 409, 'Request belongs to different label evidence.');
                return (int) $retry->id;
            }
            $last = DB::table('pharmacy_container_label_proofs')->where('execution_id', $executionId)->where('container_identifier', $d['identifier'])->orderByDesc('revision')->first();
            abort_unless(($last ? (int) $last->id : null) === ($d['previous_id'] === null ? null : (int) $d['previous_id']), 409, 'Container proof history changed.');
            $source = app(PharmacyFinishedContainerLabelContext::class)->inspect($actor, $executionId, $d['identifier']);
            abort_unless(hash_equals($d['source_hash'], $digest->digest($source)), 409, 'Label source changed; refresh and compare before saving.');
            $revision = $last ? $last->revision + 1 : 1;
            $document = app(PharmacyFinishedContainerLabelDocument::class)->render($source, $revision);
            $id = DB::table('pharmacy_container_label_proofs')->insertGetId(['execution_id' => $executionId, 'container_identifier' => $d['identifier'],
                'revision' => $revision, 'previous_id' => $d['previous_id'], 'created_by' => $actor->id, 'request_id' => $d['request_id'],
                'request_hash' => $requestHash, 'source_snapshot' => json_encode($source, JSON_THROW_ON_ERROR), 'source_hash' => $d['source_hash'],
                'document' => $document, 'document_hash' => hash('sha256', $document), 'created_at' => now()]);
            DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch->formulation_id, 'batch_id' => $batch->id,
                'actor_id' => $actor->id, 'action' => 'container_label_proof_retained',
                'details' => json_encode(['label_id' => $id, 'release_enabled' => false, 'stock_adjusted' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            return $id;
        });
    }
}
