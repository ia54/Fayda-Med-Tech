<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** Quantity conservation for proposals only; never changes stock or clinical decisions. */
class PharmacyIncidentAccounting
{
    public function line(array $line): array
    {
        $amounts = [];
        foreach (['reserved_quantity', 'additional_taken', 'consumed', 'unused_retained', 'disposed_unused', 'unaccounted'] as $field) {
            if (! array_key_exists($field, $line) || $line[$field] === null || is_bool($line[$field])) {
                throw ValidationException::withMessages([$field => 'An explicit quantity is required; unknown is not zero.']);
            }
            $amounts[$field] = PharmacyStock::milli($line[$field]);
        }
        $custody = $amounts['reserved_quantity'] + $amounts['additional_taken'];
        $accounted = $amounts['consumed'] + $amounts['unused_retained'] + $amounts['disposed_unused'] + $amounts['unaccounted'];
        if ($custody !== $accounted) {
            throw ValidationException::withMessages(['accounting' => 'Consumed, retained unused, disposed unused and unaccounted quantities must equal the original reservation plus explicitly recorded additional material.']);
        }

        // Disposed unused excludes ingredients already classified as consumed.
        return ['quantities' => array_map(fn ($n) => PharmacyStock::decimal($n), $amounts),
            'custody_quantity' => PharmacyStock::decimal($custody),
            'unaccounted_remaining' => $amounts['unaccounted'] > 0,
            'unused_custody_remaining' => $amounts['unused_retained'] > 0,
            'stock_adjusted' => false];
    }
}
