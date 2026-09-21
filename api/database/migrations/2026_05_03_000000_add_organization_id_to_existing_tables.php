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
        $tables = [
            'documents',
            'signatures',
            'ocr_results',
            'api_credentials',
            'api_logs'
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                if (!Schema::hasColumn($table->getTable(), 'organization_id')) {
                    $table->unsignedBigInteger('organization_id')->nullable()->after('id');
                    $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
                    $table->index('organization_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'documents',
            'signatures',
            'ocr_results',
            'api_credentials',
            'api_logs'
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                if (Schema::hasColumn($table->getTable(), 'organization_id')) {
                    $table->dropForeign([$table->getTable() . '_organization_id_foreign']);
                    $table->dropColumn('organization_id');
                }
            });
        }
    }
};
