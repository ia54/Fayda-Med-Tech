<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PharmacyStock;
use App\Services\PharmacyAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PharmacyInventoryController extends Controller
{
    private function org(Request $r): int
    {
        abort_unless($r->user()->organization_id, 403, 'An organization is required.');

        return (int) $r->user()->organization_id;
    }

    public function locations(Request $r)
    {
        return response()->json(['data' => app(PharmacyAccess::class)->locations($r->user())->orderBy('name')->get()]);
    }

    public function storeLocation(Request $r)
    {
        abort_unless($r->user()->role === 'admin', 403);
        $d = $r->validate(['name' => 'required|string|max:255', 'address' => 'required|string|max:255', 'license_reference' => 'required|string|max:255']);
        $id = DB::transaction(function () use ($r, $d) {
            $org = $this->org($r);
            DB::table('organizations')->where('id', $org)->lockForUpdate()->first();
            $old = DB::table('pharmacy_locations')->where('organization_id', $org)->where('name', $d['name'])->first();
            if ($old) {
                abort_unless($old->address === $d['address'] && $old->license_reference === $d['license_reference'], 409, 'A location with this name already exists.');

                return $old->id;
            }

            return DB::table('pharmacy_locations')->insertGetId($d + ['organization_id' => $org, 'created_at' => now(), 'updated_at' => now()]);
        });

        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function index(Request $r)
    {
        $d = $r->validate(['location_id' => 'nullable|integer', 'page' => 'nullable|integer|min:1', 'search' => 'nullable|string|max:100', 'status' => 'nullable|in:available,quarantined,recalled']);
        $q = DB::table('pharmacy_stock_lots')->where('organization_id', $this->org($r));
        app(PharmacyAccess::class)->scope($q, $r->user());
        if (! empty($d['location_id'])) {
            $q->where('location_id', $d['location_id']);
        }
        if (! empty($d['status'])) { $q->where('status', $d['status']); }
        if (! empty($d['search'])) {
            $q->where(fn ($q) => $q->where('ndc', 'like', '%'.$d['search'].'%')->orWhere('medication', 'like', '%'.$d['search'].'%')->orWhere('lot_number', 'like', '%'.$d['search'].'%')->orWhere('recall_reference', 'like', '%'.$d['search'].'%'));
        }

        $page = $q->select('id', 'organization_id', 'source_transfer_id', 'location_id', 'ndc', 'medication', 'lot_number', 'quantity_unit', 'expires_on', 'on_hand', 'reserved', 'status', 'version', 'receipt_reference', 'recall_reference')->orderBy('expires_on')->orderBy('id')->paginate(50);
        $page->getCollection()->transform(function ($lot) { $lot->custody_hold = app(PharmacyStock::class)->custodyHold($lot); unset($lot->organization_id); return $lot; });
        return response()->json(['data' => $page]);
    }

    public function show(Request $r, $id)
    {
        abort_unless(in_array($r->user()->role, ['pharmacist', 'pharmacy_technician'], true), 403);
        $r->validate(['fill_page' => 'nullable|integer|min:1', 'event_page' => 'nullable|integer|min:1', 'count_page' => 'nullable|integer|min:1']);
        $lot = DB::table('pharmacy_stock_lots')->where('organization_id', $this->org($r))->where('id', $id)->first();
        abort_unless($lot, 404);
        app(PharmacyAccess::class)->requireLocation($r->user(), $lot->location_id);
        unset($lot->request_id, $lot->request_hash, $lot->recall_request_id);
        $lot->custody_hold = app(PharmacyStock::class)->custodyHold($lot);
        $lot->trace = DB::table('pharmacy_fills as f')->join('pharmacy_prescriptions as rx', 'rx.id', '=', 'f.prescription_id')
            ->where('f.stock_lot_id', $id)->where('rx.organization_id', $lot->organization_id)->where('rx.location_id', $lot->location_id)
            ->select('f.id', 'f.prescription_id', 'rx.rx_number', 'f.fill_number', 'f.quantity', 'f.fulfillment_status', 'f.created_at')
            ->orderByDesc('f.id')->paginate(30, ['*'], 'fill_page');
        $lot->counts = DB::table('pharmacy_stock_counts')->where('stock_lot_id', $id)->select('id', 'created_by', 'recorded_quantity', 'counted_quantity', 'lot_version', 'reason', 'evidence', 'status', 'reviewed_by', 'review_evidence', 'reviewed_at', 'created_at')->orderByDesc('id')->paginate(20, ['*'], 'count_page');
        $lot->count_pending = DB::table('pharmacy_stock_counts')->where('stock_lot_id', $id)->where('status', 'pending')->exists();
        $lot->events = DB::table('pharmacy_stock_events')->where('stock_lot_id', $id)
            ->select('id', 'fill_id', 'actor_id', 'action', 'quantity', 'details', 'created_at')->orderByDesc('id')->paginate(30, ['*'], 'event_page');
        return response()->json(['data' => $lot]);
    }

    private function countLot(Request $r, $id)
    {
        abort_unless(in_array($r->user()->role, ['pharmacist', 'pharmacy_technician'], true), 403);
        $lot = DB::table('pharmacy_stock_lots')->where('organization_id', $this->org($r))->where('id', $id)->lockForUpdate()->first();
        abort_unless($lot, 404);
        app(PharmacyAccess::class)->requireLocation($r->user(), $lot->location_id);
        return $lot;
    }

    public function count(Request $r, $id)
    {
        $lot = $this->countLot($r, $id);
        $d = $r->validate(['request_id' => 'required|uuid', 'version' => 'required|integer|min:1',
            'counted_quantity' => 'required|numeric|min:0|max:999999.999|decimal:0,3',
            'reason' => 'required|in:physical_count,observed_loss', 'evidence' => 'required|string|max:5000']);
        $hash = hash('sha256', json_encode($d, JSON_THROW_ON_ERROR));
        $old = DB::table('pharmacy_stock_counts')->where('stock_lot_id', $id)->where('request_id', $d['request_id'])->first();
        if ($old) {
            abort_unless((int) $old->created_by === (int) $r->user()->id && hash_equals($old->request_hash, $hash), 409, 'This request identifier belongs to another count.');
            return $this->show($r, $id);
        }
        abort_unless((int) $lot->version === $d['version'], 409, 'Stock changed. Refresh and recount.');
        abort_if(DB::table('pharmacy_stock_counts')->where('stock_lot_id', $id)->where('status', 'pending')->exists(), 409, 'A discrepancy is already awaiting review.');
        abort_if($lot->recall_reference !== null, 422, 'Recalled stock requires a separate disposition workflow; do not adjust it through a physical count.');
        $counted = PharmacyStock::milli($d['counted_quantity']);
        $recorded = PharmacyStock::milli($lot->on_hand);
        abort_if($counted === $recorded, 422, 'There is no quantity discrepancy to reconcile.');
        abort_if($d['reason'] !== 'physical_count' && $counted > $recorded, 422, 'An observed loss cannot increase stock.');
        $countId = DB::table('pharmacy_stock_counts')->insertGetId([
            'stock_lot_id' => $id, 'created_by' => $r->user()->id, 'request_id' => $d['request_id'], 'request_hash' => $hash,
            'recorded_quantity' => $lot->on_hand, 'counted_quantity' => PharmacyStock::decimal($counted), 'lot_version' => $lot->version + 1,
            'reason' => $d['reason'], 'evidence' => $d['evidence'], 'created_at' => now(),
        ]);
        DB::table('pharmacy_stock_lots')->where('id', $id)->update(['status' => 'quarantined', 'version' => $lot->version + 1, 'updated_at' => now()]);
        app(PharmacyStock::class)->event($r->user(), $lot, 'discrepancy_recorded', '0.000', ['count_id' => $countId, 'recorded_quantity' => $lot->on_hand, 'counted_quantity' => PharmacyStock::decimal($counted), 'status' => 'quarantined', 'evidence' => $d['evidence']]);
        return $this->show($r, $id)->setStatusCode(201);
    }

    public function reviewCount(Request $r, $id, $countId)
    {
        abort_unless($r->user()->role === 'pharmacist', 403);
        $lot = $this->countLot($r, $id);
        $d = $r->validate(['decision' => 'required|in:apply,reject', 'evidence' => 'required|string|max:5000']);
        $count = DB::table('pharmacy_stock_counts')->where('stock_lot_id', $id)->where('id', $countId)->first();
        abort_unless($count, 404);
        abort_unless($count->status === 'pending', 409, 'This discrepancy has already been reviewed.');
        abort_if((int) $count->created_by === (int) $r->user()->id, 422, 'A different pharmacist must review this discrepancy.');
        $delta = 0;
        if ($d['decision'] === 'apply') {
            abort_unless((int) $lot->version === (int) $count->lot_version && $lot->status === 'quarantined' && $lot->recall_reference === null, 409, 'Stock changed after this count. Reject this proposal and record a fresh count.');
            $counted = PharmacyStock::milli($count->counted_quantity);
            abort_unless($counted >= PharmacyStock::milli($lot->reserved), 422, 'Count is below reserved stock. Resolve fill reservations, reject this proposal and recount.');
            $delta = $counted - PharmacyStock::milli($lot->on_hand);
            DB::table('pharmacy_stock_lots')->where('id', $id)->update(['on_hand' => PharmacyStock::decimal($counted), 'version' => $lot->version + 1, 'updated_at' => now()]);
        }
        DB::table('pharmacy_stock_counts')->where('id', $countId)->update(['status' => $d['decision'] === 'apply' ? 'applied' : 'rejected', 'reviewed_by' => $r->user()->id, 'review_evidence' => $d['evidence'], 'reviewed_at' => now()]);
        $signed = ($delta < 0 ? '-' : '').PharmacyStock::decimal(abs($delta));
        app(PharmacyStock::class)->event($r->user(), $lot, $d['decision'] === 'apply' ? 'count_adjustment_applied' : 'count_adjustment_rejected', $signed,
            ['count_id' => (int) $countId, 'before' => $lot->on_hand, 'after' => $d['decision'] === 'apply' ? $count->counted_quantity : $lot->on_hand, 'evidence' => $d['evidence'], 'status' => $lot->status]);
        return $this->show($r, $id);
    }

    public function recall(Request $r, $id)
    {
        abort_unless($r->user()->role === 'pharmacist', 403);
        $d = $r->validate(['request_id' => 'required|uuid', 'version' => 'required|integer|min:1', 'reference' => 'required|string|max:2000', 'evidence' => 'required|string|max:5000']);
        DB::transaction(function () use ($r, $id, $d) {
            $lot = DB::table('pharmacy_stock_lots')->where('organization_id', $this->org($r))->where('id', $id)->lockForUpdate()->first();
            abort_unless($lot, 404);
            app(PharmacyAccess::class)->requireLocation($r->user(), $lot->location_id);
            if ($lot->recall_request_id === $d['request_id']) {
                abort_unless($lot->recall_reference === $d['reference'] && $lot->recall_evidence === $d['evidence'] && (int) $lot->recalled_by === (int) $r->user()->id, 409, 'Recall retry differs from the retained record.');
                return;
            }
            abort_if($lot->recall_reference !== null, 409, 'A recall hold is already recorded for this receipt.');
            abort_unless((int) $lot->version === $d['version'], 409, 'Stock changed. Refresh before recording the recall.');
            DB::table('pharmacy_stock_lots')->where('id', $id)->update([
                'status' => 'recalled', 'recall_reference' => $d['reference'], 'recall_evidence' => $d['evidence'],
                'recall_request_id' => $d['request_id'], 'recalled_by' => $r->user()->id, 'recalled_at' => now(),
                'version' => $lot->version + 1, 'updated_at' => now(),
            ]);
            app(PharmacyStock::class)->event($r->user(), $lot, 'recall_hold_recorded', '0.000', ['reference' => $d['reference'], 'evidence' => $d['evidence'], 'previous_status' => $lot->status, 'status' => 'recalled']);
        });
        return $this->show($r, $id);
    }

    public function store(Request $r)
    {
        abort_unless(in_array($r->user()->role, ['pharmacist', 'pharmacy_technician'], true), 403);
        $d = $r->validate([
            'request_id' => 'required|uuid', 'location_id' => 'required|integer',
            'ndc' => ['required', 'string', 'regex:/^(\d{10,11}|\d{4}-\d{4}-\d{2}|\d{5}-\d{3}-\d{2}|\d{5}-\d{4}-\d{1,2})$/D'],
            'medication' => 'required|string|max:255', 'lot_number' => 'required|string|max:100',
            'quantity_unit' => 'required|in:tablet,capsule,mL,g,each',
            'expires_on' => 'required|date_format:Y-m-d|after_or_equal:today',
            'quantity' => 'required|numeric|min:0.001|max:999999.999|decimal:0,3',
            'receipt_reference' => 'required|string|max:255',
        ]);
        $id = DB::transaction(function () use ($r, $d) {
            $org = $this->org($r);
            DB::table('organizations')->where('id', $org)->lockForUpdate()->first();
            app(PharmacyAccess::class)->requireLocation($r->user(), $d['location_id']);
            $hashData = $d;
            unset($hashData['request_id']);
            ksort($hashData);
            $hash = hash('sha256', json_encode($hashData, JSON_THROW_ON_ERROR));
            $old = DB::table('pharmacy_stock_lots')->where('organization_id', $org)->where('request_id', $d['request_id'])->first();
            if ($old) {
                abort_unless(hash_equals($old->request_hash, $hash), 409);

                return $old->id;
            }
            abort_unless(DB::table('pharmacy_locations')->where('id', $d['location_id'])->where('organization_id', $org)->where('active', true)->exists(), 404);
            $quantity = PharmacyStock::decimal(PharmacyStock::milli($d['quantity']));
            $row = $d;
            unset($row['quantity']);
            $id = DB::table('pharmacy_stock_lots')->insertGetId($row + ['organization_id' => $org, 'request_hash' => $hash, 'on_hand' => $quantity, 'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            app(PharmacyStock::class)->event($r->user(), (object) ['id' => $id], 'received', $quantity, ['receipt_reference' => $d['receipt_reference']]);

            return $id;
        });

        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function status(Request $r, $id)
    {
        abort_unless($r->user()->role === 'pharmacist', 403);
        $d = $r->validate(['version' => 'required|integer|min:1', 'status' => 'required|in:available,quarantined', 'note' => 'required|string|max:2000']);
        DB::transaction(function () use ($r, $id, $d) {
            $lot = DB::table('pharmacy_stock_lots')->where('organization_id', $this->org($r))->where('id', $id)->lockForUpdate()->first();
            abort_unless($lot, 404);
            app(PharmacyAccess::class)->requireLocation($r->user(), $lot->location_id);
            abort_if(DB::table('pharmacy_stock_counts')->where('stock_lot_id', $id)->where('status', 'pending')->exists(), 422, 'Resolve the pending count discrepancy before changing stock status.');
            abort_if($lot->recall_reference !== null, 422, 'A recalled receipt cannot be cleared through a stock status change.');
            abort_unless((int) $lot->version === (int) $d['version'], 409, 'Stock changed. Refresh before trying again.');
            abort_if($d['status'] === 'available' && $lot->expires_on < now()->toDateString(), 422, 'Expired stock cannot be released.');
            if ($d['status'] === 'available') { app(PharmacyStock::class)->assertReleaseAllowed($lot); }
            DB::table('pharmacy_stock_lots')->where('id', $id)->update(['status' => $d['status'], 'version' => $lot->version + 1, 'updated_at' => now()]);
            app(PharmacyStock::class)->event($r->user(), $lot, 'status_changed', '0.000', ['previous' => $lot->status, 'status' => $d['status'], 'note' => $d['note']]);
        });

        return response()->json(['data' => ['id' => (int) $id]]);
    }
}
