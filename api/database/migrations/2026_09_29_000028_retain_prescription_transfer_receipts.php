<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pharmacy_rx_transfer_receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations');
            $t->foreignId('source_prescription_id')->unique('pharm_rx_transfer_source')->constrained('pharmacy_prescriptions', indexName: 'pharm_rx_transfer_source_fk');
            $t->foreignId('destination_prescription_id')->unique('pharm_rx_transfer_destination')->constrained('pharmacy_prescriptions', indexName: 'pharm_rx_transfer_destination_fk');
            $t->foreignId('source_location_id')->constrained('pharmacy_locations');
            $t->foreignId('destination_location_id')->constrained('pharmacy_locations');
            $t->longText('source_snapshot');
            $t->string('snapshot_sha256', 64);
            $t->foreignId('sent_by')->constrained('users');
            $t->foreignId('accepted_by')->constrained('users');
            $t->text('sending_evidence');
            $t->text('receiving_evidence');
            $t->timestamp('accepted_at');
        });
        Schema::table('pharmacy_prescriptions', function (Blueprint $t) {
            $t->foreignId('incoming_transfer_id')->nullable()->unique()->constrained('pharmacy_rx_transfer_receipts');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Prescription transfer authority and its lineage must be retained. Use the verified recovery plan.');
    }
};
