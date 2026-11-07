<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** Allocates existing quarantined output to identified containers; never creates additional output. */
class PharmacyFinishedPackaging
{
    public function project(array $balance, array $input): array
    {
        $unit = $balance['unit'] ?? null;
        if (! in_array($unit, ['mg', 'g', 'mL', 'each', 'capsule', 'tablet'], true) || ($input['unit'] ?? null) !== $unit) {
            $this->fail('Packaging must retain the execution output unit.');
        }
        if (array_diff(array_keys($input), ['unit', 'containers', 'unpackaged_quantity', 'evidence'])) { $this->fail('Unexpected packaging fields.'); }
        $yield = $this->quantity($balance['recorded_yield'] ?? null);
        $disposed = $this->quantity($balance['previously_disposed'] ?? null);
        $held = $this->quantity($balance['held_output'] ?? null);
        if ($yield !== $disposed + $held) { $this->fail('Corrected output and prior disposal do not reconcile.'); }
        $rows = $input['containers'] ?? null;
        if (! is_array($rows) || ! array_is_list($rows) || count($rows) < 1 || count($rows) > 100) { $this->fail('Identify between one and one hundred containers.'); }
        $keys = []; $packaged = 0; $containers = [];
        foreach ($rows as $r) {
            if (! is_array($r) || array_diff(array_keys($r), ['identifier', 'quantity', 'container_reference', 'storage_reference'])
                || array_diff(['identifier', 'quantity', 'container_reference', 'storage_reference'], array_keys($r))) { $this->fail('Each container requires its quantity, packaging and storage evidence.'); }
            $key = $r['identifier'];
            if (! is_string($key) || ! preg_match('/^[A-Z0-9][A-Z0-9_-]{0,63}$/D', $key) || isset($keys[$key])) { $this->fail('Container identifiers must be unique uppercase identifiers.'); }
            $keys[$key] = true;
            $quantity = $this->quantity($r['quantity']);
            if ($quantity === 0) { $this->fail('A retained container must contain a positive quantity.'); }
            $this->text($r['container_reference']); $this->text($r['storage_reference']);
            $packaged += $quantity;
            $containers[] = ['identifier' => $key, 'quantity' => PharmacyStock::decimal($quantity),
                'container_reference' => $r['container_reference'], 'storage_reference' => $r['storage_reference'], 'status' => 'quarantined'];
        }
        $unpackaged = $this->quantity($input['unpackaged_quantity'] ?? null);
        if ($packaged + $unpackaged !== $held) { $this->fail('Container quantities plus unpackaged output must equal held output; previously disposed output cannot be packaged again.'); }
        $this->text($input['evidence'] ?? null);
        return ['containers' => $containers, 'packaged_quantity' => PharmacyStock::decimal($packaged),
            'unpackaged_quantity' => PharmacyStock::decimal($unpackaged), 'held_output' => PharmacyStock::decimal($held),
            'previously_disposed' => PharmacyStock::decimal($disposed), 'recorded_yield' => PharmacyStock::decimal($yield),
            'unit' => $unit, 'evidence' => $input['evidence'], 'output_stock_delta' => '0.000', 'ingredient_stock_delta' => '0.000',
            'requires_pharmacist_review' => true, 'release_enabled' => false];
    }

    private function quantity(mixed $value): int
    {
        if (! is_string($value) && ! is_int($value)) { $this->fail('Supply explicit decimal quantities; missing and zero are different.'); }
        return PharmacyStock::milli($value);
    }

    private function text(mixed $value): void
    {
        if (! is_string($value) || trim($value) === '' || strlen($value) > 5000) { $this->fail('Packaging, storage and reconciliation evidence must be explicit.'); }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['finished_packaging' => $message]);
    }
}
