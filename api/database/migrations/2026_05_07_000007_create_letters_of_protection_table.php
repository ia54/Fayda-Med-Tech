<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * PDF Section 10: Provider & Lien Management - Letter of Protection
     */
    public function up(): void
    {
        Schema::create('letters_of_protection', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->onDelete('cascade');
            $table->foreignId('provider_id')->constrained()->onDelete('cascade');
            $table->string('lop_number')->unique()->nullable();
            $table->decimal('amount_covered', 10, 2)->nullable();
            $table->json('services_covered')->nullable();
            $table->date('expiry_date')->nullable();
            $table->enum('status', ['issued', 'accepted', 'expired', 'cancelled'])->default('issued');
            $table->date('provider_acceptance_date')->nullable();
            $table->string('document_url')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->index('case_id');
            $table->index('provider_id');
            $table->index('status');
            $table->index('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letters_of_protection');
    }
};
