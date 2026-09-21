<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * PDF Section 8: Demand Letter Generation
     */
    public function up(): void
    {
        Schema::create('demand_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->onDelete('cascade');
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('recipient_name');
            $table->string('recipient_company')->nullable();
            $table->string('recipient_address')->nullable();
            $table->decimal('demand_amount', 12, 2);
            $table->text('content')->nullable();
            $table->enum('status', ['draft', 'sent', 'accepted', 'rejected', 'countered'])->default('draft');
            $table->date('sent_at')->nullable();
            $table->date('response_date')->nullable();
            $table->decimal('response_amount', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('case_id');
            $table->index('organization_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demand_letters');
    }
};
