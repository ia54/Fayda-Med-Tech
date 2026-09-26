<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_staff_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('location_id')->constrained('pharmacy_locations')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->boolean('active');
            $t->date('valid_until');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['location_id', 'user_id']);
        });
        Schema::create('pharmacy_access_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('location_id')->constrained('pharmacy_locations')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->json('details');
            $t->timestamp('created_at');
        });
        // Deliberately no implicit grants: an administrator must assign each site.
    }

    public function down(): void
    {
        throw new RuntimeException('Pharmacy access history is retained. Use the verified recovery plan.');
    }
};
