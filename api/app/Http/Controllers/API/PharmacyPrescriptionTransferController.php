<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PharmacyAccess;
use App\Services\PharmacyAuthorizationLimits;
use App\Services\PharmacyTransferBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Internal noncontrolled, noncompounded transfers. All writes use PharmacyWriteTransaction. */
class PharmacyPrescriptionTransferController extends Controller
{
    private function role(Request $r, bool $write = false): void
    {
        abort_unless(in_array($r->user()->role, $write ? ['pharmacist'] : ['pharmacist', 'pharmacy_technician'], true), 403);
    }

    private function rx(Request $r, int $id): object
    {
        $this->role($r);
        $q = DB::table('pharmacy_prescriptions')->where('organization_id', $r->user()->organization_id)->where('id', $id);
        app(PharmacyAccess::class)->scope($q, $r->user());
        $rx = $q->lockForUpdate()->first();
        abort_unless($rx, 404);
        return $rx;
    }

    private function hash(array $value): string
    {
        ksort($value);
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }

    private function identity(object $rx, int $destination): array
    {
        $ep = DB::table('pharmacy_episodes')->where('organization_id', $rx->organization_id)->where('id', $rx->episode_id)->first();
        abort_unless($ep, 404);
        $case = DB::table('cases')->where('organization_id', $rx->organization_id)->where('id', $ep->case_id)->first();
        abort_unless($case && $case->accident_date, 422, 'The linked case needs current identity and accident evidence.');
        if ($ep->pharmacy_patient_id) {
            $patient = DB::table('pharmacy_patients')->where('organization_id', $rx->organization_id)->where('id', $ep->pharmacy_patient_id)->first();
            abort_unless($patient, 404);
            foreach ([$rx->location_id, $destination] as $location) {
                abort_unless(DB::table('pharmacy_patient_locations')->where('patient_id', $patient->id)->where('location_id', $location)->where('active', true)->exists(), 404, 'The patient chart must be actively enrolled at both locations.');
            }
            $identity = ['type' => 'pharmacy_patient', 'id' => $patient->id, 'version' => $patient->version, 'record_number' => $patient->record_number,
                'first_name' => $patient->first_name, 'last_name' => $patient->last_name, 'date_of_birth' => $patient->date_of_birth];
        } else {
            $patient = DB::table('users')->where('organization_id', $rx->organization_id)->where('id', $ep->patient_id)->where('role', 'client')->where('status', 'active')->first();
            abort_unless($patient && DB::table('case_parties')->where('case_id', $case->id)->where('user_id', $patient->id)->exists(), 404);
            $identity = ['type' => 'portal_patient', 'id' => $patient->id, 'first_name' => $patient->first_name, 'last_name' => $patient->last_name, 'updated_at' => $patient->updated_at];
        }
        return ['patient' => $identity, 'case' => ['id' => $case->id, 'case_number' => $case->case_number, 'accident_date' => $case->accident_date]];
    }

    private function snapshot(object $rx, int $destination, ?int $ignore = null): array
    {
        abort_if((int) $rx->location_id === $destination, 422, 'Choose a different receiving location.');
        abort_unless(DB::table('pharmacy_locations')->where('organization_id', $rx->organization_id)->where('id', $destination)->where('active', true)->exists(), 404);
        $snapshot = app(PharmacyTransferBalance::class)->snapshot($rx, $ignore);
        $snapshot['identity'] = $this->identity($rx, $destination);
        $snapshot['destination_location_id'] = $destination;
        $snapshot['source_token'] = $this->hash([$snapshot['source_token'], $snapshot['identity'], $destination]);
        return $snapshot;
    }

    private function documents(object $rx): object
    {
        $documents = DB::table('pharmacy_source_documents')->where('prescription_id', $rx->id)->orderBy('id')->get();
        abort_if($documents->isEmpty(), 422, 'Attach the original prescription source before requesting a transfer.');
        $disk = Storage::disk('documents');
        foreach ($documents as $document) {
            abort_unless($disk->exists($document->path) && hash_equals($document->sha256, hash_file('sha256', $disk->path($document->path))), 409, 'A retained source file is unavailable or failed its integrity check.');
        }
        return $documents;
    }

