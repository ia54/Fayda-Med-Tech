<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_formulations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->string('code', 80);
            $t->unsignedInteger('revision');
            $t->string('name');
            $t->string('preparation_type', 20);
            $t->boolean('hazardous');
            $t->json('record');
            $t->string('status', 30)->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('request_id', 36);
            $t->string('request_hash', 64);
            $t->timestamps();
            $t->unique(['organization_id', 'code', 'revision'], 'pharmacy_formula_revision_unique');
            $t->unique(['organization_id', 'request_id']);
        });
        Schema::create('pharmacy_batch_worksheets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->foreignId('location_id')->constrained('pharmacy_locations')->restrictOnDelete();
            $t->foreignId('prescription_id')->constrained('pharmacy_prescriptions')->restrictOnDelete();
            $t->foreignId('formulation_id')->constrained('pharmacy_formulations')->restrictOnDelete();
            $t->string('batch_number', 100);
            $t->json('record');
            $t->string('status', 30)->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('request_id', 36);
            $t->string('request_hash', 64);
            $t->timestamps();
            $t->unique(['location_id', 'batch_number']);
            $t->unique(['organization_id', 'request_id']);
        });
        Schema::create('pharmacy_compounding_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('formulation_id')->constrained('pharmacy_formulations')->restrictOnDelete();
            $t->foreignId('batch_id')->nullable()->constrained('pharmacy_batch_worksheets')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('action', 40);
            $t->json('details');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Compounding records are retained. Use the verified recovery plan.');
    }
};
