<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pharmacy_patient_returns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('fill_id')->constrained('pharmacy_fills')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id'); $t->unique(['fill_id', 'request_id']);
            $t->string('request_hash', 64);
            $t->json('record'); $t->string('record_hash', 64);
            $t->string('source_hash', 64);
            $t->decimal('custody_quantity', 12, 3);
            $t->decimal('disposed_quantity', 12, 3)->default(0);
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
        Schema::create('pharmacy_patient_return_proposals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_return_id')->constrained('pharmacy_patient_returns')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id'); $t->unique(['patient_return_id', 'request_id'], 'patient_return_request_unique');
            $t->string('request_hash', 64); $t->string('kind', 24);
            $t->json('record'); $t->string('record_hash', 64); $t->string('source_hash', 64);
            $t->unsignedInteger('base_version');
            $t->string('status', 24)->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('review_evidence')->nullable();
            $t->uuid('review_request_id')->nullable(); $t->string('review_hash', 64)->nullable();
            $t->timestamp('reviewed_at')->nullable(); $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Retain patient-return custody and disposition records. Use the verified recovery plan.');
    }
};
