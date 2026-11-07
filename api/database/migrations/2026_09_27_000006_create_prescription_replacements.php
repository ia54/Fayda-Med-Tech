<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_prescription_replacements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('original_id')->unique()->constrained('pharmacy_prescriptions')->restrictOnDelete();
            $t->foreignId('replacement_id')->unique()->constrained('pharmacy_prescriptions')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id');
            $t->text('reason');
            $t->text('reference');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Prescription replacement links are retained. Use the verified recovery plan.');
    }
};
