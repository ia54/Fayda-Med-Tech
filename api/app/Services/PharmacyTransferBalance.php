<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Read-only input to the transfer workflow, not authorization to issue a transfer. */
class PharmacyTransferBalance
{
    public function snapshot(object $rx): array
    {
        $balance = app(PharmacyQuantity::class)->balance($rx);
        $fills = DB::table('pharmacy_fills')->where('prescription_id', $rx->id)->orderBy('id')->get();
        $holds = [];
        if ($rx->controlled) $holds[] = 'controlled_transfer_unvalidated';
        if ($rx->compounded) $holds[] = 'compounded_transfer_unvalidated';
        if ($rx->discontinued_at) $holds[] = 'prescription_discontinued';
        if ($rx->expires_on < now()->toDateString()) $holds[] = 'prescription_expired';
        if ($balance['legacy_allowances_used'] > 0) $holds[] = 'historical_allowances_unresolved';
        if ($balance['pending_correction_id']) $holds[] = 'allowance_correction_pending';
        if ($fills->contains(fn ($f) => ! in_array($f->fulfillment_status, ['collected', 'delivered', 'cancelled'], true))) $holds[] = 'open_fill';
        if (DB::table('pharmacy_batch_worksheets')->where('prescription_id', $rx->id)->exists()) $holds[] = 'manufacturing_history';
        if ($balance['next_authorization_number'] === null) $holds[] = 'allowances_exhausted';

        $allowances = [];
        $total = 0;
        // An unresolved/held ledger never yields quantities for a destination to consume.
        if (! $holds) {
            for ($number = $balance['next_authorization_number']; $number <= (int) $rx->refills_authorized + 1; $number++) {
                $amount = $number === $balance['next_authorization_number']
                    ? PharmacyStock::milli($balance['available_quantity']) : PharmacyStock::milli($balance['authorization_limits'][$number]);
                $allowances[] = ['source_authorization_number' => $number, 'quantity' => PharmacyStock::decimal($amount)];
                $total += $amount;
            }
        }
        $order = array_intersect_key((array) $rx, array_flip(['id', 'organization_id', 'episode_id', 'location_id', 'rx_number', 'medication',
            'strength', 'dosage_form', 'directions', 'quantity', 'quantity_unit', 'refills_authorized', 'written_on', 'expires_on',
            'prescriber_name', 'prescriber_identifier', 'source_reference', 'amendment_revision', 'controlled', 'compounded', 'discontinued_at']));
        $sources = DB::table('pharmacy_source_documents')->where('prescription_id', $rx->id)->orderBy('id')->get(['id', 'sha256']);
        return [
            'source_token' => hash('sha256', json_encode([$order, $balance['ledger_token'], $sources, $holds], JSON_THROW_ON_ERROR)),
            'order' => $order,
            'holds' => $holds,
            'allowances' => $allowances,
            'remaining_quantity' => $holds ? null : PharmacyStock::decimal($total),
            'quantity_unit' => $rx->quantity_unit,
            'source_documents' => $sources->all(),
            'transfer_authorized' => false,
        ];
    }
}
