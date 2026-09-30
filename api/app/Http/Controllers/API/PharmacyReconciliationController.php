<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PharmacyAccess;
use App\Services\PharmacyCompoundingIncident;
use App\Services\PharmacyIncidentAccounting;
use App\Services\PharmacyStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Synthetic reconciliation with independent review; final custody resolution remains separate. */
class PharmacyReconciliationController extends Controller
{
    public function index(Request $r, $incidentId)
    {
        abort_unless(in_array($r->user()?->role, ['pharmacist', 'pharmacy_technician'], true) && $r->user()->organization_id, 403);
        $r->validate(['page' => 'nullable|integer|min:1']);
        $incident = DB::table('pharmacy_compounding_incidents')->where('organization_id', $r->user()->organization_id)->where('id', $incidentId)->first();
        abort_unless($incident, 404);
        app(PharmacyAccess::class)->requireLocation($r->user(), $incident->location_id);
        $records = DB::table('pharmacy_compounding_reconciliations')->where('incident_id', $incidentId)->orderByDesc('id')->paginate(20, [
            'id', 'created_by', 'incident_version', 'proposal', 'evidence', 'status', 'reviewed_by', 'review_evidence', 'reviewed_at', 'created_at']);
        $records->getCollection()->transform(function ($record) {
            $record->proposal = json_decode($record->proposal, true, 512, JSON_THROW_ON_ERROR);

            return $record;
        });

        return response()->json(['data' => $records, 'incident' => ['id' => $incident->id, 'version' => $incident->version, 'status' => $incident->status, 'created_by' => $incident->created_by]]);
    }

    private function snapshot($incident, $lines, $lots): array
    {
        return ['incident' => (array) $incident, 'lines' => $lines->toArray(), 'lots' => $lots->toArray(),
            'batch' => (array) DB::table('pharmacy_batch_worksheets')->find($incident->batch_id),
            'allocations' => DB::table('pharmacy_ingredient_allocations')->whereIn('ingredient_lot_id', $lots->pluck('id'))->orderBy('id')->get()->toArray(),
            'counts' => DB::table('pharmacy_ingredient_counts')->whereIn('ingredient_lot_id', $lots->pluck('id'))->orderBy('id')->get()->toArray()];
    }

