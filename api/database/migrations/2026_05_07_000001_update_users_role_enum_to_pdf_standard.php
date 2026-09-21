<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Updates role ENUM to match PDF specification (6 roles)
     */
    public function up(): void
    {
        // Update role ENUM to match PDF requirements
        // PDF roles: Super Admin, Firm Admin, Attorney, Medical Biller, Provider Staff, Client
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'admin',           // Super Admin (FaydaTech platform team)
                'firm_admin',      // Firm Admin (Law firm owner/manager)
                'attorney',        // Attorney (Lawyer/paralegal)
                'medical_biller',  // Medical Biller (Billing specialist)
                'provider_staff',  // Provider Staff (Doctor's office staff)
                'client'           // Client/Patient (Injured patient/claimant)
            ])->default('client')->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['provider_staff', 'billing_team', 'law_firm_staff', 'supervisor', 'admin'])
                ->default('provider_staff')
                ->after('password');
        });
    }
};
