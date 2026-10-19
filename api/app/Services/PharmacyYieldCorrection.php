<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** Projection only: correction evidence cannot release output or move ingredients. */
class PharmacyYieldCorrection
{
    public function project(array $source, array $correction): array
    {
        $unit = $source['unit'] ?? null;
        if (! in_array($unit, ['mg', 'g', 'mL', 'each', 'capsule', 'tablet'], true) || ($correction['unit'] ?? null) !== $unit) {
            $this->fail('A yield correction must retain the execution unit.');
        }
        $original = $this->quantity($source, 'original_yield');
        $current = $this->quantity($source, 'accounted_yield');
        $disposed = $this->quantity($source, 'previously_disposed');
        $held = $this->quantity($source, 'held_output');
        $corrected = $this->quantity($correction, 'corrected_yield');
        $observed = $this->quantity($correction, 'observed_held');
        if ($disposed + $held !== $current) {
            $this->fail('Existing custody must reconcile before a yield correction can be proposed.');
        }
        if ($corrected === $current) {
            $this->fail('Use documentary addenda when the accounted yield is unchanged.');
        }
        if ($corrected < $disposed || $observed + $disposed !== $corrected) {
            $this->fail('Corrected yield must account for observed held output and all previously disposed output. Prior disposal cannot be erased.');
        }
        foreach (['reason', 'measurement_evidence', 'source_evidence'] as $field) {
            if (! is_string($correction[$field] ?? null) || trim($correction[$field]) === '' || strlen($correction[$field]) > 5000) {
                $this->fail('Retain a correction reason, measurement evidence and original-source evidence.');
            }
        }

        return [
            'original_yield' => PharmacyStock::decimal($original),
            'previous_accounted_yield' => PharmacyStock::decimal($current),
            'corrected_yield' => PharmacyStock::decimal($corrected),
            'previously_disposed' => PharmacyStock::decimal($disposed),
            'previous_held_output' => PharmacyStock::decimal($held),
            'corrected_held_output' => PharmacyStock::decimal($observed),
            'change_direction' => $corrected > $current ? 'increase' : 'decrease',
            'change_quantity' => PharmacyStock::decimal(abs($corrected - $current)),
            'unit' => $unit,
            'ingredient_stock_delta' => '0.000',
            'release_enabled' => false,
        ];
    }

    private function quantity(array $values, string $field): int
    {
        if (! isset($values[$field]) || is_bool($values[$field]) || ! is_scalar($values[$field])) {
            $this->fail('All quantities must be explicit; unknown is not zero.');
        }

        return PharmacyStock::milli($values[$field]);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['yield_correction' => $message]);
    }
}
