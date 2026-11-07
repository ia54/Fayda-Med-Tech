<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PharmacyAccess;
use App\Services\PharmacyStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Separate custody of ordinary patient-returned medication; never dispensing inventory. */
class PharmacyPatientReturnController extends Controller
{
    private function hash(array $value): string
    {
        $sort = function (array $a) use (&$sort): array {
            foreach ($a as &$v) { if (is_array($v)) { $v = $sort($v); } } unset($v);
            if (! array_is_list($a)) { ksort($a); } return $a;
        };
        return hash('sha256', json_encode($sort($value), JSON_THROW_ON_ERROR));
    }

    private function target(Request $r, $rxId, $fillId): array
    {
        abort_unless($r->user()->organization_id && $r->user()->status === 'active' && in_array($r->user()->role, ['pharmacist', 'pharmacy_technician'], true), 403);
        $q = DB::table('pharmacy_prescriptions')->where('organization_id', $r->user()->organization_id)->where('id', $rxId);
        app(PharmacyAccess::class)->scope($q, $r->user()); $rx = $q->first(); abort_unless($rx, 404);
        $fill = DB::table('pharmacy_fills')->where('prescription_id', $rx->id)->where('id', $fillId)->first(); abort_unless($fill, 404);
        return [$rx, $fill];
    }

    private function receipt(Request $r, $id): array
    {
        $row = DB::table('pharmacy_patient_returns')->where('id', $id)->first(); abort_unless($row, 404);
        $fill = DB::table('pharmacy_fills')->where('id', $row->fill_id)->first(); abort_unless($fill, 404);
        [$rx, $fill] = $this->target($r, $fill->prescription_id, $fill->id);
        return [$rx, $fill, $row];
    }

