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
        Schema::create('security_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('min_password_length')->default(8);
            $table->boolean('require_uppercase')->default(true);
            $table->boolean('require_numbers')->default(true);
            $table->boolean('require_symbols')->default(true);
            $table->boolean('enforce_2fa_all')->default(false);
            $table->boolean('enforce_2fa_admin')->default(true);
            $table->integer('two_fa_grace_period')->default(7); // Days
            $table->integer('session_timeout')->default(120); // Minutes
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_settings');
    }
};
