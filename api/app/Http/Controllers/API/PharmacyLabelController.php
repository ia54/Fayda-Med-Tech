<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use App\Services\PharmacyLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PharmacyLabelController extends Controller
{
    private function records(Request $r, $id, $fillId): array
    {
        abort_unless(in_array($r->user()->role, ['pharmacist', 'pharmacy_technician'], true), 403);
        $q = DB::table('pharmacy_prescriptions')->where('organization_id', $r->user()->organization_id)->where('id', $id);
        app(PharmacyAccess::class)->scope($q, $r->user());
        $rx = $q->first();
        abort_unless($rx, 404);
        $fill = DB::table('pharmacy_fills')->where('prescription_id', $id)->where('id', $fillId)->first();
        abort_unless($fill, 404);
        return [$rx, $fill];
    }

    private function event(Request $r, object $rx, string $action, array $details): void
    {
        DB::table('pharmacy_events')->insert(['organization_id' => $rx->organization_id, 'prescription_id' => $rx->id,
            'actor_id' => $r->user()->id, 'action' => $action, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    public function index(Request $r, $id, $fillId)
    {
        [$rx, $fill] = $this->records($r, $id, $fillId);
        $r->validate(['page' => 'nullable|integer|min:1']);
        $labels = DB::table('pharmacy_fill_labels')->where('fill_id', $fillId)->select('id', 'revision', 'created_by', 'sha256', 'reason', 'created_at')->orderByDesc('revision')->paginate(10);
        return response()->json(['data' => ['fill_version' => $fill->version,
            'source_token' => app(PharmacyLabel::class)->token(app(PharmacyLabel::class)->context($rx, $fill)),
            'current' => app(PharmacyLabel::class)->summary($rx, $fill), 'labels' => $labels]]);
    }

    public function store(Request $r, $id, $fillId)
    {
        abort_unless($r->user()->role === 'pharmacist', 403);
        [$rx, $fill] = $this->records($r, $id, $fillId);
        $d = $r->validate([
            'request_id' => 'required|uuid', 'version' => 'required|integer|min:1',
            'source_token' => 'required|string|size:64|regex:/^[a-f0-9]+$/D', 'previous_label_id' => 'required|integer|min:0',
            'dispensed_on' => 'required|date_format:Y-m-d|before_or_equal:today',
            'use_by' => 'required|date_format:Y-m-d|after_or_equal:today', 'use_by_reference' => 'required|string|max:2000',
            'substituted' => 'required|boolean', 'daw' => 'required|boolean', 'selection_reference' => 'required|string|max:2000',
            'notification_reference' => 'nullable|required_if:substituted,true|string|max:2000',
            'do_not_label' => 'required|boolean', 'disclosure_reference' => 'nullable|required_if:do_not_label,true|string|max:2000',
            'auxiliary_text' => 'nullable|string|max:2000', 'reason' => 'required|string|max:2000', 'confirmed' => 'required|accepted',
        ]);
        foreach (['notification_reference', 'disclosure_reference', 'auxiliary_text'] as $field) { $d[$field] = $d[$field] ?? null; }
        ksort($d);
        $hash = hash('sha256', json_encode($d, JSON_THROW_ON_ERROR));
        $old = DB::table('pharmacy_fill_labels')->where('fill_id', $fillId)->where('request_id', $d['request_id'])->first();
        if ($old) {
            abort_unless((int) $old->created_by === (int) $r->user()->id && hash_equals($old->request_hash, $hash), 409, 'This request identifier belongs to another label.');
            return $this->index($r, $id, $fillId);
        }
        abort_unless((int) $fill->version === $d['version'], 409, 'The fill changed. Refresh before retaining a label.');
        abort_unless($fill->fulfillment_status === 'pending', 422, 'Labels can only be issued or corrected before final preparation. Cancel a prepared fill and start again.');
        $service = app(PharmacyLabel::class);
        $context = $service->assertCanLabel($rx, $fill);
        abort_unless(hash_equals($service->token($context), $d['source_token']), 409, 'Label source details changed. Refresh and review them.');
        $current = $service->current((int) $fillId);
        abort_unless((int) ($current->id ?? 0) === $d['previous_label_id'], 409, 'Another label revision was retained. Refresh and review it.');
        abort_if($d['substituted'] && $d['daw'], 422, 'Do not substitute against a dispense-as-written instruction. Resolve the prescription authority first.');
        abort_if($d['dispensed_on'] < $rx->written_on || $d['dispensed_on'] > $rx->expires_on || $d['use_by'] < $d['dispensed_on'] || $d['use_by'] > $context['stock']['expires_on'], 422, 'Label dates must fit the prescription and source-stock expiry. A pharmacist must establish the appropriate use-by date.');
        foreach ([$context['location']->name ?? null, $context['location']->address ?? null, $context['patient']->first_name ?? null,
            $context['patient']->last_name ?? null, $rx->rx_number, $rx->prescriber_name, $rx->directions] as $value) {
            abort_unless(is_string($value) && trim($value) !== '', 422, 'Complete the source patient, pharmacy and prescription identity before issuing a label.');
        }
        $decisions = $d;
        unset($decisions['request_id'], $decisions['version'], $decisions['source_token'], $decisions['previous_label_id'], $decisions['confirmed'], $decisions['reason']);
        $barcodeCode = 'FMTL-'.strtoupper(\Illuminate\Support\Str::random(20));
        $snapshot = json_encode(['format_version' => 2, 'barcode_code' => $barcodeCode, 'synthetic_only' => true, 'actor_id' => $r->user()->id, 'context' => $context, 'decisions' => $decisions], JSON_THROW_ON_ERROR);
        $revision = ($current->revision ?? 0) + 1;
        $document = $service->document(json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR), $revision);
        $labelId = DB::table('pharmacy_fill_labels')->insertGetId(['fill_id' => $fillId, 'revision' => $revision, 'created_by' => $r->user()->id,
            'request_id' => $d['request_id'], 'request_hash' => $hash, 'source_token' => $d['source_token'], 'snapshot' => $snapshot,
            'barcode_code' => $barcodeCode, 'snapshot_sha256' => hash('sha256', $snapshot), 'document' => $document, 'sha256' => hash('sha256', $document), 'reason' => $d['reason'], 'created_at' => now()]);
        DB::table('pharmacy_fills')->where('id', $fillId)->update(['version' => $fill->version + 1, 'updated_at' => now()]);
        $this->event($r, $rx, 'label_retained', ['fill_id' => $fillId, 'label_id' => $labelId, 'revision' => $revision, 'sha256' => hash('sha256', $document), 'reason' => $d['reason']]);
        return $this->index($r, $id, $fillId)->setStatusCode(201);
    }

    public function file(Request $r, $id, $fillId, $labelId)
    {
        $this->records($r, $id, $fillId);
        $label = DB::table('pharmacy_fill_labels')->where('fill_id', $fillId)->where('id', $labelId)->first();
        abort_unless($label, 404);
        abort_unless(app(PharmacyLabel::class)->intact($label), 409, 'The retained label failed its integrity check.');
        $v = $r->validate(['purpose' => 'nullable|in:record_copy']);
        $copy = ($v['purpose'] ?? null) === 'record_copy';
        $document = $copy ? app(PharmacyLabel::class)->recordCopy($label) : $label->document;
        return response($document, 200, ['Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.($copy ? 'record-copy-' : 'synthetic-label-').$label->id.'.html"', 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "sandbox; default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'"]);
    }

    public function prints(Request $r, $id, $fillId, $labelId)
    {
        $this->records($r, $id, $fillId);
        abort_unless(DB::table('pharmacy_fill_labels')->where('fill_id', $fillId)->where('id', $labelId)->exists(), 404);
        $r->validate(['page' => 'nullable|integer|min:1']);
        return response()->json(['data' => DB::table('pharmacy_label_prints')->where('label_id', $labelId)->select('id', 'created_by', 'copies', 'occurred_on', 'reason', 'reference', 'created_at', 'purpose', 'document_sha256')->orderByDesc('id')->paginate(10)]);
    }

    public function recordPrint(Request $r, $id, $fillId, $labelId)
    {
        [$rx, $fill] = $this->records($r, $id, $fillId);
        $label = DB::table('pharmacy_fill_labels')->where('fill_id', $fillId)->where('id', $labelId)->first();
        abort_unless($label, 404);
        $d = $r->validate(['purpose' => 'sometimes|required|in:dispensing_label,record_copy', 'request_id' => 'required|uuid', 'copies' => 'required|integer|min:1|max:20',
            'occurred_on' => 'required|date_format:Y-m-d|before_or_equal:today', 'reason' => 'required|string|max:2000',
            'reference' => 'required|string|max:2000', 'confirmed' => 'required|accepted']);
        ksort($d); $hash = hash('sha256', json_encode($d, JSON_THROW_ON_ERROR));
        $old = DB::table('pharmacy_label_prints')->where('label_id', $labelId)->where('request_id', $d['request_id'])->first();
        if ($old) {
            abort_unless((int) $old->created_by === (int) $r->user()->id && hash_equals($old->request_hash, $hash), 409, 'This request identifier belongs to another print record.');
            return $this->prints($r, $id, $fillId, $labelId);
        }
        $purpose = $d['purpose'] ?? 'dispensing_label';
        if ($purpose === 'record_copy') {
            $document = app(PharmacyLabel::class)->recordCopy($label);
        } else {
            app(PharmacyLabel::class)->assertCanLabel($rx, $fill);
            app(PharmacyLabel::class)->requireCurrent($rx, $fill, (int) $labelId);
            $document = $label->document;
        }
        abort_if($d['occurred_on'] < substr($label->created_at, 0, 10), 422, 'A print cannot predate the retained label.');
        unset($d['confirmed']);
        $printId = DB::table('pharmacy_label_prints')->insertGetId($d + ['purpose' => $purpose, 'document_sha256' => hash('sha256', $document), 'label_id' => $labelId, 'created_by' => $r->user()->id, 'request_hash' => $hash, 'created_at' => now()]);
        $this->event($r, $rx, 'label_print_recorded', ['fill_id' => $fillId, 'label_id' => $labelId, 'print_id' => $printId, 'purpose' => $purpose, 'document_sha256' => hash('sha256', $document)]);
        return $this->prints($r, $id, $fillId, $labelId)->setStatusCode(201);
    }
}
