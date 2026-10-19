<?php

namespace Tests\Unit;

use App\Services\PharmacyFinishedPackaging;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PharmacyFinishedPackagingTest extends TestCase
{
    private function balance(): array { return ['recorded_yield' => '8.000', 'previously_disposed' => '2.000', 'held_output' => '6.000', 'unit' => 'g']; }
    private function input(): array
    {
        return ['unit' => 'g', 'containers' => [['identifier' => 'SYN-A', 'quantity' => '2.125', 'container_reference' => 'SYNTHETIC container', 'storage_reference' => 'SYNTHETIC storage'],
            ['identifier' => 'SYN-B', 'quantity' => '3.875', 'container_reference' => 'SYNTHETIC container', 'storage_reference' => 'SYNTHETIC storage']],
            'unpackaged_quantity' => '0.000', 'evidence' => 'SYNTHETIC reconciliation'];
    }

    public function test_packaging_reconciles_corrected_yield_and_prior_disposal_without_new_stock(): void
    {
        $p = (new PharmacyFinishedPackaging)->project($this->balance(), $this->input());
        $this->assertSame('6.000', $p['packaged_quantity']); $this->assertSame('2.000', $p['previously_disposed']);
        $this->assertSame('0.000', $p['output_stock_delta']); $this->assertSame('0.000', $p['ingredient_stock_delta']);
        $this->assertFalse($p['release_enabled']); $this->assertSame('quarantined', $p['containers'][0]['status']);
        $i = $this->input(); $i['containers'][1]['quantity'] = '2.875'; $i['unpackaged_quantity'] = '1.000';
        $this->assertSame('5.000', (new PharmacyFinishedPackaging)->project($this->balance(), $i)['packaged_quantity']);
    }

    public function test_double_counting_duplicates_missing_quantities_and_unit_conversion_fail(): void
    {
        foreach (['disposed_again', 'duplicate', 'missing', 'precision', 'float', 'unit', 'evidence', 'authority'] as $case) {
            $i = $this->input();
            if ($case === 'disposed_again') { $i['unpackaged_quantity'] = '2.000'; }
            if ($case === 'duplicate') { $i['containers'][1]['identifier'] = 'SYN-A'; }
            if ($case === 'missing') { unset($i['unpackaged_quantity']); }
            if ($case === 'precision') { $i['containers'][0]['quantity'] = '2.1251'; }
            if ($case === 'float') { $i['containers'][0]['quantity'] = 2.125; }
            if ($case === 'unit') { $i['unit'] = 'mg'; }
            if ($case === 'evidence') { $i['containers'][0]['storage_reference'] = ' '; }
            if ($case === 'authority') { $i['release_enabled'] = true; }
            try { (new PharmacyFinishedPackaging)->project($this->balance(), $i); $this->fail('Invalid packaging accepted: '.$case); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
    }
}
