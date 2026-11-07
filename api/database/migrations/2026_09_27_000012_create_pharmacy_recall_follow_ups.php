<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('pharmacy_recall_follow_ups', function (Blueprint $t) {
            $t->id(); $t->foreignId('notice_id')->constrained('pharmacy_recall_notices')->restrictOnDelete();
            $t->foreignId('fill_id')->constrained('pharmacy_fills')->restrictOnDelete();
            $t->string('status', 20)->default('open'); $t->unsignedInteger('version')->default(0);
            $t->timestamps(); $t->unique(['notice_id', 'fill_id'], 'recall_follow_up_fill_unique');
        });
        Schema::create('pharmacy_recall_follow_up_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('follow_up_id')->constrained('pharmacy_recall_follow_ups')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id'); $t->string('request_hash', 64); $t->unsignedInteger('version');
            $t->string('action', 30); $t->date('occurred_on'); $t->string('method', 30)->nullable();
            $t->text('note'); $t->text('evidence'); $t->timestamp('created_at');
            $t->unique(['follow_up_id', 'request_id'], 'recall_follow_up_event_request_unique');
            $t->unique(['follow_up_id', 'version'], 'recall_follow_up_event_version_unique');
        });
    }
    public function down(): void { throw new RuntimeException('Recall follow-up history is retained. Use the verified recovery plan.'); }
};
