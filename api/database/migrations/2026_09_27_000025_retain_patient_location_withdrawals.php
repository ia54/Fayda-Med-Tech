<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacy_patient_locations', function (Blueprint $table) {
            // Preserve every existing enrollment. Withdrawals retain the row and its event history.
            $table->boolean('active')->default(true);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Patient access history is retained. Use the verified recovery plan.');
    }
};
