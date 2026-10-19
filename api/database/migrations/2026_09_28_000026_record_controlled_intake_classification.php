<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacy_prescriptions', function (Blueprint $table) {
            // Existing prescriptions gain no inferred schedule, source or authority.
            $table->string('controlled_schedule', 7)->nullable();
            $table->string('controlled_source_format', 12)->nullable();
            $table->text('controlled_classification_reference')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Controlled prescription intake evidence is retained. Use the verified recovery plan.');
    }
};
