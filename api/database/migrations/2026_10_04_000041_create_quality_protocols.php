<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_quality_protocols', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $t->foreignId('location_id')->constrained('pharmacy_locations')->restrictOnDelete();
            $t->foreignId('formulation_id')->constrained('pharmacy_formulations')->restrictOnDelete();
            $t->unsignedBigInteger('previous_id')->nullable();
            $t->unsignedInteger('revision_number');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->json('record');
            $t->string('record_hash', 64);
            $t->string('formulation_hash', 64);
            $t->string('status', 20)->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['organization_id', 'request_id'], 'quality_protocol_request');
            $t->unique(['location_id', 'formulation_id', 'revision_number'], 'quality_protocol_revision');
        });
        Schema::create('pharmacy_quality_protocol_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('protocol_id')->constrained('pharmacy_quality_protocols')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('action', 30);
            $t->unsignedInteger('version');
            $t->text('evidence');
            $t->timestamp('created_at');
            $t->unique(['protocol_id', 'version'], 'quality_protocol_event_version');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Quality protocol evidence is retained; use verified recovery.');
    }
};
