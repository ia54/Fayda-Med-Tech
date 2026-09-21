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
        Schema::table('users', function (Blueprint $table) {
            // Drop the old name column and add first_name and last_name
            $table->dropColumn('name');
            $table->string('first_name')->after('id');
            $table->string('last_name')->after('first_name');
            
            // Update role enum with new values
            $table->dropColumn('role');
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['provider_staff', 'billing_team', 'law_firm_staff', 'supervisor', 'admin'])
                ->default('provider_staff')
                ->after('password');
            
            // Add organization field
            $table->string('organization')->nullable()->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Restore original structure
            $table->dropColumn(['first_name', 'last_name', 'organization']);
            $table->string('name')->after('id');
            $table->dropColumn('role');
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'manager', 'user'])->default('user')->after('password');
        });
    }
};
