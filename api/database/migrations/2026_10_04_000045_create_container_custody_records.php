<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_container_custody_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('execution_id')->constrained('pharmacy_batch_executions')->restrictOnDelete();
            $t->foreignId('output_proposal_id')->unique()->constrained('pharmacy_execution_custody_proposals')->restrictOnDelete();
            $t->foreignId('packaging_proposal_id')->constrained('pharmacy_packaging_proposals')->restrictOnDelete();
            $t->json('source_snapshot'); $t->string('source_hash', 64);
            $t->json('proposal'); $t->string('proposal_hash', 64);
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Container custody evidence is retained; use verified recovery.');
    }
};
