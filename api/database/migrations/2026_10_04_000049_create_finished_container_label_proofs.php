<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('pharmacy_container_label_proofs', function(Blueprint $t){
   $t->id();$t->foreignId('execution_id')->constrained('pharmacy_batch_executions',indexName:'container_label_execution_fk')->restrictOnDelete();
   $t->string('container_identifier',64);$t->unsignedInteger('revision');
   $t->foreignId('previous_id')->nullable()->constrained('pharmacy_container_label_proofs',indexName:'container_label_previous_fk')->restrictOnDelete();
   $t->foreignId('created_by')->constrained('users',indexName:'container_label_author_fk')->restrictOnDelete();
   $t->uuid('request_id');$t->string('request_hash',64);$t->longText('source_snapshot');$t->string('source_hash',64);
   $t->longText('document');$t->string('document_hash',64);$t->timestamp('created_at');
   $t->unique(['execution_id','request_id'],'container_label_request');
   $t->unique(['execution_id','container_identifier','revision'],'container_label_revision');
  });
 }
 public function down():void{throw new RuntimeException('Container label proofs are retained; use verified recovery.');}
};
