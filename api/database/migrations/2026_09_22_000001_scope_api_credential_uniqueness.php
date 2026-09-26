<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NULL organization denotes platform configuration. A generated scope
        // gives it the same concurrency protection as organization-owned keys.
        Schema::table('api_credentials', function (Blueprint $table) {
            $table->unsignedBigInteger('credential_scope')->virtualAs('COALESCE(organization_id, 0)');
            $table->unique(['credential_scope', 'provider', 'name'], 'api_credentials_scope_provider_name_unique');
        });
        Schema::table('api_credentials', function (Blueprint $table) {
            $table->dropUnique(['provider', 'name']);
        });
    }

    public function down(): void
    {
        // Restoring platform-wide uniqueness must not discard tenant records.
        if (DB::table('api_credentials')->select('provider', 'name')
            ->groupBy('provider', 'name')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot restore global credential uniqueness while tenant-specific names overlap.');
        }
        Schema::table('api_credentials', function (Blueprint $table) {
            $table->unique(['provider', 'name']);
        });
        Schema::table('api_credentials', function (Blueprint $table) {
            $table->dropUnique('api_credentials_scope_provider_name_unique');
        });
        // DBAL 3's SQLite introspection omits generated columns. Use native
        // DROP COLUMN (SQLite 3.35+ / MySQL) for this generated column only.
        $table = DB::getQueryGrammar()->wrapTable('api_credentials');
        DB::statement('ALTER TABLE ' . $table . ' DROP COLUMN credential_scope');
    }
};
