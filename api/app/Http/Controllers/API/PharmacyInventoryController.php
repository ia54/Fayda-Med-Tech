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
        $d = $r->validate(['location_id' => 'nullable|integer', 'page' => 'nullable|integer|min:1', 'search' => 'nullable|string|max:100']);
        $q = DB::table('pharmacy_stock_lots')->where('organization_id', $this->org($r));
        app(PharmacyAccess::class)->scope($q, $r->user());
        if (! empty($d['location_id'])) {
            $q->where('location_id', $d['location_id']);
        }
        if (! empty($d['search'])) {
            $q->where(fn ($q) => $q->where('ndc', 'like', '%'.$d['search'].'%')->orWhere('medication', 'like', '%'.$d['search'].'%'));
        }

        return response()->json(['data' => $q->orderBy('expires_on')->orderBy('id')->paginate(50)]);
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
            abort_unless((int) $lot->version === (int) $d['version'], 409, 'Stock changed. Refresh before trying again.');
            abort_if($d['status'] === 'available' && $lot->expires_on < now()->toDateString(), 422, 'Expired stock cannot be released.');
            DB::table('pharmacy_stock_lots')->where('id', $id)->update(['status' => $d['status'], 'version' => $lot->version + 1, 'updated_at' => now()]);
            app(PharmacyStock::class)->event($r->user(), $lot, 'status_changed', '0.000', ['previous' => $lot->status, 'status' => $d['status'], 'note' => $d['note']]);
        });

        return response()->json(['data' => ['id' => (int) $id]]);
    }
}
