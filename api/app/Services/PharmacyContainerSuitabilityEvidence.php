<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** Documentary completeness only; suitability is a pharmacist decision, never inferred. */
class PharmacyContainerSuitabilityEvidence
{
    public function inspect(array $source, array $input): array
    {
        $rows = $source['containers_for_review'] ?? null;
        if (! is_array($rows) || ! array_is_list($rows) || ! count($rows) || count($rows) > 100
            || ! array_is_list($input) || count($input) !== count($rows)) {
            $this->fail('Record exactly one assessment for every nonempty container.');
        }
        $expected = [];
        foreach ($rows as $row) {
            $id = $row['identifier'] ?? null;
            if (! is_string($id) || $id === '' || isset($expected[$id]) || PharmacyStock::milli($row['quantity']) <= 0) {
                $this->fail('Current container evidence is invalid.');
            }
            $expected[$id] = $row;
        }
        $seen = []; $assessments = []; $followUp = false;
        foreach ($input as $assessment) {
            $keys = ['identifier', 'outcome', 'container_evidence', 'storage_evidence', 'dating_scope_evidence'];
            if (! is_array($assessment) || array_diff(array_keys($assessment), $keys) || array_diff($keys, array_keys($assessment))) {
                $this->fail('Retain all assessment fields without additional authority fields.');
            }
            $id = $assessment['identifier'];
            if (! is_string($id) || ! isset($expected[$id]) || isset($seen[$id])) { $this->fail('Unknown, empty or duplicate container.'); }
            if (! in_array($assessment['outcome'], ['suitable', 'unsuitable', 'not_assessed'], true)) { $this->fail('Record an explicit suitability outcome.'); }
            foreach (['container_evidence', 'storage_evidence', 'dating_scope_evidence'] as $key) {
                if (! is_string($assessment[$key]) || trim($assessment[$key]) === '' || strlen($assessment[$key]) > 5000) {
                    $this->fail('Container, storage and dating-scope evidence are required.');
                }
            }
            $seen[$id] = true; $followUp = $followUp || $assessment['outcome'] !== 'suitable';
            $assessments[$id] = ['container' => $expected[$id], 'assessment' => $assessment];
        }
        return ['containers' => array_map(fn ($id) => $assessments[$id], array_keys($expected)),
            'unpackaged_quantity' => $source['unpackaged_quantity'], 'requires_follow_up' => $followUp,
            'requires_independent_review' => true, 'clinical_suitability_verified' => false, 'release_enabled' => false];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['container_suitability' => $message]);
    }
}
