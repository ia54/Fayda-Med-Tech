<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use App\Services\PharmacyExtractionDraft;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/** Manual transcription provenance. No OCR, model execution or clinical record mutation. */
class PharmacyTranscriptionController extends Controller
{
    private function source(Request $r, int $id, int $sourceId): object
    {
        abort_unless(in_array($r->user()->role, ['pharmacist', 'pharmacy_technician'], true), 403);
        $q = DB::table('pharmacy_prescriptions')->where('organization_id', $r->user()->organization_id)->where('id', $id);
        app(PharmacyAccess::class)->scope($q, $r->user());
        abort_unless($q->exists(), 404);
        $source = DB::table('pharmacy_source_documents')->where('prescription_id', $id)->where('id', $sourceId)->first();
        abort_unless($source, 404);
        return $source;
    }

    private function serialize(object $row): array
    {
        $result = array_intersect_key((array) $row, array_flip(['id', 'source_document_id', 'created_by', 'source_sha256', 'transcription_sha256', 'reference', 'method', 'supersedes_id', 'created_at']));
        $result['pages'] = json_decode($row->pages, true, 512, JSON_THROW_ON_ERROR);
        $result['status'] = 'unverified_transcription';
        return $result;
    }

    public function index(Request $r, int $id, int $sourceId)
    {
        $this->source($r, $id, $sourceId);
        $r->validate(['page' => 'nullable|integer|min:1']);
        $rows = DB::table('pharmacy_source_transcriptions')->where('organization_id', $r->user()->organization_id)->where('source_document_id', $sourceId)->orderByDesc('id')->paginate(10);
        $rows->through(fn ($row) => $this->serialize($row));
        return response()->json(['data' => $rows]);
    }

    public function store(Request $r, int $id, int $sourceId)
    {
        $source = $this->source($r, $id, $sourceId);
        $d = $r->validate(['request_id' => 'required|uuid', 'source_sha256' => 'required|string|regex:/^[a-f0-9]{64}$/D',
            'previous_id' => 'present|nullable|integer|min:1', 'transcription_pages' => 'required|array|min:1|max:100', 'transcription_pages.*' => 'present|nullable|string',
            'reference' => 'required|string|max:2000', 'confirmed' => 'required|accepted']);
        // Laravel converts empty strings to null; preserve an explicitly blank/unreadable page as empty text.
        $d['transcription_pages'] = array_map(fn ($text) => $text ?? '', $d['transcription_pages']);
        $payload = $d; ksort($payload);
        $hash = hash('sha256', json_encode($payload + ['prescription_id' => $id, 'source_id' => $sourceId], JSON_THROW_ON_ERROR));
        $prior = DB::table('pharmacy_source_transcriptions')->where('organization_id', $r->user()->organization_id)->where('request_id', $d['request_id'])->first();
        if ($prior) {
            abort_unless((int) $prior->created_by === (int) $r->user()->id && hash_equals($prior->request_hash, $hash), 409, 'This request identifier already has different transcription evidence.');
            return response()->json(['data' => $this->serialize($prior)]);
        }
        $disk = Storage::disk('documents');
        abort_unless(hash_equals($source->sha256, $d['source_sha256']) && $disk->exists($source->path)
            && hash_equals($source->sha256, hash_file('sha256', $disk->path($source->path))), 409, 'The original file changed or failed its integrity check.');
        $latest = DB::table('pharmacy_source_transcriptions')->where('source_document_id', $sourceId)->max('id');
        abort_unless(($latest === null && $d['previous_id'] === null) || ($latest !== null && (int) $latest === (int) $d['previous_id']), 409, 'A newer transcription exists. Review it before recording a correction.');
        try {
            $empty = json_encode(['schema_version' => 1, 'fields' => array_fill_keys(PharmacyExtractionDraft::FIELDS, [])], JSON_THROW_ON_ERROR);
            $validated = app(PharmacyExtractionDraft::class)->validate($empty, $d['transcription_pages'], $source->sha256);
        } catch (InvalidArgumentException) {
            abort(422, 'Provide ordered UTF-8 pages with some readable text, at most 100 pages and 250,000 bytes.');
        }
        $transcription = DB::table('pharmacy_source_transcriptions')->insertGetId(['organization_id' => $r->user()->organization_id, 'source_document_id' => $sourceId,
            'created_by' => $r->user()->id, 'request_id' => $d['request_id'], 'request_hash' => $hash, 'source_sha256' => $source->sha256,
            'transcription_sha256' => $validated['transcription_sha256'], 'pages' => json_encode($d['transcription_pages'], JSON_THROW_ON_ERROR),
            'reference' => $d['reference'], 'method' => 'manual', 'supersedes_id' => $latest, 'created_at' => now()]);
        DB::table('pharmacy_events')->insert(['organization_id' => $r->user()->organization_id, 'prescription_id' => $id, 'actor_id' => $r->user()->id,
            'action' => 'source_transcription_retained', 'details' => json_encode(['transcription_id' => $transcription, 'source_document_id' => $sourceId, 'supersedes_id' => $latest], JSON_THROW_ON_ERROR), 'created_at' => now()]);
        return response()->json(['data' => $this->serialize(DB::table('pharmacy_source_transcriptions')->find($transcription))], 201);
    }
}
