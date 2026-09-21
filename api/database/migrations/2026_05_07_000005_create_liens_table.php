<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * PDF Section 10: Provider & Lien Management - Lien Tracking
     */
    public function up(): void
    {
        Schema::create('liens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->onDelete('cascade');
            $table->foreignId('provider_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('lien_type', ['medical', 'attorney', 'government_medicare', 'government_medicaid', 'health_insurance']);
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'negotiated', 'settled', 'released'])->default('pending');
            $table->decimal('negotiated_amount', 10, 2)->nullable();
            $table->decimal('reduction_amount', 10, 2)->nullable();
            $table->date('payoff_date')->nullable();
            $table->string('release_document_url')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->index('case_id');
            $table->index('provider_id');
            $table->index('lien_type');
            $table->index('status');
            $table->index('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('liens');
    }
};
