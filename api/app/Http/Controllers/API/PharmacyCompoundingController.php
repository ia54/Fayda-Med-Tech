<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use App\Services\PharmacyStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Documentary planning/review only. No manufacture, inventory deduction or release. */
class PharmacyCompoundingController extends Controller
{
    private function org(Request $r, bool $pharmacist = false): int
    {
        abort_unless(in_array($r->user()->role, $pharmacist ? ['pharmacist'] : ['pharmacist', 'pharmacy_technician'], true), 403);
        abort_unless($r->user()->organization_id && app(PharmacyAccess::class)->locations($r->user())->exists(), 403);

        return (int) $r->user()->organization_id;
    }

    private function hash(array $d): string
    {
        unset($d['request_id']);
        ksort($d);

        return hash('sha256', json_encode($d, JSON_THROW_ON_ERROR));
    }

    private function event(Request $r, int $formula, ?int $batch, string $action, array $data): void
    {
        DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $formula, 'batch_id' => $batch,
            'actor_id' => $r->user()->id, 'action' => $action, 'details' => json_encode($data, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    private function formula(Request $r, $id)
    {
        $f = DB::table('pharmacy_formulations')->where('organization_id', $this->org($r))->where('id', $id)->first();
        abort_unless($f, 404);
        $f->record = json_decode($f->record, true, 512, JSON_THROW_ON_ERROR);

        return $f;
    }

    public function formulas(Request $r)
    {
        $d = $r->validate(['page' => 'nullable|integer|min:1', 'search' => 'nullable|string|max:100']);
        $q = DB::table('pharmacy_formulations')->where('organization_id', $this->org($r));
        if (! empty($d['search'])) {
            $q->where(fn ($q) => $q->where('code', 'like', '%'.$d['search'].'%')->orWhere('name', 'like', '%'.$d['search'].'%'));
        }

        return response()->json(['data' => $q->select('id', 'code', 'revision', 'name', 'preparation_type', 'hazardous', 'status', 'version', 'created_by')->orderByDesc('id')->paginate(20)]);
    }

    public function showFormula(Request $r, $id)
    {
        $f = $this->formula($r, $id);
        unset($f->request_hash, $f->request_id);
        // Formula history must not disclose another site's patient-linked batch events.
        $f->events = DB::table('pharmacy_compounding_events')->where('formulation_id', $id)->whereNull('batch_id')->orderByDesc('id')->limit(100)->get();

        return response()->json(['data' => $f]);
    }

    public function createFormula(Request $r)
    {
        $org = $this->org($r, true);
        $d = $r->validate([
            'request_id' => 'required|uuid', 'code' => ['required', 'string', 'max:80', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/D'],
            'name' => 'required|string|max:255', 'preparation_type' => 'required|in:sterile,nonsterile', 'hazardous' => 'required|boolean',
            'strength' => 'required|string|max:255', 'dosage_form' => 'required|string|max:100',
            'output_quantity' => 'required|numeric|min:0.001|max:999999.999|decimal:0,3', 'output_unit' => 'required|in:mg,g,mL,each,capsule,tablet',
            'source_reference' => 'required|string|max:2000', 'method' => 'required|string|max:20000',
            'quality_checks' => 'required|string|max:10000', 'storage' => 'required|string|max:2000', 'bud_basis' => 'required|string|max:5000',
            'aseptic_process' => 'nullable|required_if:preparation_type,sterile|string|max:10000',
            'hazard_controls' => 'nullable|required_if:hazardous,true|string|max:10000',
            'ingredients' => 'required|array|min:1|max:30', 'ingredients.*' => 'required|array:key,name,quantity,unit,specification',
            'ingredients.*.key' => 'required|string|max:40|distinct:strict', 'ingredients.*.name' => 'required|string|max:255',
            'ingredients.*.quantity' => 'required|numeric|min:0.001|max:999999.999|decimal:0,3',
            'ingredients.*.unit' => 'required|in:mg,g,mL,each,capsule,tablet', 'ingredients.*.specification' => 'required|string|max:2000',
        ]);
        $old = DB::table('pharmacy_formulations')->where('organization_id', $org)->where('request_id', $d['request_id'])->first();
        if ($old) {
            abort_unless(hash_equals($old->request_hash, $this->hash($d)), 409);

            return $this->showFormula($r, $old->id);
        }
        $revision = 1 + (int) DB::table('pharmacy_formulations')->where('organization_id', $org)->where('code', $d['code'])->max('revision');
        $record = $d;
        unset($record['request_id'],$record['code'],$record['name'],$record['preparation_type'],$record['hazardous']);
        $id = DB::table('pharmacy_formulations')->insertGetId(['organization_id' => $org, 'code' => $d['code'], 'revision' => $revision, 'name' => $d['name'],
            'preparation_type' => $d['preparation_type'], 'hazardous' => $d['hazardous'], 'record' => json_encode($record, JSON_THROW_ON_ERROR),
            'created_by' => $r->user()->id, 'request_id' => $d['request_id'], 'request_hash' => $this->hash($d), 'created_at' => now(), 'updated_at' => now()]);
        $this->event($r, $id, null, 'formulation_created', ['revision' => $revision]);

        return $this->showFormula($r, $id)->setStatusCode(201);
    }

    public function reviewFormula(Request $r, $id)
    {
        $this->org($r, true);
        $f = $this->formula($r, $id);
        $d = $r->validate(['version' => 'required|integer|min:1', 'action' => 'required|in:review,retire', 'evidence' => 'required|string|max:5000']);
        abort_unless((int) $f->version === $d['version'], 409, 'Record changed. Refresh before reviewing.');
        if ($d['action'] === 'review') {
            abort_unless($f->status === 'draft', 422, 'Only a draft can be reviewed.');
            abort_if((int) $f->created_by === (int) $r->user()->id, 422, 'An independent pharmacist must review the formulation.');
        } else {
            abort_if($f->status === 'retired', 422, 'Already retired.');
        }
        $status = $d['action'] === 'review' ? 'reviewed' : 'retired';
        DB::table('pharmacy_formulations')->where('id', $id)->update(['status' => $status, 'version' => $f->version + 1, 'updated_at' => now()]);
        $this->event($r, $id, null, 'formulation_'.$status, ['previous' => $f->status, 'evidence' => $d['evidence']]);

        return $this->showFormula($r, $id);
    }

    public function batches(Request $r)
    {
        $org = $this->org($r);
        $r->validate(['page' => 'nullable|integer|min:1']);
        $q = DB::table('pharmacy_batch_worksheets')->where('organization_id', $org);
        app(PharmacyAccess::class)->scope($q, $r->user());

        return response()->json(['data' => $q->select('id', 'batch_number', 'location_id', 'prescription_id', 'formulation_id', 'status', 'version')->orderByDesc('id')->paginate(20)]);
    }

    public function showBatch(Request $r, $id)
    {
        $q = DB::table('pharmacy_batch_worksheets')->where('organization_id', $this->org($r))->where('id', $id);
        app(PharmacyAccess::class)->scope($q, $r->user());
        $b = $q->first();
        abort_unless($b, 404);
        unset($b->request_hash,$b->request_id);
        $b->record = json_decode($b->record, true, 512, JSON_THROW_ON_ERROR);
        $b->formula = $this->formula($r, $b->formulation_id);
        unset($b->formula->request_hash,$b->formula->request_id);
        $b->events = DB::table('pharmacy_compounding_events')->where('batch_id', $id)->orderByDesc('id')->limit(100)->get();
        $b->allocations = DB::table('pharmacy_ingredient_allocations as a')
            ->join('pharmacy_ingredient_lots as l', 'l.id', '=', 'a.ingredient_lot_id')
            ->where('a.batch_id', $id)
            ->select('a.*', 'l.status as lot_status', 'l.expires_on as lot_expires_on', 'l.lot_number', 'l.quantity_unit')->get();
        $b->production_release_enabled = false;

        return response()->json(['data' => $b]);
    }

    public function createBatch(Request $r)
    {
        $org = $this->org($r);
        $d = $r->validate(['request_id' => 'required|uuid', 'prescription_id' => 'required|integer', 'formulation_id' => 'required|integer', 'batch_number' => 'required|string|max:100',
            'planned_on' => 'required|date_format:Y-m-d|after_or_equal:today', 'calculation_reference' => 'required|string|max:2000',
            'prescription_match_reference' => 'required|string|max:2000', 'site_process_reference' => 'required|string|max:2000',
            'ingredients' => 'required|array|min:1|max:30', 'ingredients.*' => 'required|array:key,supplier,lot,expires_on,quantity,unit,certificate_reference',
            'ingredients.*.key' => 'required|string|max:40|distinct:strict', 'ingredients.*.supplier' => 'required|string|max:255', 'ingredients.*.lot' => 'required|string|max:100',
            'ingredients.*.expires_on' => 'required|date_format:Y-m-d|after_or_equal:planned_on',
            'ingredients.*.quantity' => 'required|numeric|min:0.001|max:999999.999|decimal:0,3', 'ingredients.*.unit' => 'required|in:mg,g,mL,each,capsule,tablet',
            'ingredients.*.certificate_reference' => 'required|string|max:2000']);
        $old = DB::table('pharmacy_batch_worksheets')->where('organization_id', $org)->where('request_id', $d['request_id'])->first();
        if ($old) {
            abort_unless(hash_equals($old->request_hash, $this->hash($d)), 409);

            return $this->showBatch($r, $old->id);
        }
        $rx = DB::table('pharmacy_prescriptions')->where('organization_id', $org)->where('id', $d['prescription_id'])->first();
        abort_unless($rx, 404);
        app(PharmacyAccess::class)->requireLocation($r->user(), $rx->location_id);
        $f = $this->formula($r, $d['formulation_id']);
        abort_unless($rx->compounded && $rx->compound_type === $f->preparation_type && $f->status === 'reviewed', 422, 'Use a reviewed formulation matching the prescription preparation type.');
        abort_if($rx->expires_on < $d['planned_on'], 422, 'Prescription expires before the planned date.');
        abort_unless($f->record['output_unit'] === $rx->quantity_unit && PharmacyStock::milli($f->record['output_quantity']) === PharmacyStock::milli($rx->quantity), 422, 'This worksheet supports one exact formulation batch matching the prescribed quantity and unit. Scaling requires a separately reviewed formulation.');
        $expected = collect($f->record['ingredients'])->keyBy('key');
        abort_unless(count($d['ingredients']) === $expected->count(), 422, 'Include every formulation ingredient exactly once.');
        foreach ($d['ingredients'] as $line) {
            $e = $expected->get($line['key']);
            abort_unless($e && $e['unit'] === $line['unit'] && PharmacyStock::milli($e['quantity']) === PharmacyStock::milli($line['quantity']), 422, 'Ingredient quantities and units must match the reviewed formulation. No automatic substitutions or conversions.');
        }
        abort_if(DB::table('pharmacy_batch_worksheets')->where('location_id', $rx->location_id)->where('batch_number', $d['batch_number'])->exists(), 409, 'Batch number already exists at this location.');
        $record = $d;
        unset($record['request_id'],$record['prescription_id'],$record['formulation_id'],$record['batch_number']);
        $id = DB::table('pharmacy_batch_worksheets')->insertGetId(['organization_id' => $org, 'location_id' => $rx->location_id, 'prescription_id' => $rx->id, 'formulation_id' => $f->id,
            'batch_number' => $d['batch_number'], 'record' => json_encode($record, JSON_THROW_ON_ERROR), 'created_by' => $r->user()->id, 'request_id' => $d['request_id'], 'request_hash' => $this->hash($d), 'created_at' => now(), 'updated_at' => now()]);
        $this->event($r, $f->id, $id, 'worksheet_created', ['formulation_revision' => $f->revision]);

        return $this->showBatch($r, $id)->setStatusCode(201);
    }

    public function reviewBatch(Request $r, $id)
    {
        $this->org($r, true);
        $b = $this->showBatch($r, $id)->getData()->data;
        $d = $r->validate(['version' => 'required|integer|min:1', 'action' => 'required|in:review,reject', 'evidence' => 'required|string|max:5000']);
        abort_unless((int) $b->version === $d['version'], 409, 'Worksheet changed. Refresh before reviewing.');
        abort_unless($b->status === 'draft', 422, 'Reviewed/rejected worksheets are immutable. Create a new worksheet for corrections.');
        if ($d['action'] === 'review') {
            abort_if((int) $b->created_by === (int) $r->user()->id, 422, 'An independent pharmacist must review the worksheet.');
            abort_unless($b->formula->status === 'reviewed', 422, 'The formulation has been retired.');
            $rx = DB::table('pharmacy_prescriptions')->where('id', $b->prescription_id)->first();
            abort_unless($rx && $rx->expires_on >= now()->toDateString() && $b->record->planned_on >= now()->toDateString(), 422, 'The planned date or prescription is no longer current.');
            foreach ($b->record->ingredients as $line) {
                abort_if($line->expires_on < now()->toDateString(), 422, 'An ingredient has expired.');
            }
        }
        $status = $d['action'] === 'review' ? 'reviewed' : 'rejected';
        DB::table('pharmacy_batch_worksheets')->where('id',$id)->update(['status' => $status, 'version' => $b->version + 1, 'updated_at' => now()]);
        $this->event($r,$b->formulation_id,$id,'worksheet_'.$status,['evidence' => $d['evidence'], 'production_release_enabled' => false]);

        return $this->showBatch($r,$id);
    }
}
