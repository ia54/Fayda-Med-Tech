<?php

namespace App\Services;

use App\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/** Current documentary dating evidence for packaging; never clinical release authorization. */
class PharmacyReviewedBeyondUseContext
{
    public function inspect(User $actor, int $executionId): array
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        abort_unless($actor->role === 'pharmacist' && $actor->status === 'active' && $actor->organization_id, 403);
        return DB::transaction(function () use ($actor, $executionId) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $quality = app(PharmacyReviewedQualityContext::class)->inspect($actor, $executionId);
            $batch = $quality['source']['batch']; $execution = $quality['source']['execution'];
            $p = DB::table('pharmacy_beyond_use_proposals')->where('execution_id', $executionId)->orderByDesc('id')->first();
            abort_unless($p && $p->status === 'reviewed', 409, 'The latest dating proposal must be independently reviewed. Earlier proposals cannot be reused.');
            $digest = app(PharmacyCompoundingIncident::class);
            $source = json_decode($p->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $proposal = json_decode($p->proposal, true, 512, JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($p->source_hash, $digest->digest($source)) && hash_equals($p->proposal_hash, $digest->digest($proposal)), 409, 'Dating evidence integrity failed.');
            abort_unless(hash_equals($p->source_hash, $digest->digest($quality)) && (int) $p->quality_record_id === (int) $quality['quality_record']['id'], 409, 'Dating source evidence changed.');
            foreach ([$p->created_by, $p->reviewed_by] as $userId) {
                $staff = User::find($userId);
                abort_unless($staff && $staff->role === 'pharmacist' && $staff->status === 'active' && (int) $staff->organization_id === (int) $actor->organization_id, 409, 'Dating author or reviewer is no longer authorized.');
                app(PharmacyAccess::class)->requireLocation($staff, $batch['location_id']);
            }
            abort_unless($p->reviewed_at && trim((string) $p->review_evidence) !== '' && (int) $p->reviewed_by !== (int) $p->created_by
                && (int) $p->reviewed_by !== (int) $execution['created_by']
                && ! DB::table('pharmacy_execution_addenda')->where('execution_id', $executionId)->where('created_by', $p->reviewed_by)->exists(), 409, 'Independent dating review is required.');
            abort_unless(DB::table('pharmacy_compounding_events')->where('batch_id', $batch['id'])->where('action', 'beyond_use_reviewed')
                ->where('actor_id', $p->reviewed_by)->where('details->proposal_id', $p->id)->exists(), 409, 'Dating review audit is missing.');
            $checked = app(PharmacyBeyondUseEvidence::class)->inspect($proposal['evidence']);
            abort_unless(hash_equals($p->proposal_hash, $digest->digest($checked)), 409, 'Dating projection changed.');
            $original = json_decode($execution['record'], true, 512, JSON_THROW_ON_ERROR);
            abort_unless(substr($checked['evidence']['prepared_at'], 0, 10) === ($original['prepared_on'] ?? null)
                && new DateTimeImmutable($checked['prepared_at_utc']) <= now()->toDateTimeImmutable()
                && new DateTimeImmutable($checked['proposed_bud_at_utc']) > now()->toDateTimeImmutable(), 409, 'Dating evidence is inconsistent or its proposed date has elapsed.');
            return ['dating_proposal' => (array) $p, 'quality' => $quality, 'output_balance' => $quality['source']['output_custody'],
                'clinical_limits_verified' => false, 'release_enabled' => false];
        });
    }
}
