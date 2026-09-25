<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use App\Services\PharmacyStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Exact-quantity synthetic preparation records only. No product release or BUD assignment. */
class PharmacyExecutionController extends Controller
{
    private function batch(Request $r, $id)
    {
        abort_unless($r->user()->role === 'pharmacist', 403);
        $batch = DB::table('pharmacy_batch_worksheets')->where('organization_id', $r->user()->organization_id)->where('id', $id)->first();
        abort_unless($batch, 404);
        app(PharmacyAccess::class)->requireLocation($r->user(), $batch->location_id);

        return $batch;
    }

    public function store(Request $r, $id)
    {
        $batch = $this->batch($r, $id);
        $d = $r->validate(['version' => 'required|integer|min:1', 'prepared_on' => 'required|date_format:Y-m-d|after_or_equal:today|before_or_equal:today',
            'personnel_reference' => 'required|string|max:2000', 'equipment_reference' => 'required|string|max:2000',
            'process_record_reference' => 'required|string|max:5000', 'quality_results_reference' => 'required|string|max:5000',
            'yield_quantity' => 'required|numeric|min:0.001|max:999999.999|decimal:0,3', 'yield_unit' => 'required|in:mg,g,mL,each,capsule,tablet',
            'deviations' => 'required|string|max:5000', 'environment_reference' => 'nullable|string|max:5000', 'hazard_control_reference' => 'nullable|string|max:5000',
            'ingredients' => 'required|array|min:1|max:30', 'ingredients.*' => 'array:key,quantity,unit,measurement_reference',
            'ingredients.*.key' => 'required|string|max:40|distinct:strict', 'ingredients.*.quantity' => 'required|numeric|min:0.001|max:999999.999|decimal:0,3',
            'ingredients.*.unit' => 'required|in:mg,g,mL,each,capsule,tablet', 'ingredients.*.measurement_reference' => 'required|string|max:2000']);
        abort_unless((int) $batch->version === $d['version'], 409, 'Worksheet changed. Refresh before recording execution.');
        abort_if(DB::table('pharmacy_batch_executions')->where('batch_id', $id)->exists(), 409, 'Execution is already recorded. Do not repeat consumption.');
        abort_unless($batch->status === 'reviewed', 422, 'Independent worksheet review is required.');
        $formula = DB::table('pharmacy_formulations')->where('id', $batch->formulation_id)->first();
        $record = json_decode($batch->record, true, 512, JSON_THROW_ON_ERROR);
        abort_unless($formula && $formula->status === 'reviewed' && $record['planned_on'] === $d['prepared_on'], 422, 'Use a current reviewed formulation and a worksheet planned for today.');
        $rx = DB::table('pharmacy_prescriptions')->where('id', $batch->prescription_id)->first();
        abort_unless($rx && $rx->expires_on >= $d['prepared_on'], 422, 'Prescription is no longer current.');
        abort_if($formula->preparation_type === 'sterile' && empty($d['environment_reference']), 422, 'Sterile preparation requires environmental and aseptic process evidence.');
        abort_if($formula->hazardous && empty($d['hazard_control_reference']), 422, 'Hazardous preparation requires containment and handling evidence.');
        $f = json_decode($formula->record, true, 512, JSON_THROW_ON_ERROR);
        abort_unless($d['yield_unit'] === $f['output_unit'] && PharmacyStock::milli($d['yield_quantity']) <= PharmacyStock::milli($f['output_quantity']), 422, 'Yield must use the reviewed output unit and cannot exceed the planned output in this preview.');
        $allocations = DB::table('pharmacy_ingredient_allocations')->where('batch_id', $id)->get();
        $actual = collect($d['ingredients'])->keyBy('key');
        abort_unless($allocations->count() === count($record['ingredients']) && $allocations->count() === $actual->count() && $allocations->every(fn ($a) => $a->status === 'reserved'), 422, 'Every ingredient must have an active stock reservation.');
        foreach ($allocations as $a) {
            $line = $actual->get($a->ingredient_key);
            $lot = DB::table('pharmacy_ingredient_lots')->where('id', $a->ingredient_lot_id)->where('organization_id', $r->user()->organization_id)->where('location_id', $batch->location_id)->first();
            abort_unless($lot, 404);
            abort_unless($lot->status === 'available' && $lot->expires_on >= $d['prepared_on'], 422, 'A reserved ingredient is expired or quarantined.');
            abort_unless($line && $line['unit'] === $lot->quantity_unit && PharmacyStock::milli($line['quantity']) === PharmacyStock::milli($a->quantity), 422, 'This preview supports exact reserved quantities only. Do not misstate a differing actual measurement; deviation reconciliation is not available yet.');
            $amount = PharmacyStock::milli($a->quantity);
            $held = PharmacyStock::milli($lot->reserved);
            $onHand = PharmacyStock::milli($lot->on_hand);
            abort_unless($held >= $amount && $onHand >= $held, 422, 'Ingredient stock reconciliation is required.');
            DB::table('pharmacy_ingredient_lots')->where('id', $lot->id)->update(['reserved' => PharmacyStock::decimal($held - $amount), 'on_hand' => PharmacyStock::decimal($onHand - $amount), 'version' => $lot->version + 1, 'updated_at' => now()]);
            DB::table('pharmacy_ingredient_allocations')->where('id', $a->id)->update(['status' => 'consumed', 'updated_at' => now()]);
            DB::table('pharmacy_ingredient_events')->insert(['ingredient_lot_id' => $lot->id, 'batch_id' => $id, 'actor_id' => $r->user()->id, 'action' => 'consumed_in_preparation', 'quantity' => $a->quantity,
                'details' => json_encode(['measurement_reference' => $line['measurement_reference'], 'process_record_reference' => $d['process_record_reference']], JSON_THROW_ON_ERROR), 'created_at' => now()]);
        }
        unset($d['version']);
        DB::table('pharmacy_batch_executions')->insert(['batch_id' => $id, 'created_by' => $r->user()->id, 'record' => json_encode($d, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pharmacy_batch_worksheets')->where('id', $id)->update(['version' => $batch->version + 1, 'updated_at' => now()]);
        $this->event($r, $batch, 'preparation_recorded', ['output_status' => 'quarantined', 'production_release_enabled' => false]);

        return app(PharmacyCompoundingController::class)->showBatch($r, $id)->setStatusCode(201);
    }

    public function review(Request $r, $id)
    {
        $batch = $this->batch($r, $id);
        $d = $r->validate(['version' => 'required|integer|min:1', 'decision' => 'required|in:document_reviewed,rejected', 'evidence' => 'required|string|max:5000']);
        $execution = DB::table('pharmacy_batch_executions')->where('batch_id', $id)->first();
        abort_unless($execution, 404);
        abort_unless((int) $execution->version === $d['version'], 409, 'Execution record changed. Refresh before reviewing.');
        abort_unless($execution->status === 'quarantined', 422, 'This review is already recorded.');
        abort_if((int) $execution->created_by === (int) $r->user()->id, 422, 'A different pharmacist must review the execution record.');
        DB::table('pharmacy_batch_executions')->where('id', $execution->id)->update(['status' => $d['decision'], 'version' => $execution->version + 1, 'updated_at' => now()]);
        $this->event($r, $batch, 'execution_'.$d['decision'], ['evidence' => $d['evidence'], 'output_status' => 'quarantined', 'production_release_enabled' => false]);

        return app(PharmacyCompoundingController::class)->showBatch($r, $id);
    }

    private function event(Request $r, $batch, string $action, array $details): void
    {
        DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch->formulation_id, 'batch_id' => $batch->id, 'actor_id' => $r->user()->id, 'action' => $action, 'details' => json_encode($details,JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
