<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/** Retention only. No network client, queue dispatch, prescribing or dispensing mutation. */
class PharmacyExtractionLedger
{
    public function prescription(User $actor, int $id): object
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        abort_unless($actor->status === 'active' && in_array($actor->role, ['pharmacist', 'pharmacy_technician'], true), 403);
        $q = DB::table('pharmacy_prescriptions')->where('organization_id', $actor->organization_id)->where('id', $id);
        app(PharmacyAccess::class)->scope($q, $actor);
        $rx = $q->first();
        abort_unless($rx, 404);
        return $rx;
    }

    private function write(User $actor, callable $fn): mixed
    {
        return DB::transaction(function () use ($actor, $fn) {
            abort_unless(DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first(), 403);
            $fresh = User::find($actor->id);
            abort_unless($fresh && (int) $fresh->organization_id === (int) $actor->organization_id && $fresh->status === 'active', 403);
            return $fn($fresh);
        });
    }

    private function event(User $actor, int $rx, string $action, array $details): void
    {
        DB::table('pharmacy_events')->insert(['organization_id' => $actor->organization_id, 'prescription_id' => $rx, 'actor_id' => $actor->id,
            'action' => $action, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    private function context(User $actor, int $rxId, int $transcriptionId): array
    {
        $rx = $this->prescription($actor, $rxId);
        $text = DB::table('pharmacy_source_transcriptions')->where('organization_id', $actor->organization_id)->where('id', $transcriptionId)->first();
        abort_unless($text, 404);
        $source = DB::table('pharmacy_source_documents')->where('prescription_id', $rxId)->where('id', $text->source_document_id)->first();
        abort_unless($source, 404);
        $latest = DB::table('pharmacy_source_transcriptions')->where('source_document_id', $source->id)->max('id');
        abort_unless((int) $latest === $transcriptionId, 409, 'The source transcription has a newer retained version.');
        $disk = Storage::disk('documents');
        abort_unless(hash_equals($source->sha256, $text->source_sha256) && $disk->exists($source->path)
            && hash_equals($source->sha256, hash_file('sha256', $disk->path($source->path)))
            && hash_equals($text->transcription_sha256, hash('sha256', $text->pages)), 409, 'The original source or retained transcription failed its integrity check.');
        $episode = DB::table('pharmacy_episodes')->where('id', $rx->episode_id)->where('organization_id', $actor->organization_id)->first();
        abort_unless($episode, 404);
        $patient = $episode->pharmacy_patient_id
            ? DB::table('pharmacy_patients')->where('organization_id', $actor->organization_id)->where('id', $episode->pharmacy_patient_id)->first()
            : DB::table('users')->where('organization_id', $actor->organization_id)->where('id', $episode->patient_id)->first(['id', 'first_name', 'last_name', 'updated_at']);
        abort_unless($patient, 404);
        // These values are hashed locally, never placed in the model request or returned as patient data.
        $token = hash('sha256', json_encode([$rx, $episode, $patient, $source->id, $text->id, $text->source_sha256, $text->transcription_sha256], JSON_THROW_ON_ERROR));
        return ['token' => $token, 'pages' => json_decode($text->pages, true, 512, JSON_THROW_ON_ERROR), 'source_sha256' => $source->sha256];
    }

    /** Future approved server-side adapter entry point; no public submission of model results exists. */
    public function begin(User $actor, int $rxId, int $transcriptionId, array $input): object
    {
        return $this->write($actor, function ($actor) use ($rxId, $transcriptionId, $input) {
            $this->prescription($actor, $rxId);
            $d = Validator::make($input, ['request_id' => 'required|uuid', 'model' => 'required|string|regex:/^[a-zA-Z0-9._-]{1,100}$/D', 'provider_reference' => 'required|string|max:200'])->validate();
            ksort($d); $hash = hash('sha256', json_encode([$rxId, $transcriptionId, $d], JSON_THROW_ON_ERROR));
            $old = DB::table('pharmacy_extraction_attempts')->where('organization_id', $actor->organization_id)->where('request_id', $d['request_id'])->first();
            if ($old) {
                abort_unless((int) $old->requested_by === (int) $actor->id && hash_equals($old->request_hash, $hash), 409, 'This extraction request already has different source or provider details.');
                return $old;
            }
            $context = $this->context($actor, $rxId, $transcriptionId);
            $id = DB::table('pharmacy_extraction_attempts')->insertGetId(['organization_id' => $actor->organization_id, 'prescription_id' => $rxId,
                'transcription_id' => $transcriptionId, 'requested_by' => $actor->id, 'request_id' => $d['request_id'], 'request_hash' => $hash,
                'context_token' => $context['token'], 'model' => $d['model'], 'provider_reference' => $d['provider_reference'], 'schema_version' => PharmacyExtractionDraft::VERSION, 'created_at' => now()]);
            $this->event($actor, $rxId, 'extraction_attempt_retained', ['attempt_id' => $id, 'transcription_id' => $transcriptionId]);
            return DB::table('pharmacy_extraction_attempts')->find($id);
        });
    }

    public function attempt(User $actor, int $id): object
    {
        $row = DB::table('pharmacy_extraction_attempts')->where('organization_id', $actor->organization_id)->where('id', $id)->first();
        abort_unless($row, 404);
        $this->prescription($actor, $row->prescription_id);
        return $row;
    }

    public function complete(User $actor, int $id, array $completion): object
    {
        return $this->write($actor, function ($actor) use ($id, $completion) {
            $row = $this->attempt($actor, $id);
            abort_unless((int) $row->requested_by === (int) $actor->id, 403);
            $hash = hash('sha256', json_encode($completion, JSON_THROW_ON_ERROR));
            if ($row->status !== 'pending') {
                abort_unless($row->completion_hash && hash_equals($row->completion_hash, $hash), 409, 'This attempt already has a retained outcome.');
                return $row;
            }
            $context = $this->context($actor, $row->prescription_id, $row->transcription_id);
            abort_unless(hash_equals($row->context_token, $context['token']), 409, 'The source, prescription or patient changed during extraction.');
            try {
                $draft = app(PharmacyExtractionDraft::class)->fromCompletion($completion, $row->model, $context['pages'], $context['source_sha256']);
            } catch (InvalidArgumentException) {
                return $this->finish($actor, $row, ['status' => 'failed', 'failure_code' => 'invalid_response', 'completion_hash' => $hash]);
            }
            $json = json_encode($draft, JSON_THROW_ON_ERROR);
            return $this->finish($actor, $row, ['status' => 'completed', 'completion_hash' => $hash, 'draft' => $json, 'draft_sha256' => hash('sha256', $json)]);
        });
    }

    public function fail(User $actor, int $id, string $code): object
    {
        return $this->write($actor, function ($actor) use ($id, $code) {
            $row = $this->attempt($actor, $id);
            abort_unless((int) $row->requested_by === (int) $actor->id, 403);
            abort_unless(in_array($code, ['provider_failed', 'provider_timeout', 'source_changed', 'delivery_uncertain'], true), 422);
            if ($row->status !== 'pending') {
                abort_unless($row->status === 'failed' && $row->failure_code === $code, 409);
                return $row;
            }
            return $this->finish($actor, $row, ['status' => 'failed', 'failure_code' => $code]);
        });
    }

    private function finish(User $actor, object $row, array $values): object
    {
        DB::table('pharmacy_extraction_attempts')->where('id', $row->id)->update($values + ['finished_at' => now()]);
        $this->event($actor, $row->prescription_id, 'extraction_outcome_retained', ['attempt_id' => $row->id, 'status' => $values['status'], 'failure_code' => $values['failure_code'] ?? null]);
        return DB::table('pharmacy_extraction_attempts')->find($row->id);
    }

    public function review(User $actor, int $id, array $input): object
    {
        return $this->write($actor, function ($actor) use ($id, $input) {
            abort_unless($actor->role === 'pharmacist', 403);
            $row = $this->attempt($actor, $id);
            $d = Validator::make($input, ['request_id' => 'required|uuid', 'draft_sha256' => 'required|string|regex:/^[a-f0-9]{64}$/D',
                'decision' => 'required|in:review,dismiss', 'fields' => 'present|array', 'evidence' => 'required|string|max:5000', 'confirmed' => 'required|accepted'])->validate();
            ksort($d); $hash = hash('sha256', json_encode($d, JSON_THROW_ON_ERROR));
            $old = DB::table('pharmacy_extraction_reviews')->where('attempt_id', $id)->first();
            if ($old) {
                abort_unless((int) $old->reviewed_by === (int) $actor->id && $old->request_id === $d['request_id'] && hash_equals($old->request_hash, $hash), 409, 'This draft already has a retained pharmacist decision.');
                return $old;
            }
            abort_unless($row->status === 'completed' && $row->draft && hash_equals($row->draft_sha256, hash('sha256', $row->draft))
                && hash_equals($row->draft_sha256, $d['draft_sha256']), 409, 'A complete, intact draft is required.');
            $fields = [];
            if ($d['decision'] === 'dismiss') {
                abort_unless($d['fields'] === [], 422, 'A dismissed draft cannot retain selected values.');
            } else {
                $context = $this->context($actor, $row->prescription_id, $row->transcription_id);
                abort_unless(hash_equals($row->context_token, $context['token']), 409, 'The source, prescription or patient changed. Dismiss this draft and reassess.');
                $keys = array_keys($d['fields']); sort($keys); $expected = PharmacyExtractionDraft::FIELDS; sort($expected);
                abort_unless($keys === $expected, 422, 'Explicitly review every extracted field.');
                $draft = json_decode($row->draft, true, 512, JSON_THROW_ON_ERROR);
                foreach (PharmacyExtractionDraft::FIELDS as $name) {
                    abort_unless(is_array($d['fields'][$name]), 422, 'Each field needs a structured review decision.');
                    $choiceKeys = array_keys($d['fields'][$name]); sort($choiceKeys);
                    abort_unless($choiceKeys === ['action', 'candidate_index', 'reason', 'value'], 422, 'Unexpected field review data.');
                    $choice = Validator::make($d['fields'][$name], ['action' => 'required|in:accept,reject,correct', 'candidate_index' => 'present|nullable|integer|min:0',
                        'value' => 'present|nullable|string|max:2000', 'reason' => 'required|string|max:2000'])->validate();
                    if ($choice['action'] === 'accept') {
                        abort_unless($choice['value'] === null && is_int($choice['candidate_index']) && isset($draft['fields'][$name]['candidates'][$choice['candidate_index']]), 422, 'Choose an existing source-linked candidate.');
                        $choice['value'] = $draft['fields'][$name]['candidates'][$choice['candidate_index']]['value'];
                    } else {
                        abort_unless($choice['candidate_index'] === null, 422);
                        abort_unless($choice['action'] === 'reject' ? $choice['value'] === null : (is_string($choice['value']) && trim($choice['value']) !== ''), 422);
                    }
                    $fields[$name] = $choice;
                }
            }
            $review = DB::table('pharmacy_extraction_reviews')->insertGetId(['attempt_id' => $id, 'reviewed_by' => $actor->id, 'request_id' => $d['request_id'],
                'request_hash' => $hash, 'draft_sha256' => $row->draft_sha256, 'decision' => $d['decision'], 'fields' => json_encode($fields, JSON_THROW_ON_ERROR), 'evidence' => $d['evidence'], 'created_at' => now()]);
            $this->event($actor, $row->prescription_id, 'extraction_review_retained', ['attempt_id' => $id, 'review_id' => $review, 'decision' => $d['decision']]);
            return DB::table('pharmacy_extraction_reviews')->find($review);
        });
    }
}
