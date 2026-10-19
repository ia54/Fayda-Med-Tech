<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_extraction_attempts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('prescription_id')->constrained('pharmacy_prescriptions', indexName: 'pharm_extract_rx_fk');
            $t->foreignId('transcription_id')->constrained('pharmacy_source_transcriptions', indexName: 'pharm_extract_text_fk');
            $t->foreignId('requested_by')->constrained('users');
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->string('context_token', 64);
            $t->string('model', 100);
            $t->string('provider_reference', 200);
            $t->unsignedInteger('schema_version');
            $t->string('status', 30)->default('pending');
            $t->longText('draft')->nullable();
            $t->string('draft_sha256', 64)->nullable();
            $t->string('completion_hash', 64)->nullable();
            $t->string('failure_code', 40)->nullable();
            $t->timestamp('created_at');
            $t->timestamp('finished_at')->nullable();
            $t->unique(['organization_id', 'request_id'], 'pharm_extract_request_key');
            $t->index(['prescription_id', 'id'], 'pharm_extract_rx_history');
        });
        Schema::create('pharmacy_extraction_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attempt_id')->unique()->constrained('pharmacy_extraction_attempts', indexName: 'pharm_extract_review_fk');
            $t->foreignId('reviewed_by')->constrained('users');
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->string('draft_sha256', 64);
            $t->string('decision', 20);
            $t->longText('fields');
            $t->text('evidence');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Extraction attempts and human decisions must remain in history.');
    }
};
