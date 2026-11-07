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
            'latest_return' => $this->returnRows($d->id)->orderByDesc('id')->first(),
            'latest_recovery' => $this->recoveryRows($d->id)->orderByDesc('id')->first(),
            'remaining_loss_quantity' => $d->status === 'loss_resolved' && ($loss = $this->loss($d)) ? PharmacyStock::decimal($this->remainingLoss($loss)) : null];
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
        if (in_array($action, ['recover_dispatch_loss', 'review_dispatch_recovery'], true)) { return $this->recovery($actor, $rx, $fill, $action, $input); }
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
        if (in_array($action, ['return_from_dispatch', 'resolve_dispatch_loss'], true)) {
            $loss = $action === 'resolve_dispatch_loss';
            if ($loss) {
                abort_unless($dispatch->status === 'exception' && ! $rx->controlled && ! $rx->compounded, 422, 'Confirmed-loss reconciliation requires an ordinary unfulfilled dispatch with a retained exception.');
            }
            abort_unless(in_array($dispatch->status, ['in_transit', 'exception'], true), 409, 'This dispatch already has a pending or completed return.');
            $d = Validator::make($input, ['occurred_on' => 'required|date_format:Y-m-d|before_or_equal:today',
                'quantity' => $loss ? 'required|numeric|min:0|max:999999.999|decimal:0,3' : 'required|numeric|min:0.001|max:999999.999|decimal:0,3',
                'lost_quantity' => $loss ? 'required|numeric|min:0.001|max:999999.999|decimal:0,3' : 'prohibited',
                'investigation_reference' => $loss ? 'required|string|max:2000' : 'prohibited',
                'loss_evidence' => $loss ? 'required|string|max:2000' : 'prohibited',
                'nonreceipt_evidence' => $loss ? 'required|string|max:2000' : 'prohibited',
                'evidence' => 'required|string|max:2000', 'segregation_reference' => 'nullable|string|max:2000',
                'package_condition' => 'nullable|string|max:2000', 'label_code' => 'nullable|string|max:64',
                'never_received_by_patient' => 'required|accepted', 'confirmed' => 'required|accepted'])->validate();
            $lastDate = $dispatch->exception ? json_decode($dispatch->exception, true)['occurred_on'] : $original['occurred_on'];
            abort_unless($d['occurred_on'] >= $lastDate, 422, 'Return cannot predate dispatch or the retained exception.');
            $returned = PharmacyStock::milli($d['quantity']);
            $lost = $loss ? PharmacyStock::milli($d['lost_quantity']) : 0;
            abort_unless($returned + $lost === PharmacyStock::milli($fill->quantity), 422, 'Returned and confirmed-lost quantities must account for the entire dispatch. Keep unresolved quantities reserved.');
            if ($returned > 0) {
                abort_unless(! empty($d['segregation_reference']) && ! empty($d['package_condition']) && ! empty($d['label_code']), 422, 'Returned units require package condition, segregation and the original label code.');
                abort_unless(hash_equals($original['scan']['label_code'], $d['label_code']), 422, 'Returned package label does not match the dispatched fill.');
            } else {
                abort_if(! empty($d['segregation_reference']) || ! empty($d['package_condition']) || ! empty($d['label_code']), 422, 'Do not record physical return evidence when no units were recovered.');
            }
            if ($loss) { $d['lost_quantity'] = PharmacyStock::decimal($lost); $d['resolution_type'] = 'confirmed_loss'; }
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
            if (PharmacyStock::milli($record['lost_quantity'] ?? '0') > 0) {
                abort_if($rx->controlled || $rx->compounded, 422, 'Restricted medication requires its separate loss procedure.');
            }
            $returnedLotId = $this->segregate($actor, $rx, $fill, $return, $original, $record);
            $prior = json_decode($fill->fulfillment, true, 512, JSON_THROW_ON_ERROR);
            $update = ['fulfillment_status' => 'cancelled', 'fulfillment' => json_encode($prior + ['dispatch_return' => [
                'return_id' => $return->id, 'returned_lot_id' => $returnedLotId, 'returned_quantity' => $record['quantity'], 'lost_quantity' => $record['lost_quantity'] ?? '0.000', 'reviewed_by' => $actor->id, 'reviewed_at' => now()->toIso8601String()]], JSON_THROW_ON_ERROR)];
        }
        DB::table('pharmacy_dispatch_returns')->where('id', $return->id)->update(['status' => $d['decision'] === 'apply' ? 'applied' : 'rejected',
            'reviewed_by' => $actor->id, 'review_evidence' => $d['evidence'], 'reviewed_at' => now(), 'returned_lot_id' => $returnedLotId]);
        DB::table('pharmacy_fill_dispatches')->where('id', $dispatch->id)->update(['status' => $d['decision'] === 'apply' ? (PharmacyStock::milli($record['lost_quantity'] ?? '0') > 0 ? 'loss_resolved' : 'returned_quarantined') : 'exception', 'updated_at' => now()]);
        return $update;
    }

    public function recoveryRows(int $dispatchId)
    {
        return DB::table('pharmacy_dispatch_recoveries')->where('dispatch_id', $dispatchId)
            ->select('id', 'created_by', 'quantity', 'record', 'status', 'reviewed_by', 'review_evidence', 'reviewed_at', 'returned_lot_id', 'created_at');
    }

    private function loss(object $dispatch): ?object
    {
        return DB::table('pharmacy_dispatch_returns')->where('dispatch_id', $dispatch->id)->where('status', 'applied')->first();
    }

    private function remainingLoss(object $loss): int
    {
        $record = json_decode($loss->record, true, 512, JSON_THROW_ON_ERROR);
        abort_unless(hash_equals($loss->record_hash, $this->hash($record)), 409, 'Original loss evidence changed. Investigate before recording recovery.');
        $remaining = PharmacyStock::milli($record['lost_quantity'] ?? '0');
        foreach (DB::table('pharmacy_dispatch_recoveries')->where('loss_return_id', $loss->id)->where('status', 'applied')->get() as $row) {
            $evidence = json_decode($row->record, true, 512, JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($row->record_hash, $this->hash($evidence)) && PharmacyStock::milli($row->quantity) === PharmacyStock::milli($evidence['quantity']), 409, 'Prior recovery evidence changed. Reconcile the retained records.');
            $remaining -= PharmacyStock::milli($row->quantity);
        }
        abort_if($remaining < 0, 409, 'Recovered quantity exceeds the retained loss. Investigate the ledger.');
        return $remaining;
    }

    private function recovery(User $actor, object $rx, object $fill, string $action, array $input): array
    {
        $dispatch = $this->current($fill->id);
        abort_unless($dispatch && $dispatch->status === 'loss_resolved' && $fill->fulfillment_status === 'cancelled' && ! $fill->invoice_id, 422, 'Recovery requires a retained confirmed loss on an unfulfilled cancelled fill.');
        $original = $this->checked($dispatch);
        $loss = $this->loss($dispatch);
        $fulfillment = json_decode($fill->fulfillment, true, 512, JSON_THROW_ON_ERROR);
        abort_unless($loss && (int) ($fulfillment['dispatch_return']['return_id'] ?? 0) === (int) $loss->id, 409, 'The original loss resolution is missing or differs.');
        $sourceHash = $this->hash([$this->source($fill, $dispatch), (int) $loss->id, $loss->record_hash]);
        if ($action === 'recover_dispatch_loss') {
            abort_if($rx->controlled || $rx->compounded, 422, 'Restricted medication requires its separate recovery procedure.');
            abort_if(DB::table('pharmacy_dispatch_recoveries')->where('dispatch_id', $dispatch->id)->where('status', 'pending')->exists(), 409, 'Review the existing recovery before recording another.');
            $d = Validator::make($input, ['occurred_on' => 'required|date_format:Y-m-d|before_or_equal:today',
                'quantity' => 'required|numeric|min:0.001|max:999999.999|decimal:0,3', 'label_code' => 'required|string|max:64',
                'package_condition' => 'required|string|max:2000', 'segregation_reference' => 'required|string|max:2000',
                'evidence' => 'required|string|max:2000', 'never_received_by_patient' => 'required|accepted', 'confirmed' => 'required|accepted'])->validate();
            $lossRecord = json_decode($loss->record, true, 512, JSON_THROW_ON_ERROR);
            abort_unless($d['occurred_on'] >= $lossRecord['occurred_on'], 422, 'Recovery cannot predate the retained loss investigation.');
            abort_unless(hash_equals($original['scan']['label_code'], $d['label_code']), 422, 'Recovered package label does not match the lost dispatch.');
            abort_unless(PharmacyStock::milli($d['quantity']) <= $this->remainingLoss($loss), 422, 'Recovery exceeds the remaining documented loss.');
            unset($d['confirmed']); $d['quantity'] = PharmacyStock::decimal(PharmacyStock::milli($d['quantity']));
            DB::table('pharmacy_dispatch_recoveries')->insert(['dispatch_id' => $dispatch->id, 'loss_return_id' => $loss->id,
                'created_by' => $actor->id, 'quantity' => $d['quantity'], 'record' => json_encode($d, JSON_THROW_ON_ERROR),
                'record_hash' => $this->hash($d), 'source_hash' => $sourceHash, 'created_at' => now()]);
            return [];
        }
        $d = Validator::make($input, ['recovery_id' => 'required|integer|min:1', 'decision' => 'required|in:apply,reject',
            'evidence' => 'required|string|max:2000', 'confirmed' => 'required|accepted'])->validate();
        $row = DB::table('pharmacy_dispatch_recoveries')->where('id', $d['recovery_id'])->where('dispatch_id', $dispatch->id)->where('loss_return_id', $loss->id)->first();
        abort_unless($row && $row->status === 'pending', 409, 'Recovery is no longer awaiting review.');
        abort_if((int) $row->created_by === (int) $actor->id, 422, 'A different assigned pharmacist must review recovered medication.');
        $receipt = null;
        if ($d['decision'] === 'apply') {
            abort_if($rx->controlled || $rx->compounded, 422, 'Restricted medication requires its separate recovery procedure.');
            $author = User::find($row->created_by);
            abort_unless($author && $author->status === 'active' && $author->role === 'pharmacist' && (int) $author->organization_id === (int) $actor->organization_id, 409, 'Recovery author is no longer authorized. Reject and record fresh evidence.');
            app(PharmacyAccess::class)->requireLocation($author, $rx->location_id);
            $record = json_decode($row->record, true, 512, JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($row->record_hash, $this->hash($record)) && hash_equals($row->source_hash, $sourceHash)
                && PharmacyStock::milli($row->quantity) === PharmacyStock::milli($record['quantity']), 409, 'Recovery or original loss evidence changed. Reject and investigate.');
            $quantity = PharmacyStock::milli($row->quantity);
            abort_unless($quantity <= $this->remainingLoss($loss), 409, 'Recovery balance changed. Investigate before applying.');
            $lot = DB::table('pharmacy_stock_lots')->where('id', $fill->stock_lot_id)->where('organization_id', $rx->organization_id)->where('location_id', $rx->location_id)->first();
            abort_unless($lot && (int) $original['stock_lot_id'] === (int) $lot->id, 409, 'Original stock custody is missing.');
            $fields = array_intersect_key((array) $lot, array_flip(['organization_id', 'location_id', 'ndc', 'medication', 'lot_number', 'quantity_unit', 'expires_on', 'recall_ndc_key', 'recall_lot_key']));
            $receipt = DB::table('pharmacy_stock_lots')->insertGetId($fields + ['request_id' => (string) Str::uuid(),
                'request_hash' => $this->hash(['dispatch_recovery_id' => $row->id]), 'dispatch_recovery_id' => $row->id,
                'receipt_reference' => 'Recovered dispatch loss #'.$loss->id.'; recovery #'.$row->id,
                'on_hand' => PharmacyStock::decimal($quantity), 'reserved' => '0.000', 'status' => 'quarantined',
                'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            app(PharmacyStock::class)->event($actor, (object) ['id' => $receipt], 'dispatch_loss_recovered', PharmacyStock::decimal($quantity),
                ['recovery_id' => (int) $row->id, 'loss_return_id' => (int) $loss->id, 'source_lot_id' => (int) $lot->id], $fill->id);
        }
        DB::table('pharmacy_dispatch_recoveries')->where('id', $row->id)->update(['status' => $d['decision'] === 'apply' ? 'applied' : 'rejected',
            'reviewed_by' => $actor->id, 'review_evidence' => $d['evidence'], 'reviewed_at' => now(), 'returned_lot_id' => $receipt]);
        return [];
    }

    private function segregate(User $actor, object $rx, object $fill, object $return, array $dispatch, array $record): ?int
    {
        $lot = DB::table('pharmacy_stock_lots')->where('id', $fill->stock_lot_id)->where('organization_id', $rx->organization_id)->where('location_id', $rx->location_id)->lockForUpdate()->first();
        abort_unless($lot && (int) $dispatch['stock_lot_id'] === (int) $lot->id, 409, 'Source receipt is missing or differs.');
        $quantity = PharmacyStock::milli($fill->quantity);
        $returnedQuantity = PharmacyStock::milli($record['quantity']);
        $lostQuantity = PharmacyStock::milli($record['lost_quantity'] ?? '0');
        abort_unless($returnedQuantity + $lostQuantity === $quantity, 409, 'Custody quantities no longer reconcile to the prepared fill.');
        abort_unless(PharmacyStock::milli($lot->on_hand) >= $quantity && PharmacyStock::milli($lot->reserved) >= $quantity, 409, 'Source balances require reconciliation.');
        abort_if(DB::table('pharmacy_stock_counts')->where('stock_lot_id', $lot->id)->where('status', 'pending')->exists()
            || app(PharmacyDisposition::class)->pending($lot->id), 409, 'Resolve pending source stock counts or disposition before applying the return.');
        $row = array_intersect_key((array) $lot, array_flip(['organization_id', 'location_id', 'ndc', 'medication', 'lot_number', 'quantity_unit', 'expires_on', 'recall_ndc_key', 'recall_lot_key']));
        $returned = $returnedQuantity > 0 ? DB::table('pharmacy_stock_lots')->insertGetId($row + ['request_id' => (string) Str::uuid(),
            'request_hash' => $this->hash(['dispatch_return_id' => $return->id]), 'dispatch_return_id' => $return->id,
            'receipt_reference' => 'Undelivered dispatch return #'.$return->id.' from receipt #'.$lot->id,
            'on_hand' => PharmacyStock::decimal($returnedQuantity), 'reserved' => '0.000', 'status' => 'quarantined',
            'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]) : null;
        DB::table('pharmacy_stock_lots')->where('id', $lot->id)->update([
            'on_hand' => PharmacyStock::decimal(PharmacyStock::milli($lot->on_hand) - $quantity),
            'reserved' => PharmacyStock::decimal(PharmacyStock::milli($lot->reserved) - $quantity), 'version' => $lot->version + 1, 'updated_at' => now()]);
        $details = ['return_id' => (int) $return->id, 'source_lot_id' => (int) $lot->id, 'returned_lot_id' => $returned, 'returned_quantity' => PharmacyStock::decimal($returnedQuantity), 'lost_quantity' => PharmacyStock::decimal($lostQuantity)];
        if ($returnedQuantity > 0) {
            app(PharmacyStock::class)->event($actor, $lot, 'undelivered_return_segregated', '-'.PharmacyStock::decimal($returnedQuantity), $details, $fill->id);
            app(PharmacyStock::class)->event($actor, (object) ['id' => $returned], 'undelivered_return_received', PharmacyStock::decimal($returnedQuantity), $details, $fill->id);
        }
        if ($lostQuantity > 0) { app(PharmacyStock::class)->event($actor, $lot, 'dispatch_loss_confirmed', '-'.PharmacyStock::decimal($lostQuantity), $details, $fill->id); }
        return $returned;
    }
}
