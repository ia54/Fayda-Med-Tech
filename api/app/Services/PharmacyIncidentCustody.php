<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** Accounts only for previously retained unused material, without applying stock. */
class PharmacyIncidentCustody
{
    /** Pure ledger projection. Callers must independently authorize, lock and audit application. */
    public function projectLot($onHand, $reserved, array $lines, string $status, ?string $recallReference): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['custody' => 'Retained custody lines are required.']);
        }
        $held = 0;
        $disposed = 0;
        foreach ($lines as $line) {
            $checked = $this->line($line['unused_quantity'] ?? null, $line);
            $held += PharmacyStock::milli($checked['unused_quantity']);
            $disposed += PharmacyStock::milli($checked['disposed_unused']);
        }
        $before = PharmacyStock::milli($onHand);
        $reservations = PharmacyStock::milli($reserved);
        if ($reservations > $before || $held > $reservations || $disposed > $before || $before - $disposed < $reservations - $held) {
            throw ValidationException::withMessages(['custody' => 'Custody would create negative stock or consume another reservation.']);
        }

        return [
            'on_hand' => PharmacyStock::decimal($before - $disposed),
            'reserved' => PharmacyStock::decimal($reservations - $held),
            'disposed_unused' => PharmacyStock::decimal($disposed),
            'status' => $status === 'recalled' || $recallReference !== null ? 'recalled' : 'quarantined',
            'stock_increase' => '0.000',
            'release_enabled' => false,
        ];
    }

    public function line($unused, array $decision): array
    {
        $amounts = [];
        foreach (['return_to_quarantine', 'disposed_unused'] as $key) {
            if (! array_key_exists($key, $decision) || $decision[$key] === null || is_bool($decision[$key])) {
                throw ValidationException::withMessages([$key => 'Explicit quantities are required; unknown material cannot be cleared.']);
            }
            $amounts[$key] = PharmacyStock::milli($decision[$key]);
        }
        $retained = PharmacyStock::milli($unused);
        if (array_sum($amounts) !== $retained) {
            throw ValidationException::withMessages(['custody' => 'Returned-to-quarantine and disposed unused quantities must account for exactly the retained unused material.']);
        }

        return ['unused_quantity' => PharmacyStock::decimal($retained),
            'return_to_quarantine' => PharmacyStock::decimal($amounts['return_to_quarantine']),
            'disposed_unused' => PharmacyStock::decimal($amounts['disposed_unused']),
            'stock_increase' => '0.000', 'release_enabled' => false];
    }
}
