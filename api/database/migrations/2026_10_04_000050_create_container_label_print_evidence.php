<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('pharmacy_container_label_prints', function(Blueprint $t){
   $t->id();$t->foreignId('label_id')->constrained('pharmacy_container_label_proofs',indexName:'container_print_label_fk')->restrictOnDelete();
   $t->foreignId('created_by')->constrained('users',indexName:'container_print_author_fk')->restrictOnDelete();
   $t->uuid('request_id');$t->string('request_hash',64);$t->string('document_hash',64);
   $t->unsignedSmallInteger('copies');$t->date('occurred_on');$t->text('reason');$t->text('reference');$t->timestamp('created_at');
   $t->unique(['label_id','request_id'],'container_print_request');
  });
 }
 public function down():void{throw new RuntimeException('Container print evidence is retained; use verified recovery.');}
};
