<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use App\Services\PharmacyConsumptionContext;
use App\Services\PharmacyConsumptionCorrectionLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PharmacyConsumptionCorrectionController extends Controller
{
    public function worklist(Request $r)
    {
        $actor = $r->user();
        abort_unless(in_array($actor?->role, ['pharmacist', 'pharmacy_technician'], true) && $actor->status === 'active' && $actor->organization_id, 403);
        $data = $r->validate(['page' => 'nullable|integer|min:1', 'location_id' => 'nullable|integer|min:1', 'reviewable' => 'nullable|boolean']);
        $query = DB::table('pharmacy_consumption_corrections as p')
            ->join('pharmacy_batch_executions as e', 'e.id', '=', 'p.execution_id')
            ->join('pharmacy_batch_worksheets as b', 'b.id', '=', 'e.batch_id')
            ->where('b.organization_id', $actor->organization_id)->where('p.status', 'pending');
        app(PharmacyAccess::class)->scope($query, $actor, 'b.location_id');
        if (! empty($data['location_id'])) {
            app(PharmacyAccess::class)->requireLocation($actor, $data['location_id']);
            $query->where('b.location_id', $data['location_id']);
        }
        if ($r->boolean('reviewable')) {
            abort_unless($actor->role === 'pharmacist', 403);
            $query->where('p.created_by', '<>', $actor->id)->where('e.created_by', '<>', $actor->id)
                ->whereNotExists(function ($q) use ($actor) {
                    $q->selectRaw('1')->from('pharmacy_execution_addenda as a')->whereColumn('a.execution_id', 'e.id')->where('a.created_by', $actor->id);
                });
        }

        return response()->json(['data' => $query->orderBy('p.created_at')->orderBy('p.id')->paginate(20,
            ['p.id', 'p.execution_id', 'p.created_by', 'p.created_at', 'b.id as batch_id', 'b.batch_number', 'b.location_id']), 'release_enabled' => false]);
    }

    public function history(Request $r, int $executionId)
    {
        abort_unless(in_array($r->user()?->role, ['pharmacist', 'pharmacy_technician'], true) && $r->user()->status === 'active' && $r->user()->organization_id, 403);
        $r->validate(['page' => 'nullable|integer|min:1']);
        $execution = DB::table('pharmacy_batch_executions')->find($executionId);
        abort_unless($execution, 404);
        $batch = DB::table('pharmacy_batch_worksheets')->where('id', $execution->batch_id)->where('organization_id', $r->user()->organization_id)->first();
        abort_unless($batch, 404);
        app(PharmacyAccess::class)->requireLocation($r->user(), $batch->location_id);
        $rows = DB::table('pharmacy_consumption_corrections')->where('execution_id', $executionId)->orderByDesc('id')->paginate(20,
            ['id', 'ingredient_key', 'ingredient_lot_id', 'created_by', 'execution_version', 'proposal', 'correction_evidence', 'status', 'reviewed_by', 'review_evidence', 'reviewed_at', 'created_at']);
        $rows->getCollection()->transform(function ($row) {
            $row->proposal = json_decode($row->proposal, true, 512, JSON_THROW_ON_ERROR);
            $row->correction_evidence = json_decode($row->correction_evidence, true, 512, JSON_THROW_ON_ERROR);
            return $row;
        });
        return response()->json(['data' => $rows, 'release_enabled' => false]);
    }

    public function context(Request $r, int $executionId, string $ingredientKey)
    {
        $c = app(PharmacyConsumptionContext::class)->inspect($r->user(), $executionId, $ingredientKey);
        $lot = $c['receipt'];
        return response()->json(['data' => ['execution_version' => $c['execution']['version'], 'receipt_version' => $lot['version'],
            'ingredient_key' => $ingredientKey, 'ingredient_lot_id' => $lot['id'], 'lot_number' => $lot['lot_number'],
            'original_consumed' => $c['original_ingredient']['quantity'], 'accounted_consumed' => $c['accounted_consumed'],
            'on_hand' => $lot['on_hand'], 'reserved' => $lot['reserved'], 'unit' => $lot['quantity_unit'], 'receipt_status' => $lot['status'],
            'affected_batch_ids' => array_column($c['affected_batches'], 'batch_id'),
            'pending' => DB::table('pharmacy_consumption_corrections')->where('ingredient_lot_id', $lot['id'])->where('status', 'pending')->exists(),
            'release_enabled' => false]]);
    }

    public function store(Request $r, int $executionId, string $ingredientKey)
    {
        $id = app(PharmacyConsumptionCorrectionLedger::class)->retain($r->user(), $executionId, $ingredientKey, $r->all());
        return response()->json(['data' => ['id' => $id, 'release_enabled' => false]], 201);
    }

    public function decide(Request $r, int $proposalId)
    {
        $d = $r->validate(['decision' => 'required|in:applied,rejected', 'evidence' => 'required|string|max:5000']);
        $service = app(PharmacyConsumptionCorrectionLedger::class);
        if ($d['decision'] === 'applied') { $service->apply($r->user(), $proposalId, $d['evidence']); }
        else { $service->reject($r->user(), $proposalId, $d['evidence']); }
        return response()->json(['data' => ['id' => $proposalId, 'release_enabled' => false]]);
    }
}
