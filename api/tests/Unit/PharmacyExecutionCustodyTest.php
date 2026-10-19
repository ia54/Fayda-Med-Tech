<?php

namespace Tests\Unit;

use App\Services\PharmacyExecutionCustody;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PharmacyExecutionCustodyTest extends TestCase
{
    public function test_output_disposal_preserves_prior_disposal_and_never_reconsumes_ingredients(): void
    {
        $result = (new PharmacyExecutionCustody)->project(
            ['recorded_yield' => '10.000', 'previously_disposed' => '2.000', 'held_output' => '8.000', 'unit' => 'g'],
            ['retained_quarantined' => '5.250', 'disposed_output' => '2.750', 'unaccounted_output' => '0', 'unit' => 'g', 'evidence' => 'SYNTHETIC custody findings']
        );
        $this->assertSame('4.750', $result['total_disposed']);
        $this->assertSame('5.250', $result['retained_quarantined']);
        $this->assertSame('0.000', $result['ingredient_stock_delta']);
        $this->assertTrue($result['accounting_complete']);
        $this->assertFalse($result['release_enabled']);
    }

    public function test_unaccounted_output_cannot_be_presented_as_complete(): void
    {
        $result = (new PharmacyExecutionCustody)->project(
            ['recorded_yield' => '1', 'previously_disposed' => '0', 'held_output' => '1', 'unit' => 'g'],
            ['retained_quarantined' => '0', 'disposed_output' => '0', 'unaccounted_output' => '1', 'unit' => 'g', 'evidence' => 'SYNTHETIC missing output']
        );
        $this->assertFalse($result['accounting_complete']);
        $this->assertFalse($result['release_enabled']);
    }

    public function test_invalid_or_incomplete_quantities_and_units_fail(): void
    {
        foreach (['null', 'boolean', 'negative', 'precision', 'missing', 'extra', 'unit', 'history', 'evidence'] as $case) {
            $source = ['recorded_yield' => '1', 'previously_disposed' => '0', 'held_output' => '1', 'unit' => 'g'];
            $decision = ['retained_quarantined' => '1', 'disposed_output' => '0', 'unaccounted_output' => '0', 'unit' => 'g', 'evidence' => 'SYNTHETIC'];
            if ($case === 'null') { $decision['disposed_output'] = null; }
            if ($case === 'boolean') { $decision['disposed_output'] = false; }
            if ($case === 'negative') { $decision['disposed_output'] = '-1'; }
            if ($case === 'precision') { $decision['disposed_output'] = '0.0001'; }
            if ($case === 'missing') { unset($decision['unaccounted_output']); }
            if ($case === 'extra') { $decision['disposed_output'] = '1'; }
            if ($case === 'unit') { $decision['unit'] = 'mg'; }
            if ($case === 'history') { $source['previously_disposed'] = '1'; }
            if ($case === 'evidence') { $decision['evidence'] = ' '; }
            try {
                (new PharmacyExecutionCustody)->project($source, $decision);
                $this->fail('Accepted invalid output custody: '.$case);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }
}
