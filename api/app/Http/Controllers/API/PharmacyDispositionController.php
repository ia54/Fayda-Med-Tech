<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use App\Services\PharmacyDisposition;
use App\Services\PharmacyStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PharmacyDispositionController extends Controller
{
    private function lot(Request $r, $id): object
    {
        abort_unless(in_array($r->user()->role, ['pharmacist', 'pharmacy_technician'], true), 403);
        $lot = DB::table('pharmacy_stock_lots')->where('organization_id', $r->user()->organization_id)->where('id', $id)->lockForUpdate()->first();
        abort_unless($lot, 404);
        app(PharmacyAccess::class)->requireLocation($r->user(), $lot->location_id);
        return $lot;
    }

    public function index(Request $r, $id)
    {
        $this->lot($r, $id);
        $r->validate(['page' => 'nullable|integer|min:1']);
        $rows = DB::table('pharmacy_stock_dispositions')->where('stock_lot_id', $id)->select('id', 'created_by', 'quantity', 'kind', 'occurred_on', 'destination',
            'classification_evidence', 'authority_reference', 'completion_reference', 'reason', 'lot_version', 'status', 'reviewed_by', 'review_evidence', 'reviewed_at', 'created_at')
            ->orderByDesc('id')->paginate(10);
        return response()->json(['data' => ['records' => $rows, 'pending' => app(PharmacyDisposition::class)->pending((int) $id)]]);
    }

    public function store(Request $r, $id)
    {
        abort_unless($r->user()->role === 'pharmacist', 403);
        $lot = $this->lot($r, $id);
        $d = $r->validate(['request_id' => 'required|uuid', 'version' => 'required|integer|min:1', 'product_id' => 'required|integer|min:1',
            'quantity' => 'required|numeric|min:0.001|max:999999.999|decimal:0,3', 'kind' => 'required|in:supplier_return,disposal',
            'occurred_on' => 'required|date_format:Y-m-d|before_or_equal:today', 'destination' => 'required|string|max:2000',
            'classification_evidence' => 'required|string|max:5000', 'authority_reference' => 'required|string|max:5000',
            'completion_reference' => 'required|string|max:5000', 'reason' => 'required|string|max:2000', 'scope_confirmed' => 'required|accepted']);
        $d['quantity'] = PharmacyStock::decimal(PharmacyStock::milli($d['quantity'])); ksort($d);
        $hash = hash('sha256', json_encode($d, JSON_THROW_ON_ERROR));
        $old = DB::table('pharmacy_stock_dispositions')->where('stock_lot_id', $id)->where('request_id', $d['request_id'])->first();
        if ($old) {
            abort_unless((int) $old->created_by === (int) $r->user()->id && hash_equals($old->request_hash, $hash), 409, 'This request identifier belongs to another disposition.');
            return $this->index($r, $id);
        }
        abort_unless((int) $lot->version === (int) $d['version'], 409, 'Stock changed. Refresh and verify the physical evidence.');
        abort_if(app(PharmacyDisposition::class)->pending((int) $id) || DB::table('pharmacy_stock_counts')->where('stock_lot_id', $id)->where('status', 'pending')->exists(), 422, 'Resolve the pending disposition or count first.');
        abort_if(PharmacyStock::milli($lot->reserved) !== 0, 422, 'Resolve all fill and transfer reservations before disposition.');
        abort_if(PharmacyStock::milli($d['quantity']) > PharmacyStock::milli($lot->on_hand), 422, 'Disposition exceeds recorded stock. Reconcile the evidence first.');
        abort_if($d['occurred_on'] < substr($lot->created_at, 0, 10), 422, 'Disposition cannot predate this receipt.');
        $source = app(PharmacyDisposition::class)->source($lot);
        abort_unless($source['product_id'] === (int) $d['product_id'], 409, 'Product verification changed. Refresh and review the source product.');
        $snapshot = json_encode($source, JSON_THROW_ON_ERROR);
        unset($d['scope_confirmed'], $d['version'], $d['product_id']);
        $record = DB::table('pharmacy_stock_dispositions')->insertGetId($d + ['stock_lot_id' => $id, 'created_by' => $r->user()->id,
            'request_hash' => $hash, 'lot_version' => $lot->version + 1, 'source_snapshot' => $snapshot, 'source_hash' => hash('sha256', $snapshot), 'created_at' => now()]);
        DB::table('pharmacy_stock_lots')->where('id', $id)->update(['status' => $lot->status === 'recalled' ? 'recalled' : 'quarantined', 'version' => $lot->version + 1, 'updated_at' => now()]);
        app(PharmacyStock::class)->event($r->user(), $lot, 'disposition_recorded', '0.000', ['disposition_id' => $record, 'quantity_awaiting_review' => $d['quantity']]);
        return $this->index($r, $id)->setStatusCode(201);
    }

    public function review(Request $r, $id, $dispositionId)
    {
        abort_unless($r->user()->role === 'pharmacist', 403);
        $lot = $this->lot($r, $id);
        $record = DB::table('pharmacy_stock_dispositions')->where('stock_lot_id', $id)->where('id', $dispositionId)->first();
        abort_unless($record, 404);
        $d = $r->validate(['request_id' => 'required|uuid', 'decision' => 'required|in:apply,reject', 'evidence' => 'required|string|max:5000', 'confirmed' => 'required|accepted']);
        ksort($d); $hash = hash('sha256', json_encode($d, JSON_THROW_ON_ERROR));
        if ($record->status !== 'pending') {
            abort_unless((int) $record->reviewed_by === (int) $r->user()->id && $record->review_request_id === $d['request_id'] && hash_equals($record->review_hash, $hash), 409, 'This disposition has already been reviewed.');
            return $this->index($r, $id);
        }
        abort_if((int) $record->created_by === (int) $r->user()->id, 422, 'A different pharmacist must independently review this disposition.');
        $delta = '0.000';
        if ($d['decision'] === 'apply') {
            abort_unless((int) $lot->version === (int) $record->lot_version && in_array($lot->status, ['quarantined', 'recalled'], true), 409, 'Stock changed. Reject the stale record and submit fresh evidence; do not perform the physical disposition twice.');
            abort_unless(hash_equals($record->source_hash, hash('sha256', $record->source_snapshot))
                && hash_equals($record->source_hash, hash('sha256', json_encode(app(PharmacyDisposition::class)->source($lot), JSON_THROW_ON_ERROR))), 409, 'Product or recall evidence changed. Reject and record a fresh disposition against the current source.');
            abort_if(PharmacyStock::milli($lot->reserved) !== 0 || DB::table('pharmacy_stock_counts')->where('stock_lot_id', $id)->where('status', 'pending')->exists(), 422, 'Resolve stock reservations or pending counts first.');
            $remaining = PharmacyStock::milli($lot->on_hand) - PharmacyStock::milli($record->quantity);
            abort_if($remaining < 0, 422, 'Disposition exceeds recorded stock.');
            DB::table('pharmacy_stock_lots')->where('id', $id)->update(['on_hand' => PharmacyStock::decimal($remaining), 'version' => $lot->version + 1, 'updated_at' => now()]);
            $delta = '-'.$record->quantity;
        }
        DB::table('pharmacy_stock_dispositions')->where('id', $dispositionId)->update(['status' => $d['decision'] === 'apply' ? 'applied' : 'rejected',
            'reviewed_by' => $r->user()->id, 'review_request_id' => $d['request_id'], 'review_hash' => $hash, 'review_evidence' => $d['evidence'], 'reviewed_at' => now()]);
        app(PharmacyStock::class)->event($r->user(), $lot, $d['decision'] === 'apply' ? 'disposition_applied' : 'disposition_rejected', $delta,
            ['disposition_id' => (int) $dispositionId, 'decision' => $d['decision'], 'evidence' => $d['evidence'], 'before' => $lot->on_hand]);
        return $this->index($r, $id);
    }
}
