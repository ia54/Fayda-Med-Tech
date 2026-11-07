<?php

namespace Tests\Unit;

use App\Services\PharmacyContainerSuitabilityEvidence;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PharmacyContainerSuitabilityEvidenceTest extends TestCase
{
    private function source(): array
    {
        return ['containers_for_review' => [['identifier' => 'SYN-A', 'quantity' => '2.000', 'container_reference' => 'SYNTHETIC container', 'storage_reference' => 'SYNTHETIC storage']],
            'empty_container_history' => [['identifier' => 'SYN-EMPTY', 'quantity' => '0.000']], 'unpackaged_quantity' => '1.000'];
    }
    private function assessment(): array
    {
        return ['identifier' => 'SYN-A', 'outcome' => 'suitable', 'container_evidence' => 'SYNTHETIC observed container',
            'storage_evidence' => 'SYNTHETIC storage comparison', 'dating_scope_evidence' => 'SYNTHETIC supplied dating scope'];
    }
    public function test_explicit_assessments_preserve_source_and_never_release(): void
    {
        foreach (['suitable', 'unsuitable', 'not_assessed'] as $outcome) {
            $input = $this->assessment(); $input['outcome'] = $outcome;
            $result = (new PharmacyContainerSuitabilityEvidence)->inspect($this->source(), [$input]);
            $this->assertSame($outcome !== 'suitable', $result['requires_follow_up']);
            $this->assertSame($this->source()['containers_for_review'][0], $result['containers'][0]['container']);
            $this->assertSame('1.000', $result['unpackaged_quantity']);
            $this->assertTrue($result['requires_independent_review']);
            $this->assertFalse($result['clinical_suitability_verified']);
            $this->assertFalse($result['release_enabled']);
        }
    }
    public function test_missing_unknown_empty_and_authority_fields_are_rejected(): void
    {
        $valid = $this->assessment();
        $invalid = [[], [$valid, $valid], [array_replace($valid, ['identifier' => 'SYN-EMPTY'])],
            [array_replace($valid, ['outcome' => 'approved'])], [$valid + ['release_enabled' => true]]];
        foreach (['container_evidence', 'storage_evidence', 'dating_scope_evidence'] as $key) {
            $row = $valid; unset($row[$key]); $invalid[] = [$row];
            $invalid[] = [array_replace($valid, [$key => ' '])];
        }
        foreach ($invalid as $input) {
            try { (new PharmacyContainerSuitabilityEvidence)->inspect($this->source(), $input); $this->fail('Incomplete or excess authority accepted.'); }
            catch (ValidationException $e) { $this->assertArrayHasKey('container_suitability', $e->errors()); }
        }
    }
}
