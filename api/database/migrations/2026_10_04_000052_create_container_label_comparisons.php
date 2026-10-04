<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::create('pharmacy_container_label_comparisons',function(Blueprint $t){
  $t->id();$t->foreignId('label_id')->constrained('pharmacy_container_label_proofs',indexName:'container_compare_label_fk')->restrictOnDelete();
  $t->foreignId('print_id')->constrained('pharmacy_container_label_prints',indexName:'container_compare_print_fk')->restrictOnDelete();
  $t->foreignId('created_by')->constrained('users',indexName:'container_compare_author_fk')->restrictOnDelete();
  $t->uuid('request_id');$t->string('request_hash',64);$t->string('document_hash',64);$t->string('barcode_code',32);$t->string('container_identifier',64);
  $t->string('input_method',16);$t->text('manual_reason')->nullable();$t->text('evidence');$t->timestamp('created_at');
  $t->unique(['label_id','request_id'],'container_compare_request');
 });}
 public function down():void{throw new RuntimeException('Container label comparisons must be retained.');}
};
