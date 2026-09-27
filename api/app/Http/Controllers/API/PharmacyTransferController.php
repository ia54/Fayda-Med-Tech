<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use App\Services\PharmacyStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Synthetic custody ledger. All writes run inside PharmacyWriteTransaction. */
class PharmacyTransferController extends Controller
{
    private function org(Request $r, bool $write = false): int
    {
        abort_unless(in_array($r->user()->role, $write ? ['pharmacist'] : ['pharmacist', 'pharmacy_technician'], true), 403);
        abort_unless($r->user()->organization_id, 403);
        return (int) $r->user()->organization_id;
    }
    private function scoped(Request $r)
    {
        $sites = app(PharmacyAccess::class)->locations($r->user())->select('id');
        return DB::table('pharmacy_stock_transfers')->where('organization_id', $this->org($r))
            ->where(fn ($q) => $q->whereIn('source_location_id', clone $sites)->orWhereIn('destination_location_id', clone $sites));
    }
    private function hash(array $d): string { ksort($d); return hash('sha256', json_encode($d, JSON_THROW_ON_ERROR)); }
    private function event(Request $r, int $id, array $d, array $details): void
    {
        DB::table('pharmacy_stock_transfer_events')->insert(['transfer_id' => $id, 'actor_id' => $r->user()->id, 'request_id' => $d['request_id'], 'request_hash' => $this->hash($d), 'action' => $d['action'], 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
    public function destinations(Request $r)
    {
        return response()->json(['data' => DB::table('pharmacy_locations')->where('organization_id', $this->org($r))->where('active', true)->orderBy('name')->get(['id', 'name'])]);
    }
    public function index(Request $r)
    {
        $d = $r->validate(['page' => 'nullable|integer|min:1', 'status' => 'nullable|in:planned,dispatched,received,received_corrected,received_discrepancy,cancelled']);
        $q = $this->scoped($r);
        if (! empty($d['status'])) { $q->where('status', $d['status']); }
        $page = $q->select('id', 'source_lot_id', 'source_location_id', 'destination_location_id', 'quantity', 'received_quantity', 'corrected_received_quantity', 'product', 'status', 'version', 'created_at')->orderByDesc('id')->paginate(25);
        $page->getCollection()->transform(function ($t) { $t->product = json_decode($t->product, true); return $t; });
        return response()->json(['data' => $page]);
    }
    public function show(Request $r, $id)
    {
        $r->validate(['correction_page' => 'nullable|integer|min:1', 'event_page' => 'nullable|integer|min:1']);
        $t = $this->scoped($r)->where('id', $id)->first(); abort_unless($t, 404);
        unset($t->request_id, $t->request_hash);
        $t->product = json_decode($t->product, true);
        $t->can_dispatch = app(PharmacyAccess::class)->locations($r->user())->where('id', $t->source_location_id)->exists();
        $t->can_receive = app(PharmacyAccess::class)->locations($r->user())->where('id', $t->destination_location_id)->exists();
        $t->corrections = DB::table('pharmacy_transfer_corrections as c')->join('pharmacy_stock_counts as count', 'count.id', '=', 'c.stock_count_id')->where('c.transfer_id', $id)->orderByDesc('c.id')->paginate(20, ['c.id', 'c.stock_count_id', 'c.created_by', 'c.lot_version', 'c.evidence', 'c.status', 'c.reviewed_by', 'c.review_evidence', 'c.reviewed_at', 'c.created_at', 'count.counted_quantity as verified_quantity', 'count.evidence as count_evidence', 'count.review_evidence as count_review_evidence', 'count.reviewed_by as count_reviewed_by'], 'correction_page');
        $t->correction_candidates = $t->can_receive && $t->status === 'received_discrepancy' ? DB::table('pharmacy_stock_counts')->where('stock_lot_id', $t->destination_lot_id)->where('status', 'applied')->where('reason', 'physical_count')->orderByDesc('id')->limit(20)->get(['id', 'counted_quantity', 'reviewed_by', 'reviewed_at']) : [];
        $t->correction_pending = DB::table('pharmacy_transfer_corrections')->where('transfer_id', $id)->where('status', 'pending')->exists();
        $t->events = DB::table('pharmacy_stock_transfer_events')->where('transfer_id', $id)->orderByDesc('id')->paginate(25, ['id', 'actor_id', 'action', 'details', 'created_at'], 'event_page');
        return response()->json(['data' => $t]);
    }
    public function store(Request $r)
    {
        $org = $this->org($r, true);
        $d = $r->validate(['request_id' => 'required|uuid', 'source_lot_id' => 'required|integer', 'destination_location_id' => 'required|integer', 'version' => 'required|integer|min:1', 'quantity' => 'required|numeric|min:0.001|max:999999.999|decimal:0,3', 'reference' => 'required|string|max:2000']);
        $lot = DB::table('pharmacy_stock_lots')->where('organization_id', $org)->where('id', $d['source_lot_id'])->lockForUpdate()->first(); abort_unless($lot, 404);
        app(PharmacyAccess::class)->requireLocation($r->user(), $lot->location_id);
        $old = DB::table('pharmacy_stock_transfers')->where('organization_id', $org)->where('request_id', $d['request_id'])->first();
        if ($old) { abort_unless((int) $old->created_by === (int) $r->user()->id && hash_equals($old->request_hash, $this->hash($d)), 409); return $this->show($r, $old->id); }
        abort_if((int) $lot->location_id === (int) $d['destination_location_id'], 422, 'Choose a different receiving location.');
        abort_unless(DB::table('pharmacy_locations')->where('organization_id', $org)->where('id', $d['destination_location_id'])->where('active', true)->exists(), 404);
        abort_unless((int) $lot->version === (int) $d['version'], 409, 'Stock changed. Refresh before planning a transfer.');
        app(PharmacyStock::class)->assertUsable($lot);
        $quantity = PharmacyStock::milli($d['quantity']);
        abort_unless($quantity <= PharmacyStock::milli($lot->on_hand) - PharmacyStock::milli($lot->reserved), 422, 'Insufficient unreserved stock.');
        $product = collect((array) $lot)->only(['ndc', 'medication', 'lot_number', 'quantity_unit', 'expires_on'])->all();
        $id = DB::table('pharmacy_stock_transfers')->insertGetId(['organization_id' => $org, 'source_lot_id' => $lot->id, 'source_location_id' => $lot->location_id, 'destination_location_id' => $d['destination_location_id'], 'quantity' => PharmacyStock::decimal($quantity), 'product' => json_encode($product), 'request_id' => $d['request_id'], 'request_hash' => $this->hash($d), 'reference' => $d['reference'], 'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pharmacy_stock_lots')->where('id', $lot->id)->update(['reserved' => PharmacyStock::decimal(PharmacyStock::milli($lot->reserved) + $quantity), 'version' => $lot->version + 1, 'updated_at' => now()]);
        app(PharmacyStock::class)->event($r->user(), $lot, 'transfer_reserved', PharmacyStock::decimal($quantity), ['transfer_id' => $id]);
        $this->event($r, $id, $d + ['action' => 'planned'], ['reference' => $d['reference']]);
        return $this->show($r, $id)->setStatusCode(201);
    }
    public function action(Request $r, $id)
    {
        $org = $this->org($r, true);
        $d = $r->validate(['request_id' => 'required|uuid', 'version' => 'required|integer|min:1', 'action' => 'required|in:dispatch,cancel,receive', 'evidence' => 'required|string|max:5000', 'received_quantity' => 'required_if:action,receive|nullable|numeric|min:0|max:999999.999|decimal:0,3']);
        $t = $this->scoped($r)->where('id', $id)->lockForUpdate()->first(); abort_unless($t, 404);
        app(PharmacyAccess::class)->requireLocation($r->user(), $d['action'] === 'receive' ? $t->destination_location_id : $t->source_location_id);
        $old = DB::table('pharmacy_stock_transfer_events')->where('transfer_id', $id)->where('request_id', $d['request_id'])->first();
        if ($old) { abort_unless((int) $old->actor_id === (int) $r->user()->id && hash_equals($old->request_hash, $this->hash($d)), 409); return $this->show($r, $id); }
        abort_unless((int) $t->version === (int) $d['version'], 409, 'Transfer changed. Refresh before recording this action.');
        $lot = DB::table('pharmacy_stock_lots')->where('organization_id', $org)->where('id', $t->source_lot_id)->lockForUpdate()->first(); abort_unless($lot, 404);
        $quantity = PharmacyStock::milli($t->quantity);
        $update = ['version' => $t->version + 1, 'updated_at' => now()];
        if ($d['action'] === 'receive') {
            abort_unless($t->status === 'dispatched', 422, 'Only dispatched stock can be received.');
            abort_if((int) $t->dispatched_by === (int) $r->user()->id, 422, 'A different pharmacist must verify receipt.');
            $received = PharmacyStock::milli($d['received_quantity']);
            $product = json_decode($t->product, true);
            $destinationLot = DB::table('pharmacy_stock_lots')->insertGetId($product + ['organization_id' => $org, 'location_id' => $t->destination_location_id, 'request_id' => (string) Str::uuid(), 'request_hash' => $this->hash($d), 'on_hand' => PharmacyStock::decimal($received), 'reserved' => '0.000', 'status' => 'quarantined', 'receipt_reference' => 'Internal transfer #'.$t->id, 'source_transfer_id' => $t->id, 'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $update += ['status' => $received === $quantity ? 'received' : 'received_discrepancy', 'received_quantity' => PharmacyStock::decimal($received), 'destination_lot_id' => $destinationLot, 'received_by' => $r->user()->id, 'received_at' => now(), 'receipt_evidence' => $d['evidence']];
            app(PharmacyStock::class)->event($r->user(), (object) ['id' => $destinationLot], 'transfer_received_quarantined', PharmacyStock::decimal($received), ['transfer_id' => (int) $id, 'dispatched_quantity' => $t->quantity, 'evidence' => $d['evidence']]);
        } else {
            abort_unless($t->status === 'planned', 422, 'Only a planned transfer can be dispatched or cancelled.');
            if ($d['action'] === 'dispatch') {
                app(PharmacyStock::class)->assertUsable($lot);
                abort_unless(DB::table('pharmacy_locations')->where('organization_id', $org)->where('id', $t->destination_location_id)->where('active', true)->exists(), 422, 'Receiving location is inactive.');
            }
            $reserved = PharmacyStock::milli($lot->reserved); $onHand = PharmacyStock::milli($lot->on_hand);
            abort_unless($reserved >= $quantity && $onHand >= $quantity, 422, 'Reconcile source stock before changing custody.');
            DB::table('pharmacy_stock_lots')->where('id', $lot->id)->update(['reserved' => PharmacyStock::decimal($reserved - $quantity), 'on_hand' => PharmacyStock::decimal($onHand - ($d['action'] === 'dispatch' ? $quantity : 0)), 'version' => $lot->version + 1, 'updated_at' => now()]);
            $update += ['status' => $d['action'] === 'dispatch' ? 'dispatched' : 'cancelled'];
            if ($d['action'] === 'dispatch') { $update += ['dispatched_by' => $r->user()->id, 'dispatched_at' => now(), 'dispatch_evidence' => $d['evidence']]; }
            app(PharmacyStock::class)->event($r->user(), $lot, $d['action'] === 'dispatch' ? 'transfer_dispatched' : 'transfer_reservation_released', $t->quantity, ['transfer_id' => (int) $id, 'evidence' => $d['evidence']]);
        }
        DB::table('pharmacy_stock_transfers')->where('id', $id)->update($update);
        $this->event($r, (int) $id, $d, ['evidence' => $d['evidence'], 'received_quantity' => $d['received_quantity'] ?? null]);
        return $this->show($r, $id);
    }

    private function correctionStock($t)
    {
        $lot = DB::table('pharmacy_stock_lots')->where('organization_id', $t->organization_id)->where('location_id', $t->destination_location_id)->where('id', $t->destination_lot_id)->lockForUpdate()->first();
        abort_unless($lot && (int) $lot->source_transfer_id === (int) $t->id, 409, 'Receiving stock custody is incomplete.');
        return $lot;
    }

    private function validateCorrectionCount($t, $lot, int $countId): void
    {
        abort_unless($t->status === 'received_discrepancy', 422, 'Only a receipt discrepancy can be corrected.');
        abort_unless($lot->status === 'quarantined' && $lot->recall_reference === null && PharmacyStock::milli($lot->reserved) === 0, 422, 'Receiving stock must remain quarantined without reservations or recall.');
        abort_if(DB::table('pharmacy_stock_counts')->where('stock_lot_id', $lot->id)->where('status', 'pending')->exists(), 409, 'Resolve the pending stock count before receipt correction.');
        $count = DB::table('pharmacy_stock_counts')->where('stock_lot_id', $lot->id)->where('id', $countId)->first();
        abort_unless($count && $count->status === 'applied' && $count->reason === 'physical_count' && (int) $count->lot_version + 1 === (int) $lot->version, 409, 'Use the latest independently applied physical count for this destination receipt.');
        abort_unless(PharmacyStock::milli($count->counted_quantity) === PharmacyStock::milli($t->quantity) && PharmacyStock::milli($lot->on_hand) === PharmacyStock::milli($t->quantity), 422, 'Receipt-entry correction requires the verified physical count to match the original dispatch. Actual loss or excess needs a separate investigation.');
        $source = DB::table('pharmacy_stock_lots')->where('organization_id', $t->organization_id)->where('id', $t->source_lot_id)->first();
        abort_unless($source, 409);
        app(PharmacyStock::class)->assertReleaseAllowed($source);
    }

    public function proposeCorrection(Request $r, $id)
    {
        $this->org($r, true);
        $d = $r->validate(['request_id' => 'required|uuid', 'version' => 'required|integer|min:1', 'stock_count_id' => 'required|integer', 'evidence' => 'required|string|max:5000']);
        $t = $this->scoped($r)->where('id', $id)->lockForUpdate()->first(); abort_unless($t, 404);
        app(PharmacyAccess::class)->requireLocation($r->user(), $t->destination_location_id);
        $old = DB::table('pharmacy_transfer_corrections')->where('transfer_id', $id)->where('request_id', $d['request_id'])->first();
        if ($old) { abort_unless((int) $old->created_by === (int) $r->user()->id && hash_equals($old->request_hash, $this->hash($d)), 409); return $this->show($r, $id); }
        abort_if(DB::table('pharmacy_stock_transfer_events')->where('transfer_id', $id)->where('request_id', $d['request_id'])->exists(), 409, 'This request identifier was already used for another custody action.');
        abort_unless((int) $t->version === (int) $d['version'], 409, 'Transfer changed. Refresh before proposing a correction.');
        abort_if(DB::table('pharmacy_transfer_corrections')->where('transfer_id', $id)->where('status', 'pending')->exists(), 409, 'A receipt correction is already awaiting source review.');
        $lot = $this->correctionStock($t);
        $this->validateCorrectionCount($t, $lot, (int) $d['stock_count_id']);
        $correction = DB::table('pharmacy_transfer_corrections')->insertGetId(['transfer_id' => $id, 'stock_count_id' => $d['stock_count_id'], 'created_by' => $r->user()->id, 'request_id' => $d['request_id'], 'request_hash' => $this->hash($d), 'lot_version' => $lot->version, 'evidence' => $d['evidence'], 'created_at' => now()]);
        DB::table('pharmacy_stock_transfers')->where('id', $id)->update(['version' => $t->version + 1, 'updated_at' => now()]);
        $this->event($r, (int) $id, $d + ['action' => 'correction_proposed'], ['correction_id' => $correction, 'stock_count_id' => $d['stock_count_id'], 'evidence' => $d['evidence']]);
        return $this->show($r, $id)->setStatusCode(201);
    }

    public function reviewCorrection(Request $r, $id, $correctionId)
    {
        $this->org($r, true);
        $d = $r->validate(['request_id' => 'required|uuid', 'version' => 'required|integer|min:1', 'decision' => 'required|in:apply,reject', 'evidence' => 'required|string|max:5000']);
        $t = $this->scoped($r)->where('id', $id)->lockForUpdate()->first(); abort_unless($t, 404);
        app(PharmacyAccess::class)->requireLocation($r->user(), $t->source_location_id);
        $c = DB::table('pharmacy_transfer_corrections')->where('transfer_id', $id)->where('id', $correctionId)->first(); abort_unless($c, 404);
        $eventData = $d + ['correction_id' => (int) $correctionId, 'action' => 'correction_reviewed'];
        $old = DB::table('pharmacy_stock_transfer_events')->where('transfer_id', $id)->where('request_id', $d['request_id'])->first();
        if ($old) { abort_unless((int) $old->actor_id === (int) $r->user()->id && hash_equals($old->request_hash, $this->hash($eventData)), 409); return $this->show($r, $id); }
        abort_unless((int) $t->version === (int) $d['version'] && $c->status === 'pending', 409, 'Transfer or correction changed. Refresh before reviewing.');
        abort_if((int) $c->created_by === (int) $r->user()->id, 422, 'A different pharmacist at the sending location must review the correction.');
        $update = ['version' => $t->version + 1, 'updated_at' => now()];
        if ($d['decision'] === 'apply') {
            $lot = $this->correctionStock($t);
            abort_unless((int) $lot->version === (int) $c->lot_version, 409, 'Receiving stock changed after this proposal. Reject it and investigate again.');
            $this->validateCorrectionCount($t, $lot, (int) $c->stock_count_id);
            $update += ['status' => 'received_corrected', 'receipt_correction_id' => $c->id, 'corrected_received_quantity' => $lot->on_hand];
            app(PharmacyStock::class)->event($r->user(), $lot, 'transfer_receipt_corrected', '0.000', ['transfer_id' => (int) $id, 'correction_id' => $c->id, 'original_received_quantity' => $t->received_quantity, 'corrected_received_quantity' => $lot->on_hand, 'evidence' => $d['evidence']]);
        }
        DB::table('pharmacy_transfer_corrections')->where('id', $c->id)->update(['status' => $d['decision'] === 'apply' ? 'applied' : 'rejected', 'reviewed_by' => $r->user()->id, 'review_evidence' => $d['evidence'], 'reviewed_at' => now()]);
        DB::table('pharmacy_stock_transfers')->where('id', $id)->update($update);
        $this->event($r, (int) $id, $eventData, ['correction_id' => $c->id, 'decision' => $d['decision'], 'evidence' => $d['evidence']]);
        return $this->show($r, $id);
    }
}
