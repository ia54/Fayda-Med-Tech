<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacy_prescriptions', function (Blueprint $table) {
            // Unknown on older records: never infer sterility from medication names.
            $table->string('compound_type', 20)->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Compounding classifications are retained. Use the verified recovery plan.');
    }
};
