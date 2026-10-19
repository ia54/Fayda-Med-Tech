<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Validates documentary resolution; never moves inventory or releases stock. */
class PharmacyTransferResolution
{
    public function verifiedReceipt(object $transfer, object $lot): bool
    {
        if ($transfer->status !== 'received_reconciled' || !($transfer->variance_resolution_id ?? null)) return false;
        $record = DB::table('pharmacy_transfer_resolutions')->where('transfer_id', $transfer->id)
            ->where('id', $transfer->variance_resolution_id)->where('status', 'applied')->first();
        if (!$record || !$record->reviewed_by || (int) $record->reviewed_by === (int) $record->created_by) return false;
        try {
            $snapshot = json_decode($record->snapshot, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($snapshot) || !hash_equals($record->snapshot_hash, $this->digest($snapshot))) return false;
            foreach (['kind', 'transfer_id', 'destination_lot_id', 'count_id', 'count_reviewed_by', 'investigation_id', 'dispatched_quantity', 'original_received_quantity', 'verified_quantity', 'variance_quantity'] as $field) {
                if (!isset($snapshot[$field])) return false;
            }
            if ((int) $snapshot['transfer_id'] !== (int) $transfer->id || (int) $snapshot['destination_lot_id'] !== (int) $lot->id
                || (int) $record->stock_count_id !== (int) $snapshot['count_id'] || (int) $record->investigation_event_id !== (int) $snapshot['investigation_id']
                || $record->kind !== $snapshot['kind'] || (int) ($snapshot['source']['chain'][0]['id'] ?? 0) !== (int) $transfer->source_lot_id) return false;
            $count = DB::table('pharmacy_stock_counts')->where('stock_lot_id', $lot->id)->where('id', $record->stock_count_id)->where('status', 'applied')->first();
            if (!$count || $count->reason !== 'physical_count' || (int) $count->reviewed_by !== (int) $snapshot['count_reviewed_by']
                || (int) $count->reviewed_by === (int) $count->created_by) return false;
            $sent = PharmacyStock::milli($transfer->quantity); $received = PharmacyStock::milli($transfer->received_quantity);
            $verified = PharmacyStock::milli($snapshot['verified_quantity']);
            if ($sent !== PharmacyStock::milli($snapshot['dispatched_quantity']) || $received !== PharmacyStock::milli($snapshot['original_received_quantity'])
                || $verified !== PharmacyStock::milli($count->counted_quantity) || abs($sent - $received) !== PharmacyStock::milli($snapshot['variance_quantity'])) return false;
            if ($record->kind === 'confirmed_shortage') return $received < $sent && $verified === $received;
            return $record->kind === 'excess_returned' && $received > $sent && $verified === $sent
                && PharmacyStock::milli($count->recorded_quantity) === $received && trim((string) $record->external_return_evidence) !== '';
        } catch (\JsonException | \Illuminate\Validation\ValidationException $e) {
            return false;
        }
    }

    public function digest(array $snapshot): string
    {
        $normalize = function (array $value) use (&$normalize): array {
            foreach ($value as $key => $item) if (is_array($item)) $value[$key] = $normalize($item);
            if (!array_is_list($value)) ksort($value);
            return $value;
        };
        return hash('sha256', json_encode($normalize($snapshot), JSON_THROW_ON_ERROR));
    }

