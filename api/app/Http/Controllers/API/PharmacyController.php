<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Models\Invoice;
use App\Models\User;
use App\Services\PharmacyStock;
use App\Services\PharmacyAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PharmacyController extends Controller
{
    private function org(Request $r): int
    {
        abort_unless($r->user()->organization_id, 403, 'An organization is required.');

        return (int) $r->user()->organization_id;
    }

    private function allow(Request $r, array $roles): void
    {
        abort_unless(in_array($r->user()->role, $roles, true), 403);
    }

    private function rx(Request $r, $id, bool $lock = false)
    {
        $q = DB::table('pharmacy_prescriptions')->where('organization_id', $this->org($r))->where('id', $id);
        app(PharmacyAccess::class)->scope($q, $r->user());
        if ($lock) {
            $q->lockForUpdate();
        } $rx = $q->first();
        abort_unless($rx, 404);

        return $rx;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['workflow' => $message]);
    }

    private function event(Request $r, $rx, string $action, array $details): void
    {
        DB::table('pharmacy_events')->insert(['organization_id' => $this->org($r), 'prescription_id' => $rx->id, 'actor_id' => $r->user()->id, 'action' => $action, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    private function json($value): array
    {
        return $value ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : [];
    }

    private function hash(array $data): string
    {
        unset($data['request_id']);
        ksort($data);

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function version($record, array $data): void
    {
        abort_unless((int) $record->version === (int) $data['version'], 409, 'This record changed. Refresh before trying again.');
    }

    public function cases(Request $r)
    {
        $r->validate(['search' => 'nullable|string|max:100']);
        $q = CaseModel::where('organization_id', $this->org($r));
        if (! app(PharmacyAccess::class)->locations($r->user())->exists()) {
            return response()->json(['data' => []]);
        }
        if ($r->filled('search')) {
            $q->where(fn ($q) => $q->where('case_number', 'like', '%'.$r->search.'%')->orWhere('title', 'like', '%'.$r->search.'%'));
        }
        $cases = $q->select('id', 'case_number', 'title', 'accident_date')->latest('id')->limit(100)->get();
        foreach ($cases as $case) {
            $case->patients = DB::table('case_parties')->join('users', 'users.id', '=', 'case_parties.user_id')->where('case_parties.case_id', $case->id)->where('users.organization_id', $this->org($r))->where('users.role', 'client')->distinct()->get(['users.id', 'users.first_name', 'users.last_name']);
        }

        return response()->json(['data' => $cases]);
    }

    public function index(Request $r)
    {
        $v = $r->validate(['page' => 'nullable|integer|min:1', 'location_id' => 'nullable|integer', 'stage' => 'nullable|in:intake,pending,ready,collected,delivered,cancelled', 'search' => 'nullable|string|max:100']);
        $q = DB::table('pharmacy_prescriptions as rx')->join('pharmacy_episodes as ep', 'ep.id', '=', 'rx.episode_id')->leftJoin('users as patient', 'patient.id', '=', 'ep.patient_id')->leftJoin('pharmacy_patients as chart', 'chart.id', '=', 'ep.pharmacy_patient_id')->join('cases', 'cases.id', '=', 'ep.case_id')->where('rx.organization_id', $this->org($r));
        app(PharmacyAccess::class)->scope($q, $r->user(), 'rx.location_id');
        $latest = DB::table('pharmacy_fills')->selectRaw('prescription_id, MAX(id) AS latest_fill_id')->groupBy('prescription_id');
        $q->leftJoinSub($latest, 'latest_fill', fn ($join) => $join->on('latest_fill.prescription_id', '=', 'rx.id'))->leftJoin('pharmacy_fills as fill', 'fill.id', '=', 'latest_fill.latest_fill_id');
        if (! empty($v['stage'])) {
            if ($v['stage'] === 'intake') {
                $q->whereNull('fill.id');
            } else {
                $q->where('fill.fulfillment_status', $v['stage']);
            }
        }
        if (! empty($v['location_id'])) {
            $q->where('rx.location_id', $v['location_id']);
        }
        if (! empty($v['search'])) {
            $q->where(fn ($q) => $q->where('rx.rx_number', 'like', '%'.$v['search'].'%')->orWhere('rx.medication', 'like', '%'.$v['search'].'%'));
        }

        return response()->json(['data' => $q->select('rx.id', 'rx.rx_number', 'rx.medication', 'rx.strength', 'rx.location_id', 'rx.controlled', 'rx.compounded', 'ep.coverage_status', 'cases.case_number', 'fill.review_status', 'fill.claim_status')->selectRaw("COALESCE(chart.first_name, patient.first_name) AS first_name, COALESCE(chart.last_name, patient.last_name) AS last_name")->selectRaw("COALESCE(fill.fulfillment_status, 'intake') AS stage")->orderByDesc('rx.id')->paginate(20)]);
    }

    public function show(Request $r, $id)
    {
        $rx = $this->rx($r, $id);
        $ep = DB::table('pharmacy_episodes')->where('id', $rx->episode_id)->first();
        $ep->coverage = $this->json($ep->coverage);
        $rx->episode = $ep;
        $rx->patient = $ep->pharmacy_patient_id
            ? DB::table('pharmacy_patients')->where('organization_id', $this->org($r))->where('id', $ep->pharmacy_patient_id)->first(['id', 'first_name', 'last_name', 'date_of_birth', 'record_number', 'version'])
            : User::where('organization_id', $this->org($r))->findOrFail($ep->patient_id)->only(['id', 'first_name', 'last_name']);
        $rx->case = CaseModel::where('organization_id', $this->org($r))->findOrFail($ep->case_id)->only(['id', 'case_number', 'accident_date']);
        $rx->location = DB::table('pharmacy_locations')->where('id', $rx->location_id)->first(['id', 'name', 'address']);
        $rx->fills = DB::table('pharmacy_fills')->where('prescription_id', $rx->id)->orderBy('fill_number')->get()->map(function ($f) {
            foreach (['review', 'fulfillment', 'claim'] as $k) {
                $f->$k = $this->json($f->$k);
            }
            if ($f->invoice_id) {
                $inv = Invoice::withSum('payments as total_paid', 'amount')->find($f->invoice_id);
                $f->invoice = $inv?->only(['id', 'invoice_number', 'amount', 'status', 'total_paid']);
            }

            return $f;
        });
        $rx->events = DB::table('pharmacy_events')->where('prescription_id', $rx->id)->orderByDesc('id')->limit(100)->get()->map(function ($e) {
            $e->details = $this->json($e->details);

            return $e;
        });

        return response()->json(['data' => $rx]);
    }

    public function store(Request $r)
    {
        $this->allow($r, ['pharmacist', 'pharmacy_technician']);
        $d = $r->validate(['request_id' => 'required|uuid', 'case_id' => 'required|integer', 'patient_id' => 'nullable|integer|required_without:pharmacy_patient_id|prohibits:pharmacy_patient_id', 'pharmacy_patient_id' => 'nullable|integer|required_without:patient_id|prohibits:patient_id', 'location_id' => 'required|integer', 'quantity_unit' => 'required|in:tablet,capsule,mL,g,each', 'compounded' => 'required|boolean', 'rx_number' => 'required|string|max:100', 'medication' => 'required|string|max:255', 'strength' => 'required|string|max:100', 'dosage_form' => 'required|string|max:100', 'directions' => 'required|string|max:2000', 'quantity' => 'required|numeric|min:0.001|max:999999.999|decimal:0,3', 'refills_authorized' => 'required|integer|min:0|max:99', 'written_on' => 'required|date_format:Y-m-d|before_or_equal:today', 'expires_on' => 'required|date_format:Y-m-d|after_or_equal:written_on', 'prescriber_name' => 'required|string|max:255', 'prescriber_identifier' => 'required|string|max:100', 'source_reference' => 'required|string|max:255', 'controlled' => 'required|boolean']);
        $org = $this->org($r);
        $id = DB::transaction(function () use ($r, $d, $org) {
            app(PharmacyAccess::class)->requireLocation($r->user(), $d['location_id']);
            // Serialize intake for an organization, including duplicate request keys.
            DB::table('organizations')->where('id', $org)->lockForUpdate()->first();
            $old = DB::table('pharmacy_prescriptions')->where('organization_id', $org)->where('request_id', $d['request_id'])->first();
            if ($old) {
                abort_unless(hash_equals($old->request_hash, $this->hash($d)), 409, 'Request key already used for different content.');

                return $old->id;
            }
            abort_unless(DB::table('pharmacy_locations')->where('organization_id', $org)->where('id', $d['location_id'])->where('active', true)->exists(), 404);
            $case = CaseModel::where('organization_id', $org)->findOrFail($d['case_id']);
            if (!empty($d['pharmacy_patient_id'])) {
                abort_unless(DB::table('pharmacy_patients as p')->join('pharmacy_patient_locations as pl', 'pl.patient_id', '=', 'p.id')
                    ->where('p.organization_id', $org)->where('p.id', $d['pharmacy_patient_id'])->where('pl.location_id', $d['location_id'])->exists(), 404);
            } else {
            abort_unless(DB::table('case_parties')->join('users', 'users.id', '=', 'case_parties.user_id')->where('case_parties.case_id', $case->id)->where('users.id', $d['patient_id'])->where('users.role', 'client')->where('users.organization_id', $org)->exists(), 404);
            }
            if (! $case->accident_date) {
                $this->fail('The linked case needs its accident date before pharmacy intake.');
            }
            if ($d['written_on'] < $case->accident_date->toDateString()) {
                $this->fail('Prescription predates this accident. Review the case linkage.');
            }
            if (DB::table('pharmacy_prescriptions')->where('organization_id', $org)->where('location_id', $d['location_id'])->where('rx_number', $d['rx_number'])->exists()) {
                $this->fail('This prescription number already exists at this pharmacy location.');
            }
            $episodeKeys = ['organization_id' => $org, 'case_id' => $case->id, 'patient_id' => $d['patient_id'] ?? null, 'pharmacy_patient_id' => $d['pharmacy_patient_id'] ?? null];
            $ep = DB::table('pharmacy_episodes')->where($episodeKeys)->first();
            $eid = $ep?->id ?? DB::table('pharmacy_episodes')->insertGetId($episodeKeys + ['created_at' => now(), 'updated_at' => now()]);
            $data = $d;
            unset($data['case_id'],$data['patient_id'],$data['pharmacy_patient_id']);
            $id = DB::table('pharmacy_prescriptions')->insertGetId($data + ['organization_id' => $org, 'episode_id' => $eid, 'request_hash' => $this->hash($d), 'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->event($r, (object) ['id' => $id], 'prescription_received', ['source_reference' => $d['source_reference']]);

            return $id;
        });

        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function coverage(Request $r, $id)
    {
        $this->allow($r, ['pharmacist', 'pharmacy_technician', 'medical_biller']);
        $d = $r->validate(['version' => 'required|integer|min:1', 'status' => 'required|in:unverified,verified,disputed,exhausted,not_applicable', 'payer' => 'required|string|max:255', 'claim_number' => 'nullable|string|max:100', 'policy_number' => 'nullable|string|max:100', 'adjuster' => 'nullable|string|max:255', 'coordination' => 'required|string|max:2000', 'evidence' => 'required|string|max:2000', 'verified_on' => 'required|date_format:Y-m-d|before_or_equal:today', 'limit' => 'nullable|numeric|min:0|max:99999999.99|decimal:0,2']);
        DB::transaction(function () use ($r, $id, $d) {
            $rx = $this->rx($r, $id, true);
            $ep = DB::table('pharmacy_episodes')->where('id', $rx->episode_id)->lockForUpdate()->first();
            $this->version($ep, $d);
            if ($d['status'] === 'verified' && empty($d['claim_number'])) {
                $this->fail('A verified PIP record requires a claim number.');
            }
            $data = $d;
            unset($data['version'],$data['status']);
            $data['recorded_by'] = $r->user()->id;
            DB::table('pharmacy_episodes')->where('id', $ep->id)->update(['coverage_status' => $d['status'], 'coverage' => json_encode($data), 'version' => $ep->version + 1, 'updated_at' => now()]);
            $this->event($r, $rx, 'coverage_recorded', ['episode_id' => $ep->id, 'previous_status' => $ep->coverage_status, 'previous' => $this->json($ep->coverage), 'status' => $d['status'], 'evidence' => $d['evidence']]);
        });

        return $this->show($r, $id);
    }

    public function createFill(Request $r, $id)
    {
        $this->allow($r, ['pharmacist', 'pharmacy_technician']);
        $d = $r->validate(['request_id' => 'required|uuid', 'stock_lot_id' => 'required|integer', 'quantity' => 'required|numeric|min:0.001|max:999999.999|decimal:0,3', 'days_supply' => 'required|integer|min:1|max:365', 'ndc' => ['required', 'string', 'regex:/^(\d{10,11}|\d{4}-\d{4}-\d{2}|\d{5}-\d{3}-\d{2}|\d{5}-\d{4}-\d{1,2})$/D']]);
        DB::transaction(function () use ($r, $id, $d) {
            $rx = $this->rx($r, $id, true);
            $q = DB::table('pharmacy_fills')->where('prescription_id', $rx->id);
            $old = (clone $q)->where('request_id', $d['request_id'])->first();
            if ($old) {
                abort_unless(hash_equals($old->request_hash, $this->hash($d)), 409);

                return;
            }
            if ($rx->expires_on < now()->toDateString()) {
                $this->fail('Prescription has expired. Obtain an updated prescription.');
            }
            if ((float) $d['quantity'] > (float) $rx->quantity) {
                $this->fail('Quantity exceeds the prescribed amount.');
            }
            if ((clone $q)->whereNotIn('fulfillment_status', ['collected', 'delivered', 'cancelled'])->exists()) {
                $this->fail('Resolve the open fill before creating another.');
            }
            $used = (clone $q)->where('fulfillment_status', '!=', 'cancelled')->count();
            if ($used >= $rx->refills_authorized + 1) {
                $this->fail('No authorized fills remain.');
            }
            $lot = app(PharmacyStock::class)->reserve($r->user(), $rx, $d);
            $number = ((clone $q)->max('fill_number') ?? -1) + 1;
            $fid = DB::table('pharmacy_fills')->insertGetId($d + ['prescription_id' => $rx->id, 'fill_number' => $number, 'request_hash' => $this->hash($d), 'created_at' => now(), 'updated_at' => now()]);
            app(PharmacyStock::class)->event($r->user(), $lot, 'reserved', $d['quantity'], ['request_id' => $d['request_id']], $fid);
            $this->event($r, $rx, 'fill_created', ['fill_id' => $fid, 'fill_number' => $number]);
        });

        return $this->show($r, $id);
    }

    public function fillAction(Request $r, $id, $fillId)
    {
        $d = $r->validate(['version' => 'required|integer|min:1', 'action' => 'required|in:approve,hold,cancel,ready,collected,delivered,prepare_claim,record_submission,record_denial,record_maps', 'note' => 'required|string|max:2000', 'checks' => 'sometimes|array:identity,prescriber,therapy,product,label', 'checks.identity' => 'sometimes|accepted', 'checks.prescriber' => 'sometimes|accepted', 'checks.therapy' => 'sometimes|accepted', 'checks.product' => 'sometimes|accepted', 'checks.label' => 'sometimes|accepted', 'occurred_on' => 'nullable|date_format:Y-m-d|before_or_equal:today', 'reference' => 'nullable|string|max:255', 'amount' => 'nullable|numeric|min:0.01|max:99999999.99|decimal:0,2', 'counseling' => 'nullable|in:provided,declined,documented_remote', 'maps_status' => 'nullable|in:not_applicable,pending,submitted', 'maps_reference' => 'nullable|string|max:255', 'denial_type' => 'nullable|in:coverage,coding,medical_necessity,cost,no_response', 'determination_on' => 'nullable|date_format:Y-m-d|before_or_equal:today']);
        DB::transaction(function () use ($r, $id, $fillId, $d) {
            $rx = $this->rx($r, $id, true);
            $f = DB::table('pharmacy_fills')->where('prescription_id', $rx->id)->where('id', $fillId)->lockForUpdate()->first();
            abort_unless($f, 404);
            $this->version($f, $d);
            $a = $d['action'];
            $update = [];
            $ep = DB::table('pharmacy_episodes')->where('id', $rx->episode_id)->lockForUpdate()->first();
            if ($a === 'record_maps') {
                $this->allow($r, ['pharmacist']);
                if (! $rx->controlled || ! in_array($f->fulfillment_status, ['collected', 'delivered'], true) || empty($d['maps_reference'])) {
                    $this->fail('A fulfilled controlled-substance fill and submission confirmation are required.');
                }
                $fulfillment = $this->json($f->fulfillment);
                $fulfillment['maps_status'] = 'submitted';
                $fulfillment['maps_reference'] = $d['maps_reference'];
                $fulfillment['maps_recorded_by'] = $r->user()->id;
                $fulfillment['maps_recorded_at'] = now()->toIso8601String();
                $update = ['fulfillment' => json_encode($fulfillment)];
            } elseif (in_array($a, ['approve', 'hold', 'ready', 'collected', 'delivered', 'cancel'], true)) {
                $this->allow($r, ['pharmacist']);
                if (in_array($f->fulfillment_status, ['collected', 'delivered', 'cancelled'], true)) {
                    $this->fail('Completed or cancelled fills are immutable.');
                }
                if (in_array($a, ['approve', 'hold'], true)) {
                    if ($f->fulfillment_status !== 'pending') {
                        $this->fail('Review cannot change after final preparation.');
                    }
                    if ($a === 'approve') {
                        if ($rx->controlled || $rx->compounded) {
                            $this->fail('Controlled and compounded dispensing is not enabled. Dedicated controls must be validated before approval.');
                        }
                        if ($ep->pharmacy_patient_id) {
                            $chart = DB::table('pharmacy_patients')->where('id', $ep->pharmacy_patient_id)->first();
                            $clinical = $this->json($chart->clinical);
                            if (empty($clinical['reviewed_on']) || ($clinical['allergies_status'] ?? 'unknown') === 'unknown' || ($clinical['medications_status'] ?? 'unknown') === 'unknown') {
                                $this->fail('Complete the patient allergy and medication review before approving this fill.');
                            }
                        }
                        foreach (['identity', 'prescriber', 'therapy', 'product'] as $check) {
                            if (empty($d['checks'][$check])) {
                                $this->fail('Complete each pharmacist review check.');
                            }
                        }
                    }
                    $update = ['review_status' => $a === 'approve' ? 'approved' : 'held', 'review' => json_encode(['actor_id' => $r->user()->id, 'at' => now()->toIso8601String(), 'note' => $d['note'], 'checks' => $d['checks'] ?? [], 'patient_version' => $chart->version ?? null])];
                } elseif ($a === 'cancel') {
                    $update = ['fulfillment_status' => 'cancelled'];
                } else {
                    if ($rx->controlled || $rx->compounded) {
                        $this->fail('Controlled and compounded dispensing is not enabled.');
                    }
                    if ($ep->pharmacy_patient_id && (int)($this->json($f->review)['patient_version'] ?? 0) !== (int) DB::table('pharmacy_patients')->where('id', $ep->pharmacy_patient_id)->value('version')) {
                        $this->fail('The patient clinical record changed. A new pharmacist review is required; cancel a prepared fill and start again.');
                    }
                    if ($f->review_status !== 'approved') {
                        $this->fail('Pharmacist review is required.');
                    }
                    if ($rx->expires_on < now()->toDateString()) {
                        $this->fail('Prescription has expired.');
                    }
                    if ($a === 'ready') {
                        if (empty($d['checks']['label'])) {
                            $this->fail('Final product and label verification is required.');
                        } if ($f->fulfillment_status !== 'pending') {
                            $this->fail('Fill is already prepared.');
                        }$update = ['fulfillment_status' => 'ready'];
                    } else {
                        if ($f->fulfillment_status !== 'ready') {
                            $this->fail('Final preparation must be recorded first.');
                        }
                        if (empty($d['occurred_on']) || empty($d['reference']) || empty($d['counseling'])) {
                            $this->fail('Record the fulfillment date, evidence reference and counseling outcome.');
                        }
                        if ($d['occurred_on'] < $rx->written_on) {
                            $this->fail('Fulfillment predates the prescription.');
                        }
                        if ($rx->controlled && (! isset($d['maps_status']) || $d['maps_status'] === 'not_applicable')) {
                            $this->fail('Record the controlled-substance reporting status.');
                        }
                        if (($d['maps_status'] ?? null) === 'submitted' && empty($d['maps_reference'])) {
                            $this->fail('MAPS submission requires a confirmation reference.');
                        }
                        $update = ['fulfillment_status' => $a, 'fulfillment' => json_encode($d + ['actor_id' => $r->user()->id])];
                    }
                }
            } else {
                $this->allow($r, ['medical_biller']);
                if (! in_array($f->fulfillment_status, ['collected', 'delivered'], true)) {
                    $this->fail('Record actual fulfillment before preparing a bill.');
                }
                $claim = $this->json($f->claim);
                if ($a === 'prepare_claim') {
                    if ($f->invoice_id) {
                        $this->fail('An invoice already exists for this fill.');
                    }
                    if ($ep->coverage_status !== 'verified') {
                        $this->fail('Resolve coverage before preparing this PIP claim.');
                    }
                    if (empty($d['amount']) || empty($d['reference'])) {
                        $this->fail('Provide the billed amount and pricing-basis reference.');
                    }
                    $invoice = Invoice::create(['organization_id' => $this->org($r), 'case_id' => $ep->case_id, 'invoice_number' => 'RX-'.Str::upper(Str::random(12)), 'amount' => $d['amount'], 'status' => 'draft', 'metadata' => ['pharmacy_fill_id' => $f->id, 'prescription_id' => $rx->id, 'source' => 'pharmacy', 'pricing_basis' => $d['reference']], 'notes' => 'Pharmacy billing draft; no payer transmission performed.']);
                    $update = ['invoice_id' => $invoice->id, 'claim_status' => 'prepared', 'claim' => json_encode(['prepared_by' => $r->user()->id, 'pricing_basis' => $d['reference'], 'amount' => $d['amount']])];
                } elseif ($a === 'record_submission') {
                    if (! $f->invoice_id || ! in_array($f->claim_status, ['prepared', 'denied'], true)) {
                        $this->fail('Prepare or resolve this claim first.');
                    }
                    if (empty($d['reference']) || empty($d['occurred_on'])) {
                        $this->fail('Provide external submission evidence and date.');
                    }
                    if ($d['occurred_on'] < ($this->json($f->fulfillment)['occurred_on'] ?? $rx->written_on)) {
                        $this->fail('Submission predates fulfillment.');
                    }
                    $claim['submission'] = ['reference' => $d['reference'], 'date' => $d['occurred_on'], 'recorded_by' => $r->user()->id];
                    $update = ['claim_status' => 'submitted_externally', 'claim' => json_encode($claim)];
                    Invoice::where('id', $f->invoice_id)->where('status', 'draft')->update(['status' => 'sent']);
                } else {
                    if ($f->claim_status !== 'submitted_externally' || empty($d['denial_type']) || empty($d['reference'])) {
                        $this->fail('A submitted claim, issue type and evidence reference are required.');
                    }
                    if ($d['denial_type'] !== 'no_response' && empty($d['determination_on'])) {
                        $this->fail('Record the written determination date.');
                    }
                    $claim['issue'] = ['type' => $d['denial_type'], 'reference' => $d['reference'], 'determination_on' => $d['determination_on'] ?? null, 'note' => $d['note']];
                    $update = ['claim_status' => 'denied', 'claim' => json_encode($claim)];
                }
            }
            if (in_array($a, ['ready', 'collected', 'delivered', 'cancel'], true)) {
                app(PharmacyStock::class)->transition($r->user(), $rx, $f, $a);
            }
            DB::table('pharmacy_fills')->where('id', $f->id)->update($update + ['version' => $f->version + 1, 'updated_at' => now()]);
            $this->event($r, $rx, $a, ['fill_id' => $f->id, 'previous' => ['review_status' => $f->review_status, 'fulfillment_status' => $f->fulfillment_status, 'claim_status' => $f->claim_status], 'input' => $d]);
        });

        return $this->show($r, $id);
    }

    public function assistant(Request $r, $id)
    {
        $rx = $this->rx($r, $id);
        $ep = DB::table('pharmacy_episodes')->where('id', $rx->episode_id)->first();
        $checks = [];
        if ($rx->controlled || $rx->compounded) {
            $checks[] = ['message' => 'Dedicated controlled-substance and compounding controls are required before dispensing can be enabled.', 'source' => 'prescription:'.$rx->id];
        }
        if ($ep->coverage_status !== 'verified') {
            $checks[] = ['message' => 'Coverage needs resolution.', 'source' => 'episode:'.$ep->id];
        }
        foreach (DB::table('pharmacy_fills')->where('prescription_id',$rx->id)->get() as $f) {
            if ($f->fulfillment_status === 'cancelled') {
                continue;
            }
            if ($f->review_status !== 'approved') {
                $checks[] = ['message' => 'Fill '.$f->fill_number.' needs pharmacist review.', 'source' => 'fill:'.$f->id];
            }
            if ($rx->controlled && ($this->json($f->fulfillment)['maps_status'] ?? null) === 'pending') {
                $checks[] = ['message' => 'Controlled-substance reporting remains pending. Confirm the applicable deadline in the dispensing system.', 'source' => 'fill:'.$f->id];
            }
            if ($f->claim_status === 'denied') {
                $checks[] = ['message' => 'Review the payer issue and assign the appropriate dispute route.', 'source' => 'fill:'.$f->id];
            }
        }

        return response()->json(['data' => ['mode' => 'rules_only', 'ai_connected' => false, 'message' => 'AI provider not configured. These are deterministic record checks, not clinical advice.', 'checks' => $checks, 'planned_assistance' => ['Source-linked document extraction', 'Missing-information review', 'Draft correspondence for staff approval']]]);
    }
}
