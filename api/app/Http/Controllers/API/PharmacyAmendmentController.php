<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use App\Services\PharmacyStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Pre-supply amendments; supplied prescriptions continue through separately received replacements. */
class PharmacyAmendmentController extends Controller
{
    private const VALUES = ['strength', 'dosage_form', 'directions', 'quantity', 'refills_authorized'];

    private function rx(Request $r, $id): object
    {
        abort_unless(in_array($r->user()->role, ['pharmacist', 'pharmacy_technician'], true), 403);
        $q = DB::table('pharmacy_prescriptions')->where('organization_id', $r->user()->organization_id)->where('id', $id);
        app(PharmacyAccess::class)->scope($q, $r->user());
        $rx = $q->first();
        abort_unless($rx, 404);
        return $rx;
    }

    private function snapshot(object $rx): array
    {
        $values = array_intersect_key((array) $rx, array_flip(array_merge(self::VALUES, ['id', 'rx_number', 'episode_id', 'location_id', 'medication', 'quantity_unit', 'prescriber_name', 'prescriber_identifier', 'written_on', 'expires_on', 'controlled', 'compounded', 'compound_type', 'source_reference', 'amendment_revision'])));
        $values['quantity'] = PharmacyStock::decimal(PharmacyStock::milli($rx->quantity));
        $values['refills_authorized'] = (int) $rx->refills_authorized;
        return $values;
    }

    private function token(object $rx): string
    {
        return hash('sha256', json_encode([$this->snapshot($rx), $rx->discontinued_at,
            DB::table('pharmacy_fills')->where('prescription_id', $rx->id)->orderBy('id')->get(['id', 'version', 'fulfillment_status']),
            DB::table('pharmacy_source_documents')->where('prescription_id', $rx->id)->orderBy('id')->get(['id', 'sha256'])], JSON_THROW_ON_ERROR));
    }

    private function hold(object $rx): ?string
    {
        if ($rx->controlled || $rx->compounded) return 'Controlled and compounded amendments require their dedicated workflow.';
        if ($rx->discontinued_at || $rx->expires_on < now()->toDateString()) return 'Receive a new prescription for a discontinued or expired order.';
        if (DB::table('pharmacy_fills')->where('prescription_id', $rx->id)->whereIn('fulfillment_status', ['collected', 'delivered'])->exists()) return 'A supply has already been handed over. Use a separately received replacement to preserve its authorization history.';
        if (DB::table('pharmacy_fills')->where('prescription_id', $rx->id)->where('fulfillment_status', '!=', 'cancelled')->exists()) return 'Cancel open fills and resolve their reservations before amending this order.';
        if (DB::table('pharmacy_batch_worksheets')->where('prescription_id', $rx->id)->exists()
            || DB::table('pharmacy_allowance_closures')->where('prescription_id', $rx->id)->exists()) return 'Retained manufacturing or allowance decisions require separate review and replacement.';
        return null;
    }

    public function index(Request $r, $id)
    {
        $rx = $this->rx($r, $id);
        $r->validate(['page' => 'nullable|integer|min:1']);
        $rows = DB::table('pharmacy_prescription_amendments')->where('prescription_id', $id)
            ->select('id', 'revision', 'source_document_id', 'source_sha256', 'before_snapshot', 'after_snapshot', 'consulted_on', 'consultation_evidence', 'reason', 'created_by', 'created_at')->orderByDesc('revision')->paginate(10);
        $rows->getCollection()->transform(function ($row) {
            foreach (['before_snapshot', 'after_snapshot'] as $field) $row->$field = json_decode($row->$field, true, 512, JSON_THROW_ON_ERROR);
            return $row;
        });
        return response()->json(['data' => ['revision' => (int) $rx->amendment_revision, 'source_token' => $this->token($rx), 'hold_reason' => $this->hold($rx), 'amendments' => $rows]]);
    }

