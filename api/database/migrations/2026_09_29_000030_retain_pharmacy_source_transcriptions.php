<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_source_transcriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('source_document_id')->constrained('pharmacy_source_documents', indexName: 'pharm_transcript_source_fk');
            $t->foreignId('created_by')->constrained('users');
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->string('source_sha256', 64);
            $t->string('transcription_sha256', 64);
            $t->longText('pages');
            $t->text('reference');
            $t->string('method', 30);
            $t->unsignedBigInteger('supersedes_id')->nullable();
            $t->foreign('supersedes_id', 'pharm_transcript_prior_fk')->references('id')->on('pharmacy_source_transcriptions');
            $t->timestamp('created_at');
            $t->unique(['organization_id', 'request_id'], 'pharm_transcript_request_key');
            $t->index(['source_document_id', 'id'], 'pharm_transcript_history');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Retained source transcription history must not be discarded.');
    }
};
