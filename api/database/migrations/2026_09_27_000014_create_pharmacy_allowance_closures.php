<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pharmacy_allowance_closures', function (Blueprint $t) {
            $t->id();
            $t->foreignId('prescription_id')->constrained('pharmacy_prescriptions')->restrictOnDelete();
            $t->unsignedSmallInteger('authorization_number');
            $t->decimal('quantity', 12, 3);
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->string('ledger_token', 64);
            $t->string('basis', 30);
            $t->date('occurred_on');
            $t->text('reason');
            $t->text('evidence');
            $t->timestamp('created_at');
            $t->unique(['prescription_id', 'authorization_number'], 'pharmacy_allowance_closure_unique');
            $t->unique(['prescription_id', 'request_id'], 'pharmacy_allowance_closure_request');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Allowance closure evidence must be retained. Use the verified recovery plan.');
    }
};
