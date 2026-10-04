<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Shared custody checks; this service neither resolves incidents nor adjusts stock. */
class PharmacyCompoundingIncident
{
    public function digest(array $value): string
    {
        $normalize = function ($item) use (&$normalize) {
            if (is_object($item)) {
                $item = (array) $item;
            }
            if (! is_array($item)) {
                return $item;
            }
            if (! array_is_list($item)) {
                ksort($item);
            }

            return array_map($normalize, $item);
        };

        return hash('sha256', json_encode($normalize($value), JSON_THROW_ON_ERROR));
    }

    public function holdsBatch(int $organization, int $batch): bool
    {
        if (DB::table('pharmacy_consumption_corrections as correction')
            ->join('pharmacy_ingredient_lots as receipt', 'receipt.id', '=', 'correction.ingredient_lot_id')
            ->join('pharmacy_ingredient_allocations as allocation', 'allocation.ingredient_lot_id', '=', 'receipt.id')
            ->where('receipt.organization_id', $organization)->where('allocation.batch_id', $batch)
            ->where('correction.status', 'pending')->exists()) { return true; }
        return DB::table('pharmacy_compounding_incidents')->where('organization_id', $organization)
            ->where('batch_id', $batch)->where('status', '<>', 'reconciled')->exists();
    }

    public function holdsLot(int $organization, int $lot): bool
    {
        if (DB::table('pharmacy_consumption_corrections as correction')
            ->join('pharmacy_ingredient_lots as receipt', 'receipt.id', '=', 'correction.ingredient_lot_id')
            ->where('receipt.organization_id', $organization)->where('receipt.id', $lot)
            ->where('correction.status', 'pending')->exists()) { return true; }
        return DB::table('pharmacy_compounding_incident_lines as line')
            ->join('pharmacy_compounding_incidents as incident', 'incident.id', '=', 'line.incident_id')
            ->where('incident.organization_id', $organization)->where('line.ingredient_lot_id', $lot)
            ->where('incident.status', '<>', 'reconciled')->exists();
    }

    public function assertBatchClear(int $organization, int $batch): void
    {
        abort_if($this->holdsBatch($organization, $batch), 422, 'Resolve the retained preparation incident before changing its ingredient custody.');
    }

    public function assertLotClear(int $organization, int $lot): void
    {
        abort_if($this->holdsLot($organization, $lot), 422, 'This ingredient receipt has unresolved preparation-consumption evidence.');
    }
}
