<?php

namespace Tests\Unit;

use App\Services\PharmacyYieldCorrection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PharmacyYieldCorrectionTest extends TestCase
{
    private function source(): array
    {
        return ['original_yield' => '9', 'accounted_yield' => '9', 'previously_disposed' => '2', 'held_output' => '7', 'unit' => 'tablet'];
    }

    private function correction(): array
    {
        return ['corrected_yield' => '8', 'observed_held' => '6', 'unit' => 'tablet', 'reason' => 'SYNTHETIC transcription correction', 'measurement_evidence' => 'SYNTHETIC recount', 'source_evidence' => 'SYNTHETIC original record'];
    }

    public function test_correction_preserves_original_and_prior_disposal_without_ingredient_or_release_effect(): void
    {
        $result = (new PharmacyYieldCorrection)->project($this->source(), $this->correction());
        $this->assertSame('9.000', $result['original_yield']);
        $this->assertSame('2.000', $result['previously_disposed']);
        $this->assertSame('6.000', $result['corrected_held_output']);
        $this->assertSame('decrease', $result['change_direction']);
        $this->assertSame('1.000', $result['change_quantity']);
        $this->assertSame('0.000', $result['ingredient_stock_delta']);
        $this->assertFalse($result['release_enabled']);
        $next = (new PharmacyYieldCorrection)->project(array_replace($this->source(), ['accounted_yield' => '8', 'held_output' => '6']), array_replace($this->correction(), ['corrected_yield' => '10', 'observed_held' => '8']));
        $this->assertSame('9.000', $next['original_yield']);
        $this->assertSame('increase', $next['change_direction']);
        $this->assertSame('2.000', $next['change_quantity']);
    }

    public function test_disposal_erasure_unknown_noop_unit_change_and_missing_evidence_fail(): void
    {
        foreach (['disposed', 'unknown', 'noop', 'unit', 'evidence', 'balance'] as $case) {
            $source = $this->source(); $correction = $this->correction();
            if ($case === 'disposed') { $correction['corrected_yield'] = '1'; $correction['observed_held'] = '0'; }
            if ($case === 'unknown') { $correction['observed_held'] = null; }
            if ($case === 'noop') { $correction['corrected_yield'] = '9'; }
            if ($case === 'unit') { $correction['unit'] = 'g'; }
            if ($case === 'evidence') { $correction['source_evidence'] = ' '; }
            if ($case === 'balance') { $source['held_output'] = '8'; }
            try { (new PharmacyYieldCorrection)->project($source, $correction); $this->fail('Unsafe correction accepted: '.$case); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
    }
}
