<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('pharmacy_handover_rehearsals',function(Blueprint $t){
  $t->id();$t->foreignId('execution_id')->constrained('pharmacy_batch_executions')->restrictOnDelete();
  $t->foreignId('created_by')->constrained('users')->restrictOnDelete();$t->uuid('request_id');$t->unique(['execution_id','request_id'],'compound_rehearsal_request_unique');
  $t->string('request_hash',64);$t->json('snapshot');$t->string('snapshot_hash',64);$t->text('reservation_evidence');
  $t->string('status',32)->default('reserved');$t->unsignedInteger('version')->default(1);
  $t->foreignId('finished_by')->nullable()->constrained('users')->restrictOnDelete();$t->uuid('finish_request_id')->nullable();$t->string('finish_hash',64)->nullable();
  $t->json('finish_record')->nullable();$t->timestamp('finished_at')->nullable();$t->timestamp('created_at');
 });}
 public function down():void {throw new RuntimeException('Retain synthetic rehearsal history; use verified recovery.');}
};
