<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pharmacy_compounding_incidents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->foreignId('location_id')->constrained('pharmacy_locations')->restrictOnDelete();
            $t->foreignId('batch_id')->constrained('pharmacy_batch_worksheets')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->unsignedInteger('batch_version');
            $t->json('source_snapshot');
            $t->string('source_hash', 64);
            $t->timestamp('observed_at');
            $t->text('findings');
            $t->text('custody_evidence');
            $t->string('follow_up_owner');
            $t->string('status', 30)->default('unresolved');
            $t->unsignedInteger('version')->default(1);
            $t->timestamp('created_at');
            $t->unique(['organization_id', 'request_id'], 'compound_incident_request_unique');
            $t->index(['batch_id', 'status']);
        });
        Schema::create('pharmacy_compounding_incident_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('incident_id')->constrained('pharmacy_compounding_incidents')->restrictOnDelete();
            $t->foreignId('allocation_id')->constrained('pharmacy_ingredient_allocations')->restrictOnDelete();
            $t->foreignId('ingredient_lot_id')->constrained('pharmacy_ingredient_lots')->restrictOnDelete();
            $t->string('ingredient_key', 40);
            $t->string('quantity_unit', 20);
            $t->decimal('reserved_quantity', 12, 3);
            // NULL means unknown. An observed zero must be retained explicitly.
            $t->decimal('observed_quantity', 12, 3)->nullable();
            $t->text('measurement_evidence');
            $t->unique(['incident_id', 'allocation_id'], 'compound_incident_allocation_unique');
            $t->index('ingredient_lot_id');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Compounding incident evidence is retained. Use the verified recovery plan.');
    }
};
