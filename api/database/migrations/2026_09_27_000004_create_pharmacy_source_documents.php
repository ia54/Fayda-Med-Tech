<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_source_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('prescription_id')->constrained('pharmacy_prescriptions')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id');
            $t->string('original_name');
            $t->string('mime_type', 100);
            $t->unsignedInteger('size');
            $t->string('path');
            $t->char('sha256', 64);
            $t->string('reference', 500);
            $t->timestamp('created_at');
            $t->unique(['prescription_id', 'request_id']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Prescription source evidence is retained. Use the verified recovery plan.');
    }
};
