<?php

namespace Tests\Unit;

use App\Services\PharmacyCompoundingIncident;
use App\Services\PharmacyJointAccounting;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PharmacyJointAccountingTest extends TestCase
{
    private function fixture(): array
    {
        $prior = [['allocation_id' => 2, 'ingredient_lot_id' => 10, 'unit' => 'g', 'accounting' => ['quantities' => ['reserved_quantity' => '2.000', 'additional_taken' => '0.000', 'consumed' => '1.000', 'unused_retained' => '0.750', 'disposed_unused' => '0.250', 'unaccounted' => '0.000']]]];
        $snapshot = ['members' => [['id' => 1, 'status' => 'unresolved'], ['id' => 2, 'status' => 'accounted_custody_held']],
            'lines' => [['incident_id' => 1, 'allocation_id' => 1, 'ingredient_lot_id' => 10, 'quantity_unit' => 'g', 'reserved_quantity' => '2.000'], ['incident_id' => 2, 'allocation_id' => 2, 'ingredient_lot_id' => 10, 'quantity_unit' => 'g', 'reserved_quantity' => '2.000']],
            'accounting' => [['id' => 7, 'incident_id' => 2, 'status' => 'applied', 'proposal' => json_encode($prior), 'proposal_hash' => (new PharmacyCompoundingIncident)->digest($prior)]]];
        $input = [['allocation_id' => 1, 'additional_taken' => 0, 'consumed' => 1, 'unused_retained' => 1, 'disposed_unused' => 0, 'unaccounted' => 0, 'evidence' => 'SYNTHETIC newly confirmed'], ['allocation_id' => 2, 'additional_taken' => 0, 'consumed' => 0, 'unused_retained' => '0.750', 'disposed_unused' => 0, 'unaccounted' => 0, 'evidence' => 'SYNTHETIC prior accounting retained']];

        return [$snapshot, $input];
    }

    public function test_mixed_accounting_preserves_prior_consumption_without_repeating_it(): void
    {
        [$snapshot,$input] = $this->fixture();
        $result = (new PharmacyJointAccounting)->proposal($snapshot, $input);
        $this->assertSame('new_accounting', $result[0]['mode']);
        $this->assertSame('1.000', $result[0]['accounting']['quantities']['consumed']);
        $this->assertSame('already_accounted', $result[1]['mode']);
        $this->assertSame('0.750', $result[1]['unused_retained']);
        $this->assertSame(7, $result[1]['reconciliation_id']);
        $this->assertArrayNotHasKey('accounting', $result[1]);
    }

    public function test_missing_duplicate_repeated_consumption_and_corrupt_prior_evidence_fail(): void
    {
        foreach (['missing', 'duplicate', 'repeat', 'changed_unused', 'corrupt'] as $case) {
            [$snapshot,$input] = $this->fixture();
            if ($case === 'missing') {
                array_pop($input);
            }
            if ($case === 'duplicate') {
                $input[1]['allocation_id'] = 1;
            }
            if ($case === 'repeat') {
                $input[1]['consumed'] = 1;
            }
            if ($case === 'changed_unused') {
                $input[1]['unused_retained'] = 1;
            }
            if ($case === 'corrupt') {
                $snapshot['accounting'][0]['proposal'] = '[]';
            }
            try {
                (new PharmacyJointAccounting)->proposal($snapshot, $input);
                $this->fail('Invalid joint proposal accepted');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }

    public function test_prior_accounting_requires_conserved_complete_quantities_even_with_valid_hash(): void
    {
        foreach (['missing', 'unbalanced', 'changed_reservation', 'unaccounted'] as $case) {
            [$snapshot, $input] = $this->fixture();
            $prior = json_decode($snapshot['accounting'][0]['proposal'], true);
            $quantities = &$prior[0]['accounting']['quantities'];
            if ($case === 'missing') {
                unset($quantities['consumed']);
            } elseif ($case === 'unbalanced') {
                $quantities['consumed'] = '1.500';
            } elseif ($case === 'changed_reservation') {
                $quantities['reserved_quantity'] = '3.000';
                $quantities['consumed'] = '2.000';
            } else {
                $quantities['unaccounted'] = '0.250';
                $quantities['disposed_unused'] = '0.000';
            }
            unset($quantities);
            $snapshot['accounting'][0]['proposal'] = json_encode($prior);
            $snapshot['accounting'][0]['proposal_hash'] = (new PharmacyCompoundingIncident)->digest($prior);
            try {
                (new PharmacyJointAccounting)->proposal($snapshot, $input);
                $this->fail('Invalid prior accounting accepted: '.$case);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }

    public function test_joint_projection_preserves_other_reservations_and_prior_custody(): void
    {
        [$snapshot, $input] = $this->fixture();
        $snapshot['lots'] = [['id' => 10, 'on_hand' => '8.000', 'reserved' => '4.750', 'status' => 'quarantined']];
        $result = (new PharmacyJointAccounting)->project($snapshot, $input);
        $this->assertSame([['ingredient_lot_id' => 10, 'deduct' => '1.000', 'on_hand' => '7.000', 'reserved' => '3.750', 'status' => 'quarantined']], $result);
        $snapshot['lots'][0]['recall_reference'] = 'SYNTHETIC recall';
        $this->assertSame('recalled', (new PharmacyJointAccounting)->project($snapshot, $input)[0]['status']);
    }

    public function test_joint_projection_rejects_incomplete_custody_and_competing_stock_consumption(): void
    {
        foreach (['prior_custody_missing', 'over_reserved', 'other_reservation', 'unknown', 'missing_lot', 'duplicate_lot'] as $case) {
            [$snapshot, $input] = $this->fixture();
            $snapshot['lots'] = [['id' => 10, 'on_hand' => '8.000', 'reserved' => '4.750', 'status' => 'quarantined']];
            if ($case === 'prior_custody_missing') {
                $snapshot['lots'][0]['reserved'] = '2.000';
            } elseif ($case === 'over_reserved') {
                $snapshot['lots'][0]['on_hand'] = '4.000';
            } elseif ($case === 'other_reservation') {
                $input[0]['additional_taken'] = '5.000';
                $input[0]['consumed'] = '6.000';
            } elseif ($case === 'unknown') {
                $input[0]['consumed'] = '0.500';
                $input[0]['unaccounted'] = '0.500';
            } elseif ($case === 'missing_lot') {
                $snapshot['lots'] = [];
            } else {
                $snapshot['lots'][] = $snapshot['lots'][0];
            }
            try {
                (new PharmacyJointAccounting)->project($snapshot, $input);
                $this->fail('Unsafe projection accepted: '.$case);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }

    public function test_shared_receipts_are_aggregated_once_across_multiple_new_incidents(): void
    {
        [$snapshot, $input] = $this->fixture();
        $snapshot['members'][] = ['id' => 3, 'status' => 'unresolved'];
        $snapshot['lines'][] = ['incident_id' => 3, 'allocation_id' => 3, 'ingredient_lot_id' => 10, 'quantity_unit' => 'g', 'reserved_quantity' => '3.000'];
        $snapshot['lines'][] = ['incident_id' => 3, 'allocation_id' => 4, 'ingredient_lot_id' => 11, 'quantity_unit' => 'g', 'reserved_quantity' => '1.500'];
        $input[] = ['allocation_id' => 3, 'additional_taken' => '0.500', 'consumed' => '2.000', 'unused_retained' => '1.000', 'disposed_unused' => '0.500', 'unaccounted' => 0, 'evidence' => 'SYNTHETIC shared receipt measured'];
        $input[] = ['allocation_id' => 4, 'additional_taken' => 0, 'consumed' => '0.750', 'unused_retained' => '0.500', 'disposed_unused' => '0.250', 'unaccounted' => 0, 'evidence' => 'SYNTHETIC second receipt measured'];
        $snapshot['lots'] = [
            ['id' => 11, 'on_hand' => '5.000', 'reserved' => '2.500', 'status' => 'recalled'],
            ['id' => 10, 'on_hand' => '12.000', 'reserved' => '7.750', 'status' => 'quarantined'],
        ];
        $service = new PharmacyJointAccounting;
        $result = $service->project($snapshot, $input);
        $this->assertSame([
            ['ingredient_lot_id' => 10, 'deduct' => '3.500', 'on_hand' => '8.500', 'reserved' => '4.750', 'status' => 'quarantined'],
            ['ingredient_lot_id' => 11, 'deduct' => '1.000', 'on_hand' => '4.000', 'reserved' => '1.500', 'status' => 'recalled'],
        ], $result);
        $snapshot['lines'] = array_reverse($snapshot['lines']);
        $this->assertSame($result, $service->project($snapshot, array_reverse($input)));
        // The second receipt fails even when every first-receipt quantity is valid.
        $snapshot['lots'][0]['on_hand'] = '2.500';
        $input[3]['additional_taken'] = '1.000';
        $input[3]['consumed'] = '1.750';
        $this->expectException(ValidationException::class);
        $service->project($snapshot, $input);
    }
}
