<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * PDF Section 7: Insurance Management - Claim Management
     */
    public function up(): void
    {
        Schema::create('insurance_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_company_id')->constrained()->onDelete('cascade');
            $table->foreignId('case_id')->constrained()->onDelete('cascade');
            $table->string('claim_number')->unique();
            $table->enum('coverage_type', ['liability', 'pip', 'medpay', 'uninsured_motorist']);
            $table->decimal('coverage_limit', 10, 2)->nullable();
            $table->enum('claim_status', ['open', 'pending', 'settled', 'denied'])->default('open');
            $table->decimal('demand_amount', 10, 2)->nullable();
            $table->decimal('settlement_offer', 10, 2)->nullable();
            $table->decimal('final_settlement', 10, 2)->nullable();
            $table->foreignId('adjuster_id')->nullable()->constrained('insurance_adjusters')->nullOnDelete();
            $table->text('adjuster_notes')->nullable();
            $table->json('correspondence_log')->nullable();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->index('insurance_company_id');
            $table->index('case_id');
            $table->index('claim_status');
            $table->index('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_claims');
    }
};
