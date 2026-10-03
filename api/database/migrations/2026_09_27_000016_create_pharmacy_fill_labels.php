<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pharmacy_fill_labels', function (Blueprint $t) {
            $t->id();
            $t->foreignId('fill_id')->constrained('pharmacy_fills')->restrictOnDelete();
            $t->unsignedInteger('revision');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->string('source_token', 64);
            $t->longText('snapshot');
            $t->string('snapshot_sha256', 64);
            $t->longText('document');
            $t->string('sha256', 64);
            $t->text('reason');
            $t->timestamp('created_at');
            $t->unique(['fill_id', 'revision'], 'pharmacy_label_revision');
            $t->unique(['fill_id', 'request_id'], 'pharmacy_label_request');
        });
        Schema::create('pharmacy_label_prints', function (Blueprint $t) {
            $t->id();
            $t->foreignId('label_id')->constrained('pharmacy_fill_labels')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->unsignedSmallInteger('copies');
            $t->date('occurred_on');
            $t->text('reason');
            $t->text('reference');
            $t->timestamp('created_at');
            $t->unique(['label_id', 'request_id'], 'pharmacy_label_print_request');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Label and print evidence must be retained. Use the verified recovery plan.');
    }
};
