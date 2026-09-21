<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add missing columns to organizations table for PDF compliance
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // Add missing columns that the seeder expects
            $table->string('phone')->nullable()->after('email');
            $table->text('address')->nullable()->after('phone');
            $table->string('city')->nullable()->after('address');
            $table->string('state')->nullable()->after('city');
            $table->string('zip_code')->nullable()->after('state');
            $table->json('settings')->nullable()->after('support_documents');
            $table->string('stripe_customer_id')->nullable()->after('settings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['phone', 'address', 'city', 'state', 'zip_code', 'settings', 'stripe_customer_id']);
        });
    }
};
