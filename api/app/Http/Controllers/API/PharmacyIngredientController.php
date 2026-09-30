<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use App\Services\PharmacyStock;
use App\Services\PharmacyCompoundingIncident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Preview ingredient custody only; reservations never constitute manufacture or release. */
class PharmacyIngredientController extends Controller
{
    private function org(Request $r, bool $pharmacist = false): int
    {
        abort_unless(in_array($r->user()->role, $pharmacist ? ['pharmacist'] : ['pharmacist', 'pharmacy_technician'], true), 403);
        abort_unless($r->user()->organization_id, 403);

        return (int) $r->user()->organization_id;
    }

    private function lot(Request $r, $id)
    {
        $lot = DB::table('pharmacy_ingredient_lots')->where('organization_id', $this->org($r))->where('id', $id)->first();
        abort_unless($lot, 404);
        app(PharmacyAccess::class)->requireLocation($r->user(), $lot->location_id);

        return $lot;
    }

    private function event(Request $r, int $lot, ?int $batch, string $action, string $quantity, array $details): void
    {
        DB::table('pharmacy_ingredient_events')->insert(['ingredient_lot_id' => $lot, 'batch_id' => $batch, 'actor_id' => $r->user()->id,
            'action' => $action, 'quantity' => $quantity, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    public function index(Request $r)
    {
        $d = $r->validate(['location_id' => 'nullable|integer', 'status' => 'nullable|in:available,quarantined,recalled', 'search' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $q = DB::table('pharmacy_ingredient_lots')->where('organization_id', $this->org($r));
        app(PharmacyAccess::class)->scope($q, $r->user());
        if (! empty($d['location_id'])) {
            $q->where('location_id', $d['location_id']);
        }
        if (! empty($d['status'])) { $q->where('status', $d['status']); }
        if (! empty($d['search'])) {
            $q->where(fn ($q) => $q->where('ingredient_name', 'like', '%'.$d['search'].'%')->orWhere('lot_number', 'like', '%'.$d['search'].'%')->orWhere('supplier', 'like', '%'.$d['search'].'%')->orWhere('recall_reference', 'like', '%'.$d['search'].'%'));
        }

        return response()->json(['data' => $q->select('id', 'location_id', 'ingredient_name', 'supplier', 'lot_number', 'quantity_unit', 'expires_on', 'on_hand', 'reserved', 'status', 'version', 'recall_reference')->orderBy('expires_on')->orderBy('id')->paginate(30)]);
    }

    public function show(Request $r, $id)
    {
        $lot = $this->lot($r, $id);
        unset($lot->request_id,$lot->request_hash);
        $lot->trace = DB::table('pharmacy_ingredient_allocations as a')
            ->join('pharmacy_batch_worksheets as b', 'b.id', '=', 'a.batch_id')
            ->where('a.ingredient_lot_id', $id)->where('b.organization_id', $lot->organization_id)->where('b.location_id', $lot->location_id)
            ->select('b.id as batch_id', 'b.batch_number', 'a.ingredient_key', 'a.quantity', 'a.status')->orderBy('b.id')->orderBy('a.id')->get();
        $lot->counts = DB::table('pharmacy_ingredient_counts')->where('ingredient_lot_id', $id)
            ->select('id', 'created_by', 'recorded_quantity', 'counted_quantity', 'lot_version', 'reason', 'evidence', 'status', 'reviewed_by', 'review_evidence', 'reviewed_at', 'created_at')->orderByDesc('id')->get();
        $lot->events = DB::table('pharmacy_ingredient_events')->where('ingredient_lot_id', $id)->orderByDesc('id')->limit(100)->get();

        return response()->json(['data' => $lot]);
    }

    public function store(Request $r)
    {
        $org = $this->org($r);
        $d = $r->validate(['request_id' => 'required|uuid', 'location_id' => 'required|integer', 'ingredient_name' => 'required|string|max:255',
            'supplier' => 'required|string|max:255', 'lot_number' => 'required|string|max:100', 'quantity_unit' => 'required|in:mg,g,mL,each,capsule,tablet',
            'quantity' => 'required|numeric|min:0.001|max:999999.999|decimal:0,3', 'expires_on' => 'required|date_format:Y-m-d|after_or_equal:today',
            'specification' => 'required|string|max:2000', 'certificate_reference' => 'required|string|max:2000', 'receipt_reference' => 'required|string|max:2000']);
        app(PharmacyAccess::class)->requireLocation($r->user(), $d['location_id']);
        $hashData = $d;
        unset($hashData['request_id']);
        ksort($hashData);
        $hash = hash('sha256', json_encode($hashData, JSON_THROW_ON_ERROR));
        $old = DB::table('pharmacy_ingredient_lots')->where('organization_id', $org)->where('request_id', $d['request_id'])->first();
        if ($old) {
            abort_unless(hash_equals($old->request_hash, $hash), 409);

            return $this->show($r, $old->id);
        }
        // The same physical receipt must not increase stock again under a new request UUID.
        abort_if(DB::table('pharmacy_ingredient_lots')->where('organization_id', $org)->where('location_id', $d['location_id'])->where('supplier', $d['supplier'])->where('lot_number', $d['lot_number'])->where('ingredient_name', $d['ingredient_name'])->where('receipt_reference', $d['receipt_reference'])->exists(), 409, 'This ingredient receipt is already recorded.');
        $quantity = PharmacyStock::decimal(PharmacyStock::milli($d['quantity']));
        $row = $d;
        unset($row['quantity']);
        $id = DB::table('pharmacy_ingredient_lots')->insertGetId($row + ['organization_id' => $org, 'request_hash' => $hash, 'on_hand' => $quantity, 'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->event($r, $id, null, 'received', $quantity, ['receipt_reference' => $d['receipt_reference'], 'status' => 'quarantined']);

        return $this->show($r, $id)->setStatusCode(201);
    }

    public function status(Request $r, $id)
    {
        $this->org($r, true);
        $lot = $this->lot($r, $id);
        $d = $r->validate(['version' => 'required|integer|min:1', 'status' => 'required|in:available,quarantined', 'evidence' => 'required|string|max:5000']);
        abort_unless((int) $lot->version === $d['version'], 409, 'Stock changed. Refresh before reviewing.');
        if ($d['status'] === 'available') app(PharmacyCompoundingIncident::class)->assertLotClear((int) $lot->organization_id, (int) $lot->id);
        abort_if($d['status'] === 'available' && DB::table('pharmacy_ingredient_counts')->where('ingredient_lot_id', $id)->where('status', 'pending')->exists(), 422, 'Resolve the pending stock discrepancy before releasing quarantine.');
        abort_if($lot->recall_reference !== null, 422, 'A recalled receipt cannot be released or cleared through a stock status change.');
        abort_if($d['status'] === $lot->status, 422, 'Status is unchanged.');
        abort_if($d['status'] === 'available' && $lot->expires_on < now()->toDateString(), 422, 'Expired ingredients cannot be released from quarantine.');
        DB::table('pharmacy_ingredient_lots')->where('id', $id)->update(['status' => $d['status'], 'version' => $lot->version + 1, 'updated_at' => now()]);
        $this->event($r, $id, null, 'status_changed', '0.000', ['previous' => $lot->status, 'status' => $d['status'], 'evidence' => $d['evidence']]);

        return $this->show($r, $id);
    }

    public function count(Request $r, $id)
    {
        $lot = $this->lot($r, $id);
        $d = $r->validate(['request_id' => 'required|uuid', 'version' => 'required|integer|min:1',
            'counted_quantity' => 'required|numeric|min:0|max:999999.999|decimal:0,3',
            'reason' => 'required|in:physical_count,observed_loss', 'evidence' => 'required|string|max:5000']);
        $hash = hash('sha256', json_encode($d, JSON_THROW_ON_ERROR));
        $old = DB::table('pharmacy_ingredient_counts')->where('ingredient_lot_id', $id)->where('request_id', $d['request_id'])->first();
        if ($old) {
            abort_unless((int) $old->created_by === (int) $r->user()->id && hash_equals($old->request_hash, $hash), 409, 'This request identifier belongs to another count.');
            return $this->show($r, $id);
        }
        app(PharmacyCompoundingIncident::class)->assertLotClear((int) $lot->organization_id, (int) $lot->id);
        abort_unless((int) $lot->version === $d['version'], 409, 'Stock changed. Refresh and recount.');
        abort_if(DB::table('pharmacy_ingredient_counts')->where('ingredient_lot_id', $id)->where('status', 'pending')->exists(), 409, 'A discrepancy is already awaiting review.');
        abort_if($lot->recall_reference !== null, 422, 'Recalled stock requires a separate disposition workflow; do not adjust it through a physical count.');
        $counted = PharmacyStock::milli($d['counted_quantity']);
        $recorded = PharmacyStock::milli($lot->on_hand);
        abort_if($counted === $recorded, 422, 'There is no quantity discrepancy to reconcile.');
        abort_if($d['reason'] !== 'physical_count' && $counted > $recorded, 422, 'An observed loss cannot increase stock.');
        $countId = DB::table('pharmacy_ingredient_counts')->insertGetId([
            'ingredient_lot_id' => $id, 'created_by' => $r->user()->id, 'request_id' => $d['request_id'], 'request_hash' => $hash,
            'recorded_quantity' => $lot->on_hand, 'counted_quantity' => PharmacyStock::decimal($counted), 'lot_version' => $lot->version + 1,
            'reason' => $d['reason'], 'evidence' => $d['evidence'], 'created_at' => now(),
        ]);
        DB::table('pharmacy_ingredient_lots')->where('id', $id)->update(['status' => 'quarantined', 'version' => $lot->version + 1, 'updated_at' => now()]);
        $this->event($r, $id, null, 'discrepancy_recorded', '0.000', ['count_id' => $countId, 'recorded_quantity' => $lot->on_hand, 'counted_quantity' => PharmacyStock::decimal($counted), 'status' => 'quarantined', 'evidence' => $d['evidence']]);
        return $this->show($r, $id)->setStatusCode(201);
    }

    public function reviewCount(Request $r, $id, $countId)
    {
        $this->org($r, true);
        $lot = $this->lot($r, $id);
        $d = $r->validate(['decision' => 'required|in:apply,reject', 'evidence' => 'required|string|max:5000']);
        $count = DB::table('pharmacy_ingredient_counts')->where('ingredient_lot_id', $id)->where('id', $countId)->first();
        abort_unless($count, 404);
        abort_unless($count->status === 'pending', 409, 'This discrepancy has already been reviewed.');
        abort_if((int) $count->created_by === (int) $r->user()->id, 422, 'A different pharmacist must review this discrepancy.');
        $delta = 0;
        if ($d['decision'] === 'apply') {
            app(PharmacyCompoundingIncident::class)->assertLotClear((int) $lot->organization_id, (int) $lot->id);
            abort_unless((int) $lot->version === (int) $count->lot_version && $lot->status === 'quarantined', 409, 'Stock changed after this count. Reject this proposal and record a fresh count.');
            $counted = PharmacyStock::milli($count->counted_quantity);
            abort_unless($counted >= PharmacyStock::milli($lot->reserved), 422, 'Count is below reserved stock. Resolve worksheet reservations, reject this proposal and recount.');
            $delta = $counted - PharmacyStock::milli($lot->on_hand);
            DB::table('pharmacy_ingredient_lots')->where('id', $id)->update(['on_hand' => PharmacyStock::decimal($counted), 'version' => $lot->version + 1, 'updated_at' => now()]);
        }
        DB::table('pharmacy_ingredient_counts')->where('id', $countId)->update(['status' => $d['decision'] === 'apply' ? 'applied' : 'rejected', 'reviewed_by' => $r->user()->id, 'review_evidence' => $d['evidence'], 'reviewed_at' => now()]);
        $signed = ($delta < 0 ? '-' : '').PharmacyStock::decimal(abs($delta));
        $this->event($r, $id, null, $d['decision'] === 'apply' ? 'count_adjustment_applied' : 'count_adjustment_rejected', $signed,
            ['count_id' => (int) $countId, 'before' => $lot->on_hand, 'after' => $d['decision'] === 'apply' ? $count->counted_quantity : $lot->on_hand, 'evidence' => $d['evidence'], 'status' => $lot->status]);
        return $this->show($r, $id);
    }

    public function recall(Request $r, $id)
    {
        $this->org($r, true);
        $lot = $this->lot($r, $id);
        $d = $r->validate(['version' => 'required|integer|min:1', 'reference' => 'required|string|max:2000', 'evidence' => 'required|string|max:5000']);
        abort_if($lot->recall_reference !== null, 409, 'A recall hold is already recorded for this receipt.');
        abort_unless((int) $lot->version === $d['version'], 409, 'Stock changed. Refresh before recording the recall.');
        DB::table('pharmacy_ingredient_lots')->where('id', $id)->update([
            'status' => 'recalled', 'recall_reference' => $d['reference'], 'recall_evidence' => $d['evidence'],
            'recalled_by' => $r->user()->id, 'recalled_at' => now(), 'version' => $lot->version + 1, 'updated_at' => now(),
        ]);
        $this->event($r, $id, null, 'recall_hold_recorded', '0.000', ['reference' => $d['reference'], 'evidence' => $d['evidence'], 'previous_status' => $lot->status, 'status' => 'recalled']);
        return $this->show($r, $id);
    }

    public function allocation(Request $r, $id)
    {
        $org = $this->org($r, true);
        $batch = DB::table('pharmacy_batch_worksheets')->where('organization_id', $org)->where('id', $id)->first();
        abort_unless($batch, 404);
        app(PharmacyAccess::class)->requireLocation($r->user(), $batch->location_id);
        $d = $r->validate(['version' => 'required|integer|min:1', 'action' => 'required|in:reserve,release', 'evidence' => 'required|string|max:5000',
            'lots' => 'required_if:action,reserve|array|min:1|max:30', 'lots.*' => 'array:key,lot_id', 'lots.*.key' => 'required|string|max:40|distinct:strict', 'lots.*.lot_id' => 'required|integer']);
        abort_unless((int) $batch->version === $d['version'], 409, 'Worksheet changed. Refresh before allocating.');
        app(PharmacyCompoundingIncident::class)->assertBatchClear($org, (int) $id);
        $allocations = DB::table('pharmacy_ingredient_allocations')->where('batch_id', $id)->get();
        if ($d['action'] === 'release') {
            abort_unless($allocations->isNotEmpty() && $allocations->every(fn ($a) => $a->status === 'reserved'), 422, 'No active reservation. Released worksheets cannot reserve again.');
            foreach ($allocations as $a) {
                $lot = $this->lot($r, $a->ingredient_lot_id);
                app(PharmacyCompoundingIncident::class)->assertLotClear($org, (int) $lot->id);
                $amount = PharmacyStock::milli($a->quantity);
                $reserved = PharmacyStock::milli($lot->reserved);
                abort_unless($reserved >= $amount, 422, 'Ingredient reconciliation is required.');
                DB::table('pharmacy_ingredient_lots')->where('id', $lot->id)->update(['reserved' => PharmacyStock::decimal($reserved - $amount), 'version' => $lot->version + 1, 'updated_at' => now()]);
                DB::table('pharmacy_ingredient_allocations')->where('id', $a->id)->update(['status' => 'released', 'updated_at' => now()]);
                $this->event($r, $lot->id, $id, 'reservation_released', $a->quantity, ['evidence' => $d['evidence']]);
            }
        } else {
            abort_unless($batch->status === 'reviewed' && $allocations->isEmpty(), 422, 'Use a reviewed worksheet with no previous allocation.');
            $formula = DB::table('pharmacy_formulations')->where('id', $batch->formulation_id)->first();
            abort_unless($formula && $formula->status === 'reviewed', 422, 'The formulation is no longer available for planning.');
            $record = json_decode($batch->record, true, 512, JSON_THROW_ON_ERROR);
            $specs = collect(json_decode($formula->record, true, 512, JSON_THROW_ON_ERROR)['ingredients'])->keyBy('key');
            $rx = DB::table('pharmacy_prescriptions')->where('id', $batch->prescription_id)->first();
            abort_if($rx?->discontinued_at, 422, 'The linked prescription is discontinued. Release unused reservations; no further preparation is allowed.');
            abort_unless($record['planned_on'] >= now()->toDateString() && $rx && $rx->expires_on >= $record['planned_on'], 422, 'The planned date or prescription is no longer current.');
            $selections = collect($d['lots'])->keyBy('key');
            abort_unless($selections->count() === count($record['ingredients']), 422, 'Select stock for every ingredient.');
            foreach ($record['ingredients'] as $line) {
                $selection = $selections->get($line['key']);
                abort_unless($selection, 422, 'Missing ingredient stock selection.');
                $lot = $this->lot($r, $selection['lot_id']);
                app(PharmacyCompoundingIncident::class)->assertLotClear($org, (int) $lot->id);
                abort_unless((int) $lot->location_id === (int) $batch->location_id, 404);
                $spec = $specs->get($line['key']);
                abort_unless($lot->status === 'available' && $lot->expires_on >= $record['planned_on'], 422, 'Ingredient stock is expired or quarantined.');
                abort_unless($lot->ingredient_name === $spec['name'] && $lot->specification === $spec['specification'] && $lot->supplier === $line['supplier'] && $lot->lot_number === $line['lot'] && $lot->certificate_reference === $line['certificate_reference'] && $lot->expires_on === $line['expires_on'] && $lot->quantity_unit === $line['unit'], 422, 'Stock identity, specification and source details must match the reviewed worksheet exactly. Create a corrected worksheet for changes.');
                $amount = PharmacyStock::milli($line['quantity']);
                $reserved = PharmacyStock::milli($lot->reserved);
                abort_unless($amount <= PharmacyStock::milli($lot->on_hand) - $reserved, 422, 'Insufficient available ingredient stock.');
                DB::table('pharmacy_ingredient_lots')->where('id', $lot->id)->update(['reserved' => PharmacyStock::decimal($reserved + $amount), 'version' => $lot->version + 1, 'updated_at' => now()]);
                DB::table('pharmacy_ingredient_allocations')->insert(['batch_id' => $id, 'ingredient_lot_id' => $lot->id, 'ingredient_key' => $line['key'], 'quantity' => $line['quantity'], 'created_at' => now(), 'updated_at' => now()]);
                $this->event($r, $lot->id, $id, 'reserved', $line['quantity'], ['evidence' => $d['evidence']]);
            }
        }
        DB::table('pharmacy_batch_worksheets')->where('id',$id)->update(['version' => $batch->version + 1, 'updated_at' => now()]);
        DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch->formulation_id, 'batch_id' => $id, 'actor_id' => $r->user()->id, 'action' => 'ingredients_'.$d['action'],
            'details' => json_encode(['evidence' => $d['evidence'], 'production_release_enabled' => false],JSON_THROW_ON_ERROR), 'created_at' => now()]);

        return app(PharmacyCompoundingController::class)->showBatch($r,$id);
    }
}
