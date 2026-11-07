<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pharmacy_stock_products', function (Blueprint $t) {
            $t->string('package_code', 14)->nullable();
        });
        Schema::table('pharmacy_fill_labels', function (Blueprint $t) {
            $t->string('barcode_code', 64)->nullable()->unique('pharmacy_label_barcode');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Retain barcode and package evidence. Use the verified recovery plan.');
    }
};
