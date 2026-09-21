<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('case_settlements', function (Blueprint $table) {
            $table->decimal('attorney_fees', 15, 2)->default(0)->after('settlement_amount');
            $table->decimal('costs', 15, 2)->default(0)->after('attorney_fees');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_settlements', function (Blueprint $table) {
            $table->dropColumn(['attorney_fees', 'costs']);
        });
    }
};
