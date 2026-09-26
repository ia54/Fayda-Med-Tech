<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('case_settlements', function (Blueprint $table) {
            $table->foreignId('supersedes_id')->nullable()->unique()->constrained('case_settlements')->restrictOnDelete();
            $table->text('correction_reason')->nullable();
        });
    }
    public function down(): void {
        Schema::table('case_settlements', function (Blueprint $table) {
            $table->dropForeign(['supersedes_id']);
            $table->dropUnique(['supersedes_id']);
            $table->dropColumn(['supersedes_id', 'correction_reason']);
        });
    }
};
