<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use App\Services\PharmacyCompoundingIncident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Observation retention only. No stock reconciliation or product release. */
class PharmacyCompoundingIncidentController extends Controller
{
    public function worklist(Request $r)
    {
        abort_unless(in_array($r->user()?->role, ['pharmacist', 'pharmacy_technician'], true) && $r->user()->organization_id, 403);
        $d = $r->validate(['page' => 'nullable|integer|min:1', 'location_id' => 'nullable|integer', 'status' => 'nullable|in:open,reconciled,all']);
        $q = DB::table('pharmacy_compounding_incidents as i')
            ->join('pharmacy_batch_worksheets as b', 'b.id', '=', 'i.batch_id')
            ->where('i.organization_id', $r->user()->organization_id)
            ->where('b.organization_id', $r->user()->organization_id);
        app(PharmacyAccess::class)->scope($q, $r->user(), 'i.location_id');
        if (!empty($d['location_id'])) {
            app(PharmacyAccess::class)->requireLocation($r->user(), $d['location_id']);
            $q->where('i.location_id', $d['location_id']);
        }
        $status = $d['status'] ?? 'open';
        if ($status === 'open') $q->where('i.status', '<>', 'reconciled');
        if ($status === 'reconciled') $q->where('i.status', 'reconciled');
        return response()->json(['data' => $q->orderBy('i.created_at')->orderBy('i.id')->paginate(20, [
            'i.id', 'i.batch_id', 'i.location_id', 'i.status', 'i.follow_up_owner', 'i.observed_at', 'i.created_at', 'b.batch_number',
        ])]);
    }

    public function index(Request $r, $id)
    {
        abort_unless(in_array($r->user()?->role, ['pharmacist', 'pharmacy_technician'], true) && $r->user()->organization_id, 403);
        $r->validate(['page' => 'nullable|integer|min:1']);
        $batch = DB::table('pharmacy_batch_worksheets')->where('organization_id', $r->user()->organization_id)->where('id', $id)->first();
        abort_unless($batch, 404);
        app(PharmacyAccess::class)->requireLocation($r->user(), $batch->location_id);
        $records = DB::table('pharmacy_compounding_incidents')->where('organization_id', $batch->organization_id)
            ->where('batch_id', $id)->orderByDesc('id')->paginate(20, [
                'id', 'created_by', 'batch_version', 'observed_at', 'findings', 'custody_evidence', 'follow_up_owner', 'status', 'version', 'created_at',
            ]);
        $lines = DB::table('pharmacy_compounding_incident_lines')->whereIn('incident_id', $records->getCollection()->pluck('id'))
            ->orderBy('id')->get(['incident_id', 'allocation_id', 'ingredient_lot_id', 'ingredient_key', 'quantity_unit', 'reserved_quantity', 'observed_quantity', 'measurement_evidence'])->groupBy('incident_id');
        $records->getCollection()->transform(function ($record) use ($lines) {
            $record->ingredients = $lines->get($record->id, collect())->values();
            return $record;
        });
        return response()->json(['data' => $records]);
    }

