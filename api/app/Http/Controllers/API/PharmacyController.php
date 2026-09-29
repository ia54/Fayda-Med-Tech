<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Models\Invoice;
use App\Models\User;
use App\Services\PharmacyAccess;
use App\Services\PharmacyStock;
use App\Services\PharmacyProduct;
use App\Services\PharmacyLabel;
use App\Services\PharmacyQuantity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    public function discontinue(Request $r, $id)
    {
        $this->allow($r, ['pharmacist']);
        $rx = $this->rx($r, $id, true);
        $d = $r->validate(['request_id' => 'required|uuid', 'reason' => 'required|string|max:2000', 'reference' => 'required|string|max:2000']);
        if ($rx->discontinued_at) {
            abort_unless($rx->discontinuation_request_id === $d['request_id'] && $rx->discontinuation_reason === $d['reason'] && $rx->discontinuation_reference === $d['reference'] && (int) $rx->discontinued_by === (int) $r->user()->id, 409, 'This prescription has already been discontinued. The retained evidence cannot be replaced.');
            return $this->show($r, $id);
        }
        $this->recordDiscontinuation($r, $rx, $d);
        return $this->show($r, $id);
    }

    private function recordDiscontinuation(Request $r, $rx, array $d): void
    {
        DB::table('pharmacy_prescriptions')->where('id', $rx->id)->update([
            'discontinued_at' => now(), 'discontinued_by' => $r->user()->id,
            'discontinuation_reason' => $d['reason'], 'discontinuation_reference' => $d['reference'],
            'discontinuation_request_id' => $d['request_id'], 'updated_at' => now(),
        ]);
        $this->event($r, $rx, 'prescription_discontinued', ['reason' => $d['reason'], 'reference' => $d['reference']]);
    }

    public function linkReplacement(Request $r, $id)
    {
        $this->allow($r, ['pharmacist']);
        $original = $this->rx($r, $id, true);
        $d = $r->validate(['request_id' => 'required|uuid', 'replacement_id' => 'required|integer', 'discontinue_original' => 'sometimes|boolean', 'reason' => 'required|string|max:2000', 'reference' => 'required|string|max:2000']);
        $stopOriginal = (bool) ($d['discontinue_original'] ?? false);
        $replacement = $this->rx($r, $d['replacement_id'], true);
        $old = DB::table('pharmacy_prescription_replacements')->where('original_id', $original->id)->first();
        if ($old) {
            abort_unless($old->request_id === $d['request_id'] && (bool) $old->discontinued_original === $stopOriginal && (int) $old->replacement_id === $replacement->id && $old->reason === $d['reason'] && $old->reference === $d['reference'] && (int) $old->created_by === (int) $r->user()->id, 409, 'This prescription already has a retained replacement link.');
            return $this->show($r, $id);
        }
        abort_unless($original->discontinued_at || $stopOriginal, 422, 'Discontinue the original first or explicitly request discontinuation with replacement.');
        abort_if($original->discontinued_at && $stopOriginal, 409, 'The original was already discontinued. Refresh and link the replacement without changing the retained discontinuation.');
        if ($stopOriginal) {
            abort_if($replacement->expires_on && $replacement->expires_on < now()->toDateString(), 422, 'The replacement prescription is expired.');
        }
        abort_if($replacement->discontinued_at, 422, 'The replacement prescription is discontinued.');
        abort_unless($replacement->id > $original->id, 422, 'Select a separately received prescription newer than the original record.');
        abort_unless((int) $replacement->episode_id === (int) $original->episode_id && (int) $replacement->location_id === (int) $original->location_id, 422, 'The replacement must belong to the same patient, accident case and pharmacy location.');
        abort_if(DB::table('pharmacy_prescription_replacements')->where('replacement_id', $replacement->id)->exists(), 409, 'This replacement is already linked to another prescription.');
        if ($stopOriginal) {
            $this->recordDiscontinuation($r, $original, $d);
        }
        DB::table('pharmacy_prescription_replacements')->insert([
            'original_id' => $original->id, 'replacement_id' => $replacement->id, 'discontinued_original' => $stopOriginal, 'created_by' => $r->user()->id,
            'request_id' => $d['request_id'], 'reason' => $d['reason'], 'reference' => $d['reference'], 'created_at' => now(),
        ]);
        $details = ['original_id' => $original->id, 'replacement_id' => $replacement->id, 'reason' => $d['reason'], 'reference' => $d['reference']];
        $this->event($r, $original, 'replacement_linked', $details);
        $this->event($r, $replacement, 'original_prescription_linked', $details);
        return $this->show($r, $id);
    }

    public function sources(Request $r, $id)
    {
        $this->allow($r, ['pharmacist', 'pharmacy_technician']);
        $rx = $this->rx($r, $id);

        return response()->json(['data' => DB::table('pharmacy_source_documents')->where('prescription_id', $rx->id)
            ->orderByDesc('id')->get(['id', 'original_name', 'mime_type', 'size', 'sha256', 'reference', 'created_by', 'created_at'])]);
    }

    public function addSource(Request $r, $id)
    {
        $this->allow($r, ['pharmacist', 'pharmacy_technician']);
        $rx = $this->rx($r, $id, true);
        $v = $r->validate(['request_id' => 'required|uuid', 'reference' => 'required|string|max:500',
            'file' => 'required|file|mimetypes:application/pdf,image/jpeg,image/png|max:10240']);
        $file = $r->file('file');
        $sha = hash_file('sha256', $file->getRealPath());
        $name = mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255);
        $existing = DB::table('pharmacy_source_documents')->where('prescription_id', $rx->id)->where('request_id', $v['request_id'])->first();
        if ($existing) {
            abort_unless($existing->sha256 === $sha && $existing->reference === $v['reference'] && $existing->original_name === $name && (int) $existing->created_by === (int) $r->user()->id, 409, 'This upload request was already used for different evidence.');

            return response()->json(['data' => ['id' => $existing->id]]);
        }
        $path = $file->store('pharmacy-sources/'.$rx->id, 'documents');
        // The transaction middleware removes newly written files if the database write rolls back.
        $r->attributes->set('pharmacy_new_private_files', [$path]);
        $sourceId = DB::table('pharmacy_source_documents')->insertGetId([
            'prescription_id' => $rx->id, 'created_by' => $r->user()->id, 'request_id' => $v['request_id'],
            'original_name' => $name, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
            'path' => $path, 'sha256' => $sha, 'reference' => $v['reference'], 'created_at' => now(),
        ]);
        $this->event($r, $rx, 'source_document_added', ['source_document_id' => $sourceId, 'sha256' => $sha, 'reference' => $v['reference']]);

        return response()->json(['data' => ['id' => $sourceId]], 201);
    }

    public function sourceFile(Request $r, $id, $sourceId)
    {
        $this->allow($r, ['pharmacist', 'pharmacy_technician']);
        $rx = $this->rx($r, $id);
        $source = DB::table('pharmacy_source_documents')->where('prescription_id', $rx->id)->where('id', $sourceId)->first();
        abort_unless($source, 404);
        $disk = Storage::disk('documents');
        abort_unless($disk->exists($source->path), 404, 'Source file is unavailable.');
        abort_unless(hash_equals($source->sha256, hash_file('sha256', $disk->path($source->path))), 409, 'Source file integrity check failed.');

        return $disk->download($source->path, $source->original_name, [
            'Content-Type' => $source->mime_type, 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "sandbox; default-src 'none'",
        ]);
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
        $v = $r->validate(['page' => 'nullable|integer|min:1', 'location_id' => 'nullable|integer', 'stage' => 'nullable|in:intake,pending,ready,collected,delivered,cancelled', 'status' => 'nullable|in:active,discontinued', 'replacement_for' => 'nullable|integer', 'attention' => 'nullable|in:discontinued_work,allowance_correction,handover_addenda,handover_review', 'search' => 'nullable|string|max:100']);
        $q = DB::table('pharmacy_prescriptions as rx')->join('pharmacy_episodes as ep', 'ep.id', '=', 'rx.episode_id')->leftJoin('users as patient', 'patient.id', '=', 'ep.patient_id')->leftJoin('pharmacy_patients as chart', 'chart.id', '=', 'ep.pharmacy_patient_id')->join('cases', 'cases.id', '=', 'ep.case_id')->where('rx.organization_id', $this->org($r));
        app(PharmacyAccess::class)->scope($q, $r->user(), 'rx.location_id');
        $latest = DB::table('pharmacy_fills')->selectRaw('prescription_id, MAX(id) AS latest_fill_id')->groupBy('prescription_id');
        $q->leftJoinSub($latest, 'latest_fill', fn ($join) => $join->on('latest_fill.prescription_id', '=', 'rx.id'))->leftJoin('pharmacy_fills as fill', 'fill.id', '=', 'latest_fill.latest_fill_id');
        $openFills = DB::table('pharmacy_fills')->selectRaw('prescription_id, COUNT(*) AS open_fill_count')->whereIn('fulfillment_status', ['pending', 'ready'])->groupBy('prescription_id');
        $reservedBatches = DB::table('pharmacy_batch_worksheets as b')->join('pharmacy_ingredient_allocations as a', 'a.batch_id', '=', 'b.id')
            ->where('b.organization_id', $this->org($r))->where('a.status', 'reserved')->selectRaw('b.prescription_id, b.location_id, COUNT(DISTINCT b.id) AS reserved_batch_count')->groupBy('b.prescription_id', 'b.location_id');
        $q->leftJoinSub($openFills, 'open_fills', fn ($join) => $join->on('open_fills.prescription_id', '=', 'rx.id'))
            ->leftJoinSub($reservedBatches, 'reserved_batches', fn ($join) => $join->on('reserved_batches.prescription_id', '=', 'rx.id')->on('reserved_batches.location_id', '=', 'rx.location_id'));
        $pendingCorrections = DB::table('pharmacy_allowance_corrections as ac')->join('pharmacy_allowance_closures as cl', 'cl.id', '=', 'ac.closure_id')
            ->where('ac.status', 'pending')->selectRaw('cl.prescription_id, COUNT(*) AS pending_correction_count')->groupBy('cl.prescription_id');
        $q->leftJoinSub($pendingCorrections, 'pending_corrections', fn ($join) => $join->on('pending_corrections.prescription_id', '=', 'rx.id'));
        $handoverReader = in_array($r->user()->role, ['pharmacist', 'pharmacy_technician'], true);
        if (in_array($v['attention'] ?? null, ['handover_addenda', 'handover_review'], true)) {
            abort_unless($handoverReader, 403);
            abort_if(($v['attention'] ?? null) === 'handover_review' && $r->user()->role !== 'pharmacist', 403);
        }
        $handover = DB::table('pharmacy_handover_addenda as ha')->join('pharmacy_fills as hf', 'hf.id', '=', 'ha.fill_id')
            ->where('ha.status', 'pending')->whereIn('hf.fulfillment_status', ['collected', 'delivered'])
            ->selectRaw('hf.prescription_id, COUNT(*) AS pending_handover_count, MIN(hf.id) AS pending_handover_fill_id')
            ->selectRaw('COUNT(CASE WHEN ha.created_by <> ? AND ? = 1 THEN 1 END) AS reviewable_handover_count, MIN(CASE WHEN ha.created_by <> ? AND ? = 1 THEN hf.id ELSE NULL END) AS reviewable_handover_fill_id', [$r->user()->id, (int) ($r->user()->role === 'pharmacist'), $r->user()->id, (int) ($r->user()->role === 'pharmacist')])
            ->groupBy('hf.prescription_id');
        // The outer prescription query enforces organization and current site access.
        // Restrict disclosure to roles allowed to open the documentary history.
        if (! $handoverReader) { $handover->whereRaw('1 = 0'); }
        $q->leftJoinSub($handover, 'handover_work', fn ($join) => $join->on('handover_work.prescription_id', '=', 'rx.id'));
        if (($v['attention'] ?? null) === 'handover_addenda') { $q->where('handover_work.pending_handover_count', '>', 0); }
        if (($v['attention'] ?? null) === 'handover_review') { $q->where('handover_work.reviewable_handover_count', '>', 0); }
        if (($v['attention'] ?? null) === 'allowance_correction') {
            $q->where('pending_corrections.pending_correction_count', '>', 0);
        }
        if (($v['attention'] ?? null) === 'discontinued_work') {
            $q->whereNotNull('rx.discontinued_at')->where(fn ($q) => $q->where('open_fills.open_fill_count', '>', 0)->orWhere('reserved_batches.reserved_batch_count', '>', 0));
        }
        if (! empty($v['stage'])) {
            if ($v['stage'] === 'intake') {
                $q->whereNull('fill.id');
            } else {
                $q->where('fill.fulfillment_status', $v['stage']);
            }
        }
        if (! empty($v['replacement_for'])) {
            $this->allow($r, ['pharmacist']);
            $original = $this->rx($r, $v['replacement_for']);
            if (! $original->discontinued_at) {
                $q->where(fn ($q) => $q->whereNull('rx.expires_on')->orWhere('rx.expires_on', '>=', now()->toDateString()));
            }
            $q->where('rx.episode_id', $original->episode_id)->where('rx.location_id', $original->location_id)->where('rx.id', '>', $original->id)->whereNull('rx.discontinued_at')
                ->whereNotIn('rx.id', DB::table('pharmacy_prescription_replacements')->select('replacement_id'));
        }
        if (! empty($v['status'])) {
            $v['status'] === 'discontinued' ? $q->whereNotNull('rx.discontinued_at') : $q->whereNull('rx.discontinued_at');
        }
        if (! empty($v['location_id'])) {
            $q->where('rx.location_id', $v['location_id']);
        }
        if (! empty($v['search'])) {
            $q->where(fn ($q) => $q->where('rx.rx_number', 'like', '%'.$v['search'].'%')->orWhere('rx.medication', 'like', '%'.$v['search'].'%'));
        }

        $rows = $q->select('rx.id', 'rx.discontinued_at', 'rx.rx_number', 'rx.medication', 'rx.strength', 'rx.location_id', 'rx.controlled', 'rx.compounded', 'rx.compound_type', 'ep.coverage_status', 'cases.case_number', 'fill.review_status', 'fill.claim_status')->selectRaw('COALESCE(handover_work.pending_handover_count, 0) AS pending_handover_count, handover_work.pending_handover_fill_id')->selectRaw('COALESCE(handover_work.reviewable_handover_count, 0) AS reviewable_handover_count, handover_work.reviewable_handover_fill_id')->selectRaw('COALESCE(pending_corrections.pending_correction_count, 0) AS pending_correction_count')->selectRaw('COALESCE(open_fills.open_fill_count, 0) AS open_fill_count, COALESCE(reserved_batches.reserved_batch_count, 0) AS reserved_batch_count')->selectRaw('COALESCE(chart.first_name, patient.first_name) AS first_name, COALESCE(chart.last_name, patient.last_name) AS last_name')->selectRaw("COALESCE(fill.fulfillment_status, 'intake') AS stage")->orderByDesc('rx.id')->paginate(20);
        foreach ($rows as $row) {
            // PDO aggregate types differ across SQLite, MySQL and MariaDB.
            foreach (['pending_handover_count', 'reviewable_handover_count'] as $field) { $row->$field = (int) $row->$field; }
            foreach (['pending_handover_fill_id', 'reviewable_handover_fill_id'] as $field) {
                $row->$field = $row->$field === null ? null : (int) $row->$field;
            }
        }
        return response()->json(['data' => $rows]);
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
        $rx->fills = DB::table('pharmacy_fills as f')->leftJoin('pharmacy_stock_lots as stock', fn ($join) => $join->on('stock.id', '=', 'f.stock_lot_id')->where('stock.organization_id', $rx->organization_id)->where('stock.location_id', $rx->location_id))->where('f.prescription_id', $rx->id)->select('f.*', 'stock.status as stock_status', 'stock.recall_reference as stock_recall_reference')->orderBy('f.fill_number')->get()->map(function ($f) use ($rx) {
            $stock = DB::table('pharmacy_stock_lots')->where('organization_id', $rx->organization_id)->where('location_id', $rx->location_id)->where('id', $f->stock_lot_id)->first();
            $f->stock_custody_hold = $stock ? app(PharmacyStock::class)->custodyHold($stock) : 'Stock receipt is unavailable. Investigate before use.';
            foreach (['review', 'fulfillment', 'claim'] as $k) {
                $f->$k = $this->json($f->$k);
            }
            $f->current_product = $stock ? app(PharmacyProduct::class)->current((int) $stock->id) : null;
            $f->reviewed_product = $stock && ! empty($f->review['product_id'])
                ? DB::table('pharmacy_stock_products')->where('stock_lot_id', $stock->id)->where('id', $f->review['product_id'])->first(PharmacyProduct::FIELDS) : null;
            $f->current_label = app(PharmacyLabel::class)->summary($rx, $f);
            if ($f->invoice_id) {
                $inv = Invoice::withSum('payments as total_paid', 'amount')->find($f->invoice_id);
                $f->invoice = $inv?->only(['id', 'invoice_number', 'amount', 'status', 'total_paid']);
            }

            return $f;
        });
        $rx->quantity_balance = app(PharmacyQuantity::class)->balance($rx);
        $rx->reserved_batches = DB::table('pharmacy_batch_worksheets as b')->join('pharmacy_ingredient_allocations as a', 'a.batch_id', '=', 'b.id')
            ->where('b.organization_id', $this->org($r))->where('b.location_id', $rx->location_id)->where('b.prescription_id', $rx->id)->where('a.status', 'reserved')
            ->select('b.id', 'b.batch_number')->distinct()->orderBy('b.id')->get();
        foreach (['replacement' => ['original_id', 'replacement_id'], 'replaces' => ['replacement_id', 'original_id']] as $key => [$from, $to]) {
            $rx->$key = DB::table('pharmacy_prescription_replacements as link')->join('pharmacy_prescriptions as related', 'related.id', '=', 'link.'.$to)
                ->where('link.'.$from, $rx->id)->where('related.organization_id', $this->org($r))->where('related.location_id', $rx->location_id)
                ->first(['related.id', 'related.rx_number', 'related.medication', 'link.reason', 'link.reference', 'link.created_by', 'link.created_at']);
        }
        $intake = DB::table('pharmacy_events')->where('prescription_id', $rx->id)->where('action', 'prescription_received')->orderBy('id')->first();
        $rx->case_link = $intake ? ($this->json($intake->details)['case_link'] ?? null) : null;
        $rx->events = DB::table('pharmacy_events')->where('prescription_id', $rx->id)->orderByDesc('id')->limit(100)->get()->map(function ($e) {
            $e->details = $this->json($e->details);

            return $e;
        });

        return response()->json(['data' => $rx]);
    }

    public function store(Request $r)
    {
        $this->allow($r, ['pharmacist', 'pharmacy_technician']);
        $d = $r->validate(['request_id' => 'required|uuid', 'case_id' => 'required|integer', 'patient_id' => 'nullable|integer|required_without:pharmacy_patient_id|prohibits:pharmacy_patient_id', 'pharmacy_patient_id' => 'nullable|integer|required_without:patient_id|prohibits:patient_id', 'case_link_reference' => 'required_with:pharmacy_patient_id|prohibited_if:pharmacy_patient_id,null|string|max:2000', 'case_link_confirmed' => 'required_with:pharmacy_patient_id|prohibited_if:pharmacy_patient_id,null|in:1,true,on,yes', 'patient_version' => 'required_with:pharmacy_patient_id|prohibited_if:pharmacy_patient_id,null|integer|min:1', 'location_id' => 'required|integer', 'quantity_unit' => 'required|in:tablet,capsule,mL,g,each', 'compounded' => 'required|boolean', 'compound_type' => 'nullable|required_if:compounded,true|in:sterile,nonsterile|prohibited_if:compounded,false', 'rx_number' => 'required|string|max:100', 'medication' => 'required|string|max:255', 'strength' => 'required|string|max:100', 'dosage_form' => 'required|string|max:100', 'directions' => 'required|string|max:2000', 'quantity' => 'required|numeric|min:0.001|max:999999.999|decimal:0,3', 'refills_authorized' => 'required|integer|min:0|max:99', 'written_on' => 'required|date_format:Y-m-d|before_or_equal:today', 'expires_on' => 'required|date_format:Y-m-d|after_or_equal:written_on', 'prescriber_name' => 'required|string|max:255', 'prescriber_identifier' => 'required|string|max:100', 'source_reference' => 'required|string|max:255', 'controlled' => 'required|boolean', 'controlled_schedule' => 'nullable|required_if:controlled,true|prohibited_if:controlled,false|in:unknown,II,III,IV,V', 'controlled_source_format' => 'nullable|required_if:controlled,true|prohibited_if:controlled,false|in:unknown,paper,electronic,fax,oral', 'controlled_classification_reference' => 'nullable|required_if:controlled,true|prohibited_if:controlled,false|string|max:2000']);
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
            $caseLink = null;
            if (! empty($d['pharmacy_patient_id'])) {
                abort_unless(DB::table('pharmacy_patients as p')->join('pharmacy_patient_locations as pl', 'pl.patient_id', '=', 'p.id')
                    ->where('p.organization_id', $org)->where('p.id', $d['pharmacy_patient_id'])->where('pl.location_id', $d['location_id'])->where('pl.active', true)->exists(), 404);
                $chart = DB::table('pharmacy_patients')->where('id', $d['pharmacy_patient_id'])->lockForUpdate()->first();
                abort_unless((int) $chart->version === (int) $d['patient_version'], 409, 'Patient chart changed. Refresh and confirm the case linkage again.');
                $caseLink = ['reference' => $d['case_link_reference'], 'confirmed' => true, 'patient_version' => $chart->version,
                    'pharmacy_patient_id' => $chart->id, 'record_number' => $chart->record_number, 'first_name' => $chart->first_name,
                    'last_name' => $chart->last_name, 'date_of_birth' => $chart->date_of_birth, 'case_id' => $case->id,
                    'case_number' => $case->case_number, 'accident_date' => $case->accident_date?->toDateString()];
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
            unset($data['case_id'],$data['patient_id'],$data['pharmacy_patient_id'],$data['case_link_reference'],$data['case_link_confirmed'],$data['patient_version']);
            $id = DB::table('pharmacy_prescriptions')->insertGetId($data + ['organization_id' => $org, 'episode_id' => $eid, 'request_hash' => $this->hash($d), 'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->event($r, (object) ['id' => $id], 'prescription_received', ['source_reference' => $d['source_reference'], 'case_link' => $caseLink, 'controlled_classification' => $d['controlled'] ? ['schedule' => $d['controlled_schedule'], 'source_format' => $d['controlled_source_format'], 'reference' => $d['controlled_classification_reference']] : null]);

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

    public function closeAllowance(Request $r, $id)
    {
        $this->allow($r, ['pharmacist']);
        $d = $r->validate([
            'request_id' => 'required|uuid', 'ledger_token' => 'required|string|regex:/^[a-f0-9]{64}$/D',
            'authorization_number' => 'required|integer|min:1|max:100',
            'basis' => 'required|in:patient_request,prescriber_instruction',
            'occurred_on' => 'required|date_format:Y-m-d|before_or_equal:today',
            'reason' => 'required|string|max:2000', 'evidence' => 'required|string|max:2000',
            'confirmed' => 'required|accepted',
        ]);
        DB::transaction(function () use ($r, $id, $d) {
            $rx = $this->rx($r, $id, true);
            $prior = DB::table('pharmacy_allowance_closures')->where('prescription_id', $rx->id)->where('request_id', $d['request_id'])->first();
            if ($prior) {
                abort_unless((int) $prior->actor_id === (int) $r->user()->id && hash_equals($prior->request_hash, $this->hash($d)), 409, 'This request already has different retained evidence.');
                return;
            }
            abort_if($rx->controlled || $rx->compounded, 422, 'This closure workflow does not apply to controlled or compounded prescriptions.');
            abort_if($rx->discontinued_at || $rx->expires_on < now()->toDateString(), 422, 'This prescription is no longer active for further supply.');
            $balance = app(PharmacyQuantity::class)->balance($rx);
            abort_if($balance['pending_correction_id'], 422, 'Resolve the pending allowance correction before changing prescription quantities.');
            abort_unless(hash_equals($balance['ledger_token'], $d['ledger_token']), 409, 'The prescription quantity record changed. Refresh and review it before closing a remainder.');
            $fills = DB::table('pharmacy_fills')->where('prescription_id', $rx->id);
            abort_if((clone $fills)->whereNotIn('fulfillment_status', ['collected', 'delivered', 'cancelled'])->exists(), 422, 'Resolve the open fill before closing a remainder.');
            $group = collect($balance['allowances'])->firstWhere('number', (int) $d['authorization_number']);
            abort_unless($group && (int) $d['authorization_number'] === $balance['next_authorization_number'] && ! $group['closure']
                && PharmacyStock::milli($group['handed_over']) > 0 && PharmacyStock::milli($group['remaining']) > 0, 422, 'Only the current partially supplied allowance has a remainder that can be closed.');
            $latest = $rx->written_on;
            foreach ((clone $fills)->where('authorization_number', $d['authorization_number'])->whereIn('fulfillment_status', ['collected', 'delivered'])->get() as $fill) {
                $latest = max($latest, $this->json($fill->fulfillment)['occurred_on'] ?? substr($fill->updated_at, 0, 10));
            }
            abort_if($d['occurred_on'] < $latest, 422, 'Closure cannot predate the most recent handover for this allowance.');
            $record = $d;
            unset($record['confirmed']);
            $closureId = DB::table('pharmacy_allowance_closures')->insertGetId($record + [
                'prescription_id' => $rx->id, 'quantity' => $group['remaining'], 'actor_id' => $r->user()->id,
                'request_hash' => $this->hash($d), 'created_at' => now(),
            ]);
            $this->event($r, $rx, 'allowance_remainder_closed', ['closure_id' => $closureId, 'authorization_number' => $d['authorization_number'],
                'quantity' => $group['remaining'], 'basis' => $d['basis'], 'occurred_on' => $d['occurred_on'], 'reason' => $d['reason'], 'evidence' => $d['evidence']]);
        });
        return $this->show($r, $id);
    }

    public function requestAllowanceCorrection(Request $r, $id, $closureId)
    {
        $this->allow($r, ['pharmacist']);
        $rx = $this->rx($r, $id, true);
        $closure = DB::table('pharmacy_allowance_closures')->where('prescription_id', $rx->id)->where('id', $closureId)->first();
        abort_unless($closure, 404);
        $d = $r->validate(['request_id' => 'required|uuid', 'ledger_token' => 'required|string|regex:/^[a-f0-9]{64}$/D', 'reason' => 'required|string|max:2000', 'evidence' => 'required|string|max:2000', 'confirmed' => 'required|accepted']);
        $old = DB::table('pharmacy_allowance_corrections')->where('closure_id', $closure->id)->where('request_id', $d['request_id'])->first();
        if ($old) {
            abort_unless((int) $old->created_by === (int) $r->user()->id && hash_equals($old->request_hash, $this->hash($d)), 409, 'This request already has different retained correction evidence.');
            return $this->show($r, $id);
        }
        $balance = app(PharmacyQuantity::class)->balance($rx);
        abort_unless(hash_equals($balance['ledger_token'], $d['ledger_token']), 409, 'The quantity history changed. Refresh before requesting a correction.');
        abort_if($balance['pending_correction_id'], 409, 'Resolve the pending correction first.');
        abort_unless($balance['correctable_closure_id'] === (int) $closure->id, 422, 'This closure cannot be reopened: the prescription must be active and unrestricted, with no open fill or later supply.');
        $correctionId = DB::table('pharmacy_allowance_corrections')->insertGetId([
            'closure_id' => $closure->id, 'created_by' => $r->user()->id, 'request_id' => $d['request_id'], 'request_hash' => $this->hash($d),
            'ledger_token' => '', 'reason' => $d['reason'], 'evidence' => $d['evidence'], 'created_at' => now(),
        ]);
        $reviewToken = app(PharmacyQuantity::class)->balance($rx)['ledger_token'];
        DB::table('pharmacy_allowance_corrections')->where('id', $correctionId)->update(['ledger_token' => $reviewToken]);
        $this->event($r, $rx, 'allowance_correction_requested', ['correction_id' => $correctionId, 'closure_id' => $closure->id, 'quantity' => $closure->quantity, 'reason' => $d['reason'], 'evidence' => $d['evidence']]);
        return $this->show($r, $id);
    }

    public function reviewAllowanceCorrection(Request $r, $id, $correctionId)
    {
        $this->allow($r, ['pharmacist']);
        $rx = $this->rx($r, $id, true);
        $correction = DB::table('pharmacy_allowance_corrections as c')->join('pharmacy_allowance_closures as cl', 'cl.id', '=', 'c.closure_id')
            ->where('cl.prescription_id', $rx->id)->where('c.id', $correctionId)->select('c.*')->first();
        abort_unless($correction, 404);
        $d = $r->validate(['request_id' => 'required|uuid', 'decision' => 'required|in:apply,reject', 'ledger_token' => 'required|string|regex:/^[a-f0-9]{64}$/D', 'evidence' => 'required|string|max:2000', 'confirmed' => 'required|accepted']);
        if ($correction->status !== 'pending') {
            abort_unless((int) $correction->reviewed_by === (int) $r->user()->id && $correction->review_request_id === $d['request_id'] && hash_equals($correction->review_hash, $this->hash($d)), 409, 'This correction already has a retained review.');
            return $this->show($r, $id);
        }
        abort_if((int) $correction->created_by === (int) $r->user()->id, 422, 'A different assigned pharmacist must review the correction.');
        if ($d['decision'] === 'apply') {
            $balance = app(PharmacyQuantity::class)->balance($rx);
            abort_unless(hash_equals($balance['ledger_token'], $d['ledger_token']) && hash_equals($correction->ledger_token, $d['ledger_token']), 409, 'The quantity history changed. Reject this stale request and reassess.');
            abort_unless($balance['correctable_closure_id'] === (int) $correction->closure_id, 422, 'This closure is no longer eligible to reopen. Reject the request and resolve the prescription history.');
        }
        DB::table('pharmacy_allowance_corrections')->where('id', $correction->id)->update([
            'status' => $d['decision'] === 'apply' ? 'applied' : 'rejected', 'reviewed_by' => $r->user()->id, 'review_request_id' => $d['request_id'],
            'review_hash' => $this->hash($d), 'review_evidence' => $d['evidence'], 'reviewed_at' => now(),
        ]);
        $this->event($r, $rx, $d['decision'] === 'apply' ? 'allowance_correction_applied' : 'allowance_correction_rejected', ['correction_id' => $correction->id, 'closure_id' => $correction->closure_id, 'evidence' => $d['evidence']]);
        return $this->show($r, $id);
    }

    public function createFill(Request $r, $id)
    {
        $this->allow($r, ['pharmacist', 'pharmacy_technician']);
        $d = $r->validate(['request_id' => 'required|uuid', 'stock_lot_id' => 'required|integer', 'partial_reason' => 'nullable|string|max:2000', 'quantity' => 'required|numeric|min:0.001|max:999999.999|decimal:0,3', 'days_supply' => 'required|integer|min:1|max:365', 'ndc' => ['required', 'string', 'regex:/^(\d{10,11}|\d{4}-\d{4}-\d{2}|\d{5}-\d{3}-\d{2}|\d{5}-\d{4}-\d{1,2})$/D']]);
        DB::transaction(function () use ($r, $id, $d) {
            $rx = $this->rx($r, $id, true);
            $q = DB::table('pharmacy_fills')->where('prescription_id', $rx->id);
            $old = (clone $q)->where('request_id', $d['request_id'])->first();
            if ($old) {
                abort_unless(hash_equals($old->request_hash, $this->hash($d)), 409);

                return;
            }
            abort_if($rx->discontinued_at, 422, 'This prescription is discontinued. No new fill can be created.');
            if ($rx->expires_on < now()->toDateString()) {
                $this->fail('Prescription has expired. Obtain an updated prescription.');
            }
            if (PharmacyStock::milli($d['quantity']) > PharmacyStock::milli($rx->quantity)) {
                $this->fail('Quantity exceeds the prescribed amount.');
            }
            if ((clone $q)->whereNotIn('fulfillment_status', ['collected', 'delivered', 'cancelled'])->exists()) {
                $this->fail('Resolve the open fill before creating another.');
            }
            $balance = app(PharmacyQuantity::class)->balance($rx);
            abort_if($balance['pending_correction_id'], 422, 'Resolve the pending allowance correction before changing prescription quantities.');
            if (! $balance['next_authorization_number']) {
                $this->fail('No authorized fills remain.');
            }
            if (PharmacyStock::milli($d['quantity']) > PharmacyStock::milli($balance['available_quantity'])) {
                $this->fail('Quantity exceeds the remaining amount for this original or refill allowance.');
            }
            if ($balance['mode'] === 'quantity' && PharmacyStock::milli($d['quantity']) < PharmacyStock::milli($balance['available_quantity']) && empty(trim($d['partial_reason'] ?? ''))) {
                $this->fail('Record the reason for supplying less than the remaining authorized quantity.');
            }
            $authorization = $balance['mode'] === 'quantity' ? $balance['next_authorization_number'] : null;
            $lot = app(PharmacyStock::class)->reserve($r->user(), $rx, $d);
            $number = ((clone $q)->max('fill_number') ?? -1) + 1;
            $fid = DB::table('pharmacy_fills')->insertGetId($d + ['prescription_revision' => $rx->amendment_revision, 'authorization_number' => $authorization, 'prescription_id' => $rx->id, 'fill_number' => $number, 'request_hash' => $this->hash($d), 'created_at' => now(), 'updated_at' => now()]);
            app(PharmacyStock::class)->event($r->user(), $lot, 'reserved', $d['quantity'], ['request_id' => $d['request_id']], $fid);
            $this->event($r, $rx, 'fill_created', ['fill_id' => $fid, 'fill_number' => $number, 'authorization_number' => $authorization, 'quantity' => $d['quantity'], 'partial_reason' => $d['partial_reason'] ?? null]);
        });

        return $this->show($r, $id);
    }

    public function fillAction(Request $r, $id, $fillId)
    {
        $d = $r->validate(['version' => 'required|integer|min:1', 'action' => 'required|in:approve,hold,cancel,ready,collected,delivered,prepare_claim,record_submission,record_denial,record_maps', 'note' => 'required|string|max:2000', 'product_id' => 'nullable|integer|min:1', 'label_id' => 'nullable|integer|min:1', 'checks' => 'sometimes|array:identity,prescriber,therapy,product,label', 'checks.identity' => 'sometimes|accepted', 'checks.prescriber' => 'sometimes|accepted', 'checks.therapy' => 'sometimes|accepted', 'checks.product' => 'sometimes|accepted', 'checks.label' => 'sometimes|accepted', 'occurred_on' => 'nullable|date_format:Y-m-d|before_or_equal:today', 'reference' => 'nullable|string|max:255', 'amount' => 'nullable|numeric|min:0.01|max:99999999.99|decimal:0,2', 'counseling' => 'nullable|in:provided,declined,documented_remote', 'handover' => 'nullable|array', 'scan' => 'nullable|array', 'maps_status' => 'nullable|in:not_applicable,pending,submitted', 'maps_reference' => 'nullable|string|max:255', 'denial_type' => 'nullable|in:coverage,coding,medical_necessity,cost,no_response', 'determination_on' => 'nullable|date_format:Y-m-d|before_or_equal:today']);
        DB::transaction(function () use ($r, $id, $fillId, $d) {
            $rx = $this->rx($r, $id, true);
            $f = DB::table('pharmacy_fills')->where('prescription_id', $rx->id)->where('id', $fillId)->lockForUpdate()->first();
            abort_unless($f, 404);
            $this->version($f, $d);
            $a = $d['action'];
            abort_if($rx->discontinued_at && in_array($a, ['approve', 'ready', 'collected', 'delivered'], true), 422, 'This prescription is discontinued. Cancel the open fill to release its stock reservation.');
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
                        $product = app(PharmacyProduct::class)->current((int) $f->stock_lot_id);
                        if (! $product) {
                            $this->fail('A pharmacist must verify the stock receipt product details before approving this fill.');
                        }
                        abort_unless((int) ($d['product_id'] ?? 0) === (int) $product->id, 409, 'Product verification changed or was not selected. Refresh and review the displayed product details.');
                        foreach (['identity', 'prescriber', 'therapy', 'product'] as $check) {
                            if (empty($d['checks'][$check])) {
                                $this->fail('Complete each pharmacist review check.');
                            }
                        }
                    }
                    $update = ['review_status' => $a === 'approve' ? 'approved' : 'held', 'review' => json_encode(['actor_id' => $r->user()->id, 'at' => now()->toIso8601String(), 'note' => $d['note'], 'checks' => $d['checks'] ?? [], 'product_id' => $product->id ?? null, 'patient_version' => $chart->version ?? null, 'source_last_id' => DB::table('pharmacy_source_documents')->where('prescription_id', $rx->id)->max('id') ?? 0])];
                } elseif ($a === 'cancel') {
                    $update = ['fulfillment_status' => 'cancelled'];
                } else {
                    if ($rx->controlled || $rx->compounded) {
                        $this->fail('Controlled and compounded dispensing is not enabled.');
                    }
                    $product = app(PharmacyProduct::class)->current((int) $f->stock_lot_id);
                    if (! $product || (int) ($this->json($f->review)['product_id'] ?? 0) !== (int) $product->id) {
                        $this->fail('Verified product details need a fresh pharmacist review. Review a pending fill again; cancel a prepared fill and start again.');
                    }
                    if ($ep->pharmacy_patient_id && (int) ($this->json($f->review)['patient_version'] ?? 0) !== (int) DB::table('pharmacy_patients')->where('id', $ep->pharmacy_patient_id)->value('version')) {
                        $this->fail('The patient clinical record changed. A new pharmacist review is required; cancel a prepared fill and start again.');
                    }
                    if ((int) ($this->json($f->review)['source_last_id'] ?? 0) !== (int) DB::table('pharmacy_source_documents')->where('prescription_id', $rx->id)->max('id')) {
                        $this->fail('New prescription evidence needs pharmacist review. Review a pending fill again; cancel a prepared fill and start again.');
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
            if (in_array($a, ['ready', 'collected', 'delivered'], true)) {
                $priorFulfillment = $this->json($f->fulfillment);
                $labelId = $a === 'ready' ? (int) ($d['label_id'] ?? 0) : (int) ($priorFulfillment['prepared_label_id'] ?? 0);
                $label = app(PharmacyLabel::class)->requireCurrent($rx, $f, $labelId);
                abort_unless(DB::table('pharmacy_label_prints')->where('label_id', $labelId)->where('purpose', 'dispensing_label')->exists(), 422, 'Record label print evidence before the final product and label check.');
                $scan = app(\App\Services\PharmacyBarcode::class)->verify($d['scan'] ?? [], $rx, $f, $label, $a, (int) $r->user()->id);
                $labelData = $this->json($label->snapshot);
                if ($a === 'ready') {
                    $update['fulfillment'] = json_encode($priorFulfillment + ['prepared_scan' => $scan, 'prepared_label_id' => $labelId, 'prepared_label_sha256' => $label->sha256, 'prepared_at' => now()->toIso8601String()], JSON_THROW_ON_ERROR);
                } else {
                    abort_if($d['occurred_on'] < $labelData['decisions']['dispensed_on'] || $d['occurred_on'] > $labelData['decisions']['use_by'], 422, 'Handover date does not fit the retained label dates.');
                    $handover = app(\App\Services\PharmacyHandover::class)->evidence($d['handover'] ?? [], $a, $d['occurred_on'], $priorFulfillment, (int) $r->user()->id);
                    $update['fulfillment'] = json_encode($priorFulfillment + array_replace($d, ['handover' => $handover, 'scan' => $scan]) + ['actor_id' => $r->user()->id], JSON_THROW_ON_ERROR);
                }
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
        $pharmacyStaff = in_array($r->user()->role, ['pharmacist', 'pharmacy_technician'], true);
        $sourceLastId = DB::table('pharmacy_source_documents')->where('prescription_id', $rx->id)->max('id') ?? 0;
        $chart = null;
        if ($rx->discontinued_at) {
            $checks[] = ['code' => 'prescription_discontinued', 'message' => 'This prescription is discontinued. Resolve open reservations without recording a new supply.', 'source' => 'prescription:'.$rx->id];
        } elseif ($rx->expires_on < now()->toDateString()) {
            $checks[] = ['code' => 'prescription_expired', 'message' => 'This prescription has expired. Review separately received authority before any new supply.', 'source' => 'prescription:'.$rx->id];
        }
        if ($pharmacyStaff) {
            if (! $sourceLastId) {
                $checks[] = ['code' => 'source_missing', 'message' => 'No original prescription file has been retained. Retain and review the received source evidence.', 'source' => 'prescription:'.$rx->id];
            }
            if ($ep->pharmacy_patient_id) {
                $chart = DB::table('pharmacy_patients')->where('organization_id', $rx->organization_id)->where('id', $ep->pharmacy_patient_id)->first();
                $clinical = $chart ? $this->json($chart->clinical) : [];
                if (empty($clinical['reviewed_on'])) {
                    $checks[] = ['code' => 'clinical_review_missing', 'message' => 'Patient clinical history has no recorded review date. A pharmacist must review and document it.', 'source' => 'patient:'.$ep->pharmacy_patient_id];
                }
                foreach (['allergies' => 'Allergy history', 'medications' => 'Current medication history'] as $field => $label) {
                    if (($clinical[$field.'_status'] ?? 'unknown') === 'unknown') {
                        $checks[] = ['code' => $field.'_unknown', 'message' => $label.' is unresolved. Unknown does not mean none reported.', 'source' => 'patient:'.$ep->pharmacy_patient_id];
                    }
                }
                $intake = DB::table('pharmacy_events')->where('prescription_id', $rx->id)->where('action', 'prescription_received')->orderBy('id')->first();
                if (! $intake || empty($this->json($intake->details)['case_link']['confirmed'])) {
                    $checks[] = ['code' => 'case_link_missing', 'message' => 'This historical intake has no dedicated patient-to-case identity evidence. Resolve the linkage with the responsible pharmacist; do not infer verification.', 'source' => 'prescription:'.$rx->id];
                }
            }
        }
        if ($rx->compounded && ! $rx->compound_type) {
            $checks[] = ['message' => 'This older compounded prescription has no verified sterile/nonsterile classification. Resolve it before production planning.', 'source' => 'prescription:'.$rx->id];
        }
        if ($rx->controlled && (! $rx->controlled_schedule || $rx->controlled_schedule === 'unknown' || ! $rx->controlled_source_format || $rx->controlled_source_format === 'unknown')) {
            $checks[] = ['code' => 'controlled_classification_unresolved', 'message' => 'The controlled schedule or received format is unresolved. A pharmacist must resolve the classification from original evidence; no dispensing authority is inferred.', 'source' => 'prescription:'.$rx->id];
        }
        if ($rx->controlled || $rx->compounded) {
            $checks[] = ['message' => 'Dedicated controlled-substance and compounding controls are required before dispensing can be enabled.', 'source' => 'prescription:'.$rx->id];
        }
        if ($ep->coverage_status !== 'verified') {
            $checks[] = ['message' => 'Coverage needs resolution.', 'source' => 'episode:'.$ep->id];
        }
        foreach (DB::table('pharmacy_fills')->where('prescription_id', $rx->id)->get() as $f) {
            if ($f->fulfillment_status === 'cancelled') {
                continue;
            }
            if ($pharmacyStaff && $f->review_status === 'approved' && in_array($f->fulfillment_status, ['pending', 'ready'], true)) {
                $review = $this->json($f->review);
                if (($ep->pharmacy_patient_id && (int) ($review['patient_version'] ?? 0) !== (int) ($chart->version ?? 0))
                    || (int) ($review['source_last_id'] ?? 0) !== (int) $sourceLastId) {
                    $checks[] = ['code' => 'review_stale', 'fill_id' => $f->id, 'message' => 'Fill '.$f->fill_number.' has new patient or source evidence since approval. Review a pending fill again; cancel a prepared fill and start again.', 'source' => 'fill:'.$f->id];
                }
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
