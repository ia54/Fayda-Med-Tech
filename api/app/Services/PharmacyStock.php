<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Stock operations run inside the prescription transaction, always locking Rx before lot. */
class PharmacyStock
{
    public static function milli($quantity): int
    {
        // Avoid floating point arithmetic for fractional dispensing quantities.
        $value = (string) $quantity;
        if (! preg_match('/^\d{1,9}(?:\.\d{1,3})?$/D', $value)) {
            throw ValidationException::withMessages(['quantity' => 'Use a positive quantity with at most three decimal places.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $value), 2, '');

        return (int) $whole * 1000 + (int) str_pad($fraction, 3, '0');
    }

    public static function decimal(int $quantity): string
    {
        return intdiv($quantity, 1000).'.'.str_pad((string) ($quantity % 1000), 3, '0', STR_PAD_LEFT);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['stock' => $message]);
    }

    private function usable($lot): void
    {
        if ($lot->status !== 'available' || $lot->expires_on < now()->toDateString()) {
            $this->fail('This stock is expired or quarantined. Cancel the fill to release its reservation and select usable stock.');
        }
    }

    public function reserve(User $actor, $rx, array $data)
    {
        $lot = DB::table('pharmacy_stock_lots')->where('organization_id', $actor->organization_id)
            ->where('location_id', $rx->location_id)->where('id', $data['stock_lot_id'])->lockForUpdate()->first();
        abort_unless($lot, 404);
        $this->usable($lot);
        if ($lot->ndc !== $data['ndc'] || $lot->quantity_unit !== $rx->quantity_unit) {
            $this->fail('Selected stock must match the fill NDC and prescription quantity unit.');
        }
        $quantity = self::milli($data['quantity']);
        if ($quantity > self::milli($lot->on_hand) - self::milli($lot->reserved)) {
            $this->fail('Insufficient available stock at this pharmacy location.');
        }
        DB::table('pharmacy_stock_lots')->where('id', $lot->id)->update([
            'reserved' => self::decimal(self::milli($lot->reserved) + $quantity),
            'version' => $lot->version + 1, 'updated_at' => now(),
        ]);

        return $lot;
    }

    public function transition(User $actor, $rx, $fill, string $action): void
    {
        $lot = DB::table('pharmacy_stock_lots')->where('organization_id', $actor->organization_id)
            ->where('location_id', $rx->location_id)->where('id', $fill->stock_lot_id)->lockForUpdate()->first();
        abort_unless($lot, 404);
        if ($action !== 'cancel') {
            $this->usable($lot);
        }
        if ($action === 'ready') {
            return;
        }
        $quantity = self::milli($fill->quantity);
        $reserved = self::milli($lot->reserved);
        $onHand = self::milli($lot->on_hand);
        if ($reserved < $quantity || $onHand < $quantity) {
            $this->fail('Stock reconciliation is required before this action.');
        }
        DB::table('pharmacy_stock_lots')->where('id', $lot->id)->update([
            'reserved' => self::decimal($reserved - $quantity),
            'on_hand' => self::decimal($onHand - ($action === 'cancel' ? 0 : $quantity)),
            'version' => $lot->version + 1, 'updated_at' => now(),
        ]);
        $this->event($actor, $lot, $action === 'cancel' ? 'reservation_released' : 'dispensed', $fill->quantity, [], $fill->id);
    }

    public function event(User $actor, $lot, string $action, $quantity, array $details, ?int $fillId = null): void
    {
        DB::table('pharmacy_stock_events')->insert([
            'stock_lot_id' => $lot->id, 'fill_id' => $fillId, 'actor_id' => $actor->id,
            'action' => $action, 'quantity' => $quantity, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }
}
