<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/** Caller holds the prescription/fill locks inside the pharmacy write transaction. */
class PharmacyDispatch
{
    private function hash(array $record): string
    {
        $sort = function (array $value) use (&$sort): array {
            foreach ($value as &$item) { if (is_array($item)) { $item = $sort($item); } }
            unset($item);
            if (! array_is_list($value)) { ksort($value); }
            return $value;
        };
        return hash('sha256', json_encode($sort($record), JSON_THROW_ON_ERROR));
    }

    public function current(int $fillId): ?object
    {
        return DB::table('pharmacy_fill_dispatches')->where('fill_id', $fillId)->first();
    }

    public function summary(int $fillId): ?array
    {
        $d = $this->current($fillId);
        if (! $d) { return null; }
        return ['id' => $d->id, 'status' => $d->status, 'created_by' => $d->created_by,
            'record' => json_decode($d->record, true, 512, JSON_THROW_ON_ERROR),
            'exception' => $d->exception ? json_decode($d->exception, true, 512, JSON_THROW_ON_ERROR) : null,
            'latest_return' => $this->returnRows($d->id)->orderByDesc('id')->first()];
    }

    public function returnRows(int $dispatchId)
    {
        return DB::table('pharmacy_dispatch_returns')->where('dispatch_id', $dispatchId)
            ->select('id', 'created_by', 'record', 'status', 'reviewed_by', 'review_evidence', 'reviewed_at', 'returned_lot_id', 'created_at');
    }

    private function checked(object $dispatch): array
    {
        $record = json_decode($dispatch->record, true, 512, JSON_THROW_ON_ERROR);
        abort_unless(hash_equals($dispatch->record_hash, $this->hash($record)), 409, 'Dispatch evidence changed. Investigate custody before proceeding.');
        return $record;
    }

