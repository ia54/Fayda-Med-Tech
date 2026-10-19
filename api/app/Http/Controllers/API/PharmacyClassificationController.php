<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Documentary classification only. Never changes controlled status or dispensing authority. */
class PharmacyClassificationController extends Controller
{
    private const FIELDS = ['controlled_schedule', 'controlled_source_format', 'controlled_classification_reference'];

    private function rx(Request $r, $id): object
    {
        abort_unless(in_array($r->user()->role, ['pharmacist', 'pharmacy_technician'], true), 403);
        $q = DB::table('pharmacy_prescriptions')->where('organization_id', $r->user()->organization_id)->where('id', $id);
        app(PharmacyAccess::class)->scope($q, $r->user());
        if (! $r->isMethod('GET')) $q->lockForUpdate();
        $rx = $q->first();
        abort_unless($rx, 404);
        abort_unless($rx->controlled, 422, 'This workflow only corrects the documentary classification of controlled intake.');
        return $rx;
    }

    private function snapshot(object $rx): array
    {
        return array_intersect_key((array) $rx, array_flip(self::FIELDS));
    }

    private function revision(object $rx): int
    {
        return (int) (DB::table('pharmacy_classification_corrections')->where('prescription_id', $rx->id)->max('revision') ?? 0);
    }

    private function token(object $rx): string
    {
        return hash('sha256', json_encode([$rx->id, $this->snapshot($rx), $this->revision($rx),
            DB::table('pharmacy_source_documents')->where('prescription_id', $rx->id)->orderBy('id')->get(['id', 'sha256'])], JSON_THROW_ON_ERROR));
    }

    public function index(Request $r, $id)
    {
        $rx = $this->rx($r, $id);
        $r->validate(['page' => 'nullable|integer|min:1']);
        $rows = DB::table('pharmacy_classification_corrections')->where('prescription_id', $id)
            ->select('id', 'revision', 'source_document_id', 'source_sha256', 'before_snapshot', 'after_snapshot', 'reason', 'created_by', 'created_at')
            ->orderByDesc('revision')->paginate(10);
        $rows->getCollection()->transform(function ($row) {
            foreach (['before_snapshot', 'after_snapshot'] as $field) $row->$field = json_decode($row->$field, true, 512, JSON_THROW_ON_ERROR);
            return $row;
        });
        return response()->json(['data' => ['revision' => $this->revision($rx), 'source_token' => $this->token($rx), 'current' => $this->snapshot($rx), 'corrections' => $rows]]);
    }

    public function store(Request $r, $id)
    {
        abort_unless($r->user()->role === 'pharmacist', 403);
        $rx = $this->rx($r, $id);
        $d = $r->validate(['request_id' => 'required|uuid', 'source_token' => 'required|string|regex:/^[a-f0-9]{64}$/D',
            'values' => 'required|array:controlled_schedule,controlled_source_format,controlled_classification_reference',
            'values.controlled_schedule' => 'required|in:unknown,II,III,IV,V',
            'values.controlled_source_format' => 'required|in:unknown,paper,electronic,fax,oral',
            'values.controlled_classification_reference' => 'required|string|max:2000',
            'source_document_id' => 'required|integer|min:1', 'reason' => 'required|string|max:2000', 'confirmed' => 'required|accepted']);
        ksort($d['values']); ksort($d);
        $hash = hash('sha256', json_encode($d, JSON_THROW_ON_ERROR));
        $old = DB::table('pharmacy_classification_corrections')->where('prescription_id', $id)->where('request_id', $d['request_id'])->first();
        if ($old) {
            abort_unless((int) $old->created_by === (int) $r->user()->id && hash_equals($old->request_hash, $hash), 409, 'This request identifier belongs to another classification correction.');
            return $this->index($r, $id);
        }
        abort_unless(hash_equals($this->token($rx), $d['source_token']), 409, 'Classification or attached evidence changed. Refresh and compare the current record before saving.');
        $source = DB::table('pharmacy_source_documents')->where('prescription_id', $id)->where('id', $d['source_document_id'])->first();
        abort_unless($source, 404);
        $disk = Storage::disk('documents');
        abort_unless($disk->exists($source->path) && hash_equals($source->sha256, hash_file('sha256', $disk->path($source->path))), 409, 'Retained classification evidence is missing or failed its integrity check.');
        $before = $this->snapshot($rx);
        abort_unless($before != $d['values'], 422, 'No classification values changed.');
        $revision = $this->revision($rx) + 1;
        $correction = DB::table('pharmacy_classification_corrections')->insertGetId([
            'prescription_id' => $id, 'revision' => $revision, 'request_id' => $d['request_id'], 'request_hash' => $hash,
            'source_document_id' => $source->id, 'source_sha256' => $source->sha256,
            'before_snapshot' => json_encode($before, JSON_THROW_ON_ERROR), 'after_snapshot' => json_encode($d['values'], JSON_THROW_ON_ERROR),
            'reason' => $d['reason'], 'created_by' => $r->user()->id, 'created_at' => now(),
        ]);
        DB::table('pharmacy_prescriptions')->where('id', $id)->update($d['values'] + ['updated_at' => now()]);
        DB::table('pharmacy_events')->insert(['organization_id' => $rx->organization_id, 'prescription_id' => $id, 'actor_id' => $r->user()->id,
            'action' => 'controlled_classification_corrected', 'details' => json_encode(['correction_id' => $correction, 'revision' => $revision, 'source_document_id' => $source->id], JSON_THROW_ON_ERROR), 'created_at' => now()]);
        return $this->index($r, $id)->setStatusCode(201);
    }
}
