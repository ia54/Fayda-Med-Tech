<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('pharmacy_stock_lots', function (Blueprint $t) {
            $t->text('recall_reference')->nullable();
            $t->text('recall_evidence')->nullable();
            $t->uuid('recall_request_id')->nullable();
            $t->foreignId('recalled_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('recalled_at')->nullable();
        });
    }
    public function down(): void
    {
        throw new RuntimeException('Recall evidence is retained. Use the verified recovery plan.');
    }
};
