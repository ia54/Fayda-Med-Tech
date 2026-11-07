<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pharmacy_dispatch_recoveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('dispatch_id')->constrained('pharmacy_fill_dispatches')->restrictOnDelete();
            $t->foreignId('loss_return_id')->constrained('pharmacy_dispatch_returns')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->decimal('quantity', 12, 3);
            $t->json('record');
            $t->string('record_hash', 64);
            $t->string('source_hash', 64);
            $t->string('status', 24)->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('review_evidence')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->foreignId('returned_lot_id')->nullable()->constrained('pharmacy_stock_lots')->restrictOnDelete();
            $t->timestamp('created_at');
        });
        Schema::table('pharmacy_stock_lots', function (Blueprint $t) {
            $t->foreignId('dispatch_recovery_id')->nullable()->unique()->constrained('pharmacy_dispatch_recoveries')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Retain dispatch recovery history. Use the verified recovery plan.');
    }
};
