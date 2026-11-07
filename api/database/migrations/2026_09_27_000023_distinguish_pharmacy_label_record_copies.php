<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pharmacy_label_prints', function (Blueprint $t) {
            // Every existing row came through the dispensing-proof print workflow.
            $t->string('purpose', 24)->default('dispensing_label');
            $t->string('document_sha256', 64)->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Retain the distinction between record copies and dispensing print evidence.');
    }
};
