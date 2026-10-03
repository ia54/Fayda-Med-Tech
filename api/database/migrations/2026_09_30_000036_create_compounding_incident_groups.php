<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_incident_groups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $t->foreignId('location_id')->constrained('pharmacy_locations')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->json('source_snapshot');
            $t->string('source_hash', 64);
            $t->text('evidence');
            $t->string('status', 30)->default('pending');
            $t->unsignedInteger('version')->default(1);
            $t->timestamp('created_at');
            $t->unique(['organization_id', 'request_id'], 'incident_group_request_unique');
            $t->index(['organization_id', 'location_id', 'status'], 'incident_group_scope');
        });
        Schema::create('pharmacy_incident_group_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('group_id')->constrained('pharmacy_incident_groups')->restrictOnDelete();
            $t->foreignId('incident_id')->constrained('pharmacy_compounding_incidents')->restrictOnDelete();
            $t->unsignedInteger('incident_version');
            $t->string('incident_status', 30);
            $t->json('allocation_snapshot');
            $t->unique(['group_id', 'incident_id'], 'incident_group_member_unique');
        });
        Schema::create('pharmacy_incident_group_proposals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('group_id')->constrained('pharmacy_incident_groups')->restrictOnDelete();
            $t->string('phase', 30);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->unsignedInteger('group_version');
            $t->json('proposal');
            $t->string('proposal_hash', 64);
            $t->json('source_snapshot');
            $t->string('source_hash', 64);
            $t->text('evidence');
            $t->string('status', 30)->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('review_evidence')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamp('created_at');
            $t->unique(['group_id', 'request_id'], 'incident_group_proposal_request');
            $t->index(['group_id', 'phase', 'status'], 'incident_group_proposal_status');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Joint incident evidence is retained; use verified recovery.');
    }
};