    private function ordinary(object $rx, object $fill): void
    {
        abort_if($rx->controlled || $rx->compounded, 422, 'Controlled and compounded patient returns require their separate approved procedures.');
        $handover = json_decode($fill->fulfillment ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        abort_unless(in_array($fill->fulfillment_status, ['collected', 'delivered'], true) && ! empty($handover['handover']) && ! empty($handover['occurred_on']), 422, 'A retained completed handover is required. Undelivered dispatches use their separate custody workflow.');
    }

    private function fillHash(object $fill): string
    {
        return $this->hash(['id' => (int) $fill->id, 'stock_lot_id' => (int) $fill->stock_lot_id, 'quantity' => PharmacyStock::decimal(PharmacyStock::milli($fill->quantity)),
            'ndc' => $fill->ndc, 'fulfillment_status' => $fill->fulfillment_status, 'fulfillment' => json_decode($fill->fulfillment ?: '{}', true, 512, JSON_THROW_ON_ERROR)]);
    }

    private function retainedBalance(object $row): array
    {
        $original = json_decode($row->record, true, 512, JSON_THROW_ON_ERROR);
        abort_unless(hash_equals($row->record_hash, $this->hash($original)), 409, 'Receipt evidence changed. Investigate before changing custody.');
        $custody = PharmacyStock::milli($original['quantity']); $disposed = 0; $lastDate = $original['occurred_on'];
        foreach (DB::table('pharmacy_patient_return_proposals')->where('patient_return_id', $row->id)->where('status', 'applied')->orderBy('id')->get() as $p) {
            $record = json_decode($p->record, true, 512, JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($p->record_hash, $this->hash($record)) && ($record['kind'] ?? null) === $p->kind, 409, 'Retained review evidence changed.');
            $quantity = PharmacyStock::milli($record['quantity']);
            if ($p->kind === 'disposition') { $custody -= $quantity; $disposed += $quantity; }
            else { $custody = $quantity; }
            abort_if($custody < 0, 409, 'Retained custody is inconsistent.'); $lastDate = max($lastDate, $record['occurred_on']);
        }
        abort_unless($custody === PharmacyStock::milli($row->custody_quantity) && $disposed === PharmacyStock::milli($row->disposed_quantity), 409, 'Custody balance differs from retained reviews.');
        return ['last_date' => $lastDate];
    }

    private function recordedQuantity(int $fillId, ?int $except = null): int
    {
        $q = DB::table('pharmacy_patient_returns')->where('fill_id', $fillId);
        if ($except) { $q->where('id', '<>', $except); }
        $quantity = 0;
        foreach ($q->get() as $row) { $this->retainedBalance($row); $quantity += PharmacyStock::milli($row->custody_quantity) + PharmacyStock::milli($row->disposed_quantity); }
        return $quantity;
    }

    private function source(object $row, object $fill): string
    {
        return $this->hash(['record_hash' => $row->record_hash, 'fill_hash' => $this->fillHash($fill),
            'custody' => PharmacyStock::decimal(PharmacyStock::milli($row->custody_quantity)), 'disposed' => PharmacyStock::decimal(PharmacyStock::milli($row->disposed_quantity))]);
    }

    private function event(Request $r, object $rx, string $action, array $details): void
    {
        DB::table('pharmacy_events')->insert(['organization_id' => $rx->organization_id, 'prescription_id' => $rx->id,
            'actor_id' => $r->user()->id, 'action' => $action, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    private function display(object $row): object
    {
        $row->custody_quantity = PharmacyStock::decimal(PharmacyStock::milli($row->custody_quantity));
        $row->disposed_quantity = PharmacyStock::decimal(PharmacyStock::milli($row->disposed_quantity));
        return $row;
    }

    public function index(Request $r, $id, $fillId)
    {
        [$rx, $fill] = $this->target($r, $id, $fillId); $r->validate(['page' => 'sometimes|integer|min:1']);
        $rows = DB::table('pharmacy_patient_returns')->where('fill_id', $fill->id)
            ->select('id', 'created_by', 'record', 'custody_quantity', 'disposed_quantity', 'version', 'created_at')->orderByDesc('id')->paginate(20);
        $rows->through(fn ($row) => $this->display($row));
        return response()->json(['data' => ['returns' => $rows, 'recorded_quantity' => PharmacyStock::decimal($this->recordedQuantity($fill->id)),
            'remaining_quantity' => PharmacyStock::decimal(max(0, PharmacyStock::milli($fill->quantity) - $this->recordedQuantity($fill->id))), 'unit' => $rx->quantity_unit]]);
    }

    public function store(Request $r, $id, $fillId)
    {
        [$rx, $fill] = $this->target($r, $id, $fillId); abort_unless($r->user()->role === 'pharmacist', 403); $this->ordinary($rx, $fill);
        $d = $r->validate(['request_id' => 'required|uuid', 'quantity' => 'required|numeric|min:0.001|max:999999.999|decimal:0,3',
            'reason' => 'required|in:recall,dispensing_error', 'occurred_on' => 'required|date_format:Y-m-d|before_or_equal:today',
            'returned_product' => 'required|string|max:2000', 'identity_evidence' => 'required|string|max:2000',
            'authority_reference' => 'required|string|max:2000', 'condition' => 'required|string|max:2000',
            'segregation_reference' => 'required|string|max:2000', 'evidence' => 'required|string|max:2000',
            'scope_confirmed' => 'required|accepted', 'confirmed' => 'required|accepted']);
        $d['quantity'] = PharmacyStock::decimal(PharmacyStock::milli($d['quantity'])); $hash = $this->hash($d);
        $old = DB::table('pharmacy_patient_returns')->where('fill_id', $fill->id)->where('request_id', $d['request_id'])->first();
        if ($old) { abort_unless((int) $old->created_by === (int) $r->user()->id && hash_equals($old->request_hash, $hash), 409, 'This request identifier belongs to another receipt.'); return $this->index($r, $id, $fillId); }
        $handover = json_decode($fill->fulfillment, true, 512, JSON_THROW_ON_ERROR);
        abort_unless($d['occurred_on'] >= $handover['occurred_on'], 422, 'Patient receipt cannot predate the original handover.');
        abort_unless(PharmacyStock::milli($d['quantity']) + $this->recordedQuantity($fill->id) <= PharmacyStock::milli($fill->quantity), 422, 'Reported returns exceed this fill. Investigate quantity, units and identity before recording custody.');
        $requestId = $d['request_id']; unset($d['request_id'], $d['confirmed']);
        $d['unit'] = $rx->quantity_unit;
        $returnId = DB::table('pharmacy_patient_returns')->insertGetId(['fill_id' => $fill->id, 'created_by' => $r->user()->id, 'request_id' => $requestId,
            'request_hash' => $hash, 'record' => json_encode($d, JSON_THROW_ON_ERROR), 'record_hash' => $this->hash($d), 'source_hash' => $this->fillHash($fill),
            'custody_quantity' => $d['quantity'], 'created_at' => now(), 'updated_at' => now()]);
        $this->event($r, $rx, 'patient_return_received', ['return_id' => $returnId, 'fill_id' => $fill->id, 'quantity' => $d['quantity'], 'dispensing_stock_changed' => false]);
        return $this->index($r, $id, $fillId)->setStatusCode(201);
    }

    public function show(Request $r, $returnId)
    {
        [$rx, $fill, $row] = $this->receipt($r, $returnId); $r->validate(['page' => 'sometimes|integer|min:1']);
        $proposals = DB::table('pharmacy_patient_return_proposals')->where('patient_return_id', $row->id)
            ->select('id', 'created_by', 'kind', 'record', 'status', 'reviewed_by', 'review_evidence', 'reviewed_at', 'created_at')->orderByDesc('id')->paginate(20);
        return response()->json(['data' => ['receipt' => collect((array) $this->display(clone $row))->only(['id', 'created_by', 'record', 'custody_quantity', 'disposed_quantity', 'version', 'created_at']),
            'pending' => DB::table('pharmacy_patient_return_proposals')->where('patient_return_id', $row->id)->where('status', 'pending')->exists(),
            'proposals' => $proposals, 'unit' => $rx->quantity_unit, 'maximum_custody_quantity' => PharmacyStock::decimal(max(0,
                PharmacyStock::milli($fill->quantity) - $this->recordedQuantity($fill->id, $row->id) - PharmacyStock::milli($row->disposed_quantity)))]]);
    }

    public function propose(Request $r, $returnId)
    {
        [$rx, $fill, $row] = $this->receipt($r, $returnId); abort_unless($r->user()->role === 'pharmacist', 403); $this->ordinary($rx, $fill);
        $d = $r->validate(['request_id' => 'required|uuid', 'version' => 'required|integer|min:1', 'kind' => 'required|in:recount,disposition',
            'quantity' => 'required|numeric|min:0|max:999999.999|decimal:0,3', 'occurred_on' => 'required|date_format:Y-m-d|before_or_equal:today',
            'reason' => 'required|string|max:2000', 'evidence' => 'required|string|max:2000', 'authority_reference' => 'required|string|max:2000',
            'destination' => 'required_if:kind,disposition|prohibited_if:kind,recount|string|max:2000',
            'completion_reference' => 'required_if:kind,disposition|prohibited_if:kind,recount|string|max:2000', 'confirmed' => 'required|accepted']);
        $d['quantity'] = PharmacyStock::decimal(PharmacyStock::milli($d['quantity'])); $hash = $this->hash($d);
        $old = DB::table('pharmacy_patient_return_proposals')->where('patient_return_id', $row->id)->where('request_id', $d['request_id'])->first();
        if ($old) { abort_unless((int) $old->created_by === (int) $r->user()->id && hash_equals($old->request_hash, $hash), 409, 'This request identifier belongs to another proposal.'); return $this->show($r, $returnId); }
        abort_unless((int) $row->version === (int) $d['version'], 409, 'Custody changed. Refresh before recording a proposal.');
        abort_if(DB::table('pharmacy_patient_return_proposals')->where('patient_return_id', $row->id)->where('status', 'pending')->exists(), 409, 'Review the pending proposal first.');
        $record = json_decode($row->record, true, 512, JSON_THROW_ON_ERROR);
        abort_unless(hash_equals($row->record_hash, $this->hash($record)) && hash_equals($row->source_hash, $this->fillHash($fill)), 409, 'Original receipt or handover evidence changed. Investigate before adjusting custody.');
        $balance = $this->retainedBalance($row);
        abort_unless($d['occurred_on'] >= $balance['last_date'], 422, 'Custody action cannot predate an already applied custody event.');
        $quantity = PharmacyStock::milli($d['quantity']);
        if ($d['kind'] === 'disposition') { abort_unless($quantity > 0 && $quantity <= PharmacyStock::milli($row->custody_quantity), 422, 'Disposition requires a positive quantity within retained custody.'); }
        else { abort_unless($quantity + PharmacyStock::milli($row->disposed_quantity) + $this->recordedQuantity($fill->id, $row->id) <= PharmacyStock::milli($fill->quantity), 422, 'Recount exceeds the original fill; investigate identity and quantity.'); }
        $requestId = $d['request_id']; $kind = $d['kind']; unset($d['request_id'], $d['version'], $d['confirmed']);
        $proposalId = DB::table('pharmacy_patient_return_proposals')->insertGetId(['patient_return_id' => $row->id, 'created_by' => $r->user()->id, 'request_id' => $requestId,
            'request_hash' => $hash, 'kind' => $kind, 'record' => json_encode($d, JSON_THROW_ON_ERROR), 'record_hash' => $this->hash($d),
            'source_hash' => $this->source($row, $fill), 'base_version' => $row->version + 1, 'created_at' => now()]);
        DB::table('pharmacy_patient_returns')->where('id', $row->id)->update(['version' => $row->version + 1, 'updated_at' => now()]);
        $this->event($r, $rx, 'patient_return_proposed', ['return_id' => $row->id, 'proposal_id' => $proposalId, 'kind' => $kind]);
        return $this->show($r, $returnId)->setStatusCode(201);
    }

    public function review(Request $r, $returnId, $proposalId)
    {
        [$rx, $fill, $row] = $this->receipt($r, $returnId); abort_unless($r->user()->role === 'pharmacist', 403);
        $proposal = DB::table('pharmacy_patient_return_proposals')->where('patient_return_id', $row->id)->where('id', $proposalId)->first(); abort_unless($proposal, 404);
        $d = $r->validate(['request_id' => 'required|uuid', 'decision' => 'required|in:apply,reject', 'evidence' => 'required|string|max:2000', 'confirmed' => 'required|accepted']); $hash = $this->hash($d);
        if ($proposal->status !== 'pending') {
            abort_unless((int) $proposal->reviewed_by === (int) $r->user()->id && $proposal->review_request_id === $d['request_id'] && hash_equals($proposal->review_hash, $hash), 409, 'This proposal already has a review decision.');
            return $this->show($r, $returnId);
        }
        abort_if((int) $proposal->created_by === (int) $r->user()->id, 422, 'A different assigned pharmacist must review the custody action.');
        $updates = [];
        if ($d['decision'] === 'apply') {
            $this->ordinary($rx, $fill); $author = User::find($proposal->created_by);
            abort_unless($author && $author->status === 'active' && $author->role === 'pharmacist' && (int) $author->organization_id === (int) $r->user()->organization_id, 409, 'Proposal author is no longer authorized. Reject and record fresh evidence.');
            app(PharmacyAccess::class)->requireLocation($author, $rx->location_id);
            $record = json_decode($proposal->record, true, 512, JSON_THROW_ON_ERROR); $original = json_decode($row->record, true, 512, JSON_THROW_ON_ERROR);
            abort_unless((int) $row->version === (int) $proposal->base_version && hash_equals($row->record_hash, $this->hash($original))
                && hash_equals($row->source_hash, $this->fillHash($fill)) && hash_equals($proposal->record_hash, $this->hash($record))
                && hash_equals($proposal->source_hash, $this->source($row, $fill)), 409, 'Custody or source evidence changed. Reject and investigate.');
            $this->retainedBalance($row);
            abort_unless(($record['kind'] ?? null) === $proposal->kind && in_array($proposal->kind, ['recount', 'disposition'], true), 409, 'Proposal kind changed.');
            $quantity = PharmacyStock::milli($record['quantity']); $custody = PharmacyStock::milli($row->custody_quantity); $disposed = PharmacyStock::milli($row->disposed_quantity);
            if ($proposal->kind === 'disposition') { abort_unless($quantity > 0 && $quantity <= $custody, 409, 'Disposition quantity no longer fits custody.'); $custody -= $quantity; $disposed += $quantity; }
            else { $custody = $quantity; }
            abort_unless($custody + $disposed + $this->recordedQuantity($fill->id, $row->id) <= PharmacyStock::milli($fill->quantity), 409, 'Other return records changed the available reconciliation quantity.');
            $updates = ['custody_quantity' => PharmacyStock::decimal($custody), 'disposed_quantity' => PharmacyStock::decimal($disposed)];
        }
        DB::table('pharmacy_patient_returns')->where('id', $row->id)->update($updates + ['version' => $row->version + 1, 'updated_at' => now()]);
        DB::table('pharmacy_patient_return_proposals')->where('id', $proposal->id)->update(['status' => $d['decision'] === 'apply' ? 'applied' : 'rejected',
            'reviewed_by' => $r->user()->id, 'review_evidence' => $d['evidence'], 'review_request_id' => $d['request_id'], 'review_hash' => $hash, 'reviewed_at' => now()]);
        $this->event($r, $rx, 'patient_return_reviewed', ['return_id' => $row->id, 'proposal_id' => $proposal->id, 'decision' => $d['decision'], 'before_custody' => $row->custody_quantity, 'after' => $updates]);
        return $this->show($r, $returnId);
    }
}
