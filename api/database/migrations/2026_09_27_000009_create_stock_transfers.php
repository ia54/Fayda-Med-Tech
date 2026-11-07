<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('pharmacy_stock_transfers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->foreignId('source_lot_id')->constrained('pharmacy_stock_lots')->restrictOnDelete();
            $t->foreignId('source_location_id')->constrained('pharmacy_locations')->restrictOnDelete();
            $t->foreignId('destination_location_id')->constrained('pharmacy_locations')->restrictOnDelete();
            $t->foreignId('destination_lot_id')->nullable()->constrained('pharmacy_stock_lots')->restrictOnDelete();
            $t->uuid('request_id'); $t->string('request_hash', 64);
            $t->decimal('quantity', 12, 3); $t->decimal('received_quantity', 12, 3)->nullable();
            $t->json('product'); $t->text('reference');
            $t->string('status', 40)->default('planned'); $t->unsignedInteger('version')->default(1);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('dispatched_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('dispatched_at')->nullable(); $t->timestamp('received_at')->nullable();
            $t->text('dispatch_evidence')->nullable(); $t->text('receipt_evidence')->nullable();
            $t->timestamps();
            $t->unique(['organization_id', 'request_id'], 'stock_transfer_request_unique');
            $t->index(['organization_id', 'status']);
        });
        Schema::create('pharmacy_stock_transfer_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('transfer_id')->constrained('pharmacy_stock_transfers')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id'); $t->string('request_hash', 64); $t->string('action', 30);
            $t->json('details'); $t->timestamp('created_at');
            $t->unique(['transfer_id', 'request_id'], 'stock_transfer_event_request_unique');
        });
        Schema::table('pharmacy_stock_lots', function (Blueprint $t) {
            $t->foreignId('source_transfer_id')->nullable()->constrained('pharmacy_stock_transfers')->restrictOnDelete();
        });
    }
    public function down(): void { throw new RuntimeException('Transfer custody history is retained. Use the verified recovery plan.'); }
};
