<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_allowance_corrections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('closure_id')->constrained('pharmacy_allowance_closures')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->string('ledger_token', 64);
            $t->text('reason');
            $t->text('evidence');
            $t->string('status', 20)->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->uuid('review_request_id')->nullable();
            $t->string('review_hash', 64)->nullable();
            $t->text('review_evidence')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamp('created_at');
            $t->unique(['closure_id', 'request_id'], 'pharmacy_allowance_correction_request');
            $t->index(['closure_id', 'status'], 'pharmacy_allowance_correction_status');
        });
        Schema::table('pharmacy_allowance_closures', function (Blueprint $t) {
            // Add the replacement lookup before dropping the old unique index (MySQL FK dependency).
            $t->index(['prescription_id', 'authorization_number'], 'pharmacy_allowance_closure_history');
        });
        Schema::table('pharmacy_allowance_closures', function (Blueprint $t) {
            $t->dropUnique('pharmacy_allowance_closure_unique');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Allowance corrections and original decisions must be retained. Use the verified recovery plan.');
    }
};
