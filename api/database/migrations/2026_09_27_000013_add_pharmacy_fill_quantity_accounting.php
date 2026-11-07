<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pharmacy_fills', function (Blueprint $t) {
            // NULL preserves the old one-record-per-allowance semantics. No inferred entitlement.
            $t->unsignedSmallInteger('authorization_number')->nullable();
            $t->text('partial_reason')->nullable();
            $t->index(['prescription_id', 'authorization_number'], 'pharmacy_fill_authorization_index');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Prescription quantity history must be retained. Use the verified recovery plan.');
    }
};