    public function apply(Request $r, $incidentId, $proposalId)
    {
        abort_unless($r->user()?->role === 'pharmacist' && $r->user()->organization_id, 403);
        $d = $r->validate(['evidence' => 'required|string|max:5000']);

        return DB::transaction(function () use ($r, $incidentId, $proposalId, $d) {
            $org = (int) $r->user()->organization_id;
            DB::table('organizations')->where('id', $org)->lockForUpdate()->first();
            $incident = DB::table('pharmacy_compounding_incidents')->where('organization_id', $org)->where('id', $incidentId)->lockForUpdate()->first();
            abort_unless($incident, 404);
            app(PharmacyAccess::class)->requireLocation($r->user(), $incident->location_id);
            $proposal = DB::table('pharmacy_compounding_reconciliations')->where('incident_id', $incidentId)->where('id', $proposalId)->lockForUpdate()->first();
            abort_unless($proposal, 404);
            abort_if(in_array((int) $r->user()->id, [(int) $proposal->created_by, (int) $incident->created_by], true), 422, 'A different pharmacist must review the incident reconciliation.');
            if ($proposal->status !== 'pending') {
                abort_unless($proposal->status === 'applied' && (int) $proposal->reviewed_by === (int) $r->user()->id && $proposal->review_evidence === $d['evidence'], 409);

                return response()->json(['data' => ['id' => $proposalId, 'status' => 'applied']]);
            }
            $author = User::find($proposal->created_by);
            abort_unless($author && $author->role === 'pharmacist' && (int) $author->organization_id === $org && $author->status === 'active', 422, 'Proposal author no longer has current pharmacy authority.');
            app(PharmacyAccess::class)->requireLocation($author, $incident->location_id);
            abort_unless($incident->status === 'unresolved' && (int) $incident->version === (int) $proposal->incident_version + 1, 409, 'Incident changed.');
            $lines = DB::table('pharmacy_compounding_incident_lines')->where('incident_id', $incidentId)->orderBy('allocation_id')->get();
            $lots = DB::table('pharmacy_ingredient_lots')->where('organization_id', $org)->where('location_id', $incident->location_id)->whereIn('id', $lines->pluck('ingredient_lot_id'))->orderBy('id')->lockForUpdate()->get();
            $prior = clone $incident;
            $prior->version = $proposal->incident_version;
            $service = app(PharmacyCompoundingIncident::class);
            $snapshot = json_decode($proposal->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $entries = json_decode($proposal->proposal, true, 512, JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($proposal->source_hash, $service->digest($snapshot)) && hash_equals($proposal->source_hash, $service->digest($this->snapshot($prior, $lines, $lots))) && hash_equals($proposal->proposal_hash, $service->digest($entries)), 409, 'Reconciliation evidence or ingredient custody changed.');
            abort_if(DB::table('pharmacy_ingredient_counts')->whereIn('ingredient_lot_id', $lots->pluck('id'))->where('status', 'pending')->exists(), 422, 'Resolve pending ingredient counts first.');
            abort_if(DB::table('pharmacy_compounding_incident_lines as line')->join('pharmacy_compounding_incidents as i', 'i.id', '=', 'line.incident_id')->where('i.organization_id', $org)->whereIn('line.ingredient_lot_id', $lots->pluck('id'))->where('i.id', '<>', $incidentId)->where('i.status', '<>', 'reconciled')->exists(), 422, 'Overlapping ingredient incidents require joint custody reconciliation.');
            $totals = [];
            foreach ($entries as $entry) {
                $q = app(PharmacyIncidentAccounting::class)->line($entry['accounting']['quantities']);
                abort_if($q['unaccounted_remaining'], 422, 'Unaccounted material requires further investigation before ledger application.');
                $lotId = $entry['ingredient_lot_id'];
                $totals[$lotId] ??= ['deduct' => 0, 'reserved' => 0, 'unused' => 0];
                foreach (['consumed', 'disposed_unused'] as $key) {
                    $totals[$lotId]['deduct'] += PharmacyStock::milli($q['quantities'][$key]);
                }
                $totals[$lotId]['reserved'] += PharmacyStock::milli($q['quantities']['reserved_quantity']);
                $totals[$lotId]['unused'] += PharmacyStock::milli($q['quantities']['unused_retained']);
            }
            foreach ($lots as $lot) {
                $v = $totals[$lot->id];
                $onHand = PharmacyStock::milli($lot->on_hand);
                $reserved = PharmacyStock::milli($lot->reserved);
                $remaining = $onHand - $v['deduct'];
                $held = $reserved - $v['reserved'] + $v['unused'];
                abort_unless($reserved >= $v['reserved'] && $remaining >= 0 && $held >= 0 && $remaining >= $held, 422, 'Reconciliation would consume another reservation or create negative stock.');
                DB::table('pharmacy_ingredient_lots')->where('id', $lot->id)->update(['on_hand' => PharmacyStock::decimal($remaining), 'reserved' => PharmacyStock::decimal($held), 'status' => $lot->status === 'recalled' || $lot->recall_reference !== null ? 'recalled' : 'quarantined', 'version' => $lot->version + 1, 'updated_at' => now()]);
                DB::table('pharmacy_ingredient_events')->insert(['ingredient_lot_id' => $lot->id, 'batch_id' => $incident->batch_id, 'actor_id' => $r->user()->id, 'action' => 'incident_consumption_reconciled', 'quantity' => PharmacyStock::decimal($v['deduct']), 'details' => json_encode(['proposal_id' => $proposalId, 'previous_on_hand' => $lot->on_hand, 'remaining_on_hand' => PharmacyStock::decimal($remaining), 'unused_reserved' => PharmacyStock::decimal($v['unused']), 'custody_hold_retained' => true], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            }
            DB::table('pharmacy_ingredient_allocations')->whereIn('id', $lines->pluck('allocation_id'))->update(['status' => 'incident_accounted', 'updated_at' => now()]);
            DB::table('pharmacy_compounding_reconciliations')->where('id', $proposalId)->update(['status' => 'applied', 'reviewed_by' => $r->user()->id, 'review_evidence' => $d['evidence'], 'reviewed_at' => now()]);
            DB::table('pharmacy_compounding_incidents')->where('id', $incidentId)->update(['status' => 'accounted_custody_held', 'version' => $incident->version + 1]);
            $batch = DB::table('pharmacy_batch_worksheets')->find($incident->batch_id);
            DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch->formulation_id, 'batch_id' => $batch->id, 'actor_id' => $r->user()->id, 'action' => 'reconciliation_applied', 'details' => json_encode(['proposal_id' => $proposalId, 'evidence' => $d['evidence'], 'custody_hold_retained' => true, 'output_release_enabled' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);

            return response()->json(['data' => ['id' => $proposalId, 'status' => 'applied']], 200);
        });
    }

    public function reject(Request $r, $incidentId, $proposalId)
    {
        abort_unless($r->user()?->role === 'pharmacist' && $r->user()->organization_id, 403);
        $d = $r->validate(['evidence' => 'required|string|max:5000']);

        return DB::transaction(function () use ($r, $incidentId, $proposalId, $d) {
            $org = (int) $r->user()->organization_id;
            DB::table('organizations')->where('id', $org)->lockForUpdate()->first();
            $incident = DB::table('pharmacy_compounding_incidents')->where('organization_id', $org)->where('id', $incidentId)->lockForUpdate()->first();
            abort_unless($incident, 404);
            app(PharmacyAccess::class)->requireLocation($r->user(), $incident->location_id);
            $proposal = DB::table('pharmacy_compounding_reconciliations')->where('incident_id', $incidentId)->where('id', $proposalId)->lockForUpdate()->first();
            abort_unless($proposal, 404);
            abort_if((int) $proposal->created_by === (int) $r->user()->id || (int) $incident->created_by === (int) $r->user()->id, 422, 'A different pharmacist must review the incident reconciliation.');
            if ($proposal->status !== 'pending') {
                abort_unless($proposal->status === 'rejected' && (int) $proposal->reviewed_by === (int) $r->user()->id && $proposal->review_evidence === $d['evidence'], 409, 'A different decision is already retained.');

                return response()->json(['data' => ['id' => $proposal->id, 'status' => 'rejected']]);
            }
            DB::table('pharmacy_compounding_reconciliations')->where('id', $proposalId)->update(['status' => 'rejected', 'reviewed_by' => $r->user()->id, 'review_evidence' => $d['evidence'], 'reviewed_at' => now()]);
            DB::table('pharmacy_compounding_incidents')->where('id', $incidentId)->update(['version' => $incident->version + 1]);
            $batch = DB::table('pharmacy_batch_worksheets')->find($incident->batch_id);
            DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch->formulation_id, 'batch_id' => $batch->id, 'actor_id' => $r->user()->id, 'action' => 'reconciliation_rejected', 'details' => json_encode(['incident_id' => $incidentId, 'proposal_id' => $proposalId, 'evidence' => $d['evidence'], 'custody_hold_retained' => true, 'stock_adjusted' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);

            return response()->json(['data' => ['id' => $proposalId, 'status' => 'rejected']]);
        });
    }

    public function store(Request $r, $id)
    {
        abort_unless($r->user()?->role === 'pharmacist' && $r->user()->organization_id, 403);
        $rules = ['request_id' => 'required|uuid', 'version' => 'required|integer|min:1', 'evidence' => 'required|string|max:5000',
            'ingredients' => 'required|array|min:1|max:30',
            'ingredients.*' => 'required|array:allocation_id,additional_taken,consumed,unused_retained,disposed_unused,unaccounted,evidence',
            'ingredients.*.allocation_id' => 'required|integer|distinct:strict', 'ingredients.*.evidence' => 'required|string|max:5000'];
        foreach (['additional_taken', 'consumed', 'unused_retained', 'disposed_unused', 'unaccounted'] as $field) {
            $rules['ingredients.*.'.$field] = 'required|numeric|min:0|max:999999.999|decimal:0,3';
        }
        $d = $r->validate($rules);

        return DB::transaction(function () use ($r, $id, $d) {
            $org = (int) $r->user()->organization_id;
            DB::table('organizations')->where('id', $org)->lockForUpdate()->first();
            $incident = DB::table('pharmacy_compounding_incidents')->where('organization_id', $org)->where('id', $id)->lockForUpdate()->first();
            abort_unless($incident, 404);
            app(PharmacyAccess::class)->requireLocation($r->user(), $incident->location_id);
            $hash = app(PharmacyCompoundingIncident::class)->digest($d);
            $old = DB::table('pharmacy_compounding_reconciliations')->where('incident_id', $id)->where('request_id', $d['request_id'])->first();
            if ($old) {
                abort_unless((int) $old->created_by === (int) $r->user()->id && hash_equals($old->request_hash, $hash), 409);

                return response()->json(['data' => ['id' => $old->id, 'status' => $old->status]]);
            }
            abort_unless((int) $incident->version === $d['version'] && $incident->status === 'unresolved', 409, 'Incident changed; refresh before proposing reconciliation.');
            abort_if(DB::table('pharmacy_compounding_reconciliations')->where('incident_id', $id)->where('status', 'pending')->exists(), 409, 'A reconciliation proposal is already pending.');
            $lines = DB::table('pharmacy_compounding_incident_lines')->where('incident_id', $id)->orderBy('allocation_id')->get();
            $submitted = collect($d['ingredients'])->keyBy('allocation_id');
            abort_unless($lines->count() === $submitted->count() && $lines->every(fn ($line) => $submitted->has($line->allocation_id)), 422, 'Account for every original allocation.');
            $proposal = [];
            foreach ($lines as $line) {
                $input = $submitted[$line->allocation_id];
                $projection = app(PharmacyIncidentAccounting::class)->line($input + ['reserved_quantity' => $line->reserved_quantity]);
                $proposal[] = ['allocation_id' => $line->allocation_id, 'ingredient_lot_id' => $line->ingredient_lot_id, 'unit' => $line->quantity_unit, 'accounting' => $projection, 'evidence' => $input['evidence']];
            }
            $lots = DB::table('pharmacy_ingredient_lots')->where('organization_id', $org)->where('location_id', $incident->location_id)->whereIn('id', $lines->pluck('ingredient_lot_id'))->orderBy('id')->lockForUpdate()->get();
            abort_unless($lots->count() === $lines->pluck('ingredient_lot_id')->unique()->count(), 409, 'Ingredient custody is incomplete.');
            $snapshot = $this->snapshot($incident, $lines, $lots);
            $proposalId = DB::table('pharmacy_compounding_reconciliations')->insertGetId(['incident_id' => $id, 'created_by' => $r->user()->id, 'request_id' => $d['request_id'], 'request_hash' => $hash, 'incident_version' => $incident->version,
                'proposal' => json_encode($proposal, JSON_THROW_ON_ERROR), 'proposal_hash' => app(PharmacyCompoundingIncident::class)->digest($proposal), 'source_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'source_hash' => app(PharmacyCompoundingIncident::class)->digest($snapshot), 'evidence' => $d['evidence'], 'created_at' => now()]);
            DB::table('pharmacy_compounding_incidents')->where('id', $id)->update(['version' => $incident->version + 1]);
            $batch = DB::table('pharmacy_batch_worksheets')->find($incident->batch_id);
            DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch->formulation_id, 'batch_id' => $batch->id, 'actor_id' => $r->user()->id, 'action' => 'reconciliation_proposed', 'details' => json_encode(['incident_id' => $id, 'proposal_id' => $proposalId, 'stock_adjusted' => false],JSON_THROW_ON_ERROR), 'created_at' => now()]);

            return response()->json(['data' => ['id' => $proposalId, 'status' => 'pending']],201);
        });
    }
}