    public function store(Request $r, $id)
    {
        abort_unless($r->user()->role === 'pharmacist', 403);
        $rx = $this->rx($r, $id);
        $d = $r->validate(['request_id' => 'required|uuid', 'source_token' => 'required|string|regex:/^[a-f0-9]{64}$/D',
            'values' => 'required|array:strength,dosage_form,directions,quantity,refills_authorized',
            'values.strength' => 'required|string|max:100', 'values.dosage_form' => 'required|string|max:100', 'values.directions' => 'required|string|max:2000',
            'values.quantity' => 'required|numeric|min:0.001|max:999999.999|decimal:0,3', 'values.refills_authorized' => 'required|integer|min:0|max:99',
            'source_document_id' => 'required|integer|min:1', 'consulted_on' => 'required|date_format:Y-m-d|before_or_equal:today',
            'consultation_evidence' => 'required|string|max:5000', 'reason' => 'required|string|max:2000', 'confirmed' => 'required|accepted']);
        ksort($d['values']); ksort($d); $hash = hash('sha256', json_encode($d, JSON_THROW_ON_ERROR));
        $old = DB::table('pharmacy_prescription_amendments')->where('prescription_id', $id)->where('request_id', $d['request_id'])->first();
        if ($old) {
            abort_unless((int) $old->created_by === (int) $r->user()->id && hash_equals($old->request_hash, $hash), 409, 'This request identifier belongs to another amendment.');
            return $this->index($r, $id);
        }
        abort_unless(hash_equals($this->token($rx), $d['source_token']), 409, 'The prescription, fills or source evidence changed. Refresh before amending.');
        abort_if($hold = $this->hold($rx), 422, $hold ?? 'Amendment is unavailable.');
        abort_if($d['consulted_on'] < $rx->written_on, 422, 'Consultation cannot predate this prescription.');
        $source = DB::table('pharmacy_source_documents')->where('prescription_id', $id)->where('id', $d['source_document_id'])->first();
        abort_unless($source, 404);
        $disk = Storage::disk('documents');
        abort_unless($disk->exists($source->path) && hash_equals($source->sha256, hash_file('sha256', $disk->path($source->path))), 409, 'Retained amendment evidence is missing or failed its integrity check.');
        $before = $this->snapshot($rx);
        $values = $d['values'];
        $values['quantity'] = PharmacyStock::decimal(PharmacyStock::milli($values['quantity']));
        $values['refills_authorized'] = (int) $values['refills_authorized'];
        abort_unless(array_intersect_key($before, $values) != $values, 422, 'No prescription values changed.');
        $revision = (int) $rx->amendment_revision + 1;
        $after = array_replace($before, $values, ['amendment_revision' => $revision]);
        $amendment = DB::table('pharmacy_prescription_amendments')->insertGetId(['prescription_id' => $id, 'revision' => $revision,
            'request_id' => $d['request_id'], 'request_hash' => $hash, 'source_document_id' => $source->id, 'source_sha256' => $source->sha256,
            'before_snapshot' => json_encode($before, JSON_THROW_ON_ERROR), 'after_snapshot' => json_encode($after, JSON_THROW_ON_ERROR),
            'consulted_on' => $d['consulted_on'], 'consultation_evidence' => $d['consultation_evidence'], 'reason' => $d['reason'],
            'created_by' => $r->user()->id, 'created_at' => now()]);
        DB::table('pharmacy_prescriptions')->where('id', $id)->update($values + ['amendment_revision' => $revision, 'updated_at' => now()]);
        DB::table('pharmacy_events')->insert(['organization_id' => $rx->organization_id, 'prescription_id' => $id, 'actor_id' => $r->user()->id,
            'action' => 'prescription_amended', 'details' => json_encode(['amendment_id' => $amendment, 'revision' => $revision, 'source_document_id' => $source->id], JSON_THROW_ON_ERROR), 'created_at' => now()]);
        return $this->index($r, $id)->setStatusCode(201);
    }
}
