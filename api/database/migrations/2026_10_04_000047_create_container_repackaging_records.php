<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_container_repackaging', function (Blueprint $t) {
            $t->id();
            $t->foreignId('execution_id')->constrained('pharmacy_batch_executions', indexName: 'repackaging_execution_fk')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users', indexName: 'repackaging_author_fk')->restrictOnDelete();
            $t->uuid('request_id'); $t->string('request_hash', 64); $t->unsignedInteger('execution_version');
            $t->json('source_snapshot'); $t->string('source_hash', 64); $t->json('proposal'); $t->string('proposal_hash', 64);
            $t->string('status', 20)->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users', indexName: 'repackaging_reviewer_fk')->restrictOnDelete();
            $t->text('review_evidence')->nullable(); $t->timestamp('reviewed_at')->nullable(); $t->timestamp('created_at');
            $t->unique(['execution_id', 'request_id'], 'repackaging_request');
            $t->index(['execution_id', 'status'], 'repackaging_status');
        });
        Schema::create('pharmacy_container_identifier_reservations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations', indexName: 'container_reservation_org_fk')->restrictOnDelete();
            $t->foreignId('execution_id')->constrained('pharmacy_batch_executions', indexName: 'container_reservation_execution_fk')->restrictOnDelete();
            $t->string('identifier', 64);
            $t->foreignId('initial_identity_id')->nullable()->unique('container_reservation_initial_unique')
                ->constrained('pharmacy_container_identities', indexName: 'container_reservation_initial_fk')->restrictOnDelete();
            $t->foreignId('repackaging_id')->nullable()->constrained('pharmacy_container_repackaging', indexName: 'container_reservation_repackaging_fk')->restrictOnDelete();
            $t->timestamp('created_at');
            $t->unique(['organization_id', 'identifier'], 'container_identifier_reserved');
        });
        // Preserve old identity rows byte-for-byte: their snapshots remain authoritative.
        DB::table('pharmacy_container_identities')->orderBy('id')->chunkById(200, function ($rows) {
            $reservations = [];
            foreach ($rows as $row) {
                $reservations[] = ['organization_id' => $row->organization_id, 'execution_id' => $row->execution_id,
                    'identifier' => $row->identifier, 'initial_identity_id' => $row->id, 'repackaging_id' => null, 'created_at' => $row->created_at];
            }
            DB::table('pharmacy_container_identifier_reservations')->insert($reservations);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Container origins and repackaging evidence are retained; use verified recovery.');
    }
};
