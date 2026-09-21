<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * PDF Section 6: EOB Processing & AI Extraction
     */
    public function up(): void
    {
        Schema::create('eobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('document_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('invoice_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('provider_name');
            $table->string('patient_name');
            $table->string('payer_name');
            $table->decimal('billed_amount', 12, 2)->default(0);
            $table->decimal('allowed_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('patient_responsibility', 12, 2)->default(0);
            $table->date('service_date')->nullable();
            $table->date('eob_date')->nullable();
            $table->decimal('ai_confidence', 5, 2)->nullable();
            $table->enum('status', ['pending', 'processed', 'matched', 'rejected'])->default('pending');
            $table->json('extracted_data')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('case_id');
            $table->index('organization_id');
            $table->index('status');
            $table->index('payer_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eobs');
    }
};
