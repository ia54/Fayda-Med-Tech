<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_batch_executions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('batch_id')->unique()->constrained('pharmacy_batch_worksheets')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->json('record');
            $t->string('status', 30)->default('quarantined');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Executed preparation records are retained. Use the verified recovery plan.');
    }
};