    private function event(Request $r, int $rx, string $action, array $details): void
    {
        DB::table('pharmacy_events')->insert(['organization_id' => $r->user()->organization_id, 'prescription_id' => $rx, 'actor_id' => $r->user()->id,
            'action' => $action, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    public function preview(Request $r, int $id)
    {
        $rx = $this->rx($r, $id);
        $d = $r->validate(['destination_location_id' => 'required|integer']);
        return response()->json(['data' => $this->snapshot($rx, (int) $d['destination_location_id'])]);
    }

    public function destinations(Request $r, int $id)
    {
        $rx = $this->rx($r, $id);
        return response()->json(['data' => DB::table('pharmacy_locations')->where('organization_id', $rx->organization_id)->where('active', true)
            ->where('id', '!=', $rx->location_id)->orderBy('name')->get(['id', 'name'])]);
    }

    public function store(Request $r, int $id)
    {
        $this->role($r, true);
        $rx = $this->rx($r, $id);
        $d = $r->validate(['request_id' => 'required|uuid', 'destination_location_id' => 'required|integer', 'source_token' => 'required|string|regex:/^[a-f0-9]{64}$/D',
            'sending_evidence' => 'required|string|max:5000', 'sharing_reference' => 'required|string|max:2000', 'confirmed' => 'required|accepted']);
        $hash = $this->hash($d + ['source_prescription_id' => $id]);
        $old = DB::table('pharmacy_rx_transfer_requests')->where('organization_id', $rx->organization_id)->where('request_id', $d['request_id'])->first();
        if ($old) {
            abort_unless((int) $old->sent_by === (int) $r->user()->id && hash_equals($old->request_hash, $hash), 409, 'This request identifier already has different transfer evidence.');
            return $this->show($r, $old->id);
        }
        $snapshot = $this->snapshot($rx, (int) $d['destination_location_id']);
        abort_unless(hash_equals($snapshot['source_token'], $d['source_token']), 409, 'The order, patient, case or source evidence changed. Refresh the transfer review.');
        abort_if($snapshot['holds'], 422, 'Resolve the prescription holds before requesting transfer.');
        $this->documents($rx);
        $request = DB::table('pharmacy_rx_transfer_requests')->insertGetId(['organization_id' => $rx->organization_id, 'source_prescription_id' => $rx->id,
            'source_location_id' => $rx->location_id, 'destination_location_id' => $d['destination_location_id'], 'request_id' => $d['request_id'], 'request_hash' => $hash,
            'source_token' => $snapshot['source_token'], 'source_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'sending_evidence' => $d['sending_evidence'],
            'sharing_reference' => $d['sharing_reference'], 'sent_by' => $r->user()->id, 'created_at' => now()]);
        DB::table('pharmacy_prescriptions')->where('id', $id)->update(['pending_transfer_id' => $request, 'updated_at' => now()]);
        $this->event($r, $id, 'prescription_transfer_requested', ['transfer_request_id' => $request, 'destination_location_id' => $d['destination_location_id']]);
        return $this->show($r, $request)->setStatusCode(201);
    }

    private function visible(Request $r, int $id): object
    {
        $this->role($r);
        $transfer = DB::table('pharmacy_rx_transfer_requests')->where('organization_id', $r->user()->organization_id)->where('id', $id)->first();
        abort_unless($transfer, 404);
        $access = app(PharmacyAccess::class);
        $sourceAccess = $access->locations($r->user())->where('id', $transfer->source_location_id)->exists();
        $destinationAccess = $access->locations($r->user())->where('id', $transfer->destination_location_id)->exists();
        abort_unless($sourceAccess || $destinationAccess, 404);
        if (! $sourceAccess) {
            $rx = DB::table('pharmacy_prescriptions')->find($transfer->source_prescription_id);
            $this->identity($rx, (int) $transfer->destination_location_id);
        }
        return $transfer;
    }

    public function index(Request $r)
    {
        $this->role($r);
        $r->validate(['page' => 'nullable|integer|min:1', 'status' => 'nullable|in:pending,accepted,rejected,cancelled']);
        $locations = app(PharmacyAccess::class)->locations($r->user())->pluck('id');
        $q = DB::table('pharmacy_rx_transfer_requests as t')->join('pharmacy_prescriptions as rx', 'rx.id', '=', 't.source_prescription_id')
            ->join('pharmacy_episodes as ep', 'ep.id', '=', 'rx.episode_id')->where('t.organization_id', $r->user()->organization_id)
            ->where(function ($q) use ($locations) {
                $q->whereIn('t.source_location_id', $locations)->orWhere(function ($q) use ($locations) {
                    $q->whereIn('t.destination_location_id', $locations)->where(function ($q) {
                        $q->whereNull('ep.pharmacy_patient_id')->orWhereExists(function ($q) {
                            $q->selectRaw('1')->from('pharmacy_patient_locations as pl')->whereColumn('pl.patient_id', 'ep.pharmacy_patient_id')
                                ->whereColumn('pl.location_id', 't.destination_location_id')->where('pl.active', true);
                        });
                    });
                });
            });
        if ($r->filled('status')) $q->where('t.status', $r->input('status'));
        return response()->json(['data' => $q->select('t.id', 't.source_prescription_id', 't.source_location_id', 't.destination_location_id', 't.status', 't.sent_by', 't.created_at', 't.reviewed_at')->orderByDesc('t.id')->paginate(20)]);
    }

    public function show(Request $r, int $id)
    {
        $t = $this->visible($r, $id);
        $record = array_intersect_key((array) $t, array_flip(['id', 'source_prescription_id', 'source_location_id', 'destination_location_id', 'source_token', 'sending_evidence', 'sharing_reference', 'sent_by', 'created_at', 'status', 'receipt_id', 'reviewed_by', 'review_evidence', 'reviewed_at']));
        $record['source_location_name'] = DB::table('pharmacy_locations')->where('organization_id', $t->organization_id)->where('id', $t->source_location_id)->value('name');
        $record['destination_location_name'] = DB::table('pharmacy_locations')->where('organization_id', $t->organization_id)->where('id', $t->destination_location_id)->value('name');
        $record['source_snapshot'] = json_decode($t->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
        $record['destination_prescription_id'] = $t->receipt_id ? DB::table('pharmacy_rx_transfer_receipts')->where('id', $t->receipt_id)->value('destination_prescription_id') : null;
        return response()->json(['data' => $record]);
    }

    public function sourceFile(Request $r, int $id, int $sourceId)
    {
        $t = $this->visible($r, $id);
        $snapshot = json_decode($t->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
        $retained = collect($snapshot['source_documents'])->firstWhere('id', $sourceId);
        abort_unless($retained, 404);
        $document = DB::table('pharmacy_source_documents')->where('prescription_id', $t->source_prescription_id)->where('id', $sourceId)->first();
        $disk = Storage::disk('documents');
        abort_unless($document && $disk->exists($document->path) && hash_equals($retained['sha256'], hash_file('sha256', $disk->path($document->path))), 409, 'The retained source file failed its integrity check.');
        return $disk->download($document->path, $document->original_name, ['Content-Type' => $document->mime_type, 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "sandbox; default-src 'none'"]);
    }

    public function review(Request $r, int $id)
    {
        $this->role($r, true);
        $t = $this->visible($r, $id);
        $d = $r->validate(['request_id' => 'required|uuid', 'decision' => 'required|in:accept,reject,cancel', 'source_token' => 'required|string|regex:/^[a-f0-9]{64}$/D',
            'evidence' => 'required|string|max:5000', 'rx_number' => 'nullable|required_if:decision,accept|string|max:100', 'confirmed' => 'required|accepted']);
        $access = app(PharmacyAccess::class);
        $access->requireLocation($r->user(), $d['decision'] === 'cancel' ? $t->source_location_id : $t->destination_location_id);
        $hash = $this->hash($d);
        if ($t->status !== 'pending') {
            abort_unless((int) $t->reviewed_by === (int) $r->user()->id && $t->review_request_id === $d['request_id'] && hash_equals($t->review_hash, $hash), 409, 'This transfer already has a retained decision.');
            return $this->show($r, $id);
        }
        abort_unless(hash_equals($t->source_token, $d['source_token']), 409, 'Review the retained transfer request before deciding.');
        if ($d['decision'] !== 'cancel') abort_if((int) $t->sent_by === (int) $r->user()->id, 422, 'A different receiving pharmacist must decide the transfer.');
        $rx = DB::table('pharmacy_prescriptions')->where('organization_id', $t->organization_id)->where('id', $t->source_prescription_id)->lockForUpdate()->first();
        abort_unless($rx && (int) $rx->pending_transfer_id === $id, 409, 'The source transfer hold is inconsistent. Investigate before deciding.');
        $receiptId = null;
        if ($d['decision'] === 'accept') {
            $sender = User::where('organization_id', $t->organization_id)->where('id', $t->sent_by)->where('role', 'pharmacist')->where('status', 'active')->first();
            abort_unless($sender, 422, 'The sending pharmacist no longer has active authority.');
            $access->requireLocation($sender, $t->source_location_id);
            $snapshot = $this->snapshot($rx, (int) $t->destination_location_id, $id);
            abort_unless(hash_equals($snapshot['source_token'], $t->source_token)
                && hash_equals(hash('sha256', $t->source_snapshot), hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR))),
                409, 'The source order, patient, case or evidence changed. Reject or cancel this request and reassess.');
            abort_if($snapshot['holds'], 422, 'The prescription no longer qualifies for transfer.');
            $documents = $this->documents($rx);
            abort_if(DB::table('pharmacy_prescriptions')->where('location_id', $t->destination_location_id)->where('rx_number', $d['rx_number'])->exists(), 422, 'This receiving prescription number already exists.');
            abort_if(DB::table('pharmacy_prescriptions')->where('organization_id', $t->organization_id)->where('request_id', $d['request_id'])->exists(), 409, 'This decision identifier already belongs to another prescription.');
            $values = array_intersect_key((array) $rx, array_flip(['organization_id', 'episode_id', 'medication', 'strength', 'dosage_form', 'directions', 'quantity', 'quantity_unit', 'written_on', 'expires_on', 'prescriber_name', 'prescriber_identifier', 'source_reference']));
            $destination = DB::table('pharmacy_prescriptions')->insertGetId($values + ['location_id' => $t->destination_location_id, 'request_id' => $d['request_id'], 'request_hash' => $hash,
                'rx_number' => $d['rx_number'], 'refills_authorized' => count($snapshot['allowances']) - 1, 'controlled' => false, 'compounded' => false, 'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $receiptId = DB::table('pharmacy_rx_transfer_receipts')->insertGetId(['organization_id' => $t->organization_id, 'source_prescription_id' => $rx->id,
                'destination_prescription_id' => $destination, 'source_location_id' => $t->source_location_id, 'destination_location_id' => $t->destination_location_id,
                'source_snapshot' => $t->source_snapshot, 'snapshot_sha256' => hash('sha256', $t->source_snapshot), 'sent_by' => $t->sent_by, 'accepted_by' => $r->user()->id,
                'sending_evidence' => $t->sending_evidence, 'receiving_evidence' => $d['evidence'], 'accepted_at' => now()]);
            DB::table('pharmacy_prescriptions')->where('id', $destination)->update(['incoming_transfer_id' => $receiptId]);
            DB::table('pharmacy_prescriptions')->where('id', $rx->id)->update(['discontinued_at' => now(), 'discontinued_by' => $r->user()->id,
                'discontinuation_reason' => 'Prescription transferred to another location', 'discontinuation_reference' => 'Transfer receipt '.$receiptId,
                'discontinuation_request_id' => $d['request_id'], 'updated_at' => now()]);
            foreach ($documents as $document) {
                $copy = array_intersect_key((array) $document, array_flip(['original_name', 'mime_type', 'size', 'path', 'sha256']));
                DB::table('pharmacy_source_documents')->insert($copy + ['prescription_id' => $destination, 'created_by' => $r->user()->id, 'request_id' => (string) Str::uuid(),
                    'reference' => 'Received with transfer receipt '.$receiptId.'; original source document '.$document->id, 'created_at' => now()]);
            }
            $identity = $snapshot['identity']; $patient = $identity['patient']; $case = $identity['case'];
            $caseLink = $patient['type'] === 'pharmacy_patient' ? ['reference' => $d['evidence'], 'confirmed' => true, 'patient_version' => $patient['version'],
                'pharmacy_patient_id' => $patient['id'], 'record_number' => $patient['record_number'], 'first_name' => $patient['first_name'], 'last_name' => $patient['last_name'],
                'date_of_birth' => $patient['date_of_birth'], 'case_id' => $case['id'], 'case_number' => $case['case_number'], 'accident_date' => $case['accident_date']] : null;
            $this->event($r, $destination, 'prescription_received', ['source_reference' => $rx->source_reference, 'transfer_receipt_id' => $receiptId, 'source_prescription_id' => $rx->id, 'case_link' => $caseLink]);
            app(PharmacyAuthorizationLimits::class)->forPrescription(DB::table('pharmacy_prescriptions')->find($destination));
        }
        DB::table('pharmacy_prescriptions')->where('id', $rx->id)->update(['pending_transfer_id' => null, 'updated_at' => now()]);
        DB::table('pharmacy_rx_transfer_requests')->where('id', $id)->update(['status' => ['accept' => 'accepted', 'reject' => 'rejected', 'cancel' => 'cancelled'][$d['decision']],
            'receipt_id' => $receiptId, 'reviewed_by' => $r->user()->id, 'review_request_id' => $d['request_id'], 'review_hash' => $hash, 'review_evidence' => $d['evidence'], 'reviewed_at' => now()]);
        $this->event($r, $rx->id, 'prescription_transfer_'.$d['decision'], ['transfer_request_id' => $id, 'receipt_id' => $receiptId, 'evidence' => $d['evidence']]);
        return $this->show($r, $id);
    }
}
