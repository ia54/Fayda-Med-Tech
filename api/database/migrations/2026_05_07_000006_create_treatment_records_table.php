<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * PDF Section 10: Provider & Lien Management - Treatment Tracking
     */
    public function up(): void
    {
        Schema::create('treatment_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->onDelete('cascade');
            $table->foreignId('provider_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('patient_id')->nullable()->constrained('users')->onDelete('set null');
            $table->date('treatment_date');
            $table->string('treatment_type')->nullable();
            $table->json('diagnosis_codes')->nullable()->comment('ICD-10 codes');
            $table->json('procedure_codes')->nullable()->comment('CPT codes');
            $table->text('notes')->nullable();
            $table->enum('mmi_status', ['pending', 'reached', 'exceeded'])->nullable()->comment('Maximum Medical Improvement');
            $table->decimal('disability_rating', 5, 2)->nullable()->comment('0-100%');
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->index('case_id');
            $table->index('provider_id');
            $table->index('treatment_date');
            $table->index('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treatment_records');
    }
};
