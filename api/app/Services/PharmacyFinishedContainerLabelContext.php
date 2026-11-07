<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Source snapshot for a finished-container label proof; no dispensing authority. */
class PharmacyFinishedContainerLabelContext
{
    public function inspect(User $actor, int $executionId, string $identifier): array
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        abort_unless($actor->role === 'pharmacist' && $actor->status === 'active' && $actor->organization_id, 403);
        return DB::transaction(function () use ($actor, $executionId, $identifier) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $review = app(PharmacyReviewedSuitabilityContext::class)->inspect($actor, $executionId);
            $current = $review['current_container_evidence'];
            $container = collect($current['containers_for_review'])->firstWhere('identifier', $identifier);
            abort_unless($container, 422, 'Select a current nonempty reviewed container.');
            $source = $current['dating']['quality']['source']; $rx = $source['prescription'];
            abort_if($rx['discontinued_at'] || $rx['expires_on'] < now()->toDateString(), 409, 'Current prescription evidence is required for label proofs.');
            $location = DB::table('pharmacy_locations')->where('id', $rx['location_id'])->where('organization_id', $actor->organization_id)->first();
            abort_unless($location && $location->active, 409, 'An active scoped location is required.');
            $episode = DB::table('pharmacy_episodes')->where('id', $rx['episode_id'])->where('organization_id', $actor->organization_id)->first();
            abort_unless($episode, 409, 'Prescription episode evidence is missing.');
            abort_unless(DB::table('cases')->where('id', $episode->case_id)->where('organization_id', $actor->organization_id)->exists(), 409, 'Current case scope is required.');
            if ($episode->pharmacy_patient_id) {
                abort_unless(DB::table('pharmacy_patient_locations')->where('patient_id', $episode->pharmacy_patient_id)->where('location_id', $rx['location_id'])->where('active', true)->exists(), 409, 'Active patient enrollment is required.');
            } else {
                abort_unless(DB::table('case_parties')->where('case_id', $episode->case_id)->where('user_id', $episode->patient_id)->exists(), 409, 'Current patient case linkage is required.');
            }
            $patient = $episode->pharmacy_patient_id
                ? DB::table('pharmacy_patients')->where('id', $episode->pharmacy_patient_id)->where('organization_id', $actor->organization_id)->first()
                : DB::table('users')->where('id', $episode->patient_id)->where('organization_id', $actor->organization_id)->where('role', 'client')->where('status', 'active')->first();
            abort_unless($patient, 409, 'Patient evidence is missing.');
            // Retain only identity/version fields required by the proof, never unrelated profile fields.
            $identity = array_intersect_key((array) $patient, array_flip(['id', 'first_name', 'last_name', 'version']));
            foreach (['first_name', 'last_name'] as $key) { abort_unless(trim((string) ($identity[$key] ?? '')) !== '', 409, 'Patient name requires reconciliation.'); }
            $dating = json_decode($current['dating']['dating_proposal']['proposal'], true, 512, JSON_THROW_ON_ERROR);
            return ['reviewed_suitability' => $review, 'container' => $container,
                'unit' => $current['custody']['container_balance']['unit'], 'patient' => $identity,
                'patient_source' => $episode->pharmacy_patient_id ? 'pharmacy_patient' : 'portal_user',
                'location' => array_intersect_key((array) $location, array_flip(['id', 'name', 'address'])),
                'prescription' => array_intersect_key($rx, array_flip(['id', 'rx_number', 'medication', 'strength', 'dosage_form', 'directions', 'prescriber_name', 'version'])),
                'formulation' => $source['formulation'], 'dating_evidence' => $dating['evidence'],
                'synthetic_only' => true, 'release_enabled' => false];
        });
    }
}
