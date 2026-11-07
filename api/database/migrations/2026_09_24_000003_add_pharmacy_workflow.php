<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Widen in place: preserve every existing account's role.
        Schema::table('users', fn (Blueprint $t) => $t->string('role', 50)->default('client')->change());
        foreach (['pharmacist' => 'Pharmacist', 'pharmacy_technician' => 'Pharmacy Technician'] as $slug => $name) {
            DB::table('roles')->insertOrIgnore(['slug' => $slug, 'name' => $name, 'description' => 'Dedicated pharmacy workflow', 'permissions' => '[]', 'color' => 'default', 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::create('pharmacy_locations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('address');
            $t->string('license_reference');
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['organization_id', 'name']);
        });
        Schema::create('pharmacy_stock_lots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->foreignId('location_id')->constrained('pharmacy_locations')->restrictOnDelete();
            $t->string('request_id', 36);
            $t->string('request_hash', 64);
            $t->string('ndc', 20);
            $t->string('medication');
            $t->string('lot_number', 100);
            $t->string('quantity_unit', 20);
            $t->date('expires_on');
            $t->decimal('on_hand', 12, 3);
            $t->decimal('reserved', 12, 3)->default(0);
            $t->string('status')->default('available');
            $t->string('receipt_reference');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['organization_id', 'request_id']);
        });
        Schema::create('pharmacy_episodes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->foreignId('case_id')->constrained('cases')->restrictOnDelete();
            $t->foreignId('patient_id')->constrained('users')->restrictOnDelete();
            $t->string('coverage_status')->default('unverified');
            $t->json('coverage')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['organization_id', 'case_id', 'patient_id'], 'pharmacy_episode_patient_unique');
        });
        Schema::create('pharmacy_prescriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->foreignId('episode_id')->constrained('pharmacy_episodes')->restrictOnDelete();
            $t->foreignId('location_id')->constrained('pharmacy_locations')->restrictOnDelete();
            $t->string('request_id', 36);
            $t->string('request_hash', 64);
            $t->string('rx_number', 100);
            $t->string('medication');
            $t->string('strength', 100);
            $t->string('dosage_form', 100);
            $t->text('directions');
            $t->decimal('quantity', 12, 3);
            $t->string('quantity_unit', 20);
            $t->unsignedSmallInteger('refills_authorized');
            $t->date('written_on');
            $t->date('expires_on');
            $t->string('prescriber_name');
            $t->string('prescriber_identifier', 100);
            $t->string('source_reference', 255);
            $t->boolean('controlled')->default(false);
            $t->boolean('compounded')->default(false);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['organization_id', 'request_id']);
            $t->unique(['location_id', 'rx_number']);
        });
        Schema::create('pharmacy_fills', function (Blueprint $t) {
            $t->id();
            $t->foreignId('prescription_id')->constrained('pharmacy_prescriptions')->restrictOnDelete();
            $t->foreignId('stock_lot_id')->constrained('pharmacy_stock_lots')->restrictOnDelete();
            $t->unsignedSmallInteger('fill_number');
            $t->string('request_id', 36);
            $t->string('request_hash', 64);
            $t->decimal('quantity', 12, 3);
            $t->unsignedSmallInteger('days_supply');
            $t->string('ndc', 20);
            $t->string('review_status')->default('pending');
            $t->string('fulfillment_status')->default('pending');
            $t->string('claim_status')->default('not_prepared');
            $t->json('review')->nullable();
            $t->json('fulfillment')->nullable();
            $t->json('claim')->nullable();
            $t->foreignId('invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['prescription_id', 'fill_number']);
            $t->unique(['prescription_id', 'request_id']);
        });
        Schema::create('pharmacy_stock_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stock_lot_id')->constrained('pharmacy_stock_lots')->restrictOnDelete();
            $t->foreignId('fill_id')->nullable()->constrained('pharmacy_fills')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('action');
            $t->decimal('quantity', 12, 3);
            $t->json('details');
            $t->timestamp('created_at');
        });
        Schema::create('pharmacy_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->foreignId('prescription_id')->constrained('pharmacy_prescriptions')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('action');
            $t->json('details');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        // Operational records must be retained. Rollback requires an explicit recovery plan.
        throw new RuntimeException('Pharmacy records are retained; restore the verified pre-release backup to roll back.');
    }
};
