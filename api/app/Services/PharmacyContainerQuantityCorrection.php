<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** Explicit recount corrections preserve container identity and prior disposal; no physical transfer or release. */
class PharmacyContainerQuantityCorrection
{
    public function project(array $source, array $input): array
    {
        $unit = $source['unit'] ?? null;
        if (! in_array($unit, ['mg', 'g', 'mL', 'each', 'capsule', 'tablet'], true) || ($input['unit'] ?? null) !== $unit) {
            $this->fail('Recounts must retain the original output unit.');
        }
        $this->keys($input, ['unit', 'corrected_yield', 'containers', 'unpackaged', 'reason', 'measurement_evidence', 'source_evidence']);
        foreach (['reason', 'measurement_evidence', 'source_evidence'] as $field) { $this->text($input[$field]); }
        $original = $this->quantity($source['original_yield'] ?? null);
        $previous = $this->quantity($source['recorded_yield'] ?? null);
        $disposed = $this->quantity($source['previously_disposed'] ?? null);
        $held = $this->quantity($source['held_output'] ?? null);
        $corrected = $this->quantity($input['corrected_yield']);
        if ($previous !== $disposed + $held) { $this->fail('Existing output must reconcile before recount correction.'); }
        if (! is_array($source['containers'] ?? null) || ! array_is_list($source['containers']) || count($source['containers']) < 1 || count($source['containers']) > 100
            || ! is_array($input['containers']) || ! array_is_list($input['containers']) || count($source['containers']) !== count($input['containers'])) {
            $this->fail('Provide a measured recount for every retained container.');
        }
        $findings = [];
        foreach ($input['containers'] as $row) {
            if (! is_array($row)) { $this->fail('Invalid container recount.'); }
            $this->keys($row, ['identifier', 'observed_quantity', 'reason', 'measurement_evidence', 'source_evidence']);
            if (! is_string($row['identifier']) || isset($findings[$row['identifier']])) { $this->fail('Duplicate or invalid recount identifier.'); }
            $findings[$row['identifier']] = $row;
        }
        $seen = []; $rows = []; $before = 0; $after = 0; $changed = false;
        foreach ($source['containers'] as $container) {
            $key = $container['identifier'] ?? null;
            if (! is_string($key) || ! preg_match('/^[A-Z0-9][A-Z0-9_-]{0,63}$/D', $key) || isset($seen[$key]) || ! isset($findings[$key])) {
                $this->fail('Recounts must exactly match the retained container identities.');
            }
            $seen[$key] = true;
            $prior = $this->quantity($container['quantity'] ?? null);
            $finding = $findings[$key]; unset($finding['identifier']);
            $row = $this->finding($prior, $finding);
            $rows[] = ['identifier' => $key] + $row;
            $before += $prior; $after += $this->quantity($row['observed_quantity']);
            $changed = $changed || $row['change_direction'] !== 'unchanged';
        }
        $unpackagedBefore = $this->quantity($source['unpackaged_quantity'] ?? null);
        $unpackaged = $this->finding($unpackagedBefore, $input['unpackaged']);
        $before += $unpackagedBefore; $after += $this->quantity($unpackaged['observed_quantity']);
        $changed = $changed || $unpackaged['change_direction'] !== 'unchanged';
        if ($before !== $held || $after + $disposed !== $corrected) {
            $this->fail('Observed container and unpackaged amounts plus all prior disposal must equal corrected yield.');
        }
        if (! $changed) { $this->fail('A quantity correction requires a changed measured quantity; use documentary findings for unchanged amounts.'); }
        return ['containers' => $rows, 'unpackaged' => $unpackaged, 'original_yield' => PharmacyStock::decimal($original),
            'previous_accounted_yield' => PharmacyStock::decimal($previous), 'corrected_yield' => PharmacyStock::decimal($corrected),
            'previously_disposed' => PharmacyStock::decimal($disposed), 'previous_held_output' => PharmacyStock::decimal($held),
            'corrected_held_output' => PharmacyStock::decimal($after), 'change_direction' => $this->direction($previous, $corrected),
            'change_quantity' => PharmacyStock::decimal(abs($corrected - $previous)), 'unit' => $unit,
            'reason' => $input['reason'], 'measurement_evidence' => $input['measurement_evidence'], 'source_evidence' => $input['source_evidence'],
            'ingredient_stock_delta' => '0.000', 'physical_transfer' => false, 'release_enabled' => false];
    }

    private function finding(int $prior, mixed $input): array
    {
        if (! is_array($input)) { $this->fail('Explicit recount findings are required.'); }
        $this->keys($input, ['observed_quantity', 'reason', 'measurement_evidence', 'source_evidence']);
        foreach (['reason', 'measurement_evidence', 'source_evidence'] as $field) { $this->text($input[$field]); }
        $observed = $this->quantity($input['observed_quantity']);
        return ['previous_quantity' => PharmacyStock::decimal($prior), 'observed_quantity' => PharmacyStock::decimal($observed),
            'change_direction' => $this->direction($prior, $observed), 'change_quantity' => PharmacyStock::decimal(abs($observed - $prior)),
            'reason' => $input['reason'], 'measurement_evidence' => $input['measurement_evidence'], 'source_evidence' => $input['source_evidence']];
    }

    private function direction(int $before, int $after): string { return $after === $before ? 'unchanged' : ($after > $before ? 'increase' : 'decrease'); }
    private function keys(array $input, array $keys): void
    {
        if (array_diff(array_keys($input), $keys) || array_diff($keys, array_keys($input))) { $this->fail('Missing or unexpected recount fields.'); }
    }
    private function quantity(mixed $value): int
    {
        if (! is_string($value) && ! is_int($value)) { $this->fail('Supply explicit exact decimal quantities.'); }
        return PharmacyStock::milli($value);
    }
    private function text(mixed $value): void
    {
        if (! is_string($value) || trim($value) === '' || strlen($value) > 5000) { $this->fail('Each recount needs a reason, measured evidence and original-source evidence.'); }
    }
    private function fail(string $message): never { throw ValidationException::withMessages(['container_quantity_correction' => $message]); }
}
