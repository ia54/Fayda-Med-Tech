<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('pharmacy_recall_notices', function (Blueprint $t) {
            $t->unsignedInteger('version')->default(1);
            $t->timestamp('withdrawn_at')->nullable();
        });
        Schema::create('pharmacy_recall_corrections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('notice_id')->constrained('pharmacy_recall_notices')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id'); $t->string('request_hash', 64);
            $t->unsignedInteger('notice_version');
            $t->text('reason'); $t->text('evidence');
            $t->string('status', 20)->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->uuid('review_request_id')->nullable(); $t->string('review_hash', 64)->nullable();
            $t->text('review_evidence')->nullable();
            $t->timestamp('reviewed_at')->nullable(); $t->timestamp('created_at');
            $t->unique(['notice_id', 'request_id'], 'pharmacy_recall_correction_request');
            $t->index(['notice_id', 'status'], 'pharmacy_recall_correction_status');
        });
    }
    public function down(): void { throw new RuntimeException('Recall notices and correction evidence must be retained. Use the verified recovery plan.'); }
};
