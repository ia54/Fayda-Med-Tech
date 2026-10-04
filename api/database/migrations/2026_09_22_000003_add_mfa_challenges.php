<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auth_challenges', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('password_fingerprint', 64);
            $table->string('purpose');
            $table->text('enrollment_secret')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->index();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('two_factor_last_step')->nullable();
        });
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->timestamp('mfa_verified_at')->nullable();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('auth_challenges');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('two_factor_last_step'));
        Schema::table('oauth_access_tokens', fn (Blueprint $table) => $table->dropColumn('mfa_verified_at'));
    }
};
