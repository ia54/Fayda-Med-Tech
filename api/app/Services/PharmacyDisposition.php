<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PharmacyDisposition
{
    public function pending(int $lotId): bool
    {
        return DB::table('pharmacy_stock_dispositions')->where('stock_lot_id', $lotId)->where('status', 'pending')->exists();
    }

    /** The source snapshot is evidence, never permission to clear a recall or custody discrepancy. */
    public function source(object $lot): array
    {
        $product = app(PharmacyProduct::class)->current((int) $lot->id);
        abort_unless($product, 422, 'Verify the source product before recording disposition.');
        $source = ['product_id' => (int) $product->id, 'chain' => []];
        $org = (int) $lot->organization_id; $seen = [];
        while (true) {
            abort_if(isset($seen[$lot->id]) || (int) $lot->organization_id !== $org, 422, 'Reconcile source custody before disposition.');
            $seen[$lot->id] = true;
            abort_if(DB::table('pharmacy_fills as f')->join('pharmacy_prescriptions as rx', 'rx.id', '=', 'f.prescription_id')
                ->where('f.stock_lot_id', $lot->id)->where(fn ($q) => $q->where('rx.controlled', true)->orWhere('rx.compounded', true))->exists(),
                422, 'Controlled or compounded stock requires its separate disposition procedure.');

            $source['chain'][] = ['id' => $lot->id, 'location_id' => $lot->location_id, 'ndc' => $lot->ndc,
                'lot_number' => $lot->lot_number, 'quantity_unit' => $lot->quantity_unit, 'expires_on' => $lot->expires_on,
                'recall_reference' => $lot->recall_reference,
                'notice_ids' => app(PharmacyRecall::class)->notices($lot)->orderBy('n.id')->pluck('n.id')->all()];
            if (($lot->dispatch_return_id ?? null) !== null) {
                $return = DB::table('pharmacy_dispatch_returns as r')->join('pharmacy_fill_dispatches as d', 'd.id', '=', 'r.dispatch_id')
                    ->join('pharmacy_fills as f', 'f.id', '=', 'd.fill_id')->where('r.id', $lot->dispatch_return_id)
                    ->where('r.status', 'applied')->where('r.returned_lot_id', $lot->id)->where('d.status', 'returned_quarantined')
                    ->where('f.fulfillment_status', 'cancelled')->first(['r.id', 'd.id as dispatch_id', 'f.stock_lot_id']);
                abort_unless($return, 422, 'Returned-stock custody is incomplete.');
                $source['chain'][count($source['chain']) - 1]['dispatch_return'] = ['id' => $return->id, 'dispatch_id' => $return->dispatch_id];
                $location = $lot->location_id;
                $lot = DB::table('pharmacy_stock_lots')->where('organization_id', $org)->where('location_id', $location)->where('id', $return->stock_lot_id)->first();
                abort_unless($lot, 422, 'Original dispatch source is missing.');
                continue;
            }
            if ($lot->source_transfer_id === null) { break; }
            $transfer = DB::table('pharmacy_stock_transfers')->where('organization_id', $org)->where('id', $lot->source_transfer_id)->first();
            $valid = $transfer && $transfer->status === 'received' && PharmacyStock::milli($transfer->quantity) === PharmacyStock::milli($transfer->received_quantity);
            if ($transfer && $transfer->status === 'received_corrected' && $transfer->receipt_correction_id !== null) {
                $correction = DB::table('pharmacy_transfer_corrections')->where('id', $transfer->receipt_correction_id)->where('transfer_id', $transfer->id)->where('status', 'applied')->first();
                $valid = $correction && $transfer->corrected_received_quantity !== null && PharmacyStock::milli($transfer->quantity) === PharmacyStock::milli($transfer->corrected_received_quantity);
            }
            if ($transfer && $transfer->status === 'received_reconciled') {
                $valid = app(PharmacyTransferResolution::class)->verifiedReceipt($transfer, $lot);
            }
            abort_unless($valid && (int) $transfer->destination_lot_id === (int) $lot->id && (int) $transfer->destination_location_id === (int) $lot->location_id,
                422, 'Resolve the transfer discrepancy before disposition. This action cannot write off an unexplained loss or excess.');
            $source['chain'][count($source['chain']) - 1]['transfer'] = ['id' => $transfer->id, 'status' => $transfer->status, 'correction_id' => $transfer->receipt_correction_id, 'resolution_id' => $transfer->variance_resolution_id ?? null];
            $lot = DB::table('pharmacy_stock_lots')->where('organization_id', $org)->where('id', $transfer->source_lot_id)->first();
            abort_unless($lot && (int) $lot->location_id === (int) $transfer->source_location_id, 422, 'Source custody is incomplete.');
        }
        return $source;
    }
}
