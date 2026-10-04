<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_patients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->string('record_number', 100);
            $t->string('first_name', 100);
            $t->string('last_name', 100);
            $t->date('date_of_birth');
            $t->string('phone', 50)->nullable();
            $t->string('address')->nullable();
            $t->string('request_id', 36);
            $t->string('request_hash', 64);
            $t->json('clinical');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['organization_id', 'record_number']);
            $t->unique(['organization_id', 'request_id']);
        });
        Schema::create('pharmacy_patient_locations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_id')->constrained('pharmacy_patients')->restrictOnDelete();
            $t->foreignId('location_id')->constrained('pharmacy_locations')->restrictOnDelete();
            $t->unique(['patient_id', 'location_id']);
        });
        Schema::create('pharmacy_patient_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_id')->constrained('pharmacy_patients')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->json('details');
            $t->timestamp('created_at');
        });
        Schema::table('pharmacy_episodes', function (Blueprint $t) {
            $t->unsignedBigInteger('patient_id')->nullable()->change();
            $t->foreignId('pharmacy_patient_id')->nullable()->constrained('pharmacy_patients')->restrictOnDelete();
            $t->unique(['organization_id', 'case_id', 'pharmacy_patient_id'], 'pharmacy_episode_chart_unique');
        });
    }
    public function down(): void
    {
        throw new RuntimeException('Clinical records are retained. Use the verified recovery plan.');
    }
};
