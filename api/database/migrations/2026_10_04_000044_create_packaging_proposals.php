<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_packaging_proposals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('execution_id')->constrained('pharmacy_batch_executions')->restrictOnDelete();
            $t->foreignId('dating_proposal_id')->constrained('pharmacy_beyond_use_proposals')->restrictOnDelete();
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
            $t->unique(['execution_id', 'request_id'], 'packaging_request');
            $t->index(['execution_id', 'status'], 'packaging_status');
        });
        Schema::create('pharmacy_container_identities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $t->foreignId('execution_id')->constrained('pharmacy_batch_executions')->restrictOnDelete();
            $t->foreignId('first_proposal_id')->constrained('pharmacy_packaging_proposals')->restrictOnDelete();
            $t->string('identifier', 64);
            $t->timestamp('created_at');
            $t->unique(['organization_id', 'identifier'], 'container_identity_scope');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Packaging evidence is retained; use verified recovery.');
    }
};
