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
        Schema::create('signatures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('document_signer_id')->nullable();
            $table->string('provider')->default('docusign');
            $table->string('provider_envelope_id')->nullable()->index();
            $table->string('provider_event')->nullable();
            $table->enum('status', ['pending', 'completed', 'declined', 'failed'])->default('pending');
            $table->string('signed_file_path')->nullable();
            $table->string('signed_file_url')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'status']);
            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
            $table->foreign('document_signer_id')->references('id')->on('document_signers')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signatures');
    }
};
