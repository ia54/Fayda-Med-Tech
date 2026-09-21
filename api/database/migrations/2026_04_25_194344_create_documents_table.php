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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->string('title');
            $table->string('original_name');
            $table->string('filename');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('path');
            $table->string('url')->nullable();
            $table->enum('document_status', ['draft', 'sent_for_signature', 'signed', 'cancelled'])->default('draft');
            $table->enum('signature_status', ['not_sent', 'pending', 'completed', 'declined', 'voided'])->default('not_sent');
            $table->enum('ocr_status', ['not_processed', 'processing', 'processed', 'failed'])->default('not_processed');
            $table->string('docusign_envelope_id')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['uploaded_by', 'created_at']);
            $table->index(['document_status', 'signature_status', 'ocr_status']);
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
