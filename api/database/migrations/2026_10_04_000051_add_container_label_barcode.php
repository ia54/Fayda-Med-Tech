<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::table('pharmacy_container_label_proofs',function(Blueprint $t){$t->string('barcode_code',32)->nullable()->unique('container_label_code_unique');});}
 public function down():void{throw new RuntimeException('Retained container label codes must not be discarded.');}
};
