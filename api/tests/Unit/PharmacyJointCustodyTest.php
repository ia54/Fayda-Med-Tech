<?php

namespace Tests\Unit;

use App\Services\PharmacyJointCustody;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PharmacyJointCustodyTest extends TestCase
{
    private function fixture(): array
    {
        return [
            [['incident_id' => 1, 'allocation_id' => 1, 'ingredient_lot_id' => 10, 'unit' => 'g', 'mode' => 'new_accounting', 'accounting' => ['quantities' => ['unused_retained' => '1.000']]],
                ['incident_id' => 2, 'allocation_id' => 2, 'ingredient_lot_id' => 10, 'unit' => 'g', 'mode' => 'already_accounted', 'unused_retained' => '0.750']],
            [['allocation_id' => 1, 'return_to_quarantine' => '0.750', 'disposed_unused' => '0.250', 'evidence' => 'SYNTHETIC measured'],
                ['allocation_id' => 2, 'return_to_quarantine' => '0.250', 'disposed_unused' => '0.500', 'evidence' => 'SYNTHETIC prior unused measured']],
            [['id' => 10, 'on_hand' => '7.000', 'reserved' => '3.750', 'status' => 'quarantined', 'quantity_unit' => 'g']],
        ];
    }

    public function test_joint_custody_deducts_only_unused_disposal_and_preserves_other_reservations(): void
    {
        [$accounting, $inputs, $lots] = $this->fixture();
        $result = (new PharmacyJointCustody)->project($accounting, $inputs, $lots);
        $this->assertSame([['ingredient_lot_id' => 10, 'on_hand' => '6.250', 'reserved' => '2.000', 'disposed_unused' => '0.750', 'status' => 'quarantined', 'stock_increase' => '0.000', 'release_enabled' => false]], $result);
        $lots[0]['recall_reference'] = 'SYNTHETIC recall';
        $this->assertSame('recalled', (new PharmacyJointCustody)->project($accounting, $inputs, $lots)[0]['status']);
    }

    public function test_incomplete_duplicate_unknown_and_overdrawn_custody_fail(): void
    {
        foreach (['missing', 'duplicate', 'unknown', 'incomplete', 'insufficient', 'unit', 'no_lot'] as $case) {
            [$accounting, $inputs, $lots] = $this->fixture();
            if ($case === 'missing') {
                array_pop($inputs);
            }
            if ($case === 'duplicate') {
                $inputs[1]['allocation_id'] = 1;
            }
            if ($case === 'unknown') {
                $inputs[0]['disposed_unused'] = null;
            }
            if ($case === 'incomplete') {
                $inputs[0]['return_to_quarantine'] = '0.500';
            }
            if ($case === 'insufficient') {
                $lots[0]['reserved'] = '1.000';
            }
            if ($case === 'unit') {
                $lots[0]['quantity_unit'] = 'ml';
            }
            if ($case === 'no_lot') {
                $lots = [];
            }
            try {
                (new PharmacyJointCustody)->project($accounting, $inputs, $lots);
                $this->fail('Unsafe custody accepted: '.$case);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }
}