    public function start(User $actor, object $rx, object $fill, array $input, array $scan): void
    {
        abort_if($this->current($fill->id), 409, 'A dispatch already exists for this fill.');
        $d = Validator::make($input, [
            'occurred_on' => 'required|date_format:Y-m-d|before_or_equal:today',
            'method' => 'required|in:pharmacy_staff,tracked_carrier',
            'courier_reference' => 'required|string|max:2000', 'package_reference' => 'required|string|max:2000',
            'quantity' => 'required|numeric|min:0.001|max:999999.999|decimal:0,3', 'confirmed' => 'required|accepted',
        ])->validate();
        $prepared = json_decode($fill->fulfillment, true, 512, JSON_THROW_ON_ERROR);
        abort_unless(! empty($prepared['prepared_at']) && $d['occurred_on'] >= substr($prepared['prepared_at'], 0, 10), 422, 'Dispatch cannot predate preparation.');
        abort_unless(PharmacyStock::milli($d['quantity']) === PharmacyStock::milli($fill->quantity), 422, 'Dispatch must account for the entire prepared quantity.');
        unset($d['confirmed']);
        $d['quantity'] = PharmacyStock::decimal(PharmacyStock::milli($d['quantity']));
        $record = $d + ['stock_lot_id' => (int) $fill->stock_lot_id, 'location_id' => (int) $rx->location_id,
            'prepared_label_id' => $prepared['prepared_label_id'], 'prepared_label_sha256' => $prepared['prepared_label_sha256'], 'scan' => $scan];
        DB::table('pharmacy_fill_dispatches')->insert(['fill_id' => $fill->id, 'created_by' => $actor->id,
            'record' => json_encode($record, JSON_THROW_ON_ERROR), 'record_hash' => $this->hash($record), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function complete(object $fill, array $handover, string $date): void
    {
        $dispatch = $this->current($fill->id);
        abort_unless($dispatch && $dispatch->status === 'in_transit', 422, 'Delivery requires an active dispatch without unresolved custody exceptions.');
        $record = $this->checked($dispatch);
        abort_unless($date >= $record['occurred_on'] && $handover['delivery_method'] === $record['method'], 422, 'Delivery date or method does not match the retained dispatch.');
        DB::table('pharmacy_fill_dispatches')->where('id', $dispatch->id)->update(['status' => 'delivered', 'updated_at' => now()]);
    }

    private function source(object $fill, object $dispatch): string
    {
        return $this->hash([(int) $fill->id, (int) $fill->stock_lot_id, PharmacyStock::decimal(PharmacyStock::milli($fill->quantity)),
            $fill->fulfillment_status, json_decode($fill->fulfillment, true, 512, JSON_THROW_ON_ERROR), $dispatch->record_hash]);
    }

    public function custody(User $actor, object $rx, object $fill, string $action, array $input): array
    {
        abort_unless($actor->role === 'pharmacist' && $actor->status === 'active', 403);
        abort_unless($fill->fulfillment_status === 'ready' && ! $fill->invoice_id, 422, 'Only an unfulfilled prepared dispatch can use this custody workflow.');
        $dispatch = $this->current($fill->id);
        abort_unless($dispatch, 422, 'There is no retained dispatch for this fill.');
        $original = $this->checked($dispatch);
        if ($action === 'delivery_exception') {
            abort_unless($dispatch->status === 'in_transit', 409, 'Resolve the existing dispatch exception or return first.');
            $d = Validator::make($input, ['occurred_on' => 'required|date_format:Y-m-d|before_or_equal:today',
                'reason' => 'required|in:failed_delivery,missing_package,damaged_package', 'evidence' => 'required|string|max:2000',
                'custodian_reference' => 'required|string|max:2000', 'confirmed' => 'required|accepted'])->validate();
            abort_unless($d['occurred_on'] >= $original['occurred_on'], 422, 'Exception cannot predate dispatch.');
            unset($d['confirmed']);
            DB::table('pharmacy_fill_dispatches')->where('id', $dispatch->id)->update(['status' => 'exception',
                'exception' => json_encode($d + ['recorded_by' => $actor->id, 'recorded_at' => now()->toIso8601String()], JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            return [];
        }
        if ($action === 'return_from_dispatch') {
            abort_unless(in_array($dispatch->status, ['in_transit', 'exception'], true), 409, 'This dispatch already has a pending or completed return.');
            $d = Validator::make($input, ['occurred_on' => 'required|date_format:Y-m-d|before_or_equal:today',
                'quantity' => 'required|numeric|min:0.001|max:999999.999|decimal:0,3',
                'evidence' => 'required|string|max:2000', 'segregation_reference' => 'required|string|max:2000',
                'package_condition' => 'required|string|max:2000', 'label_code' => 'required|string|max:64',
                'never_received_by_patient' => 'required|accepted', 'confirmed' => 'required|accepted'])->validate();
            $lastDate = $dispatch->exception ? json_decode($dispatch->exception, true)['occurred_on'] : $original['occurred_on'];
            abort_unless($d['occurred_on'] >= $lastDate, 422, 'Return cannot predate dispatch or the retained exception.');
            abort_unless(PharmacyStock::milli($d['quantity']) === PharmacyStock::milli($fill->quantity), 422, 'Partial or missing returns require separate loss reconciliation; keep the reservation held.');
            abort_unless(hash_equals($original['scan']['label_code'], $d['label_code']), 422, 'Returned package label does not match the dispatched fill.');
            unset($d['confirmed']);
            $d['quantity'] = PharmacyStock::decimal(PharmacyStock::milli($d['quantity']));
            DB::table('pharmacy_dispatch_returns')->insert(['dispatch_id' => $dispatch->id, 'created_by' => $actor->id,
                'record' => json_encode($d, JSON_THROW_ON_ERROR), 'record_hash' => $this->hash($d), 'source_hash' => $this->source($fill, $dispatch), 'created_at' => now()]);
            DB::table('pharmacy_fill_dispatches')->where('id', $dispatch->id)->update(['status' => 'return_pending', 'updated_at' => now()]);
            return [];
        }
        $d = Validator::make($input, ['return_id' => 'required|integer|min:1', 'decision' => 'required|in:apply,reject',
            'evidence' => 'required|string|max:2000', 'confirmed' => 'required|accepted'])->validate();
        $return = DB::table('pharmacy_dispatch_returns')->where('dispatch_id', $dispatch->id)->where('id', $d['return_id'])->first();
        abort_unless($return && $return->status === 'pending' && $dispatch->status === 'return_pending', 409, 'Return is no longer awaiting review.');
        abort_if((int) $return->created_by === (int) $actor->id, 422, 'A different assigned pharmacist must review the returned medication.');
        $returnedLotId = null; $update = [];
        if ($d['decision'] === 'apply') {
            $author = User::find($return->created_by);
            abort_unless($author && $author->status === 'active' && $author->role === 'pharmacist' && (int) $author->organization_id === (int) $actor->organization_id, 409, 'Return author is no longer authorized. Reject and record fresh custody evidence.');
            app(PharmacyAccess::class)->requireLocation($author, $rx->location_id);
            $record = json_decode($return->record, true, 512, JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($return->record_hash, $this->hash($record)) && hash_equals($return->source_hash, $this->source($fill, $dispatch)), 409, 'Return or preparation evidence changed. Reject and investigate before recording fresh evidence.');
            $returnedLotId = $this->segregate($actor, $rx, $fill, $return, $original);
            $prior = json_decode($fill->fulfillment, true, 512, JSON_THROW_ON_ERROR);
            $update = ['fulfillment_status' => 'cancelled', 'fulfillment' => json_encode($prior + ['dispatch_return' => [
                'return_id' => $return->id, 'returned_lot_id' => $returnedLotId, 'reviewed_by' => $actor->id, 'reviewed_at' => now()->toIso8601String()]], JSON_THROW_ON_ERROR)];
        }
        DB::table('pharmacy_dispatch_returns')->where('id', $return->id)->update(['status' => $d['decision'] === 'apply' ? 'applied' : 'rejected',
            'reviewed_by' => $actor->id, 'review_evidence' => $d['evidence'], 'reviewed_at' => now(), 'returned_lot_id' => $returnedLotId]);
        DB::table('pharmacy_fill_dispatches')->where('id', $dispatch->id)->update(['status' => $d['decision'] === 'apply' ? 'returned_quarantined' : 'exception', 'updated_at' => now()]);
        return $update;
    }

    private function segregate(User $actor, object $rx, object $fill, object $return, array $dispatch): int
    {
        $lot = DB::table('pharmacy_stock_lots')->where('id', $fill->stock_lot_id)->where('organization_id', $rx->organization_id)->where('location_id', $rx->location_id)->lockForUpdate()->first();
        abort_unless($lot && (int) $dispatch['stock_lot_id'] === (int) $lot->id, 409, 'Source receipt is missing or differs.');
        $quantity = PharmacyStock::milli($fill->quantity);
        abort_unless(PharmacyStock::milli($lot->on_hand) >= $quantity && PharmacyStock::milli($lot->reserved) >= $quantity, 409, 'Source balances require reconciliation.');
        abort_if(DB::table('pharmacy_stock_counts')->where('stock_lot_id', $lot->id)->where('status', 'pending')->exists()
            || app(PharmacyDisposition::class)->pending($lot->id), 409, 'Resolve pending source stock counts or disposition before applying the return.');
        $row = array_intersect_key((array) $lot, array_flip(['organization_id', 'location_id', 'ndc', 'medication', 'lot_number', 'quantity_unit', 'expires_on', 'recall_ndc_key', 'recall_lot_key']));
        $returned = DB::table('pharmacy_stock_lots')->insertGetId($row + ['request_id' => (string) Str::uuid(),
            'request_hash' => $this->hash(['dispatch_return_id' => $return->id]), 'dispatch_return_id' => $return->id,
            'receipt_reference' => 'Undelivered dispatch return #'.$return->id.' from receipt #'.$lot->id,
            'on_hand' => PharmacyStock::decimal($quantity), 'reserved' => '0.000', 'status' => 'quarantined',
            'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pharmacy_stock_lots')->where('id', $lot->id)->update([
            'on_hand' => PharmacyStock::decimal(PharmacyStock::milli($lot->on_hand) - $quantity),
            'reserved' => PharmacyStock::decimal(PharmacyStock::milli($lot->reserved) - $quantity), 'version' => $lot->version + 1, 'updated_at' => now()]);
        $details = ['return_id' => (int) $return->id, 'source_lot_id' => (int) $lot->id, 'returned_lot_id' => $returned];
        app(PharmacyStock::class)->event($actor, $lot, 'undelivered_return_segregated', '-'.PharmacyStock::decimal($quantity), $details, $fill->id);
        app(PharmacyStock::class)->event($actor, (object) ['id' => $returned], 'undelivered_return_received', PharmacyStock::decimal($quantity), $details, $fill->id);
        return $returned;
    }
}
