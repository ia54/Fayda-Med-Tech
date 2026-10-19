<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pharmacy_stock_dispositions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stock_lot_id')->constrained('pharmacy_stock_lots')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->decimal('quantity', 12, 3);
            $t->string('kind', 30);
            $t->date('occurred_on');
            $t->text('destination');
            $t->text('classification_evidence');
            $t->text('authority_reference');
            $t->text('completion_reference');
            $t->text('reason');
            $t->unsignedInteger('lot_version');
            $t->longText('source_snapshot');
            $t->string('source_hash', 64);
            $t->string('status', 20)->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->uuid('review_request_id')->nullable();
            $t->string('review_hash', 64)->nullable();
            $t->text('review_evidence')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamp('created_at');
            $t->unique(['stock_lot_id', 'request_id'], 'pharmacy_disposition_request');
            $t->index(['stock_lot_id', 'status'], 'pharmacy_disposition_pending');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Stock disposition evidence must be retained. Use the verified recovery plan.');
    }
};
