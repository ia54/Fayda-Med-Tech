<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Quantity authority only. Transfer acceptance must independently validate people and evidence. */
class PharmacyAuthorizationLimits
{
    public function forPrescription(object $rx): array
    {
        $quantity = PharmacyStock::milli($rx->quantity);
        $count = (int) $rx->refills_authorized + 1;
        if (! ($rx->incoming_transfer_id ?? null)) {
            return ['limits' => array_fill(1, $count, $quantity), 'transfer' => null];
        }
        $fail = function (): never {
            throw ValidationException::withMessages(['quantity' => 'The transferred authorization record is inconsistent. Investigate before further supply.']);
        };
        $receipt = DB::table('pharmacy_rx_transfer_receipts')->find($rx->incoming_transfer_id);
        if (! $receipt || (int) $receipt->organization_id !== (int) $rx->organization_id
            || (int) $receipt->destination_prescription_id !== (int) $rx->id
            || (int) $receipt->destination_location_id !== (int) $rx->location_id
            || (int) $receipt->source_location_id === (int) $rx->location_id
            || (int) $receipt->sent_by === (int) $receipt->accepted_by
            || $rx->controlled || $rx->compounded
            || ! hash_equals($receipt->snapshot_sha256, hash('sha256', $receipt->source_snapshot))) $fail();
        $snapshot = json_decode($receipt->source_snapshot, true);
        $order = $snapshot['order'] ?? null;
        $allowances = $snapshot['allowances'] ?? null;
        if (! is_array($order) || ! is_array($allowances) || ! array_is_list($allowances) || count($allowances) !== $count
            || ($snapshot['holds'] ?? null) !== [] || $count < 1 || $count > 100) $fail();
        $source = DB::table('pharmacy_prescriptions')->find($receipt->source_prescription_id);
        if (! $source || ! $source->discontinued_at || (int) $source->organization_id !== (int) $rx->organization_id
            || (int) $source->location_id !== (int) $receipt->source_location_id
            || $source->controlled || $source->compounded
            || (int) ($order['id'] ?? 0) !== (int) $source->id
            || (int) ($order['refills_authorized'] ?? -1) !== (int) $source->refills_authorized) $fail();
        foreach (['episode_id', 'medication', 'strength', 'dosage_form', 'directions', 'quantity_unit', 'written_on', 'expires_on', 'prescriber_name', 'prescriber_identifier', 'source_reference'] as $field) {
            if (! array_key_exists($field, $order) || (string) $order[$field] !== (string) $rx->$field || (string) $order[$field] !== (string) $source->$field) $fail();
        }
        if (PharmacyStock::milli($order['quantity'] ?? '0') !== $quantity || PharmacyStock::milli($source->quantity) !== $quantity) $fail();
        $limits = []; $previous = null;
        foreach ($allowances as $index => $allowance) {
            if (! is_array($allowance) || ! is_int($allowance['source_authorization_number'] ?? null)) $fail();
            $number = $allowance['source_authorization_number'];
            $amount = PharmacyStock::milli($allowance['quantity'] ?? '0');
            if ($number < 1 || $number > (int) ($order['refills_authorized'] ?? -1) + 1
                || ($previous !== null && $number !== $previous + 1)
                || $amount <= 0 || $amount > $quantity || ($index > 0 && $amount !== $quantity)) $fail();
            $limits[$index + 1] = $amount;
            $previous = $number;
        }
        if ($previous !== (int) $order['refills_authorized'] + 1) $fail();
        return ['limits' => $limits, 'transfer' => ['id' => (int) $receipt->id, 'snapshot_sha256' => $receipt->snapshot_sha256,
            'source_prescription_id' => (int) $receipt->source_prescription_id, 'allowances' => $allowances]];
    }
}
