<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_compounding_custody_decisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('incident_id')->constrained('pharmacy_compounding_incidents')->restrictOnDelete();
            $t->foreignId('reconciliation_id')->constrained('pharmacy_compounding_reconciliations')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->unsignedInteger('incident_version');
            $t->json('proposal');
            $t->string('proposal_hash', 64);
            $t->json('source_snapshot');
            $t->string('source_hash', 64);
            $t->text('evidence');
            $t->string('status', 30)->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('review_evidence')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamp('created_at');
            $t->unique(['incident_id', 'request_id'], 'compound_custody_request_unique');
            $t->index(['incident_id', 'status'], 'compound_custody_status');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Custody decisions are retained; use verified recovery.');
    }
};
