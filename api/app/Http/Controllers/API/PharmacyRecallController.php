<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use App\Services\PharmacyRecall;
use App\Services\PharmacyStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Shared notice scope; affected patient/stock records remain location-scoped. */
class PharmacyRecallController extends Controller
{
    private function org(Request $r, bool $write = false): int
    {
        abort_unless($r->user()->organization_id && in_array($r->user()->role, $write ? ['pharmacist'] : ['pharmacist', 'pharmacy_technician'], true), 403);
        abort_unless(app(PharmacyAccess::class)->locations($r->user())->exists(), 403, 'An active pharmacy location assignment is required.');
        return (int) $r->user()->organization_id;
    }
    public function index(Request $r)
    {
        $r->validate(['page' => 'nullable|integer|min:1', 'search' => 'nullable|string|max:100', 'status' => 'nullable|in:active,pending,withdrawn']);
        $q = DB::table('pharmacy_recall_notices')->where('organization_id', $this->org($r));
        if ($r->input('status') === 'withdrawn') { $q->whereNotNull('withdrawn_at'); }
        elseif ($r->input('status') === 'active') { $q->whereNull('withdrawn_at'); }
        elseif ($r->input('status') === 'pending') { $q->whereIn('id', DB::table('pharmacy_recall_corrections')->where('status', 'pending')->select('notice_id')); }
        if ($r->filled('search')) { $search = '%'.$r->input('search').'%'; $q->where(fn ($q) => $q->where('reference', 'like', $search)->orWhere('product_description', 'like', $search)->orWhere('lot_number', 'like', $search)); }
        $page = $q->select('id', 'version', 'withdrawn_at', 'product_description', 'reference', 'lot_number', 'all_lots', 'ndcs', 'created_by', 'created_at')->orderByDesc('id')->paginate(25);
        $page->getCollection()->transform(function ($n) { $n->ndcs = json_decode($n->ndcs, true); $n->correction_pending = DB::table('pharmacy_recall_corrections')->where('notice_id', $n->id)->where('status', 'pending')->exists(); return $n; });
        return response()->json(['data' => $page]);
    }
    public function show(Request $r, $id)
    {
        $r->validate(['stock_page' => 'nullable|integer|min:1', 'fill_page' => 'nullable|integer|min:1', 'follow_up' => 'nullable|in:not_started,open,completed', 'correction_page' => 'nullable|integer|min:1']);
        $n = DB::table('pharmacy_recall_notices')->where('organization_id', $this->org($r))->where('id', $id)->first(); abort_unless($n, 404);
        unset($n->request_id, $n->request_hash, $n->lot_key); $n->ndcs = json_decode($n->ndcs, true);
        $n->correction_pending = DB::table('pharmacy_recall_corrections')->where('notice_id', $n->id)->where('status', 'pending')->exists();
        $n->corrections = DB::table('pharmacy_recall_corrections')->where('notice_id', $n->id)->orderByDesc('id')->paginate(10, ['id', 'created_by', 'notice_version', 'reason', 'evidence', 'status', 'reviewed_by', 'review_evidence', 'reviewed_at', 'created_at'], 'correction_page');
        // Matching needs the retained key, but it is not an API/display identifier.
        $match = clone $n; $match->lot_key = $n->all_lots ? null : PharmacyRecall::keys('', $n->lot_number)['recall_lot_key'];
        $stock = app(PharmacyRecall::class)->matchNotice(DB::table('pharmacy_stock_lots as stock'), $match);
        app(PharmacyAccess::class)->scope($stock, $r->user(), 'stock.location_id');
        $n->stock = $stock->select('stock.id', 'stock.location_id', 'stock.ndc', 'stock.medication', 'stock.lot_number', 'stock.expires_on', 'stock.on_hand', 'stock.reserved', 'stock.quantity_unit', 'stock.status')->orderBy('stock.location_id')->orderBy('stock.id')->paginate(25, ['*'], 'stock_page');
        $fills = DB::table('pharmacy_fills as f')->join('pharmacy_stock_lots as stock', 'stock.id', '=', 'f.stock_lot_id')
            ->join('pharmacy_prescriptions as rx', 'rx.id', '=', 'f.prescription_id')->where('rx.organization_id', $n->organization_id)->whereColumn('rx.location_id', 'stock.location_id');
        app(PharmacyRecall::class)->matchNotice($fills, $match); app(PharmacyAccess::class)->scope($fills, $r->user(), 'stock.location_id');
        $fills->leftJoin('pharmacy_recall_follow_ups as follow', function ($join) use ($n) { $join->on('follow.fill_id', '=', 'f.id')->where('follow.notice_id', $n->id); });
        if ($r->input('follow_up') === 'not_started') { $fills->whereNull('follow.id'); }
        elseif ($r->filled('follow_up')) { $fills->where('follow.status', $r->input('follow_up')); }
        $n->fills = $fills->selectRaw("COALESCE(follow.status, 'not_started') as follow_up_status")->addSelect('f.id', 'f.prescription_id', 'rx.rx_number', 'stock.id as stock_lot_id', 'stock.location_id', 'f.fill_number', 'f.quantity', 'stock.quantity_unit', 'f.fulfillment_status', 'f.created_at')->orderByDesc('f.id')->paginate(25, ['*'], 'fill_page');
        return response()->json(['data' => $n]);
    }
    private function requestHash(array $d): string
    {
        unset($d['request_id']); ksort($d);
        return hash('sha256', json_encode($d, JSON_THROW_ON_ERROR));
    }
    public function requestCorrection(Request $r, $id)
    {
        $org = $this->org($r, true);
        $n = DB::table('pharmacy_recall_notices')->where('organization_id', $org)->where('id', $id)->lockForUpdate()->first(); abort_unless($n, 404);
        $d = $r->validate(['request_id' => 'required|uuid', 'version' => 'required|integer|min:1', 'reason' => 'required|string|max:2000', 'evidence' => 'required|string|max:5000', 'confirmed' => 'required|accepted']);
        $old = DB::table('pharmacy_recall_corrections')->where('notice_id', $n->id)->where('request_id', $d['request_id'])->first();
        if ($old) {
            abort_unless((int) $old->created_by === (int) $r->user()->id && hash_equals($old->request_hash, $this->requestHash($d)), 409, 'This correction request already has different retained evidence.');
            return $this->show($r, $id);
        }
        abort_if($n->withdrawn_at, 409, 'This internal notice has already been withdrawn as an erroneous entry.');
        abort_unless((int) $n->version === (int) $d['version'], 409, 'The notice changed. Refresh before requesting a correction.');
        abort_if(DB::table('pharmacy_recall_corrections')->where('notice_id', $n->id)->where('status', 'pending')->exists(), 409, 'A correction is already awaiting independent review.');
        DB::table('pharmacy_recall_corrections')->insert(['notice_id' => $n->id, 'created_by' => $r->user()->id, 'request_id' => $d['request_id'], 'request_hash' => $this->requestHash($d), 'notice_version' => $n->version + 1, 'reason' => $d['reason'], 'evidence' => $d['evidence'], 'created_at' => now()]);
        DB::table('pharmacy_recall_notices')->where('id', $n->id)->update(['version' => $n->version + 1]);
        return $this->show($r, $id);
    }
    public function reviewCorrection(Request $r, $id, $correctionId)
    {
        $org = $this->org($r, true);
        $n = DB::table('pharmacy_recall_notices')->where('organization_id', $org)->where('id', $id)->lockForUpdate()->first(); abort_unless($n, 404);
        $c = DB::table('pharmacy_recall_corrections')->where('notice_id', $n->id)->where('id', $correctionId)->first(); abort_unless($c, 404);
        $d = $r->validate(['request_id' => 'required|uuid', 'version' => 'required|integer|min:1', 'decision' => 'required|in:apply,reject', 'evidence' => 'required|string|max:5000', 'confirmed' => 'required|accepted']);
        if ($c->status !== 'pending') {
            abort_unless((int) $c->reviewed_by === (int) $r->user()->id && $c->review_request_id === $d['request_id'] && hash_equals($c->review_hash, $this->requestHash($d)), 409, 'This correction already has a retained review.');
            return $this->show($r, $id);
        }
        abort_if((int) $c->created_by === (int) $r->user()->id, 422, 'A different assigned pharmacist must independently review this correction.');
        if ($d['decision'] === 'apply') {
            abort_if($n->withdrawn_at, 409, 'This notice was already withdrawn.');
            abort_unless((int) $n->version === (int) $d['version'] && (int) $n->version === (int) $c->notice_version, 409, 'This notice changed. Reject the stale request and reassess.');
            // Organization-write serialization covers receipts arriving before or during this decision.
            // Existing matching receipts remain unusable until their own local release review.
            $lots = app(PharmacyRecall::class)->matchNotice(DB::table('pharmacy_stock_lots as stock'), $n)->orderBy('stock.id')->lockForUpdate()->get(['stock.*']);
            foreach ($lots as $lot) {
                $status = $lot->status === 'recalled' || $lot->recall_reference !== null ? 'recalled' : 'quarantined';
                DB::table('pharmacy_stock_lots')->where('id', $lot->id)->update(['status' => $status, 'version' => $lot->version + 1, 'updated_at' => now()]);
                app(PharmacyStock::class)->event($r->user(), $lot, 'notice_correction_quarantine', '0.000', ['notice_id' => $n->id, 'correction_id' => $c->id, 'previous_status' => $lot->status, 'status' => $status]);
            }
            DB::table('pharmacy_recall_notices')->where('id', $n->id)->update(['withdrawn_at' => now(), 'version' => $n->version + 1]);
        } else {
            DB::table('pharmacy_recall_notices')->where('id', $n->id)->update(['version' => $n->version + 1]);
        }
        DB::table('pharmacy_recall_corrections')->where('id', $c->id)->update(['status' => $d['decision'] === 'apply' ? 'applied' : 'rejected', 'reviewed_by' => $r->user()->id, 'review_request_id' => $d['request_id'], 'review_hash' => $this->requestHash($d), 'review_evidence' => $d['evidence'], 'reviewed_at' => now()]);
        return $this->show($r, $id);
    }
    public function store(Request $r)
    {
        $org = $this->org($r, true);
        $d = $r->validate(['request_id' => 'required|uuid', 'product_description' => 'required|string|max:255', 'reference' => 'required|string|max:2000', 'evidence' => 'required|string|max:5000', 'all_lots' => 'required|boolean', 'lot_number' => 'required_unless:all_lots,true|nullable|string|max:100', 'ndcs' => 'required|array|min:1|max:20', 'ndcs.*' => ['required', 'string', 'distinct:strict', 'regex:/^(\d{10,11}|\d{4}-\d{4}-\d{2}|\d{5}-\d{3}-\d{2}|\d{5}-\d{4}-\d{1,2})$/D']]);
        abort_if($d['all_lots'] && ! empty($d['lot_number']), 422, 'Choose either one lot or all lots, not both.');
        $hash = hash('sha256', json_encode($d, JSON_THROW_ON_ERROR));
        $old = DB::table('pharmacy_recall_notices')->where('organization_id', $org)->where('request_id', $d['request_id'])->first();
        if ($old) { abort_unless((int) $old->created_by === (int) $r->user()->id && hash_equals($old->request_hash, $hash), 409); return $this->show($r, $old->id); }
        $lotNumber = $d['all_lots'] ? null : trim($d['lot_number']);
        abort_if(! $d['all_lots'] && $lotNumber === '', 422, 'The lot identifier cannot be blank.');
        $id = DB::table('pharmacy_recall_notices')->insertGetId(['organization_id' => $org, 'created_by' => $r->user()->id, 'request_id' => $d['request_id'], 'request_hash' => $hash, 'product_description' => $d['product_description'], 'reference' => $d['reference'], 'evidence' => $d['evidence'], 'all_lots' => $d['all_lots'], 'lot_number' => $lotNumber, 'lot_key' => $lotNumber === null ? null : PharmacyRecall::keys('', $lotNumber)['recall_lot_key'], 'ndcs' => json_encode($d['ndcs']), 'created_at' => now()]);
        foreach (array_unique(array_map(fn ($ndc) => PharmacyRecall::keys($ndc, '')['recall_ndc_key'], $d['ndcs'])) as $key) {
            DB::table('pharmacy_recall_codes')->insert(['notice_id' => $id, 'ndc_key' => $key]);
        }
        return $this->show($r, $id)->setStatusCode(201);
    }
}
