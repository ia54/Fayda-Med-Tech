<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacy_prescriptions', function (Blueprint $t) {
            $t->timestamp('discontinued_at')->nullable();
            $t->foreignId('discontinued_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('discontinuation_reason')->nullable();
            $t->text('discontinuation_reference')->nullable();
            $t->uuid('discontinuation_request_id')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Prescription discontinuation evidence is retained. Use the verified recovery plan.');
    }
};
