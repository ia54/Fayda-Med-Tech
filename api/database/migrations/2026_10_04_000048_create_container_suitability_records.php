<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('pharmacy_container_suitability', function (Blueprint $t) {
            $t->id();
            $t->foreignId('execution_id')->constrained('pharmacy_batch_executions', indexName: 'suitability_execution_fk')->restrictOnDelete();
            $t->foreignId('dating_proposal_id')->constrained('pharmacy_beyond_use_proposals', indexName: 'suitability_dating_fk')->restrictOnDelete();
            $t->foreignId('previous_id')->nullable()->constrained('pharmacy_container_suitability', indexName: 'suitability_previous_fk')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users', indexName: 'suitability_author_fk')->restrictOnDelete();
            $t->uuid('request_id'); $t->string('request_hash', 64);
            $t->json('source_snapshot'); $t->string('source_hash', 64); $t->json('proposal'); $t->string('proposal_hash', 64);
            $t->string('status', 20)->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users', indexName: 'suitability_reviewer_fk')->restrictOnDelete();
            $t->text('review_evidence')->nullable(); $t->timestamp('reviewed_at')->nullable(); $t->timestamp('created_at');
            $t->unique(['execution_id', 'request_id'], 'suitability_request');
            $t->index(['execution_id', 'status'], 'suitability_status');
        });
    }
    public function down(): void { throw new RuntimeException('Suitability evidence is retained; use verified recovery.'); }
};
