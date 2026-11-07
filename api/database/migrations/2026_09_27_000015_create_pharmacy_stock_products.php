<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pharmacy_stock_products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stock_lot_id')->constrained('pharmacy_stock_lots')->restrictOnDelete();
            $t->unsignedInteger('revision');
            $t->string('generic_name');
            $t->string('brand_name')->nullable();
            $t->string('strength');
            $t->string('dosage_form');
            $t->string('manufacturer');
            $t->date('verified_on');
            $t->text('evidence');
            $t->text('reason');
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->timestamp('created_at');
            $t->unique(['stock_lot_id', 'revision'], 'pharmacy_stock_product_revision');
            $t->unique(['stock_lot_id', 'request_id'], 'pharmacy_stock_product_request');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Verified product history must be retained. Use the verified recovery plan.');
    }
};