    public function store(Request $r, $id)
    {
        abort_unless($r->user()?->role === 'pharmacist' && $r->user()->organization_id, 403);
        $d = $r->validate(['request_id' => 'required|uuid', 'version' => 'required|integer|min:1',
            'observed_at' => 'required|date_format:Y-m-d H:i:s|before_or_equal:now',
            'findings' => 'required|string|max:5000', 'custody_evidence' => 'required|string|max:5000', 'follow_up_owner' => 'required|string|max:255',
            'ingredients' => 'required|array|min:1|max:30', 'ingredients.*' => 'required|array:allocation_id,observed_quantity,measurement_evidence',
            'ingredients.*.allocation_id' => 'required|integer|distinct:strict',
            'ingredients.*.observed_quantity' => 'present|nullable|numeric|min:0|max:999999.999|decimal:0,3',
            'ingredients.*.measurement_evidence' => 'required|string|max:5000']);

        return DB::transaction(function () use ($r, $id, $d) {
            $org = (int) $r->user()->organization_id;
            DB::table('organizations')->where('id', $org)->lockForUpdate()->first();
            $batch = DB::table('pharmacy_batch_worksheets')->where('organization_id', $org)->where('id', $id)->lockForUpdate()->first();
            abort_unless($batch, 404);
            app(PharmacyAccess::class)->requireLocation($r->user(), $batch->location_id);
            $hash = hash('sha256', json_encode($d, JSON_THROW_ON_ERROR));
            $old = DB::table('pharmacy_compounding_incidents')->where('organization_id', $org)->where('request_id', $d['request_id'])->first();
            if ($old) {
                abort_unless((int) $old->batch_id === (int) $id && (int) $old->created_by === (int) $r->user()->id && hash_equals($old->request_hash, $hash), 409);

                return response()->json(['data' => ['id' => $old->id, 'status' => $old->status]]);
            }
            abort_unless((int) $batch->version === $d['version'], 409, 'Worksheet changed. Refresh before retaining the incident.');
            abort_if(DB::table('pharmacy_batch_executions')->where('batch_id', $id)->exists(), 422, 'An existing execution requires its own correction and reconciliation workflow.');
            abort_if(DB::table('pharmacy_compounding_incidents')->where('batch_id', $id)->where('status', '<>', 'reconciled')->exists(), 409, 'An unresolved incident already exists.');
            $allocations = DB::table('pharmacy_ingredient_allocations')->where('batch_id', $id)->orderBy('id')->get();
            $observations = collect($d['ingredients'])->keyBy('allocation_id');
            abort_unless($allocations->isNotEmpty() && $allocations->count() === $observations->count() && $allocations->every(fn ($a) => $a->status === 'reserved' && $observations->has($a->id)), 422, 'Retain every active allocation; explicitly use null for unknown consumption.');
            $lots = DB::table('pharmacy_ingredient_lots')->where('organization_id', $org)->where('location_id', $batch->location_id)->whereIn('id', $allocations->pluck('ingredient_lot_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($lots->count() === $allocations->pluck('ingredient_lot_id')->unique()->count(), 409, 'Ingredient custody is incomplete.');
            $snapshot = json_encode(['worksheet' => (array) $batch, 'allocations' => $allocations->toArray(), 'lots' => $lots->values()->toArray()], JSON_THROW_ON_ERROR);
            $incident = DB::table('pharmacy_compounding_incidents')->insertGetId(['organization_id' => $org, 'location_id' => $batch->location_id, 'batch_id' => $id, 'created_by' => $r->user()->id, 'request_id' => $d['request_id'], 'request_hash' => $hash, 'batch_version' => $batch->version, 'source_snapshot' => $snapshot, 'source_hash' => app(PharmacyCompoundingIncident::class)->digest(json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR)), 'observed_at' => $d['observed_at'], 'findings' => $d['findings'], 'custody_evidence' => $d['custody_evidence'], 'follow_up_owner' => $d['follow_up_owner'], 'created_at' => now()]);
            foreach ($allocations as $a) {
                $line = $observations->get($a->id);
                DB::table('pharmacy_compounding_incident_lines')->insert(['incident_id' => $incident, 'allocation_id' => $a->id, 'ingredient_lot_id' => $a->ingredient_lot_id, 'ingredient_key' => $a->ingredient_key, 'quantity_unit' => $lots[$a->ingredient_lot_id]->quantity_unit, 'reserved_quantity' => $a->quantity, 'observed_quantity' => $line['observed_quantity'], 'measurement_evidence' => $line['measurement_evidence']]);
            }
            foreach ($lots as $lot) {
                $status = $lot->recall_reference !== null || $lot->status === 'recalled' ? 'recalled' : 'quarantined';
                DB::table('pharmacy_ingredient_lots')->where('id', $lot->id)->update(['status' => $status, 'version' => $lot->version + 1, 'updated_at' => now()]);
                DB::table('pharmacy_ingredient_events')->insert(['ingredient_lot_id' => $lot->id, 'batch_id' => $id, 'actor_id' => $r->user()->id, 'action' => 'preparation_incident_hold', 'quantity' => '0.000', 'details' => json_encode(['incident_id' => $incident, 'previous_status' => $lot->status, 'status' => $status], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            }
            DB::table('pharmacy_batch_worksheets')->where('id', $id)->update(['version' => $batch->version + 1, 'updated_at' => now()]);
            DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch->formulation_id, 'batch_id' => $id, 'actor_id' => $r->user()->id, 'action' => 'preparation_incident_recorded', 'details' => json_encode(['incident_id' => $incident, 'source_hash' => app(PharmacyCompoundingIncident::class)->digest(json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR)), 'stock_adjusted' => false, 'production_release_enabled' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);

            return response()->json(['data' => ['id' => $incident, 'status' => 'unresolved']],201);
        });
    }
}
