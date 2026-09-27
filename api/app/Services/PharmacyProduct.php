<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PharmacyProduct
{
    public const FIELDS = ['id', 'stock_lot_id', 'revision', 'generic_name', 'brand_name', 'strength', 'dosage_form', 'manufacturer', 'verified_on', 'evidence', 'reason', 'actor_id', 'created_at'];

    public function current(int $lotId): ?object
    {
        return DB::table('pharmacy_stock_products')->where('stock_lot_id', $lotId)->orderByDesc('revision')->first(self::FIELDS);
    }
}
