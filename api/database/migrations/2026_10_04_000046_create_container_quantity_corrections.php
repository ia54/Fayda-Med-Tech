<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_container_quantity_corrections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('execution_id')->constrained('pharmacy_batch_executions', indexName: 'container_recount_execution_fk')->restrictOnDelete();
            $t->foreignId('yield_proposal_id')->unique('container_recount_yield_unique')->constrained('pharmacy_yield_correction_proposals', indexName: 'container_recount_yield_fk')->restrictOnDelete();
            $t->foreignId('packaging_proposal_id')->constrained('pharmacy_packaging_proposals', indexName: 'container_recount_packaging_fk')->restrictOnDelete();
            $t->json('source_snapshot'); $t->string('source_hash', 64);
            $t->json('proposal'); $t->string('proposal_hash', 64);
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Container quantity correction evidence is retained; use verified recovery.');
    }
};
