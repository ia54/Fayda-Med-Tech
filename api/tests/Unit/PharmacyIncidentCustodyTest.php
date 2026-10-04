<?php

namespace Tests\Unit;

use App\Services\PharmacyIncidentCustody;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PharmacyIncidentCustodyTest extends TestCase
{
    public function test_shared_lot_custody_preserves_other_reservations_and_deducts_only_unused_disposal(): void
    {
        $service = new PharmacyIncidentCustody;
        $lines = [
            ['unused_quantity' => '1.125', 'return_to_quarantine' => '1.100', 'disposed_unused' => '0.025'],
            ['unused_quantity' => '0.875', 'return_to_quarantine' => '0.500', 'disposed_unused' => '0.375'],
        ];
        $result = $service->projectLot('5', '3', $lines, 'quarantined', null);
        $this->assertSame('4.600', $result['on_hand']);
        $this->assertSame('1.000', $result['reserved']);
        $this->assertSame('0.400', $result['disposed_unused']);
        $this->assertSame('quarantined', $result['status']);
        $this->assertFalse($result['release_enabled']);
        foreach ([['recalled', null], ['quarantined', 'SYNTHETIC-RECALL']] as [$status, $reference]) {
            $this->assertSame('recalled', $service->projectLot('5', '3', $lines, $status, $reference)['status']);
        }
    }

    public function test_incomplete_or_inconsistent_lot_custody_cannot_be_projected(): void
    {
        $line = ['unused_quantity' => '2', 'return_to_quarantine' => '1', 'disposed_unused' => '1'];
        foreach ([['5', '1', [$line]], ['1', '2', [$line]], ['5', '3', []], ['5', '3', [['return_to_quarantine' => 0, 'disposed_unused' => 0]]]] as [$stock, $reserved, $lines]) {
            try {
                (new PharmacyIncidentCustody)->projectLot($stock, $reserved, $lines, 'quarantined', null);
                $this->fail('Invalid custody accepted');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }

    public function test_split_unused_custody_never_increases_stock_or_releases_material(): void
    {
        $result = (new PharmacyIncidentCustody)->line('1.125', ['return_to_quarantine' => '1.100', 'disposed_unused' => '0.025']);
        $this->assertSame('1.125', $result['unused_quantity']);
        $this->assertSame('0.000', $result['stock_increase']);
        $this->assertFalse($result['release_enabled']);
        $zero = (new PharmacyIncidentCustody)->line('0', ['return_to_quarantine' => 0, 'disposed_unused' => 0]);
        $this->assertSame('0.000', $zero['unused_quantity']);
    }

    public function test_unknown_negative_excess_and_missing_custody_quantities_are_rejected(): void
    {
        foreach ([['return_to_quarantine' => null, 'disposed_unused' => 1], ['return_to_quarantine' => -1, 'disposed_unused' => 2],
            ['return_to_quarantine' => '1.001', 'disposed_unused' => 0], ['return_to_quarantine' => 0, 'disposed_unused' => 0],
            ['return_to_quarantine' => 1], ['return_to_quarantine' => true, 'disposed_unused' => 0]] as $input) {
            try {
                (new PharmacyIncidentCustody)->line(1, $input);
                $this->fail('Invalid custody accepted');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }
}
