<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** Pure correction accounting; callers must retain evidence and require independent review. */
class PharmacyConsumptionCorrection
{
    public function project(array $source, array $correction): array
    {
        $unit = $source['unit'] ?? null;
        if (! in_array($unit, ['mg', 'g', 'mL', 'each', 'capsule', 'tablet'], true) || ($correction['unit'] ?? null) !== $unit) {
            $this->fail('Retain the ingredient receipt unit; conversions require separate evidence.');
        }
        $original = $this->quantity($source, 'original_consumed');
        $current = $this->quantity($source, 'accounted_consumed');
        $onHand = $this->quantity($source, 'on_hand');
        $reserved = $this->quantity($source, 'reserved');
        $corrected = $this->quantity($correction, 'corrected_consumed');
        $observed = $this->quantity($correction, 'observed_on_hand');
        if ($reserved > $onHand || $corrected === $current) {
            $this->fail('Reconcile existing reservations and require an actual consumption correction.');
        }
        $delta = $corrected - $current;
        $expected = $onHand - $delta;
        if ($expected < $reserved || $expected > 999999999999 || $observed !== $expected) {
            $this->fail('Observed receipt balance must reconcile exactly without consuming other reservations. Investigate any unexplained difference separately.');
        }
        foreach (['reason', 'measurement_evidence', 'source_evidence', 'receipt_count_evidence'] as $field) {
            if (! is_string($correction[$field] ?? null) || trim($correction[$field]) === '' || strlen($correction[$field]) > 5000) {
                $this->fail('Retain the reason, original source, measurement and receipt-count evidence.');
            }
        }
        return [
            'original_consumed' => PharmacyStock::decimal($original),
            'previous_accounted_consumed' => PharmacyStock::decimal($current),
            'corrected_consumed' => PharmacyStock::decimal($corrected),
            'previous_on_hand' => PharmacyStock::decimal($onHand),
            'corrected_on_hand' => PharmacyStock::decimal($observed),
            'reserved_unchanged' => PharmacyStock::decimal($reserved),
            'stock_direction' => $delta > 0 ? 'decrease' : 'increase',
            'stock_change_quantity' => PharmacyStock::decimal(abs($delta)),
            'unit' => $unit,
            'required_receipt_status' => 'quarantined',
            'release_enabled' => false,
        ];
    }

    private function quantity(array $values, string $field): int
    {
        if (! isset($values[$field]) || is_bool($values[$field]) || ! is_scalar($values[$field])) {
            $this->fail('Explicit measured quantities are required; unknown is not zero.');
        }
        return PharmacyStock::milli($values[$field]);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['consumption_correction' => $message]);
    }
}
