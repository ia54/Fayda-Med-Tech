<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pharmacy_stock_counts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stock_lot_id')->constrained('pharmacy_stock_lots')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->decimal('recorded_quantity', 12, 3);
            $t->decimal('counted_quantity', 12, 3);
            $t->unsignedInteger('lot_version');
            $t->string('reason', 40);
            $t->text('evidence');
            $t->string('status', 20)->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('review_evidence')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamp('created_at');
            $t->unique(['stock_lot_id', 'request_id'], 'stock_count_request_unique');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Medication counts are retained. Use the verified recovery plan.');
    }
};
