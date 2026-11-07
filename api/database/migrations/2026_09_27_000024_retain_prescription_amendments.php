<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pharmacy_prescriptions', fn (Blueprint $t) => $t->unsignedInteger('amendment_revision')->default(1));
        Schema::table('pharmacy_fills', fn (Blueprint $t) => $t->unsignedInteger('prescription_revision')->default(1));
        Schema::create('pharmacy_prescription_amendments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('prescription_id')->constrained('pharmacy_prescriptions');
            $t->unsignedInteger('revision');
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->foreignId('source_document_id')->constrained('pharmacy_source_documents');
            $t->string('source_sha256', 64);
            $t->longText('before_snapshot');
            $t->longText('after_snapshot');
            $t->date('consulted_on');
            $t->text('consultation_evidence');
            $t->text('reason');
            $t->foreignId('created_by')->constrained('users');
            $t->timestamp('created_at');
            $t->unique(['prescription_id', 'revision'], 'pharm_rx_amendment_revision');
            $t->unique(['prescription_id', 'request_id'], 'pharm_rx_amendment_request');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Prescription amendment history must be retained.');
    }
};
