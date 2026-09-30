<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PharmacyAccess;
use App\Services\PharmacyCompoundingIncident;
use App\Services\PharmacyIncidentCustody;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Synthetic unused-material custody proposals; no release or dispensing authority. */
class PharmacyCustodyController extends Controller
{
    public function index(Request $r, $incidentId)
    {
        abort_unless(in_array($r->user()?->role, ['pharmacist', 'pharmacy_technician'], true) && $r->user()->organization_id, 403);
        $r->validate(['page' => 'nullable|integer|min:1']);
        $incident = DB::table('pharmacy_compounding_incidents')->where('organization_id', $r->user()->organization_id)->where('id', $incidentId)->first();
        abort_unless($incident, 404);
        app(PharmacyAccess::class)->requireLocation($r->user(), $incident->location_id);
        $records = DB::table('pharmacy_compounding_custody_decisions')->where('incident_id', $incidentId)->orderByDesc('id')->paginate(20, [
            'id', 'created_by', 'incident_version', 'proposal', 'evidence', 'status', 'reviewed_by', 'review_evidence', 'reviewed_at', 'created_at']);
        $records->getCollection()->transform(function ($record) {
            $record->proposal = json_decode($record->proposal, true, 512, JSON_THROW_ON_ERROR);

            return $record;
        });

        $accounting = DB::table('pharmacy_compounding_reconciliations')->where('incident_id', $incidentId)->where('status', 'applied')->first();
        $unused = $accounting ? array_map(fn ($entry) => ['allocation_id' => $entry['allocation_id'], 'ingredient_lot_id' => $entry['ingredient_lot_id'], 'unit' => $entry['unit'], 'unused_quantity' => $entry['accounting']['quantities']['unused_retained']], json_decode($accounting->proposal, true, 512, JSON_THROW_ON_ERROR)) : [];
        return response()->json(['data' => $records, 'unused' => $unused, 'incident' => ['id' => $incident->id, 'version' => $incident->version, 'status' => $incident->status, 'created_by' => $incident->created_by]]);
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
            $proposal = DB::table('pharmacy_compounding_custody_decisions')->where('incident_id', $incidentId)->where('id', $proposalId)->lockForUpdate()->first();
            abort_unless($proposal, 404);
            abort_if(in_array((int) $r->user()->id, [(int) $proposal->created_by, (int) $incident->created_by], true), 422, 'A different pharmacist must review custody.');
            if ($proposal->status !== 'pending') {
                abort_unless($proposal->status === 'applied' && (int) $proposal->reviewed_by === (int) $r->user()->id && $proposal->review_evidence === $d['evidence'], 409);

                return response()->json(['data' => ['id' => $proposalId, 'status' => 'applied']]);
            }
            $author = User::find($proposal->created_by);
            abort_unless($author && $author->status === 'active' && $author->role === 'pharmacist' && (int) $author->organization_id === $org, 422, 'Custody author no longer has pharmacy authority.');
            app(PharmacyAccess::class)->requireLocation($author, $incident->location_id);
            abort_unless($incident->status === 'accounted_custody_held' && (int) $incident->version === (int) $proposal->incident_version + 1, 409, 'Incident changed.');
            $lines = DB::table('pharmacy_compounding_incident_lines')->where('incident_id', $incidentId)->orderBy('allocation_id')->get();
            $lots = DB::table('pharmacy_ingredient_lots')->where('organization_id', $org)->where('location_id', $incident->location_id)->whereIn('id', $lines->pluck('ingredient_lot_id'))->orderBy('id')->lockForUpdate()->get();
            $reconciliation = DB::table('pharmacy_compounding_reconciliations')->where('incident_id', $incidentId)->where('id', $proposal->reconciliation_id)->where('status', 'applied')->first();
            abort_unless($reconciliation, 409, 'Applied accounting is missing.');
            $prior = clone $incident;
            $prior->version = $proposal->incident_version;
            $fresh = $this->snapshot($prior, $lines, $lots);
            $fresh['reconciliation'] = (array) $reconciliation;
            $service = app(PharmacyCompoundingIncident::class);
            $stored = json_decode($proposal->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $entries = json_decode($proposal->proposal, true, 512, JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($proposal->source_hash, $service->digest($stored)) && hash_equals($proposal->source_hash, $service->digest($fresh)) && hash_equals($proposal->proposal_hash, $service->digest($entries)), 409, 'Custody evidence or stock changed.');
            abort_if(DB::table('pharmacy_ingredient_counts')->whereIn('ingredient_lot_id', $lots->pluck('id'))->where('status', 'pending')->exists(), 422, 'Resolve pending counts first.');
            abort_if(DB::table('pharmacy_compounding_incident_lines as line')->join('pharmacy_compounding_incidents as i', 'i.id', '=', 'line.incident_id')->where('i.organization_id', $org)->whereIn('line.ingredient_lot_id', $lots->pluck('id'))->where('i.id', '<>', $incidentId)->where('i.status', '<>', 'reconciled')->exists(), 422, 'Overlapping incidents require joint custody reconciliation.');
            foreach ($lots as $lot) {
                $lotLines = array_values(array_map(fn ($entry) => $entry['custody'], array_filter($entries, fn ($entry) => (int) $entry['ingredient_lot_id'] === (int) $lot->id)));
                $projection = app(PharmacyIncidentCustody::class)->projectLot($lot->on_hand, $lot->reserved, $lotLines, $lot->status, $lot->recall_reference);
                DB::table('pharmacy_ingredient_lots')->where('id', $lot->id)->update(['on_hand' => $projection['on_hand'], 'reserved' => $projection['reserved'], 'status' => $projection['status'], 'version' => $lot->version + 1, 'updated_at' => now()]);
                DB::table('pharmacy_ingredient_events')->insert(['ingredient_lot_id' => $lot->id, 'batch_id' => $incident->batch_id, 'actor_id' => $r->user()->id, 'action' => 'incident_unused_custody_resolved', 'quantity' => $projection['disposed_unused'], 'details' => json_encode(['proposal_id' => $proposalId, 'previous_on_hand' => $lot->on_hand, 'previous_reserved' => $lot->reserved, 'projection' => $projection], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            }
            DB::table('pharmacy_compounding_custody_decisions')->where('id', $proposalId)->update(['status' => 'applied', 'reviewed_by' => $r->user()->id, 'review_evidence' => $d['evidence'], 'reviewed_at' => now()]);
            DB::table('pharmacy_compounding_incidents')->where('id', $incidentId)->update(['status' => 'reconciled', 'version' => $incident->version + 1]);
            $batch = DB::table('pharmacy_batch_worksheets')->find($incident->batch_id);
            DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch->formulation_id, 'batch_id' => $batch->id, 'actor_id' => $r->user()->id, 'action' => 'custody_applied', 'details' => json_encode(['incident_id' => $incidentId, 'proposal_id' => $proposalId, 'evidence' => $d['evidence'], 'output_release_enabled' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);

            return response()->json(['data' => ['id' => $proposalId, 'status' => 'applied']]);
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
            $proposal = DB::table('pharmacy_compounding_custody_decisions')->where('incident_id', $incidentId)->where('id', $proposalId)->lockForUpdate()->first();
            abort_unless($proposal, 404);
            abort_if((int) $proposal->created_by === (int) $r->user()->id || (int) $incident->created_by === (int) $r->user()->id, 422, 'A different pharmacist must review the incident custody.');
            if ($proposal->status !== 'pending') {
                abort_unless($proposal->status === 'rejected' && (int) $proposal->reviewed_by === (int) $r->user()->id && $proposal->review_evidence === $d['evidence'], 409, 'A different decision is already retained.');

                return response()->json(['data' => ['id' => $proposal->id, 'status' => 'rejected']]);
            }
            DB::table('pharmacy_compounding_custody_decisions')->where('id', $proposalId)->update(['status' => 'rejected', 'reviewed_by' => $r->user()->id, 'review_evidence' => $d['evidence'], 'reviewed_at' => now()]);
            DB::table('pharmacy_compounding_incidents')->where('id', $incidentId)->update(['version' => $incident->version + 1]);
            $batch = DB::table('pharmacy_batch_worksheets')->find($incident->batch_id);
            DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch->formulation_id, 'batch_id' => $batch->id, 'actor_id' => $r->user()->id, 'action' => 'custody_rejected', 'details' => json_encode(['incident_id' => $incidentId, 'proposal_id' => $proposalId, 'evidence' => $d['evidence'], 'custody_hold_retained' => true, 'stock_adjusted' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);

            return response()->json(['data' => ['id' => $proposalId, 'status' => 'rejected']]);
        });
    }

    public function store(Request $r, $id)
    {
        abort_unless($r->user()?->role === 'pharmacist' && $r->user()->organization_id, 403);
        $d = $r->validate([
            'request_id' => 'required|uuid', 'version' => 'required|integer|min:1',
            'evidence' => 'required|string|max:5000', 'ingredients' => 'required|array|min:1|max:30',
            'ingredients.*' => 'required|array:allocation_id,return_to_quarantine,disposed_unused,evidence',
            'ingredients.*.allocation_id' => 'required|integer|distinct:strict',
            'ingredients.*.return_to_quarantine' => 'required|numeric|min:0|max:999999.999|decimal:0,3',
            'ingredients.*.disposed_unused' => 'required|numeric|min:0|max:999999.999|decimal:0,3',
            'ingredients.*.evidence' => 'required|string|max:5000',
        ]);

        return DB::transaction(function () use ($r, $id, $d) {
            $org = (int) $r->user()->organization_id;
            DB::table('organizations')->where('id', $org)->lockForUpdate()->first();
            $incident = DB::table('pharmacy_compounding_incidents')->where('organization_id', $org)->where('id', $id)->lockForUpdate()->first();
            abort_unless($incident, 404);
            app(PharmacyAccess::class)->requireLocation($r->user(), $incident->location_id);
            $service = app(PharmacyCompoundingIncident::class);
            $hash = $service->digest($d);
            $old = DB::table('pharmacy_compounding_custody_decisions')->where('incident_id', $id)->where('request_id', $d['request_id'])->first();
            if ($old) {
                abort_unless((int) $old->created_by === (int) $r->user()->id && hash_equals($old->request_hash, $hash), 409);

                return response()->json(['data' => ['id' => $old->id, 'status' => $old->status]]);
            }
            abort_unless($incident->status === 'accounted_custody_held' && (int) $incident->version === $d['version'], 409, 'Refresh the accounted incident before proposing custody.');
            abort_if(DB::table('pharmacy_compounding_custody_decisions')->where('incident_id', $id)->where('status', 'pending')->exists(), 409, 'A custody proposal is already pending.');
            $applied = DB::table('pharmacy_compounding_reconciliations')->where('incident_id', $id)->where('status', 'applied')->lockForUpdate()->get();
            abort_unless($applied->count() === 1, 409, 'A single applied reconciliation is required.');
            $reconciliation = $applied->first();
            $accounted = json_decode($reconciliation->proposal, true, 512, JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($reconciliation->proposal_hash, $service->digest($accounted)), 409, 'Applied accounting evidence changed.');
            $lines = DB::table('pharmacy_compounding_incident_lines')->where('incident_id', $id)->orderBy('allocation_id')->get();
            $submitted = collect($d['ingredients'])->keyBy('allocation_id');
            $accountedLines = collect($accounted)->keyBy('allocation_id');
            abort_unless($lines->count() === $submitted->count() && $lines->count() === count($accounted) && $lines->every(fn ($line) => $submitted->has($line->allocation_id) && $accountedLines->has($line->allocation_id)), 422, 'Account for every retained allocation.');
            $proposal = [];
            foreach ($lines as $line) {
                $entry = $accountedLines[$line->allocation_id];
                abort_unless((int) $entry['ingredient_lot_id'] === (int) $line->ingredient_lot_id && $entry['unit'] === $line->quantity_unit, 409, 'Ingredient evidence changed.');
                $input = $submitted[$line->allocation_id];
                $projection = app(PharmacyIncidentCustody::class)->line($entry['accounting']['quantities']['unused_retained'], $input);
                $proposal[] = ['allocation_id' => $line->allocation_id, 'ingredient_lot_id' => $line->ingredient_lot_id, 'unit' => $line->quantity_unit, 'custody' => $projection, 'evidence' => $input['evidence']];
            }
            $lots = DB::table('pharmacy_ingredient_lots')->where('organization_id', $org)->where('location_id', $incident->location_id)->whereIn('id', $lines->pluck('ingredient_lot_id'))->orderBy('id')->lockForUpdate()->get();
            abort_unless($lots->count() === $lines->pluck('ingredient_lot_id')->unique()->count(), 409, 'Ingredient custody is incomplete.');
            $snapshot = $this->snapshot($incident, $lines, $lots);
            $snapshot['reconciliation'] = (array) $reconciliation;
            $proposalId = DB::table('pharmacy_compounding_custody_decisions')->insertGetId([
                'incident_id' => $id, 'reconciliation_id' => $reconciliation->id, 'created_by' => $r->user()->id,
                'request_id' => $d['request_id'], 'request_hash' => $hash, 'incident_version' => $incident->version,
                'proposal' => json_encode($proposal, JSON_THROW_ON_ERROR), 'proposal_hash' => $service->digest($proposal),
                'source_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'source_hash' => $service->digest($snapshot),
                'evidence' => $d['evidence'], 'created_at' => now(),
            ]);
            DB::table('pharmacy_compounding_incidents')->where('id', $id)->update(['version' => $incident->version + 1]);
            $batch = DB::table('pharmacy_batch_worksheets')->find($incident->batch_id);
            DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch->formulation_id, 'batch_id' => $batch->id, 'actor_id' => $r->user()->id, 'action' => 'custody_proposed', 'details' => json_encode(['incident_id' => $id, 'proposal_id' => $proposalId, 'stock_adjusted' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);

            return response()->json(['data' => ['id' => $proposalId, 'status' => 'pending']], 201);
        });
    }
}
