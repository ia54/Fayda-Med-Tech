<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_execution_custody_proposals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('execution_id')->constrained('pharmacy_batch_executions')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->unsignedInteger('execution_version');
            $t->json('source_snapshot');
            $t->string('source_hash', 64);
            $t->json('proposal');
            $t->string('proposal_hash', 64);
            $t->text('evidence');
            $t->string('status', 30)->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('review_evidence')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamp('created_at');
            $t->unique(['execution_id', 'request_id'], 'execution_custody_request_unique');
            $t->index(['execution_id', 'status'], 'execution_custody_status');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Execution custody evidence is retained; use verified recovery.');
    }
};
