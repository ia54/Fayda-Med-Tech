<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacy_prescription_replacements', function (Blueprint $t) {
            $t->boolean('discontinued_original')->default(false);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Prescription replacement decisions are retained. Use the verified recovery plan.');
    }
};
