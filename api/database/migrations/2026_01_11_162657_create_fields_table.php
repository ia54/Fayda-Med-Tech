<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained('blocks')->onDelete('cascade');
            $table->string('label');
            $table->string('name');
            $table->enum('type', ['text', 'richtext', 'media', 'array', 'group', 'checkbox', 'select']);
            $table->json('options')->nullable(); // {label: string, value: string}
            $table->json('value')->nullable(); // string | object | array | boolean
            $table->boolean('required')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fields');
    }
};
