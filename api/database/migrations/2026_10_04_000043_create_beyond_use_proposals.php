<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_beyond_use_proposals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('execution_id')->constrained('pharmacy_batch_executions')->restrictOnDelete();
            $t->foreignId('quality_record_id')->constrained('pharmacy_batch_quality_records')->restrictOnDelete();
            $t->unsignedBigInteger('previous_id')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->json('source_snapshot');
            $t->string('source_hash', 64);
            $t->json('proposal');
            $t->string('proposal_hash', 64);
            $t->string('status', 20)->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('review_evidence')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamp('created_at');
            $t->unique(['execution_id', 'request_id'], 'beyond_use_request');
            $t->index(['execution_id', 'status'], 'beyond_use_status');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Beyond-use proposals is retained; use verified recovery.');
    }
};
