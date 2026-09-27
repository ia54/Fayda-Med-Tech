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
        $history = DB::table('pharmacy_allowance_closures')->where('prescription_id', $rx->id)->orderBy('id')->get();
        $corrections = DB::table('pharmacy_allowance_corrections')->whereIn('closure_id', $history->pluck('id'))->orderBy('id')->get();
        $reversed = $corrections->where('status', 'applied')->pluck('closure_id');
        $active = $history->whereNotIn('id', $reversed);
        if ($active->count() !== $active->unique('authorization_number')->count()) {
            throw ValidationException::withMessages(['quantity' => 'Multiple active closures need investigation.']);
        }
        $closures = $active->keyBy('authorization_number');
        $pending = $corrections->where('status', 'pending');
        $token = hash('sha256', json_encode([
            'prescription' => [$rx->id, $rx->quantity, $rx->refills_authorized, $rx->controlled, $rx->compounded, $rx->discontinued_at, $rx->expires_on],
            'fills' => $all->map(fn ($f) => [$f->id, $f->version, $f->quantity, $f->authorization_number, $f->fulfillment_status])->all(),
            'closures' => $history->all(),
            'corrections' => $corrections->map(fn ($c) => [$c->id, $c->closure_id, $c->status, $c->created_by, $c->reviewed_by, $c->reviewed_at])->all(),
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
        $closable = $pending->isEmpty() && ! $restricted && ! $rx->discontinued_at && $rx->expires_on >= now()->toDateString()
            && $remaining > 0 && $current && ! $current['closure']
            && PharmacyStock::milli($current['handed_over']) > 0 && $available > 0
            && ! $fills->contains(fn ($f) => ! in_array($f->fulfillment_status, ['collected', 'delivered'], true));
        $correctable = null;
        if (! $restricted && ! $rx->discontinued_at && $rx->expires_on >= now()->toDateString()
            && ! $fills->contains(fn ($f) => ! in_array($f->fulfillment_status, ['collected', 'delivered'], true))) {
            foreach ($closures as $number => $closure) {
                if (! $fills->contains(fn ($f) => $f->authorization_number === null || (int) $f->authorization_number > (int) $number)) {
                    $correctable = (int) $closure->id;
                }
            }
        }
        $retained = $history->map(function ($c) use ($corrections, $reversed) {
            $row = array_intersect_key((array) $c, array_flip(['id', 'authorization_number', 'quantity', 'actor_id', 'basis', 'occurred_on', 'reason', 'evidence', 'created_at']));
            $row['corrected'] = $reversed->contains($c->id);
            $row['corrections'] = $corrections->where('closure_id', $c->id)->map(fn ($r) => array_intersect_key((array) $r, array_flip(['id', 'created_by', 'reason', 'evidence', 'status', 'reviewed_by', 'review_evidence', 'reviewed_at', 'created_at'])))->values()->all();
            return $row;
        })->all();
        return ['closure_history' => $retained, 'correctable_closure_id' => $correctable, 'pending_correction_id' => $pending->first()?->id, 'closable_authorization_number' => $closable ? $next : null, 'ledger_token' => $token, 'mode' => $restricted ? 'restricted' : 'quantity', 'legacy_allowances_used' => $legacy,
            'next_authorization_number' => $remaining ? $next : null,
            'available_quantity' => PharmacyStock::decimal($available),
            'allowances_remaining' => $remaining, 'allowances' => array_values($groups)];
    }
}
