<?php

namespace Tests\Unit;

use App\Services\PharmacyIncidentAccounting;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PharmacyIncidentAccountingTest extends TestCase
{
    private function quantities(array $changes = []): array
    {
        return array_replace(['reserved_quantity' => '2.000', 'additional_taken' => '0.125',
            'consumed' => '1.100', 'unused_retained' => '0.900', 'disposed_unused' => '0.025', 'unaccounted' => '0.100'], $changes);
    }

    public function test_fractional_custody_conservation_preserves_unresolved_and_unused_material(): void
    {
        $result = (new PharmacyIncidentAccounting)->line($this->quantities());
        $this->assertSame('2.125', $result['custody_quantity']);
        $this->assertSame('0.100', $result['quantities']['unaccounted']);
        $this->assertTrue($result['unaccounted_remaining']);
        $this->assertTrue($result['unused_custody_remaining']);
        $this->assertFalse($result['stock_adjusted']);
        $zero = (new PharmacyIncidentAccounting)->line($this->quantities(['consumed' => '2.125', 'unused_retained' => 0, 'disposed_unused' => 0, 'unaccounted' => 0]));
        $this->assertFalse($zero['unaccounted_remaining']);
        $this->assertFalse($zero['unused_custody_remaining']);
    }

    public function test_unknown_negative_extra_precision_and_double_accounted_material_are_rejected(): void
    {
        foreach ([['consumed' => null], ['unaccounted' => false], ['additional_taken' => '-1'], ['consumed' => '1.1001'], ['disposed_unused' => '1.125']] as $invalid) {
            try {
                (new PharmacyIncidentAccounting)->line($this->quantities($invalid));
                $this->fail('Invalid accounting accepted');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $missing = $this->quantities();
        unset($missing['unused_retained']);
        $this->expectException(ValidationException::class);
        (new PharmacyIncidentAccounting)->line($missing);
    }
}
