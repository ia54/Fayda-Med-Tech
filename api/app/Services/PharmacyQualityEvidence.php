<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** Validates documentary completeness, never clinical limits or release authority. */
class PharmacyQualityEvidence
{
    public function inspect(array $protocol, array $results): array
    {
        foreach (['reference', 'revision'] as $field) { $this->text($protocol[$field] ?? null); }
        $requirements = $protocol['requirements'] ?? null;
        if (! is_array($requirements) || ! array_is_list($requirements) || count($requirements) < 1 || count($requirements) > 100
            || ! array_is_list($results) || count($results) !== count($requirements)) {
            $this->fail('Retain exactly one result for every protocol requirement.');
        }
        $expected = [];
        foreach ($requirements as $requirement) {
            if (! is_array($requirement)) { $this->fail('Invalid protocol requirement.'); }
            $key = $requirement['key'] ?? null;
            if (! is_string($key) || ! preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,39}$/D', $key) || isset($expected[$key])) {
                $this->fail('Protocol requirement identifiers must be unique.');
            }
            foreach (['label', 'criterion', 'method_reference'] as $field) { $this->text($requirement[$field] ?? null); }
            $expected[$key] = $requirement;
        }
        $retained = [];
        $counts = ['pass' => 0, 'fail' => 0, 'not_assessed' => 0];
        foreach ($results as $result) {
            if (! is_array($result) || array_diff(array_keys($result), ['key', 'outcome', 'observation', 'evidence_reference'])) {
                $this->fail('Unexpected quality result fields.');
            }
            $key = $result['key'] ?? null;
            if (! is_string($key) || ! isset($expected[$key]) || isset($retained[$key])) { $this->fail('Unknown or duplicate quality requirement.'); }
            $outcome = $result['outcome'] ?? null;
            if (! is_string($outcome) || ! array_key_exists($outcome, $counts)) { $this->fail('Record pass, fail or not_assessed explicitly.'); }
            foreach (['observation', 'evidence_reference'] as $field) { $this->text($result[$field] ?? null); }
            $counts[$outcome]++;
            $retained[$key] = ['requirement' => $expected[$key], 'result' => $result];
        }
        return ['protocol_reference' => $protocol['reference'], 'protocol_revision' => $protocol['revision'],
            'evidence' => array_map(fn ($key) => $retained[$key], array_keys($expected)), 'reported_outcomes' => $counts,
            'requires_follow_up' => $counts['fail'] > 0 || $counts['not_assessed'] > 0,
            'requires_pharmacist_review' => true, 'clinical_quality_verified' => false,
            'output_status' => 'quarantined', 'release_enabled' => false];
    }

    private function text(mixed $value): void
    {
        if (! is_string($value) || trim($value) === '' || strlen($value) > 5000) { $this->fail('Quality criteria, observations and evidence references must be explicit.'); }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['quality_evidence' => $message]);
    }
}
