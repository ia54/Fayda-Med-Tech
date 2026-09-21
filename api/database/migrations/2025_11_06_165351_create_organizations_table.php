<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('org_name');
            $table->string('org_type');
            $table->string('subscription_plan');
            $table->string('email')->unique();
            $table->integer('no_of_employees')->nullable();
            $table->decimal('monthly_revenue', 15, 2)->nullable();
            $table->decimal('yearly_revenue', 15, 2)->nullable();
            $table->string('company_logo')->nullable();
            $table->string('tax_bin_no')->nullable();
            $table->json('support_documents')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
