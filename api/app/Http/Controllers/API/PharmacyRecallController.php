<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use App\Services\PharmacyRecall;
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
        $r->validate(['page' => 'nullable|integer|min:1', 'search' => 'nullable|string|max:100']);
        $q = DB::table('pharmacy_recall_notices')->where('organization_id', $this->org($r));
        if ($r->filled('search')) { $search = '%'.$r->input('search').'%'; $q->where(fn ($q) => $q->where('reference', 'like', $search)->orWhere('product_description', 'like', $search)->orWhere('lot_number', 'like', $search)); }
        $page = $q->select('id', 'product_description', 'reference', 'lot_number', 'all_lots', 'ndcs', 'created_by', 'created_at')->orderByDesc('id')->paginate(25);
        $page->getCollection()->transform(function ($n) { $n->ndcs = json_decode($n->ndcs, true); return $n; });
        return response()->json(['data' => $page]);
    }
    public function show(Request $r, $id)
    {
        $r->validate(['stock_page' => 'nullable|integer|min:1', 'fill_page' => 'nullable|integer|min:1']);
        $n = DB::table('pharmacy_recall_notices')->where('organization_id', $this->org($r))->where('id', $id)->first(); abort_unless($n, 404);
        unset($n->request_id, $n->request_hash, $n->lot_key); $n->ndcs = json_decode($n->ndcs, true);
        // Matching needs the retained key, but it is not an API/display identifier.
        $match = clone $n; $match->lot_key = $n->all_lots ? null : PharmacyRecall::keys('', $n->lot_number)['recall_lot_key'];
        $stock = app(PharmacyRecall::class)->matchNotice(DB::table('pharmacy_stock_lots as stock'), $match);
        app(PharmacyAccess::class)->scope($stock, $r->user(), 'stock.location_id');
        $n->stock = $stock->select('stock.id', 'stock.location_id', 'stock.ndc', 'stock.medication', 'stock.lot_number', 'stock.expires_on', 'stock.on_hand', 'stock.reserved', 'stock.quantity_unit', 'stock.status')->orderBy('stock.location_id')->orderBy('stock.id')->paginate(25, ['*'], 'stock_page');
        $fills = DB::table('pharmacy_fills as f')->join('pharmacy_stock_lots as stock', 'stock.id', '=', 'f.stock_lot_id')
            ->join('pharmacy_prescriptions as rx', 'rx.id', '=', 'f.prescription_id')->where('rx.organization_id', $n->organization_id)->whereColumn('rx.location_id', 'stock.location_id');
        app(PharmacyRecall::class)->matchNotice($fills, $match); app(PharmacyAccess::class)->scope($fills, $r->user(), 'stock.location_id');
        $n->fills = $fills->select('f.id', 'f.prescription_id', 'rx.rx_number', 'stock.id as stock_lot_id', 'stock.location_id', 'f.fill_number', 'f.quantity', 'stock.quantity_unit', 'f.fulfillment_status', 'f.created_at')->orderByDesc('f.id')->paginate(25, ['*'], 'fill_page');
        return response()->json(['data' => $n]);
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
