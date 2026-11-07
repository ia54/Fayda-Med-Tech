<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** Pure post-execution output accounting. No ingredient movements or release authority. */
class PharmacyExecutionCustody
{
    public function project(array $source, array $decision): array
    {
        $unit = $source['unit'] ?? null;
        if (! in_array($unit, ['mg', 'g', 'mL', 'each', 'capsule', 'tablet'], true)
            || ($decision['unit'] ?? null) !== $unit) {
            $this->fail('Output quantities must use the original execution unit.');
        }
        $original = $this->quantity($source, 'recorded_yield');
        $priorDisposed = $this->quantity($source, 'previously_disposed');
        $held = $this->quantity($source, 'held_output');
        if ($priorDisposed + $held !== $original) {
            $this->fail('The retained output history does not reconcile to the original yield.');
        }
        $retained = $this->quantity($decision, 'retained_quarantined');
        $disposed = $this->quantity($decision, 'disposed_output');
        $unaccounted = $this->quantity($decision, 'unaccounted_output');
        if ($retained + $disposed + $unaccounted !== $held) {
            $this->fail('Retained, disposed and unaccounted output must equal the currently held output. Yield corrections require separate evidence and review.');
        }
        if (! is_string($decision['evidence'] ?? null) || trim($decision['evidence']) === '') {
            $this->fail('Output custody findings and supporting evidence are required.');
        }

        return [
            'recorded_yield' => PharmacyStock::decimal($original),
            'previously_disposed' => PharmacyStock::decimal($priorDisposed),
            'disposed_output' => PharmacyStock::decimal($disposed),
            'total_disposed' => PharmacyStock::decimal($priorDisposed + $disposed),
            'retained_quarantined' => PharmacyStock::decimal($retained),
            'unaccounted_output' => PharmacyStock::decimal($unaccounted),
            'unit' => $unit,
            'accounting_complete' => $unaccounted === 0,
            'ingredient_stock_delta' => '0.000',
            'release_enabled' => false,
        ];
    }

    private function quantity(array $values, string $key): int
    {
        if (! array_key_exists($key, $values) || $values[$key] === null || is_bool($values[$key]) || ! is_scalar($values[$key])) {
            $this->fail('Explicit quantities are required; unknown and zero are different.');
        }

        return PharmacyStock::milli($values[$key]);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['output_custody' => $message]);
    }
}
