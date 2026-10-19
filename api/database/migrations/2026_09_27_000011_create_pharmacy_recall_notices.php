<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('pharmacy_recall_notices', function (Blueprint $t) {
            $t->id(); $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id'); $t->string('request_hash', 64);
            $t->string('product_description'); $t->text('reference'); $t->text('evidence');
            $t->boolean('all_lots')->default(false); $t->string('lot_number', 100)->nullable(); $t->string('lot_key', 64)->nullable();
            $t->json('ndcs'); $t->timestamp('created_at');
            $t->unique(['organization_id', 'request_id'], 'pharmacy_recall_notice_request_unique');
        });
        Schema::create('pharmacy_recall_codes', function (Blueprint $t) {
            $t->id(); $t->foreignId('notice_id')->constrained('pharmacy_recall_notices')->restrictOnDelete();
            $t->string('ndc_key', 11); $t->unique(['notice_id', 'ndc_key']); $t->index('ndc_key');
        });
        Schema::table('pharmacy_stock_lots', function (Blueprint $t) {
            $t->string('recall_ndc_key', 11)->nullable(); $t->string('recall_lot_key', 64)->nullable();
            $t->index(['organization_id', 'recall_ndc_key', 'recall_lot_key'], 'stock_recall_match_index');
        });
        DB::table('pharmacy_stock_lots')->select('id', 'ndc', 'lot_number')->orderBy('id')->chunkById(200, function ($lots) {
            foreach ($lots as $lot) {
                DB::table('pharmacy_stock_lots')->where('id', $lot->id)->update(['recall_ndc_key' => str_replace('-', '', $lot->ndc), 'recall_lot_key' => hash('sha256', strtoupper(trim($lot->lot_number)))]);
            }
        });
    }
    public function down(): void { throw new RuntimeException('Recall notices and matching history are retained. Use the verified recovery plan.'); }
};
