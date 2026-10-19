<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_execution_addenda', function (Blueprint $t) {
            $t->id();
            $t->foreignId('execution_id')->constrained('pharmacy_batch_executions')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->string('section', 60);
            $t->text('statement');
            $t->text('reason');
            $t->text('evidence');
            $t->unsignedInteger('execution_version');
            $t->timestamp('created_at');
            $t->unique(['execution_id', 'request_id']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Execution addenda are retained. Use the verified recovery plan.');
    }
};
