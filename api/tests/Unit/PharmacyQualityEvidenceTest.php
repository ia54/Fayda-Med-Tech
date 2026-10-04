<?php

namespace Tests\Unit;

use App\Services\PharmacyQualityEvidence;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PharmacyQualityEvidenceTest extends TestCase
{
    private function protocol(): array
    {
        return ['reference' => 'SYNTHETIC protocol', 'revision' => '1', 'requirements' => array_map(fn ($key) =>
            ['key' => $key, 'label' => $key, 'criterion' => 'SYNTHETIC criterion', 'method_reference' => 'SYNTHETIC method'], ['appearance', 'identity'])];
    }

    private function results(): array
    {
        return array_map(fn ($key) => ['key' => $key, 'outcome' => 'pass', 'observation' => 'SYNTHETIC zero defects',
            'evidence_reference' => 'SYNTHETIC record'], ['identity', 'appearance']);
    }

    public function test_reported_passes_never_become_clinical_or_release_approval(): void
    {
        $v = (new PharmacyQualityEvidence)->inspect($this->protocol(), $this->results());
        $this->assertSame(['pass' => 2, 'fail' => 0, 'not_assessed' => 0], $v['reported_outcomes']);
        $this->assertSame('appearance', $v['evidence'][0]['result']['key']);
        $this->assertTrue($v['requires_pharmacist_review']);
        $this->assertFalse($v['clinical_quality_verified']);
        $this->assertFalse($v['release_enabled']);
        $this->assertSame('quarantined', $v['output_status']);
    }

    public function test_failed_and_unassessed_results_remain_visible(): void
    {
        $r = $this->results(); $r[0]['outcome'] = 'fail'; $r[1]['outcome'] = 'not_assessed';
        $v = (new PharmacyQualityEvidence)->inspect($this->protocol(), $r);
        $this->assertTrue($v['requires_follow_up']);
        $this->assertSame(['pass' => 0, 'fail' => 1, 'not_assessed' => 1], $v['reported_outcomes']);
        $this->assertSame('not_assessed', $v['evidence'][0]['result']['outcome']);
    }

    public function test_incomplete_duplicate_unknown_and_forged_results_fail(): void
    {
        $r = $this->results();
        $cases = [[], [$r[0]], [$r[0], $r[0]]];
        foreach ([['key' => 'unknown'], ['outcome' => true], ['outcome' => 'not_applicable'], ['observation' => ' '],
            ['evidence_reference' => null], ['release_enabled' => true]] as $change) { $cases[] = [array_replace($r[0], $change), $r[1]]; }
        foreach ($cases as $values) {
            try { (new PharmacyQualityEvidence)->inspect($this->protocol(), $values); $this->fail('Invalid evidence accepted'); }
            catch (ValidationException $e) { $this->assertArrayHasKey('quality_evidence', $e->errors()); }
        }
    }

    public function test_undefined_and_duplicate_protocol_criteria_fail(): void
    {
        foreach (['empty', 'duplicate', 'missing_method'] as $case) {
            $p = $this->protocol();
            if ($case === 'empty') { $p['requirements'] = []; }
            elseif ($case === 'duplicate') { $p['requirements'][1] = $p['requirements'][0]; }
            else { unset($p['requirements'][0]['method_reference']); }
            try { (new PharmacyQualityEvidence)->inspect($p, $this->results()); $this->fail('Invalid protocol accepted'); }
            catch (ValidationException $e) { $this->assertArrayHasKey('quality_evidence', $e->errors()); }
        }
    }
}
