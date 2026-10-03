<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('pharmacy_transfer_corrections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('transfer_id')->constrained('pharmacy_stock_transfers')->restrictOnDelete();
            $t->foreignId('stock_count_id')->constrained('pharmacy_stock_counts')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id'); $t->string('request_hash', 64);
            $t->unsignedInteger('lot_version'); $t->text('evidence');
            $t->string('status', 20)->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('review_evidence')->nullable(); $t->timestamp('reviewed_at')->nullable(); $t->timestamp('created_at');
            $t->unique(['transfer_id', 'request_id'], 'transfer_correction_request_unique');
        });
        Schema::table('pharmacy_stock_transfers', function (Blueprint $t) {
            $t->foreignId('receipt_correction_id')->nullable()->constrained('pharmacy_transfer_corrections')->restrictOnDelete();
            $t->decimal('corrected_received_quantity', 12, 3)->nullable();
        });
    }
    public function down(): void { throw new RuntimeException('Receipt correction evidence is retained. Use the verified recovery plan.'); }
};
