<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** Pure container-level accounting; persistence and aggregate output movements are separate. */
class PharmacyContainerCustody
{
    public function project(array $source, array $decision): array
    {
        $unit = $source['unit'] ?? null;
        if (! in_array($unit, ['mg', 'g', 'mL', 'each', 'capsule', 'tablet'], true) || ($decision['unit'] ?? null) !== $unit) {
            $this->fail('Retain the original output unit.');
        }
        $this->keys($decision, ['unit', 'containers', 'unpackaged', 'evidence']);
        $this->text($decision['evidence']);
        if (! is_array($source['containers'] ?? null) || ! array_is_list($source['containers']) || count($source['containers']) < 1 || count($source['containers']) > 100
            || ! is_array($decision['containers']) || ! array_is_list($decision['containers']) || count($decision['containers']) !== count($source['containers'])) {
            $this->fail('Every retained container must have exactly one custody finding.');
        }
        $findings = [];
        foreach ($decision['containers'] as $row) {
            if (! is_array($row)) { $this->fail('Invalid container finding.'); }
            $this->keys($row, ['identifier', 'retained_quarantined', 'disposed_output', 'unaccounted_output', 'evidence']);
            if (! is_string($row['identifier']) || isset($findings[$row['identifier']])) { $this->fail('Duplicate or invalid container finding.'); }
            $findings[$row['identifier']] = $row;
        }
        $rows = []; $seen = []; $held = 0; $retained = 0; $disposed = 0; $unaccounted = 0;
        foreach ($source['containers'] as $container) {
            $key = $container['identifier'] ?? null;
            if (! is_string($key) || ! preg_match('/^[A-Z0-9][A-Z0-9_-]{0,63}$/D', $key) || isset($seen[$key]) || ! isset($findings[$key])) {
                $this->fail('Container identities must exactly match the retained source.');
            }
            $seen[$key] = true;
            $quantity = $this->quantity($container['quantity'] ?? null);
            $finding = $findings[$key]; unset($finding['identifier']);
            $row = $this->finding($quantity, $finding);
            $rows[] = ['identifier' => $key] + $row;
            $held += $quantity; $retained += $this->quantity($row['retained_quarantined']);
            $disposed += $this->quantity($row['disposed_output']); $unaccounted += $this->quantity($row['unaccounted_output']);
        }
        $unpackagedQuantity = $this->quantity($source['unpackaged_quantity'] ?? null);
        $unpackaged = $this->finding($unpackagedQuantity, $decision['unpackaged']);
        $held += $unpackagedQuantity;
        $retained += $this->quantity($unpackaged['retained_quarantined']);
        $disposed += $this->quantity($unpackaged['disposed_output']); $unaccounted += $this->quantity($unpackaged['unaccounted_output']);
        $prior = $this->quantity($source['previously_disposed'] ?? null); $yield = $this->quantity($source['recorded_yield'] ?? null);
        if ($held !== $this->quantity($source['held_output'] ?? null) || $prior + $held !== $yield) { $this->fail('Container source does not reconcile to corrected output.'); }
        return ['containers' => $rows, 'unpackaged' => $unpackaged, 'recorded_yield' => PharmacyStock::decimal($yield),
            'previously_disposed' => PharmacyStock::decimal($prior), 'held_before' => PharmacyStock::decimal($held),
            'retained_quarantined' => PharmacyStock::decimal($retained), 'disposed_output' => PharmacyStock::decimal($disposed),
            'total_disposed' => PharmacyStock::decimal($prior + $disposed), 'unaccounted_output' => PharmacyStock::decimal($unaccounted),
            'unit' => $unit, 'accounting_complete' => $unaccounted === 0, 'evidence' => $decision['evidence'],
            'ingredient_stock_delta' => '0.000', 'release_enabled' => false];
    }

    private function finding(int $quantity, mixed $input): array
    {
        if (! is_array($input)) { $this->fail('Explicit custody findings are required.'); }
        $this->keys($input, ['retained_quarantined', 'disposed_output', 'unaccounted_output', 'evidence']);
        $this->text($input['evidence']);
        $retained = $this->quantity($input['retained_quarantined']); $disposed = $this->quantity($input['disposed_output']); $missing = $this->quantity($input['unaccounted_output']);
        if ($retained + $disposed + $missing !== $quantity) { $this->fail('Each container and unpackaged remainder must reconcile independently; quantities cannot be shifted between containers.'); }
        return ['quantity_before' => PharmacyStock::decimal($quantity), 'retained_quarantined' => PharmacyStock::decimal($retained),
            'disposed_output' => PharmacyStock::decimal($disposed), 'unaccounted_output' => PharmacyStock::decimal($missing), 'evidence' => $input['evidence']];
    }

    private function keys(array $input, array $keys): void
    {
        if (array_diff(array_keys($input), $keys) || array_diff($keys, array_keys($input))) { $this->fail('Missing or unexpected custody fields.'); }
    }

    private function quantity(mixed $value): int
    {
        if (! is_string($value) && ! is_int($value)) { $this->fail('Explicit exact decimal quantities are required.'); }
        return PharmacyStock::milli($value);
    }

    private function text(mixed $value): void
    {
        if (! is_string($value) || trim($value) === '' || strlen($value) > 5000) { $this->fail('Explicit custody evidence is required.'); }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['container_custody' => $message]);
    }
}
