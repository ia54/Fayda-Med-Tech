<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('documents', fn (Blueprint $table) => $table->uuid('docusign_dispatch_id')->nullable()); }
    public function down(): void { Schema::table('documents', fn (Blueprint $table) => $table->dropColumn('docusign_dispatch_id')); }
};
