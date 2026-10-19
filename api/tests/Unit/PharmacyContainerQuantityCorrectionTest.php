<?php

namespace Tests\Unit;

use App\Services\PharmacyContainerQuantityCorrection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PharmacyContainerQuantityCorrectionTest extends TestCase
{
    private function source(): array
    {
        return ['original_yield' => '9', 'recorded_yield' => '8', 'previously_disposed' => '2', 'held_output' => '6', 'unit' => 'g',
            'containers' => [['identifier' => 'SYN-A', 'quantity' => '2.125'], ['identifier' => 'SYN-B', 'quantity' => '2.875']], 'unpackaged_quantity' => '1'];
    }
    private function finding(string $observed): array
    {
        return ['observed_quantity' => $observed, 'reason' => 'SYNTHETIC recount correction', 'measurement_evidence' => 'SYNTHETIC measurement', 'source_evidence' => 'SYNTHETIC original record'];
    }
    private function input(): array
    {
        return ['unit' => 'g', 'corrected_yield' => '8.125', 'containers' => [['identifier' => 'SYN-A'] + $this->finding('2.250'),
            ['identifier' => 'SYN-B'] + $this->finding('2.875')], 'unpackaged' => $this->finding('1'),
            'reason' => 'SYNTHETIC correction', 'measurement_evidence' => 'SYNTHETIC measured inventory', 'source_evidence' => 'SYNTHETIC original retained'];
    }
    public function test_recount_preserves_original_yield_disposal_and_exact_container_changes(): void
    {
        $p = (new PharmacyContainerQuantityCorrection)->project($this->source(), $this->input());
        $this->assertSame('9.000', $p['original_yield']); $this->assertSame('8.000', $p['previous_accounted_yield']);
        $this->assertSame('8.125', $p['corrected_yield']); $this->assertSame('2.000', $p['previously_disposed']);
        $this->assertSame('6.125', $p['corrected_held_output']); $this->assertSame('0.125', $p['change_quantity']);
        $this->assertSame('2.125', $p['containers'][0]['previous_quantity']); $this->assertSame('increase', $p['containers'][0]['change_direction']);
        $this->assertSame('unchanged', $p['containers'][1]['change_direction']);
        $this->assertFalse($p['physical_transfer']); $this->assertFalse($p['release_enabled']); $this->assertSame('0.000', $p['ingredient_stock_delta']);
        $input = $this->input(); $input['containers'] = array_reverse($input['containers']);
        $this->assertSame($p, (new PharmacyContainerQuantityCorrection)->project($this->source(), $input));
    }
    public function test_equal_total_can_retain_distinct_measured_container_corrections(): void
    {
        $input = $this->input(); $input['corrected_yield'] = '8'; $input['containers'][1]['observed_quantity'] = '2.750';
        $p = (new PharmacyContainerQuantityCorrection)->project($this->source(), $input);
        $this->assertSame('unchanged', $p['change_direction']); $this->assertSame('0.000', $p['change_quantity']);
        $this->assertSame('increase', $p['containers'][0]['change_direction']); $this->assertSame('decrease', $p['containers'][1]['change_direction']);
        $this->assertSame('2.000', $p['previously_disposed']); $this->assertFalse($p['physical_transfer']);
    }
    public function test_missing_measurements_identity_changes_and_erased_disposal_are_rejected(): void
    {
        foreach (['missing', 'duplicate', 'unknown', 'float', 'precision', 'unit', 'evidence', 'disposed', 'source', 'no_change', 'authority'] as $case) {
            $source = $this->source(); $input = $this->input();
            if ($case === 'missing') { unset($input['unpackaged']['observed_quantity']); }
            if ($case === 'duplicate') { $input['containers'][1]['identifier'] = 'SYN-A'; }
            if ($case === 'unknown') { $input['containers'][1]['identifier'] = 'SYN-C'; }
            if ($case === 'float') { $input['containers'][0]['observed_quantity'] = 2.25; }
            if ($case === 'precision') { $input['containers'][0]['observed_quantity'] = '2.2501'; }
            if ($case === 'unit') { $input['unit'] = 'mg'; }
            if ($case === 'evidence') { $input['containers'][1]['measurement_evidence'] = ' '; }
            if ($case === 'disposed') { $input['corrected_yield'] = '6.125'; }
            if ($case === 'source') { $source['held_output'] = '7'; }
            if ($case === 'no_change') { $input['corrected_yield'] = '8'; $input['containers'][0]['observed_quantity'] = '2.125'; }
            if ($case === 'authority') { $input['release_enabled'] = true; }
            try { (new PharmacyContainerQuantityCorrection)->project($source, $input); $this->fail('Invalid recount accepted: '.$case); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
    }
}
