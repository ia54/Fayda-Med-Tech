<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Existing files stay readable until the reviewed storage migration is run.
            $table->string('storage_disk')->default('public');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        // Do not silently expose private records or unarchive documents on rollback.
        throw new RuntimeException('Document protection rollback requires an explicit storage and retention plan.');
    }
};
