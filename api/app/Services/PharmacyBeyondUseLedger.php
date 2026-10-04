<?php

namespace App\Services;

use App\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** Retained dating proposals only. No clinical dating rule or medication release is enabled. */
class PharmacyBeyondUseLedger
{
    private function actor(User $actor): void
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        abort_unless($actor->role === 'pharmacist' && $actor->status === 'active' && $actor->organization_id, 403);
    }

    private function scope(User $actor, int $executionId): array
    {
        $execution = DB::table('pharmacy_batch_executions')->where('id', $executionId)->lockForUpdate()->first();
        abort_unless($execution, 404);
        $batch = DB::table('pharmacy_batch_worksheets')->where('id', $execution->batch_id)->where('organization_id', $actor->organization_id)->first();
        abort_unless($batch, 404);
        app(PharmacyAccess::class)->requireLocation($actor, $batch->location_id);
        return [$execution, $batch];
    }

    private function dates(array $projection, object $execution): void
    {
        $evidence = $projection['evidence'];
        $original = json_decode($execution->record, true, 512, JSON_THROW_ON_ERROR);
        abort_unless(substr($evidence['prepared_at'], 0, 10) === ($original['prepared_on'] ?? null), 422, 'Preparation timestamp must match the retained preparation date. Resolve a conflicting execution record first.');
        abort_if(new DateTimeImmutable($projection['prepared_at_utc']) > now()->toDateTimeImmutable(), 422, 'Preparation cannot be in the future.');
        abort_if(new DateTimeImmutable($projection['proposed_bud_at_utc']) <= now()->toDateTimeImmutable(), 422, 'The proposed beyond-use timestamp has elapsed.');
    }

    public function retain(User $actor, int $executionId, array $input): int
    {
        $this->actor($actor);
        $d = Validator::make($input, ['request_id' => 'required|uuid', 'previous_id' => 'present|nullable|integer|min:1',
            'source_hash' => 'required|string|regex:/^[a-f0-9]{64}$/', 'proposal' => 'required|array'])->validate();
        $projection = app(PharmacyBeyondUseEvidence::class)->inspect($d['proposal']);
        return DB::transaction(function () use ($actor, $executionId, $d, $projection) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            [$execution, $batch] = $this->scope($actor, $executionId);
            $digest = app(PharmacyCompoundingIncident::class);
            $requestHash = $digest->digest($d);
            $retry = DB::table('pharmacy_beyond_use_proposals')->where('execution_id', $executionId)->where('request_id', $d['request_id'])->first();
            if ($retry) {
                abort_unless((int) $retry->created_by === (int) $actor->id && hash_equals($retry->request_hash, $requestHash), 409, 'Request belongs to different dating evidence.');
                return (int) $retry->id;
            }
            $last = DB::table('pharmacy_beyond_use_proposals')->where('execution_id', $executionId)->orderByDesc('id')->first();
            abort_unless(($last ? (int) $last->id : null) === ($d['previous_id'] === null ? null : (int) $d['previous_id']), 409, 'Dating proposal history changed.');
            abort_if($last && $last->status === 'pending', 409, 'Review or reject the pending dating proposal first.');
            $source = app(PharmacyReviewedQualityContext::class)->inspect($actor, $executionId);
            abort_unless(hash_equals($d['source_hash'], $digest->digest($source)), 409, 'Reviewed quality evidence changed.');
            $this->dates($projection, $execution);
            $id = DB::table('pharmacy_beyond_use_proposals')->insertGetId(['execution_id' => $executionId,
                'quality_record_id' => $source['quality_record']['id'], 'previous_id' => $d['previous_id'], 'created_by' => $actor->id,
                'request_id' => $d['request_id'], 'request_hash' => $requestHash, 'source_snapshot' => json_encode($source, JSON_THROW_ON_ERROR),
                'source_hash' => $d['source_hash'], 'proposal' => json_encode($projection, JSON_THROW_ON_ERROR),
                'proposal_hash' => $digest->digest($projection), 'status' => 'pending', 'created_at' => now()]);
            $this->event($actor, $batch, $id, 'beyond_use_proposed');
            return $id;
        });
    }

    public function decide(User $actor, int $id, string $decision, string $evidence): void
    {
        $this->actor($actor);
        abort_unless(in_array($decision, ['reviewed', 'rejected'], true) && trim($evidence) !== '' && strlen($evidence) <= 5000, 422);
        DB::transaction(function () use ($actor, $id, $decision, $evidence) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $p = DB::table('pharmacy_beyond_use_proposals')->where('id', $id)->lockForUpdate()->first();
            abort_unless($p, 404);
            [$execution, $batch] = $this->scope($actor, $p->execution_id);
            abort_if((int) $actor->id === (int) $p->created_by || (int) $actor->id === (int) $execution->created_by
                || DB::table('pharmacy_execution_addenda')->where('execution_id', $execution->id)->where('created_by', $actor->id)->exists(), 422, 'Review requires a pharmacist independent of dating and preparation.');
            if ($p->status !== 'pending') {
                abort_unless($p->status === $decision && (int) $p->reviewed_by === (int) $actor->id && $p->review_evidence === $evidence, 409);
                return;
            }
            if ($decision === 'reviewed') {
                $author = User::find($p->created_by);
                abort_unless($author && $author->status === 'active' && $author->role === 'pharmacist' && (int) $author->organization_id === (int) $actor->organization_id, 409);
                app(PharmacyAccess::class)->requireLocation($author, $batch->location_id);
                $digest = app(PharmacyCompoundingIncident::class);
                $source = json_decode($p->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
                $proposal = json_decode($p->proposal, true, 512, JSON_THROW_ON_ERROR);
                abort_unless(hash_equals($p->source_hash, $digest->digest($source)) && hash_equals($p->proposal_hash, $digest->digest($proposal)), 409, 'Dating evidence integrity failed.');
                $current = app(PharmacyReviewedQualityContext::class)->inspect($actor, $execution->id);
                abort_unless(hash_equals($p->source_hash, $digest->digest($current)) && (int) $p->quality_record_id === (int) $current['quality_record']['id'], 409, 'Quality evidence changed. Reject and replace the dating proposal.');
                $checked = app(PharmacyBeyondUseEvidence::class)->inspect($proposal['evidence']);
                abort_unless(hash_equals($p->proposal_hash, $digest->digest($checked)), 409, 'Dating projection changed.');
                $this->dates($checked, $execution);
            }
            DB::table('pharmacy_beyond_use_proposals')->where('id', $id)->update(['status' => $decision, 'reviewed_by' => $actor->id,
                'review_evidence' => $evidence, 'reviewed_at' => now()]);
            $this->event($actor, $batch, $id, 'beyond_use_'.$decision);
        });
    }

    private function event(User $actor, object $batch, int $id, string $action): void
    {
        DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch->formulation_id, 'batch_id' => $batch->id,
            'actor_id' => $actor->id, 'action' => $action, 'details' => json_encode(['proposal_id' => $id, 'release_enabled' => false, 'stock_adjusted' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
