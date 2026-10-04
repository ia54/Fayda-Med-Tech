<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** Reconciles explicit movements within one execution; no quantity correction, disposal or release. */
class PharmacyContainerRepackaging
{
    public function project(array $source, array $input): array
    {
        $this->keys($input, ['unit', 'new_containers', 'transfers', 'observed_containers', 'observed_unpackaged', 'process_evidence', 'reconciliation_evidence']);
        $unit = $source['unit'] ?? null;
        if (! in_array($unit, ['mg', 'g', 'mL', 'each', 'capsule', 'tablet'], true) || $input['unit'] !== $unit) {
            $this->fail('Repackaging must retain the execution output unit.');
        }
        $this->text($input['process_evidence']); $this->text($input['reconciliation_evidence']);
        $held = $this->quantity($source['held_output'] ?? null);
        $disposed = $this->quantity($source['previously_disposed'] ?? null);
        $yield = $this->quantity($source['recorded_yield'] ?? null);
        if ($held + $disposed !== $yield) { $this->fail('Existing yield and disposal must reconcile before repackaging.'); }
        $this->listing($source['containers'] ?? null, 1, 100);
        $this->listing($input['new_containers'], 0, 100);
        $containers = []; $before = []; $sum = 0;
        foreach ($source['containers'] as $row) {
            if (! is_array($row)) { $this->fail('Invalid retained container.'); }
            $id = $this->identifier($row['identifier'] ?? null);
            if (isset($containers[$id])) { $this->fail('Duplicate retained container identity.'); }
            $quantity = $this->quantity($row['quantity'] ?? null);
            $this->text($row['container_reference'] ?? null); $this->text($row['storage_reference'] ?? null);
            $containers[$id] = ['identifier' => $id, 'container_reference' => $row['container_reference'],
                'storage_reference' => $row['storage_reference'], 'new_identity' => false];
            $before[$id] = $quantity; $sum += $quantity;
        }
        // The empty map key denotes the unpackaged remainder; it cannot be a container identifier.
        $before[''] = $this->quantity($source['unpackaged_quantity'] ?? null);
        if ($sum + $before[''] !== $held) { $this->fail('Existing container quantities do not reconcile with held output.'); }
        foreach ($input['new_containers'] as $row) {
            if (! is_array($row)) { $this->fail('Invalid new container.'); }
            $this->keys($row, ['identifier', 'container_reference', 'storage_reference']);
            $id = $this->identifier($row['identifier']);
            if (isset($containers[$id])) { $this->fail('New container identifiers must not replace or duplicate retained identities.'); }
            $this->text($row['container_reference']); $this->text($row['storage_reference']);
            $containers[$id] = $row + ['new_identity' => true]; $before[$id] = 0;
        }
        if (count($containers) > 100) { $this->fail('Repackaging supports at most one hundred retained container identities.'); }
        $this->listing($input['transfers'], 1, 200);
        $outgoing = array_fill_keys(array_keys($before), 0); $incoming = $outgoing; $seen = []; $transfers = [];
        foreach ($input['transfers'] as $row) {
            if (! is_array($row)) { $this->fail('Invalid transfer finding.'); }
            $this->keys($row, ['from_identifier', 'to_identifier', 'quantity', 'evidence']);
            $from = $row['from_identifier'] === null ? '' : $this->identifier($row['from_identifier']);
            $to = $row['to_identifier'] === null ? '' : $this->identifier($row['to_identifier']);
            if (! array_key_exists($from, $before) || ! array_key_exists($to, $before) || $from === $to) { $this->fail('Each transfer must connect two distinct retained or new containers, or the unpackaged remainder.'); }
            $key = $from.'>'.$to;
            if (isset($seen[$key])) { $this->fail('Retain one measured transfer per source and destination pair.'); }
            $seen[$key] = true;
            $quantity = $this->quantity($row['quantity']);
            if ($quantity === 0) { $this->fail('A transfer must have a positive measured quantity.'); }
            $this->text($row['evidence']);
            $outgoing[$from] += $quantity; $incoming[$to] += $quantity;
            // A proposal cannot borrow incoming material to conceal overdrawn source containers.
            if ($outgoing[$from] > $before[$from]) { $this->fail('Transfers exceed the quantity originally held at a source. Record sequential repackaging separately.'); }
            $transfers[] = ['from_identifier' => $row['from_identifier'], 'to_identifier' => $row['to_identifier'],
                'quantity' => PharmacyStock::decimal($quantity), 'evidence' => $row['evidence']];
        }
        $this->listing($input['observed_containers'], count($containers), count($containers));
        $observed = [];
        foreach ($input['observed_containers'] as $row) {
            if (! is_array($row)) { $this->fail('Invalid observed container quantity.'); }
            $this->keys($row, ['identifier', 'quantity', 'evidence']);
            $id = $this->identifier($row['identifier']);
            if (! isset($containers[$id]) || isset($observed[$id])) { $this->fail('Observe every retained and new container exactly once.'); }
            $this->text($row['evidence']);
            $observed[$id] = ['quantity' => $this->quantity($row['quantity']), 'evidence' => $row['evidence']];
        }
        if (! is_array($input['observed_unpackaged'])) { $this->fail('Observe the unpackaged remainder explicitly.'); }
        $this->keys($input['observed_unpackaged'], ['quantity', 'evidence']); $this->text($input['observed_unpackaged']['evidence']);
        $observed[''] = ['quantity' => $this->quantity($input['observed_unpackaged']['quantity']), 'evidence' => $input['observed_unpackaged']['evidence']];
        $results = []; $afterSum = 0;
        foreach ($before as $id => $quantity) {
            $after = $quantity - $outgoing[$id] + $incoming[$id];
            if ($observed[$id]['quantity'] !== $after) { $this->fail('Each observed final quantity must equal its original amount minus outgoing plus incoming transfers. Record discrepancies separately.'); }
            if ($id !== '' && $containers[$id]['new_identity'] && $after === 0) { $this->fail('Each new container must retain a positive quantity.'); }
            $afterSum += $after;
            $results[$id] = ['previous_quantity' => PharmacyStock::decimal($quantity), 'outgoing_quantity' => PharmacyStock::decimal($outgoing[$id]),
                'incoming_quantity' => PharmacyStock::decimal($incoming[$id]), 'quantity' => PharmacyStock::decimal($after),
                'observation_evidence' => $observed[$id]['evidence'], 'status' => 'quarantined'];
        }
        if ($afterSum !== $held) { $this->fail('Repackaging must preserve all held output.'); }
        $rows = [];
        foreach ($containers as $id => $metadata) { $rows[] = $metadata + $results[$id]; }
        return ['containers' => $rows, 'unpackaged' => $results[''], 'transfers' => $transfers,
            'held_output' => PharmacyStock::decimal($held), 'recorded_yield' => PharmacyStock::decimal($yield),
            'previously_disposed' => PharmacyStock::decimal($disposed), 'unit' => $unit,
            'process_evidence' => $input['process_evidence'], 'reconciliation_evidence' => $input['reconciliation_evidence'],
            'output_stock_delta' => '0.000', 'ingredient_stock_delta' => '0.000', 'release_enabled' => false,
            'clinical_eligibility_verified' => false, 'requires_pharmacist_review' => true];
    }

    private function keys(array $input, array $keys): void
    {
        if (array_diff(array_keys($input), $keys) || array_diff($keys, array_keys($input))) { $this->fail('Missing or unexpected repackaging fields.'); }
    }
    private function listing(mixed $rows, int $min, int $max): void
    {
        if (! is_array($rows) || ! array_is_list($rows) || count($rows) < $min || count($rows) > $max) { $this->fail('Incomplete or excessive repackaging findings.'); }
    }
    private function identifier(mixed $id): string
    {
        if (! is_string($id) || ! preg_match('/^[A-Z0-9][A-Z0-9_-]{0,63}$/D', $id)) { $this->fail('Use an existing uppercase container identifier, or null for the unpackaged remainder.'); }
        return $id;
    }
    private function quantity(mixed $value): int
    {
        if (! is_string($value) && ! is_int($value)) { $this->fail('Supply explicit exact decimal quantities.'); }
        return PharmacyStock::milli($value);
    }
    private function text(mixed $value): void
    {
        if (! is_string($value) || trim($value) === '' || strlen($value) > 5000) { $this->fail('Retain explicit packaging, storage, process and measurement evidence.'); }
    }
    private function fail(string $message): never { throw ValidationException::withMessages(['container_repackaging' => $message]); }
}