    public function snapshot(object $transfer, int $countId, int $investigationId, string $kind): array
    {
        abort_unless(in_array($kind, ['confirmed_shortage', 'excess_returned'], true), 422, 'Select a supported variance resolution.');
        abort_unless($transfer->status === 'received_discrepancy', 422, 'Only an unresolved receipt discrepancy can be reconciled.');
        $lot = DB::table('pharmacy_stock_lots')->where('organization_id', $transfer->organization_id)
            ->where('location_id', $transfer->destination_location_id)->where('id', $transfer->destination_lot_id)->lockForUpdate()->first();
        abort_unless($lot && (int) $lot->source_transfer_id === (int) $transfer->id, 409, 'Receiving custody is incomplete.');
        abort_unless($lot->status === 'quarantined' && $lot->recall_reference === null && PharmacyStock::milli($lot->reserved) === 0, 422, 'Receiving stock must remain quarantined, without reservations or recall.');
        abort_if(app(PharmacyRecall::class)->held($lot) || app(PharmacyDisposition::class)->pending((int) $lot->id), 422, 'Resolve the recall or pending disposition through its dedicated workflow.');
        abort_if(DB::table('pharmacy_stock_counts')->where('stock_lot_id', $lot->id)->where('status', 'pending')->exists(), 409, 'Complete independent physical-count review first.');
        abort_if(DB::table('pharmacy_transfer_corrections')->where('transfer_id', $transfer->id)->where('status', 'pending')->exists(), 409, 'Resolve the pending receipt-entry correction first.');
        $count = DB::table('pharmacy_stock_counts')->where('stock_lot_id', $lot->id)->where('id', $countId)->first();
        abort_unless($count && $count->status === 'applied' && $count->reason === 'physical_count'
            && (int) $count->lot_version + 1 === (int) $lot->version
            && PharmacyStock::milli($count->counted_quantity) === PharmacyStock::milli($lot->on_hand), 409, 'Use the current independently verified physical count.');
        $investigation = DB::table('pharmacy_stock_transfer_events')->where('transfer_id', $transfer->id)
            ->where('id', $investigationId)->where('action', 'investigation_noted')->first();
        abort_unless($investigation, 422, 'Retain and select investigation findings for this transfer.');
        abort_unless((int) $investigation->id === (int) DB::table('pharmacy_stock_transfer_events')->where('transfer_id', $transfer->id)->where('action', 'investigation_noted')->max('id'), 409, 'Use the latest investigation findings.');
        $sent = PharmacyStock::milli($transfer->quantity);
        $received = PharmacyStock::milli($transfer->received_quantity);
        $physical = PharmacyStock::milli($lot->on_hand);
        if ($kind === 'confirmed_shortage') {
            abort_unless($received < $sent && $physical === $received, 422, 'A confirmed shortage requires the verified physical balance to match the original short receipt.');
        } else {
            abort_unless($received > $sent && $physical === $sent
                && PharmacyStock::milli($count->recorded_quantity) === $received, 422, 'An excess return requires a reviewed physical count from the original excess balance down to the dispatched quantity.');
        }
        $source = DB::table('pharmacy_stock_lots')->where('organization_id', $transfer->organization_id)
            ->where('location_id', $transfer->source_location_id)->where('id', $transfer->source_lot_id)->first();
        abort_unless($source, 409, 'Source custody is incomplete.');
        app(PharmacyStock::class)->assertReleaseAllowed($source);
        $sourceEvidence = app(PharmacyDisposition::class)->source($source);
        $product = app(PharmacyProduct::class)->current((int) $lot->id);
        abort_unless($product, 422, 'Verify the destination product before proposing a resolution.');
        abort_if(DB::table('pharmacy_fills')->where('stock_lot_id', $lot->id)->exists(), 422, 'A receiving lot with fill history requires separate incident reconciliation.');
        return [
            'kind' => $kind, 'transfer_id' => (int) $transfer->id,
            'dispatched_quantity' => PharmacyStock::decimal($sent),
            'original_received_quantity' => PharmacyStock::decimal($received),
            'verified_quantity' => PharmacyStock::decimal($physical),
            'variance_quantity' => PharmacyStock::decimal(abs($sent - $received)),
            'destination_lot_id' => (int) $lot->id, 'destination_version' => (int) $lot->version,
            'destination_product_id' => (int) $product->id,
            'count_id' => (int) $count->id, 'count_reviewed_by' => (int) $count->reviewed_by,
            'investigation_id' => (int) $investigation->id,
            'investigation_hash' => hash('sha256', $investigation->details),
            'source' => $sourceEvidence,
        ];
    }
}
