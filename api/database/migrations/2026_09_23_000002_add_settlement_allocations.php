<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('case_settlements', function (Blueprint $table) {
            // Null preserves the unknown allocation state of legacy records.
            $table->decimal('other_deductions', 15, 2)->nullable();
        });
    }
    public function down(): void {
        Schema::table('case_settlements', fn (Blueprint $table) => $table->dropColumn('other_deductions'));
    }
};
