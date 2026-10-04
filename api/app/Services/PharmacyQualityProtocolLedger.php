<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** Retained synthetic protocol governance; not operational or product-release approval. */
class PharmacyQualityProtocolLedger
{
    private function authorize(User $actor, int $location): void
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        abort_unless($actor->role === 'pharmacist' && $actor->status === 'active' && $actor->organization_id, 403);
        app(PharmacyAccess::class)->requireLocation($actor, $location);
    }

    private function hash(mixed $value): string
    {
        return app(PharmacyCompoundingIncident::class)->digest($value);
    }

    public function retain(User $actor, int $location, int $formulation, array $input): int
    {
        $this->authorize($actor, $location);
        $d = Validator::make($input, ['request_id' => 'required|uuid', 'previous_id' => 'present|nullable|integer|min:1',
            'record' => 'required|array:reference,revision,requirements', 'record.reference' => 'required|string|max:5000',
            'record.revision' => 'required|string|max:5000', 'record.requirements' => 'required|array|min:1|max:100',
            'record.requirements.*' => 'required|array:key,label,criterion,method_reference',
            'record.requirements.*.key' => 'required|string|max:40', 'record.requirements.*.label' => 'required|string|max:5000',
            'record.requirements.*.criterion' => 'required|string|max:5000', 'record.requirements.*.method_reference' => 'required|string|max:5000',
            'evidence' => 'required|string|max:5000'])->validate();
        // Reuse structural validation only. These placeholders are never stored as observations.
        app(PharmacyQualityEvidence::class)->inspect($d['record'], array_map(fn ($r) => ['key' => $r['key'], 'outcome' => 'not_assessed',
            'observation' => 'Protocol validation only', 'evidence_reference' => 'No batch result'], $d['record']['requirements']));
        abort_if(trim($d['evidence']) === '', 422);
        return DB::transaction(function () use ($actor, $location, $formulation, $d) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $this->authorize($actor, $location);
            $formula = DB::table('pharmacy_formulations')->where('id', $formulation)->where('organization_id', $actor->organization_id)->first();
            abort_unless($formula, 404);
            $hash = $this->hash(['location' => $location, 'formulation' => $formulation, 'input' => $d]);
            $retry = DB::table('pharmacy_quality_protocols')->where('organization_id', $actor->organization_id)->where('request_id', $d['request_id'])->first();
            if ($retry) {
                abort_unless((int) $retry->created_by === (int) $actor->id && hash_equals($retry->request_hash, $hash), 409);
                return (int) $retry->id;
            }
            abort_unless($formula->status === 'reviewed', 422, 'Use an independently reviewed formulation.');
            $last = DB::table('pharmacy_quality_protocols')->where('location_id', $location)->where('formulation_id', $formulation)->orderByDesc('revision_number')->first();
            abort_unless(($last ? (int) $last->id : null) === ($d['previous_id'] === null ? null : (int) $d['previous_id']), 409, 'Protocol lineage changed.');
            abort_if($last && $last->status === 'draft', 409, 'Review or reject the existing draft first.');
            $id = DB::table('pharmacy_quality_protocols')->insertGetId(['organization_id' => $actor->organization_id, 'location_id' => $location,
                'formulation_id' => $formulation, 'previous_id' => $d['previous_id'], 'revision_number' => ($last->revision_number ?? 0) + 1,
                'created_by' => $actor->id, 'request_id' => $d['request_id'], 'request_hash' => $hash,
                'record' => json_encode($d['record'], JSON_THROW_ON_ERROR), 'record_hash' => $this->hash($d['record']),
                'formulation_hash' => $this->hash((array) $formula), 'status' => 'draft', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $this->event($id, $actor, 'created', 1, $d['evidence']);
            return $id;
        });
    }

    public function decide(User $actor, int $id, int $version, string $decision, string $evidence): void
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        abort_unless(in_array($decision, ['reviewed', 'rejected', 'retired'], true) && trim($evidence) !== '' && strlen($evidence) <= 5000, 422);
        DB::transaction(function () use ($actor, $id, $version, $decision, $evidence) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $p = DB::table('pharmacy_quality_protocols')->where('id', $id)->where('organization_id', $actor->organization_id)->lockForUpdate()->first();
            abort_unless($p, 404);
            $this->authorize($actor, $p->location_id);
            $event = DB::table('pharmacy_quality_protocol_events')->where('protocol_id', $id)->where('version', $version + 1)->first();
            if ($p->status === $decision && (int) $p->version === $version + 1 && $event && (int) $event->actor_id === (int) $actor->id && $event->action === $decision && $event->evidence === $evidence) { return; }
            abort_unless((int) $p->version === $version, 409, 'Protocol changed.');
            abort_unless($decision === 'retired' ? $p->status === 'reviewed' : $p->status === 'draft', 422);
            if ($decision !== 'retired') { abort_if((int) $p->created_by === (int) $actor->id, 422, 'Another assigned pharmacist must review.'); }
            if ($decision === 'reviewed') {
                $author = User::find($p->created_by);
                abort_unless($author && $author->organization_id === $actor->organization_id, 422);
                $this->authorize($author, $p->location_id);
                $formula = DB::table('pharmacy_formulations')->where('id', $p->formulation_id)->where('organization_id', $actor->organization_id)->first();
                abort_unless($formula && $formula->status === 'reviewed' && hash_equals($p->formulation_hash, $this->hash((array) $formula)), 409, 'Formulation evidence changed.');
                abort_unless(hash_equals($p->record_hash, $this->hash(json_decode($p->record, true, 512, JSON_THROW_ON_ERROR))), 409, 'Protocol integrity failed.');
                $active = DB::table('pharmacy_quality_protocols')->where('location_id', $p->location_id)->where('formulation_id', $p->formulation_id)->where('status', 'reviewed')->get();
                foreach ($active as $old) {
                    DB::table('pharmacy_quality_protocols')->where('id', $old->id)->update(['status' => 'retired', 'version' => $old->version + 1, 'updated_at' => now()]);
                    $this->event($old->id, $actor, 'retired', $old->version + 1, 'Superseded by reviewed protocol '.$id.': '.$evidence);
                }
            }
            DB::table('pharmacy_quality_protocols')->where('id', $id)->update(['status' => $decision, 'version' => $version + 1, 'updated_at' => now()]);
            $this->event($id, $actor, $decision, $version + 1, $evidence);
        });
    }

    private function event(int $id, User $actor, string $action, int $version, string $evidence): void
    {
        DB::table('pharmacy_quality_protocol_events')->insert(['protocol_id' => $id, 'actor_id' => $actor->id, 'action' => $action,
            'version' => $version, 'evidence' => $evidence, 'created_at' => now()]);
    }
}
