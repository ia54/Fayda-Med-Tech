<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_batch_quality_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('execution_id')->constrained('pharmacy_batch_executions')->restrictOnDelete();
            $t->foreignId('protocol_id')->constrained('pharmacy_quality_protocols')->restrictOnDelete();
            $t->unsignedBigInteger('previous_id')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->json('source_snapshot');
            $t->string('source_hash', 64);
            $t->json('results');
            $t->string('results_hash', 64);
            $t->text('evidence');
            $t->string('evidence_hash', 64);
            $t->string('status', 20)->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('review_evidence')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamp('created_at');
            $t->unique(['execution_id', 'request_id'], 'batch_quality_request');
            $t->index(['execution_id', 'status'], 'batch_quality_status');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Batch quality evidence is retained; use verified recovery.');
    }
};
