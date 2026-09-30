<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pharmacy_transfer_resolutions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('transfer_id')->constrained('pharmacy_stock_transfers')->restrictOnDelete();
            $t->foreignId('stock_count_id')->constrained('pharmacy_stock_counts')->restrictOnDelete();
            $t->foreignId('investigation_event_id')->constrained('pharmacy_stock_transfer_events')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->string('kind', 40);
            $t->json('snapshot');
            $t->string('snapshot_hash', 64);
            $t->text('evidence');
            $t->text('reporting_assessment');
            $t->text('classification_evidence');
            $t->text('external_return_evidence')->nullable();
            $t->string('status', 20)->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('review_evidence')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamp('created_at');
            $t->unique(['transfer_id', 'request_id'], 'transfer_resolution_request_unique');
            $t->index(['transfer_id', 'status']);
        });
        Schema::table('pharmacy_stock_transfers', function (Blueprint $t) {
            $t->foreignId('variance_resolution_id')->nullable()->constrained('pharmacy_transfer_resolutions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Variance resolution evidence is retained. Use the verified recovery plan.');
    }
};
