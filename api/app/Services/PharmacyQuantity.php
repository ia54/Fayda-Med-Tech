<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Prescription row must be locked before using this projection to allocate a fill. */
class PharmacyQuantity
{
    public function balance($rx): array
    {
        $all = DB::table('pharmacy_fills')->where('prescription_id', $rx->id)->orderBy('id')->get();
        $fills = $all->where('fulfillment_status', '!=', 'cancelled');
        $closures = DB::table('pharmacy_allowance_closures')->where('prescription_id', $rx->id)->orderBy('authorization_number')->get()->keyBy('authorization_number');
        $token = hash('sha256', json_encode([
            'prescription' => [$rx->id, $rx->quantity, $rx->refills_authorized, $rx->controlled, $rx->compounded, $rx->discontinued_at, $rx->expires_on],
            'fills' => $all->map(fn ($f) => [$f->id, $f->version, $f->quantity, $f->authorization_number, $f->fulfillment_status])->all(),
            'closures' => $closures->values()->all(),
        ], JSON_THROW_ON_ERROR));
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
            $closure = $closures->get($number);
            $closed = $closure ? PharmacyStock::milli($closure->quantity) : 0;
            if ($handed + $reserved + $closed > $quantity || ($closure && ($reserved !== 0 || $handed <= 0 || $closed <= 0 || $handed + $closed !== $quantity))) {
                throw ValidationException::withMessages(['quantity' => 'The retained quantity ledger is inconsistent. Investigate before creating another fill.']);
            }
            $groups[(int) $number] = ['number' => (int) $number, 'authorized' => PharmacyStock::decimal($quantity), 'handed_over' => PharmacyStock::decimal($handed), 'reserved' => PharmacyStock::decimal($reserved), 'remaining' => PharmacyStock::decimal($quantity - $handed - $reserved - $closed), 'closed_quantity' => PharmacyStock::decimal($closed), 'closure' => $closure ? array_intersect_key((array) $closure, array_flip(['id', 'quantity', 'actor_id', 'basis', 'occurred_on', 'reason', 'evidence', 'created_at'])) : null];
        }
        if ($closures->keys()->diff(array_keys($groups))->isNotEmpty()) {
            throw ValidationException::withMessages(['quantity' => 'A closed allowance has no retained supply history. Investigate before creating another fill.']);
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
            if (PharmacyStock::milli($group['handed_over']) + PharmacyStock::milli($group['closed_quantity']) === $quantity) {
                $next++;
                $available = $quantity;
            }
        }
        $remaining = max(0, (int) $rx->refills_authorized + 1 - $next + 1);
        if ($remaining === 0) {
            $available = 0;
        }
        $current = $groups[$next] ?? null;
        $closable = ! $restricted && ! $rx->discontinued_at && $rx->expires_on >= now()->toDateString()
            && $remaining > 0 && $current && ! $current['closure']
            && PharmacyStock::milli($current['handed_over']) > 0 && $available > 0
            && ! $fills->contains(fn ($f) => ! in_array($f->fulfillment_status, ['collected', 'delivered'], true));
        return ['closable_authorization_number' => $closable ? $next : null, 'ledger_token' => $token, 'mode' => $restricted ? 'restricted' : 'quantity', 'legacy_allowances_used' => $legacy,
            'next_authorization_number' => $remaining ? $next : null,
            'available_quantity' => PharmacyStock::decimal($available),
            'allowances_remaining' => $remaining, 'allowances' => array_values($groups)];
    }
}
