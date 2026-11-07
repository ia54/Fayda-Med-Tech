<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('case_settlements', function (Blueprint $table) {
            $table->uuid('request_id')->nullable();
            $table->string('request_hash', 64)->nullable();
            $table->unique(['organization_id', 'case_id', 'request_id'], 'settlement_request_unique');
        });
    }
    public function down(): void {
        // InnoDB may remove its implicit FK index when the composite index
        // becomes available. Restore that support before dropping the latter.
        if (Schema::getConnection()->getDriverName() === 'mysql'
            && !Schema::hasIndex('case_settlements', ['organization_id'])) {
            Schema::table('case_settlements', function (Blueprint $table) {
                $table->index('organization_id', 'case_settlements_organization_id_foreign');
            });
        }
        Schema::table('case_settlements', function (Blueprint $table) {
            $table->dropUnique('settlement_request_unique');
            $table->dropColumn(['request_id', 'request_hash']);
        });
    }
};
