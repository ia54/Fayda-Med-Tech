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
        Schema::table('cases', function (Blueprint $table) {
            // Update status enum to match the required pipeline
            $table->string('status')->default('New')->change();
            
            // Add sol_date for automated alerts
            if (!Schema::hasColumn('cases', 'sol_date')) {
                $table->date('sol_date')->nullable()->after('accident_date');
            }

            // Add jurisdiction for case intake
            if (!Schema::hasColumn('cases', 'jurisdiction')) {
                $table->string('jurisdiction')->nullable()->after('title');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->enum('status', ['open', 'active', 'pending_settlement', 'settled', 'closed', 'archived'])->default('open')->change();
            $table->dropColumn(['sol_date', 'jurisdiction']);
        });
    }
};
