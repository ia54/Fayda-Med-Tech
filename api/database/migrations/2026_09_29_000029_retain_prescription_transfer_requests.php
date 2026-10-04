<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pharmacy_rx_transfer_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations');
            $t->foreignId('source_prescription_id')->constrained('pharmacy_prescriptions', indexName: 'pharm_rx_request_source_fk');
            $t->foreignId('source_location_id')->constrained('pharmacy_locations');
            $t->foreignId('destination_location_id')->constrained('pharmacy_locations');
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->string('source_token', 64);
            $t->longText('source_snapshot');
            $t->text('sending_evidence');
            $t->text('sharing_reference');
            $t->foreignId('sent_by')->constrained('users');
            $t->timestamp('created_at');
            $t->string('status', 20)->default('pending');
            $t->foreignId('receipt_id')->nullable()->constrained('pharmacy_rx_transfer_receipts');
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->uuid('review_request_id')->nullable();
            $t->string('review_hash', 64)->nullable();
            $t->text('review_evidence')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->unique(['organization_id', 'request_id'], 'pharm_rx_transfer_request_key');
            $t->index(['destination_location_id', 'status'], 'pharm_rx_transfer_inbox');
        });
        Schema::table('pharmacy_prescriptions', function (Blueprint $t) {
            $t->foreignId('pending_transfer_id')->nullable()->unique()->constrained('pharmacy_rx_transfer_requests');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Prescription transfer requests and decisions must be retained. Use the verified recovery plan.');
    }
};
