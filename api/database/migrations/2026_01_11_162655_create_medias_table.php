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
        Schema::create('medias', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->string('filename');
            $table->string('url');
            $table->string('path')->comment('private only internal use');
            $table->longText('placeholder')->nullable(); // base64 bit 1 quality image data
            $table->string('ext');
            $table->string('size');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medias');
    }
};
