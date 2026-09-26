<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_ingredient_lots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->foreignId('location_id')->constrained('pharmacy_locations')->restrictOnDelete();
            $t->string('ingredient_name');
            $t->string('supplier');
            $t->string('lot_number', 100);
            $t->string('quantity_unit', 20);
            $t->date('expires_on');
            $t->text('specification');
            $t->text('certificate_reference');
            $t->text('receipt_reference');
            $t->decimal('on_hand', 12, 3);
            $t->decimal('reserved', 12, 3)->default(0);
            $t->string('status', 30)->default('quarantined');
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->timestamps();
            $t->unique(['organization_id', 'request_id'], 'ingredient_receipt_request_unique');
        });
        Schema::create('pharmacy_ingredient_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('batch_id')->constrained('pharmacy_batch_worksheets')->restrictOnDelete();
            $t->foreignId('ingredient_lot_id')->constrained('pharmacy_ingredient_lots')->restrictOnDelete();
            $t->string('ingredient_key', 40);
            $t->decimal('quantity', 12, 3);
            $t->string('status', 30)->default('reserved');
            $t->timestamps();
            $t->unique(['batch_id', 'ingredient_key'], 'ingredient_batch_key_unique');
        });
        Schema::create('pharmacy_ingredient_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ingredient_lot_id')->constrained('pharmacy_ingredient_lots')->restrictOnDelete();
            $t->foreignId('batch_id')->nullable()->constrained('pharmacy_batch_worksheets')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('action', 40);
            $t->decimal('quantity', 12, 3);
            $t->json('details');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Ingredient inventory history is retained. Use the verified recovery plan.');
    }
};
