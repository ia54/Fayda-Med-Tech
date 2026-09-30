<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PharmacyExtractionLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PharmacyExtractionController extends Controller
{
    public function index(Request $r, int $id)
    {
        app(PharmacyExtractionLedger::class)->prescription($r->user(), $id);
        $r->validate(['page' => 'nullable|integer|min:1']);
        $rows = DB::table('pharmacy_extraction_attempts')->where('organization_id', $r->user()->organization_id)->where('prescription_id', $id)
            ->select('id', 'transcription_id', 'requested_by', 'model', 'provider_reference', 'status', 'failure_code', 'created_at', 'finished_at')->orderByDesc('id')->paginate(20);
        return response()->json(['data' => $rows, 'provider_connected' => false]);
    }

    public function show(Request $r, int $id)
    {
        $row = app(PharmacyExtractionLedger::class)->attempt($r->user(), $id);
        $result = array_intersect_key((array) $row, array_flip(['id', 'prescription_id', 'transcription_id', 'requested_by', 'model', 'provider_reference', 'schema_version', 'status', 'failure_code', 'created_at', 'finished_at', 'draft_sha256']));
        abort_if($row->draft && ! hash_equals($row->draft_sha256, hash('sha256', $row->draft)), 409, 'The retained draft failed its integrity check.');
        $result['draft'] = $row->draft ? json_decode($row->draft, true, 512, JSON_THROW_ON_ERROR) : null;
        $text = DB::table('pharmacy_source_transcriptions')->where('id', $row->transcription_id)->first();
        abort_unless($text && hash_equals($text->transcription_sha256, hash('sha256', $text->pages))
            && (! $result['draft'] || hash_equals($result['draft']['transcription_sha256'], $text->transcription_sha256)), 409, 'The retained transcription failed its integrity check.');
        $rx = app(PharmacyExtractionLedger::class)->prescription($r->user(), $row->prescription_id);
        $result['current_order_values'] = [];
        foreach (['medication', 'strength', 'dosage_form', 'directions', 'quantity', 'quantity_unit', 'prescriber_name', 'prescriber_identifier'] as $field) {
            $result['current_order_values'][$field] = $rx->$field;
        }
        $result['current_order_values'] += ['refills' => $rx->refills_authorized, 'written_date' => $rx->written_on, 'expiry_date' => $rx->expires_on];
        $result['source_document_id'] = $text->source_document_id;
        $result['source_transcription'] = ['pages' => json_decode($text->pages, true, 512, JSON_THROW_ON_ERROR), 'method' => $text->method, 'reference' => $text->reference, 'created_by' => $text->created_by];
        $review = DB::table('pharmacy_extraction_reviews')->where('attempt_id', $id)->first();
        $result['review'] = $review ? array_intersect_key((array) $review, array_flip(['id', 'reviewed_by', 'decision', 'evidence', 'created_at'])) + ['fields' => json_decode($review->fields, true, 512, JSON_THROW_ON_ERROR)] : null;
        return response()->json(['data' => $result, 'provider_connected' => false]);
    }

    public function review(Request $r, int $id)
    {
        app(PharmacyExtractionLedger::class)->review($r->user(), $id, $r->all());
        return $this->show($r, $id);
    }
}
