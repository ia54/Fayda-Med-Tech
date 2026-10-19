<?php

namespace Tests\Unit;

use App\Services\PharmacyConsumptionCorrection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PharmacyConsumptionCorrectionTest extends TestCase
{
    private function source(): array
    {
        return ['original_consumed' => '2', 'accounted_consumed' => '2', 'on_hand' => '8', 'reserved' => '3', 'unit' => 'g'];
    }

    private function correction(): array
    {
        return ['corrected_consumed' => '2.5', 'observed_on_hand' => '7.5', 'unit' => 'g', 'reason' => 'SYNTHETIC transcription',
            'measurement_evidence' => 'SYNTHETIC measure', 'source_evidence' => 'SYNTHETIC execution', 'receipt_count_evidence' => 'SYNTHETIC count'];
    }

    public function test_extra_consumption_preserves_original_and_other_reservations(): void
    {
        $p = (new PharmacyConsumptionCorrection)->project($this->source(), $this->correction());
        $this->assertSame('2.000', $p['original_consumed']);
        $this->assertSame('7.500', $p['corrected_on_hand']);
        $this->assertSame('3.000', $p['reserved_unchanged']);
        $this->assertSame('decrease', $p['stock_direction']);
        $this->assertSame('0.500', $p['stock_change_quantity']);
        $this->assertSame('quarantined', $p['required_receipt_status']);
        $this->assertFalse($p['release_enabled']);
    }

    public function test_later_correction_uses_current_accounting_and_exact_observed_stock(): void
    {
        $p = (new PharmacyConsumptionCorrection)->project(array_replace($this->source(), ['accounted_consumed' => '2.5', 'on_hand' => '7.5']),
            array_replace($this->correction(), ['corrected_consumed' => '1.5', 'observed_on_hand' => '8.5']));
        $this->assertSame('2.000', $p['original_consumed']);
        $this->assertSame('increase', $p['stock_direction']);
        $this->assertSame('1.000', $p['stock_change_quantity']);
        $this->assertSame('8.500', $p['corrected_on_hand']);
    }

    public function test_unsafe_or_unexplained_corrections_fail(): void
    {
        foreach ([['corrected_consumed' => '8', 'observed_on_hand' => '2'], ['observed_on_hand' => '7'], ['observed_on_hand' => null],
            ['corrected_consumed' => '2'], ['corrected_consumed' => '-1'], ['corrected_consumed' => '2.0001'], ['unit' => 'mg'], ['receipt_count_evidence' => ' ']] as $change) {
            try { (new PharmacyConsumptionCorrection)->project($this->source(), array_replace($this->correction(), $change)); $this->fail('Unsafe correction accepted'); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
    }
}
