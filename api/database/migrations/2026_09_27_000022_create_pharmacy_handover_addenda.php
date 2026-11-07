<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pharmacy_handover_addenda', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('fill_id')->index();
            $t->unsignedBigInteger('created_by');
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->string('source_hash', 64);
            $t->string('section', 32);
            $t->text('statement');
            $t->text('reason');
            $t->text('evidence');
            $t->string('status', 24)->default('pending');
            $t->unsignedBigInteger('reviewed_by')->nullable();
            $t->text('review_evidence')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamp('created_at');
            $t->unique(['fill_id', 'request_id'], 'handover_addendum_request');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Retain original handover and correction evidence. Use the verified recovery plan.');
    }
};
