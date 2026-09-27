<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Prescription row must be locked before using this projection to allocate a fill. */
class PharmacyQuantity
{
    public function balance($rx): array
    {
        $fills = DB::table('pharmacy_fills')->where('prescription_id', $rx->id)->where('fulfillment_status', '!=', 'cancelled')->get();
        $quantity = PharmacyStock::milli($rx->quantity);
        $legacy = $fills->whereNull('authorization_number')->count();
        $restricted = (bool) $rx->controlled || (bool) $rx->compounded;
        $groups = [];
        foreach ($fills->whereNotNull('authorization_number')->groupBy('authorization_number') as $number => $items) {
            $handed = 0;
            $reserved = 0;
            foreach ($items as $fill) {
                $amount = PharmacyStock::milli($fill->quantity);
                if (in_array($fill->fulfillment_status, ['collected', 'delivered'], true)) {
                    $handed += $amount;
                } else {
                    $reserved += $amount;
                }
            }
            if ($handed + $reserved > $quantity) {
                throw ValidationException::withMessages(['quantity' => 'The retained quantity ledger is inconsistent. Investigate before creating another fill.']);
            }
            $groups[(int) $number] = ['number' => (int) $number, 'authorized' => PharmacyStock::decimal($quantity), 'handed_over' => PharmacyStock::decimal($handed), 'reserved' => PharmacyStock::decimal($reserved), 'remaining' => PharmacyStock::decimal($quantity - $handed - $reserved)];
        }
        ksort($groups);
        $next = $legacy + 1;
        $available = $quantity;
        foreach ($groups as $number => $group) {
            // Only the last allowance may be unfinished. Never skip or pool allowances.
            if ($number !== $next) {
                throw ValidationException::withMessages(['quantity' => 'The retained authorization sequence needs investigation.']);
            }
            $available = PharmacyStock::milli($group['remaining']);
            if (PharmacyStock::milli($group['handed_over']) === $quantity) {
                $next++;
                $available = $quantity;
            }
        }
        $remaining = max(0, (int) $rx->refills_authorized + 1 - $next + 1);
        if ($remaining === 0) {
            $available = 0;
        }
        return ['mode' => $restricted ? 'restricted' : 'quantity', 'legacy_allowances_used' => $legacy,
            'next_authorization_number' => $remaining ? $next : null,
            'available_quantity' => PharmacyStock::decimal($available),
            'allowances_remaining' => $remaining, 'allowances' => array_values($groups)];
    }
}
