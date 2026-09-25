<?php

namespace Tests\Feature;

use App\Http\Middleware\TwoFactorMiddleware;
use App\Models\CaseModel;
use App\Models\CaseParty;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Passport\Token;
use Tests\TestCase;

class PharmacyWorkflowTest extends TestCase
{
    private User $actor;

    private User $patient;

    private CaseModel $case;

    private int $location;

    private int $otherLocation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(TwoFactorMiddleware::class);
        Artisan::call('migrate', ['--force' => true]);
        foreach ([1, 2] as $id) {
            DB::table('organizations')->insert(['id' => $id, 'org_name' => 'Synthetic pharmacy '.$id, 'org_type' => 'provider', 'subscription_plan' => 'test', 'email' => "pharm$id@example.invalid"]);
        }
        $this->actor = User::create(['first_name' => 'Synthetic', 'last_name' => 'Pharmacist', 'email' => 'pharmacist@example.invalid', 'password' => 'synthetic-only', 'role' => 'pharmacist', 'organization_id' => 1, 'status' => 'active']);
        $this->actor->withAccessToken(new Token(['expires_at' => now()->addHour()]));
        $this->actingAs($this->actor, 'api');
        $this->patient = User::create(['first_name' => 'Synthetic', 'last_name' => 'Patient', 'email' => 'patient@example.invalid', 'password' => 'synthetic-only', 'role' => 'client', 'organization_id' => 1, 'status' => 'active']);
        $this->case = CaseModel::withoutEvents(fn () => CaseModel::create(['organization_id' => 1, 'case_number' => 'PHARM-SYN', 'title' => 'Synthetic pharmacy case', 'accident_date' => now()->subDays(30)->toDateString(), 'created_by' => $this->actor->id]));
        CaseParty::create(['case_id' => $this->case->id, 'user_id' => $this->patient->id, 'role_in_case' => 'client']);
        $this->location = DB::table('pharmacy_locations')->insertGetId(['organization_id' => 1, 'name' => 'Synthetic location A', 'address' => 'Synthetic only', 'license_reference' => 'NOT VALID']);
        $this->otherLocation = DB::table('pharmacy_locations')->insertGetId(['organization_id' => 1, 'name' => 'Synthetic location B', 'address' => 'Synthetic only', 'license_reference' => 'NOT VALID']);
        foreach ([$this->location, $this->otherLocation] as $id) {
            DB::table('pharmacy_staff_assignments')->insert(['location_id' => $id, 'user_id' => $this->actor->id, 'active' => true, 'valid_until' => now()->addYear()->toDateString()]);
        }
    }

    private function body(array $overrides = []): array
    {
        if (! empty($overrides['compounded']) && ! array_key_exists('compound_type', $overrides)) {
            $overrides['compound_type'] = 'nonsterile';
        }

        return array_replace(['request_id' => (string) Str::uuid(), 'location_id' => $this->location, 'case_id' => $this->case->id, 'patient_id' => $this->patient->id, 'rx_number' => 'SYN-'.Str::random(10), 'medication' => 'Synthetic medication', 'strength' => 'Synthetic strength', 'dosage_form' => 'tablet', 'directions' => 'Synthetic fixture only', 'quantity' => '10.000', 'quantity_unit' => 'tablet', 'refills_authorized' => 1, 'written_on' => now()->subDay()->toDateString(), 'expires_on' => now()->addMonth()->toDateString(), 'prescriber_name' => 'Synthetic prescriber', 'prescriber_identifier' => 'NOT VALID', 'source_reference' => 'synthetic fixture', 'controlled' => false, 'compounded' => false], $overrides);
    }

    private function rx(array $overrides = []): int
    {
        return $this->postJson('/api/pharmacy/prescriptions', $this->body($overrides))->assertCreated()->json('data.id');
    }

    private function lot(array $overrides = []): int
    {
        return $this->postJson('/api/pharmacy/stock', array_replace(['request_id' => (string) Str::uuid(), 'location_id' => $this->location, 'ndc' => '00000-0000-00', 'medication' => 'Synthetic medication', 'lot_number' => 'SYN-LOT', 'quantity_unit' => 'tablet', 'expires_on' => now()->addMonth()->toDateString(), 'quantity' => '50.000', 'receipt_reference' => 'Synthetic receipt'], $overrides))->assertCreated()->json('data.id');
    }

    private function fillBody(int $lot, array $overrides = []): array
    {
        return array_replace(['request_id' => (string) Str::uuid(), 'stock_lot_id' => $lot, 'ndc' => '00000-0000-00', 'quantity' => '10.000', 'days_supply' => 10], $overrides);
    }

    private function fill(int $rx, int $lot): array
    {
        return $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot))->assertOk()->json('data.fills.0');
    }

    private function act(int $rx, array $f, string $a, array $extra = [], $status = 200): array
    {
        $result = $this->postJson("/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/actions", array_replace(['version' => $f['version'], 'action' => $a, 'note' => 'Synthetic evidence'], $extra))->assertStatus($status);

        return $status === 200 ? collect($result->json('data.fills'))->firstWhere('id', $f['id']) : $f;
    }

    private function checks(): array
    {
        return ['checks' => ['identity' => true, 'prescriber' => true, 'therapy' => true, 'product' => true]];
    }

    private function complete(int $rx, array $f): array
    {
        $f = $this->act($rx, $f, 'approve', $this->checks());
        $f = $this->act($rx, $f, 'ready', ['checks' => ['label' => true]]);

        return $this->act($rx, $f, 'collected', ['occurred_on' => now()->toDateString(), 'reference' => 'Synthetic handover', 'counseling' => 'provided']);
    }

    public function test_full_workflow_records_stock_once_and_preserves_billing_link(): void
    {
        Http::preventStrayRequests();
        $rx = $this->rx();
        $lot = $this->lot();
        $f = $this->fill($rx, $lot);
        $this->assertEquals(10, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('reserved'));
        $this->act($rx, $f, 'ready', ['checks' => ['label' => true]], 422);
        $this->act($rx, $f, 'approve', [], 422);
        foreach (['pharmacy_technician', 'medical_biller', 'admin'] as $role) {
            $this->actor->role = $role;
            $this->act($rx, $f, 'approve', $this->checks(), 403);
        }
        $this->actor->role = 'pharmacist';
        $f = $this->complete($rx, $f);
        $this->assertEquals(40, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
        $this->assertEquals(0, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('reserved'));
        $this->act($rx, $f, 'collected', [], 422);
        $this->assertSame(1, DB::table('pharmacy_stock_events')->where('action', 'dispensed')->count());
        $this->actor->role = 'medical_biller';
        $this->act($rx, $f, 'prepare_claim', ['amount' => '123.45', 'reference' => 'Synthetic pricing basis'], 422);
        $coverage = ['version' => 1, 'status' => 'verified', 'payer' => 'Synthetic payer', 'claim_number' => 'SYN-PIP', 'coordination' => 'Synthetic coordination', 'evidence' => 'Synthetic verification', 'verified_on' => now()->toDateString()];
        $this->putJson("/api/pharmacy/prescriptions/$rx/coverage", $coverage)->assertOk();
        $this->putJson("/api/pharmacy/prescriptions/$rx/coverage", $coverage)->assertStatus(409);
        $f = $this->act($rx, $f, 'prepare_claim', ['amount' => '123.45', 'reference' => 'Synthetic pricing basis']);
        $invoice = $f['invoice_id'];
        $this->act($rx, $f, 'prepare_claim', ['amount' => '123.45', 'reference' => 'Synthetic pricing basis'], 422);
        $this->putJson("/api/invoices/$invoice", ['amount' => 1])->assertStatus(409);
        $this->deleteJson("/api/invoices/$invoice")->assertStatus(409);
        $this->act($rx, $f, 'record_submission', [], 422);
        $f = $this->act($rx, $f, 'record_submission', ['reference' => 'EXTERNAL-SYN', 'occurred_on' => now()->toDateString()]);
        $f = $this->act($rx, $f, 'record_denial', ['denial_type' => 'coverage', 'reference' => 'Synthetic determination', 'determination_on' => now()->toDateString()]);
        $this->assertSame('denied', $f['claim_status']);
        $this->getJson("/api/pharmacy/prescriptions/$rx/assistant")->assertOk()->assertJsonPath('data.ai_connected', false)->assertJsonPath('data.mode', 'rules_only');
        $this->assertSame(1, DB::table('invoices')->count());
        Http::assertNothingSent();
    }

    public function test_tenant_location_and_role_boundaries(): void
    {
        $rx = $this->rx();
        $lot = $this->lot(['location_id' => $this->otherLocation]);
        $body = $this->fillBody($lot);
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $body)->assertNotFound();
        $this->actor->organization_id = 2;
        $this->getJson('/api/pharmacy/prescriptions')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/pharmacy/stock')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson("/api/pharmacy/prescriptions/$rx")->assertNotFound();
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $body)->assertNotFound();
        $this->postJson('/api/pharmacy/prescriptions', $this->body())->assertNotFound();
        $this->actor->organization_id = 1;
        foreach (['client', 'provider_staff', 'attorney', 'firm_admin'] as $role) {
            $this->actor->role = $role;
            $this->getJson('/api/pharmacy/prescriptions')->assertForbidden();
        }
        foreach (['admin', 'medical_biller'] as $role) {
            $this->actor->role = $role;
            $this->postJson('/api/pharmacy/prescriptions', $this->body())->assertForbidden();
        }
        $this->actor->organization_id = null;
        $this->getJson('/api/pharmacy/prescriptions')->assertForbidden();
    }

    public function test_retries_refills_stale_updates_and_cancel_release_stock(): void
    {
        $body = $this->body(['refills_authorized' => 0]);
        $rx = $this->postJson('/api/pharmacy/prescriptions', $body)->assertCreated()->json('data.id');
        $this->postJson('/api/pharmacy/prescriptions', $body)->assertCreated()->assertJsonPath('data.id', $rx);
        $this->postJson('/api/pharmacy/prescriptions', array_replace($body, ['medication' => 'Changed']))->assertStatus(409);
        $lot = $this->lot(['quantity' => '10.125']);
        $fill = $this->fillBody($lot);
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", array_replace($fill, ['quantity' => '11.000']))->assertUnprocessable();
        $f = $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $fill)->assertOk()->json('data.fills.0');
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $fill)->assertOk();
        $this->assertSame(1, DB::table('pharmacy_stock_events')->where('action', 'reserved')->count());
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", array_replace($fill, ['days_supply' => 9]))->assertStatus(409);
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot))->assertUnprocessable();
        $this->act($rx, $f, 'cancel');
        $this->assertEquals(0, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('reserved'));
        $this->assertEquals(10.125, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
        $new = $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot))->assertOk()->json('data.fills.1');
        $approved = $this->act($rx, $new, 'approve', $this->checks());
        $this->act($rx, $new, 'hold', [], 409);
        $new = $this->act($rx, $approved, 'ready', ['checks' => ['label' => true]]);
        $this->act($rx, $new, 'collected', ['occurred_on' => now()->toDateString(), 'reference' => 'Synthetic', 'counseling' => 'provided']);
        $this->assertEquals(0.125, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot))->assertUnprocessable();
    }

    public function test_quarantine_and_expiry_are_rechecked_before_handover(): void
    {
        $rx = $this->rx();
        $lot = $this->lot();
        $f = $this->act($rx, $this->fill($rx, $lot), 'approve', $this->checks());
        $this->putJson("/api/pharmacy/stock/$lot/status", ['version' => 2, 'status' => 'quarantined', 'note' => 'Synthetic recall'])->assertOk();
        $this->act($rx, $f, 'ready', ['checks' => ['label' => true]], 422);
        $this->act($rx, $f, 'cancel');
        $this->assertEquals(0, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('reserved'));
        $other = $this->rx();
        $otherLot = $this->lot();
        $f = $this->act($other, $this->fill($other, $otherLot), 'approve', $this->checks());
        $f = $this->act($other, $f, 'ready', ['checks' => ['label' => true]]);
        DB::table('pharmacy_stock_lots')->where('id', $otherLot)->update(['expires_on' => now()->subDay()->toDateString()]);
        $this->act($other, $f, 'collected', ['occurred_on' => now()->toDateString(), 'reference' => 'Synthetic', 'counseling' => 'provided'], 422);
        $this->assertEquals(50, DB::table('pharmacy_stock_lots')->where('id', $otherLot)->value('on_hand'));
    }

    public function test_controlled_and_compounded_fills_cannot_bypass_unimplemented_controls(): void
    {
        foreach ([['controlled' => true], ['compounded' => true], ['controlled' => true, 'compounded' => true]] as $flags) {
            $rx = $this->rx($flags);
            $f = $this->fill($rx, $this->lot());
            $this->act($rx, $f, 'approve', $this->checks(), 422);
            DB::table('pharmacy_fills')->where('id', $f['id'])->update(['review_status' => 'approved']);
            $this->act($rx, $f, 'ready', ['checks' => ['label' => true]], 422);
        }
        $this->assertEquals(0, DB::table('pharmacy_stock_events')->where('action', 'dispensed')->count());
    }

    public function test_shared_stock_cannot_be_over_reserved_and_units_must_match(): void
    {
        $lot = $this->lot(['quantity' => '15.000']);
        $first = $this->rx();
        $this->fill($first, $lot);
        $second = $this->rx();
        $this->postJson("/api/pharmacy/prescriptions/$second/fills", $this->fillBody($lot))->assertUnprocessable();
        $this->assertEquals(10, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('reserved'));
        $this->assertSame(1, DB::table('pharmacy_fills')->count());
        $wrongUnit = $this->lot(['quantity_unit' => 'mL']);
        $this->postJson("/api/pharmacy/prescriptions/$second/fills", $this->fillBody($wrongUnit))->assertUnprocessable();
        $this->postJson("/api/pharmacy/prescriptions/$second/fills", $this->fillBody($lot, ['ndc' => '11111-1111-11']))->assertUnprocessable();
        $expired = $this->rx(['expires_on' => now()->subDay()->toDateString()]);
        $this->postJson("/api/pharmacy/prescriptions/$expired/fills", $this->fillBody($lot))->assertUnprocessable();
    }

    public function test_preview_is_unavailable_in_production_and_firm_admin_cannot_grant_pharmacy_role(): void
    {
        $this->actor->role = 'firm_admin';
        $this->postJson('/api/admin/users', ['first_name' => 'Synthetic', 'last_name' => 'Denied', 'email' => 'blocked@example.invalid', 'password' => 'Synthetic-password-123!', 'role' => 'pharmacist'])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.invalid']);
        $this->actor->role = 'pharmacist';
        $this->app->instance('env', 'production');
        $this->getJson('/api/pharmacy/prescriptions')->assertStatus(503);
        $this->postJson('/api/pharmacy/prescriptions', $this->body())->assertStatus(503);
        $this->assertSame(0, DB::table('pharmacy_prescriptions')->count());
    }

    public function test_location_assignments_fail_closed_and_revocation_removes_existing_access(): void
    {
        $rx = $this->rx();
        $lot = $this->lot();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->delete();
        $this->getJson('/api/pharmacy/locations')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/pharmacy/prescriptions')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/pharmacy/stock')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson("/api/pharmacy/prescriptions/$rx")->assertNotFound();
        $this->postJson('/api/pharmacy/prescriptions', $this->body())->assertNotFound();
        $this->putJson("/api/pharmacy/stock/$lot/status", ['version' => 1, 'status' => 'quarantined', 'note' => 'Denied'])->assertNotFound();
        $this->getJson('/api/pharmacy/staff')->assertForbidden();
        $grant = ['location_id' => $this->location, 'user_id' => $this->actor->id, 'active' => true, 'valid_until' => now()->addMonth()->toDateString(), 'version' => 0, 'reason' => 'Synthetic assignment'];
        $this->putJson('/api/pharmacy/staff', $grant)->assertForbidden();
        $this->actor->role = 'admin';
        $this->getJson('/api/pharmacy/staff')->assertOk();
        // Database role remains pharmacist; the admin actor is only the request principal in this fixture.
        $this->putJson('/api/pharmacy/staff', $grant)->assertOk();
        $this->putJson('/api/pharmacy/staff', $grant)->assertConflict();
        $this->actor->role = 'pharmacist';
        $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk();
        $this->actor->role = 'admin';
        $this->putJson('/api/pharmacy/staff', array_replace($grant, ['version' => 1, 'active' => false]))->assertOk();
        $this->actor->role = 'pharmacist';
        $this->getJson("/api/pharmacy/prescriptions/$rx/assistant")->assertNotFound();
        $this->assertSame(2, DB::table('pharmacy_access_events')->count());
        DB::table('pharmacy_staff_assignments')->where('user_id', $this->actor->id)->update(['active' => true, 'valid_until' => now()->subDay()->toDateString()]);
        $this->getJson('/api/pharmacy/locations')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/pharmacy/cases')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_independent_patient_chart_is_private_idempotent_and_invalidates_fill_review(): void
    {
        $body = ['request_id' => (string) Str::uuid(), 'record_number' => 'SYN-CHART-1', 'location_id' => $this->location,
            'first_name' => 'Synthetic', 'last_name' => 'No portal', 'date_of_birth' => '1980-01-01', 'identity_reference' => 'SYNTHETIC identity'];
        $usersBefore = DB::table('users')->count();
        $p = $this->postJson('/api/pharmacy/patients', $body)->assertCreated()->json('data');
        $this->postJson('/api/pharmacy/patients', $body)->assertOk()->assertJsonPath('data.id', $p['id']);
        $this->postJson('/api/pharmacy/patients', array_replace($body, ['first_name' => 'Changed']))->assertConflict();
        $this->assertSame($usersBefore, DB::table('users')->count());
        $this->assertSame('unknown', $p['clinical']['allergies_status']);
        $rx = $this->rx(['patient_id' => null, 'pharmacy_patient_id' => $p['id']]);
        $this->getJson('/api/pharmacy/prescriptions')->assertOk()->assertJsonPath('data.data.0.last_name', 'No portal');
        $f = $this->fill($rx, $this->lot());
        $this->act($rx, $f, 'approve', $this->checks(), 422);
        $review = ['version' => 1, 'allergies_status' => 'none_reported', 'medications_status' => 'none_reported', 'reviewed_on' => now()->toDateString(), 'source_reference' => 'Synthetic interview'];
        $this->actor->role = 'pharmacy_technician';
        $this->putJson("/api/pharmacy/patients/{$p['id']}/clinical", $review)->assertForbidden();
        $this->actor->role = 'medical_biller';
        $this->getJson("/api/pharmacy/patients/{$p['id']}")->assertForbidden();
        $this->actor->role = 'pharmacist';
        $this->putJson("/api/pharmacy/patients/{$p['id']}/clinical", $review)->assertOk();
        $this->putJson("/api/pharmacy/patients/{$p['id']}/clinical", $review)->assertConflict();
        $f = $this->act($rx, $f, 'approve', $this->checks());
        $f = $this->act($rx, $f, 'ready', ['checks' => ['label' => true]]);
        $this->putJson("/api/pharmacy/patients/{$p['id']}/clinical", array_replace($review, ['version' => 2, 'allergies_status' => 'documented', 'allergies' => 'Synthetic new allergy']))->assertOk();
        $this->act($rx, $f, 'collected', ['occurred_on' => now()->toDateString(), 'reference' => 'Synthetic', 'counseling' => 'provided'], 422);
        $this->assertEquals(0, DB::table('pharmacy_stock_events')->where('action', 'dispensed')->count());
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => false]);
        $this->getJson("/api/pharmacy/patients/{$p['id']}")->assertNotFound();
        $this->getJson('/api/pharmacy/patients')->assertOk()->assertJsonPath('data.total', 0);
        $this->assertSame(3, DB::table('pharmacy_patient_events')->count());
    }

    public function test_compounded_intake_requires_explicit_sterility_without_enabling_dispensing(): void
    {
        $this->postJson('/api/pharmacy/prescriptions', $this->body(['compounded' => true, 'compound_type' => null]))->assertUnprocessable();
        $this->postJson('/api/pharmacy/prescriptions', $this->body(['compounded' => false, 'compound_type' => 'sterile']))->assertUnprocessable();
        $this->postJson('/api/pharmacy/prescriptions', $this->body(['compounded' => true, 'compound_type' => 'unknown']))->assertUnprocessable();
        foreach (['sterile', 'nonsterile'] as $type) {
            $rx = $this->rx(['compounded' => true, 'compound_type' => $type]);
            $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->assertJsonPath('data.compound_type', $type);
            $f = $this->fill($rx, $this->lot());
            $this->act($rx, $f, 'approve', $this->checks(), 422);
        }
        $this->assertSame(2, DB::table('pharmacy_prescriptions')->count());
        $this->assertSame(0, DB::table('pharmacy_stock_events')->where('action', 'dispensed')->count());
    }

    private function formulationBody(array $overrides = []): array
    {
        return array_replace(['request_id' => (string) Str::uuid(), 'code' => 'SYN-NOT-FOR-USE', 'name' => 'Synthetic formulation NOT FOR USE',
            'preparation_type' => 'nonsterile', 'hazardous' => false, 'strength' => 'Synthetic only', 'dosage_form' => 'tablet',
            'output_quantity' => '10.000', 'output_unit' => 'tablet', 'source_reference' => 'SYNTHETIC', 'method' => 'Not a manufacturing instruction',
            'quality_checks' => 'Synthetic review', 'storage' => 'Synthetic only', 'bud_basis' => 'No clinical BUD assigned',
            'ingredients' => [['key' => 'A', 'name' => 'Synthetic ingredient', 'quantity' => '2.000', 'unit' => 'g', 'specification' => 'NOT FOR USE']]], $overrides);
    }

    private function independentReviewer(): User
    {
        $u = User::create(['first_name' => 'Synthetic', 'last_name' => 'Reviewer', 'email' => Str::uuid().'@example.invalid', 'password' => 'synthetic-only', 'role' => 'pharmacist', 'organization_id' => 1, 'status' => 'active']);
        $u->withAccessToken(new Token(['expires_at' => now()->addHour()]));
        DB::table('pharmacy_staff_assignments')->insert(['location_id' => $this->location, 'user_id' => $u->id, 'active' => true, 'valid_until' => now()->addYear()->toDateString()]);

        return $u;
    }

    public function test_compounding_records_require_independent_review_and_never_release_stock(): void
    {
        Http::preventStrayRequests();
        $body = $this->formulationBody();
        $f = $this->postJson('/api/pharmacy/formulations', $body)->assertCreated()->json('data.id');
        $this->postJson('/api/pharmacy/formulations', $body)->assertOk()->assertJsonPath('data.id', $f);
        $this->postJson('/api/pharmacy/formulations', array_replace($body, ['method' => 'Changed']))->assertStatus(409);
        $review = ['version' => 1, 'action' => 'review', 'evidence' => 'Synthetic independent review'];
        $this->postJson("/api/pharmacy/formulations/$f/review", $review)->assertStatus(422);
        $reviewer = $this->independentReviewer();
        $this->actingAs($reviewer, 'api');
        $this->postJson("/api/pharmacy/formulations/$f/review", $review)->assertOk()->assertJsonPath('data.status', 'reviewed');
        $this->postJson("/api/pharmacy/formulations/$f/review", $review)->assertStatus(409);
        $this->actingAs($this->actor, 'api');
        $rx = $this->rx(['compounded' => true]);
        $lot = $this->lot();
        $batch = ['request_id' => (string) Str::uuid(), 'prescription_id' => $rx, 'formulation_id' => $f, 'batch_number' => 'SYN-ONLY',
            'planned_on' => now()->toDateString(), 'calculation_reference' => 'Synthetic', 'prescription_match_reference' => 'Synthetic', 'site_process_reference' => 'Synthetic',
            'ingredients' => [['key' => 'A', 'supplier' => 'Synthetic', 'lot' => 'SYN', 'expires_on' => now()->addMonth()->toDateString(), 'quantity' => '2.000', 'unit' => 'g', 'certificate_reference' => 'NOT VALID']]];
        $b = $this->postJson('/api/pharmacy/batch-worksheets', $batch)->assertCreated()->assertJsonPath('data.production_release_enabled', false)->json('data.id');
        $this->postJson('/api/pharmacy/batch-worksheets', $batch)->assertOk()->assertJsonPath('data.id', $b);
        $this->postJson("/api/pharmacy/batch-worksheets/$b/review", $review)->assertStatus(422);
        $this->actingAs($reviewer, 'api');
        $this->postJson("/api/pharmacy/batch-worksheets/$b/review", $review)->assertOk()->assertJsonPath('data.status', 'reviewed')->assertJsonPath('data.production_release_enabled', false);
        $this->postJson("/api/pharmacy/batch-worksheets/$b/review", array_replace($review, ['version' => 2]))->assertStatus(422);
        $this->assertEquals(50, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
        $this->assertEquals(0, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('reserved'));
        $this->assertSame(0, DB::table('pharmacy_fills')->count());
        $this->getJson("/api/pharmacy/formulations/$f")->assertOk()->assertJsonCount(2, 'data.events');
        DB::table('pharmacy_staff_assignments')->where('user_id', $reviewer->id)->update(['active' => false]);
        $this->getJson("/api/pharmacy/batch-worksheets/$b")->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_compounding_validation_and_scope_fail_closed(): void
    {
        $this->postJson('/api/pharmacy/formulations', $this->formulationBody(['preparation_type' => 'sterile']))->assertUnprocessable()->assertJsonValidationErrors('aseptic_process');
        $this->postJson('/api/pharmacy/formulations', $this->formulationBody(['hazardous' => true]))->assertUnprocessable()->assertJsonValidationErrors('hazard_controls');
        $this->actor->role = 'pharmacy_technician';
        $this->postJson('/api/pharmacy/formulations', $this->formulationBody())->assertForbidden();
        $this->actor->role = 'pharmacist';
        $f = $this->postJson('/api/pharmacy/formulations', $this->formulationBody())->assertCreated()->json('data.id');
        $rx = $this->rx(['compounded' => true]);
        $batch = ['request_id' => (string) Str::uuid(), 'prescription_id' => $rx, 'formulation_id' => $f, 'batch_number' => 'SYN-ONLY', 'planned_on' => now()->toDateString(),
            'calculation_reference' => 'Synthetic', 'prescription_match_reference' => 'Synthetic', 'site_process_reference' => 'Synthetic',
            'ingredients' => [['key' => 'A', 'supplier' => 'Synthetic', 'lot' => 'SYN', 'expires_on' => now()->addMonth()->toDateString(), 'quantity' => '2.000', 'unit' => 'g', 'certificate_reference' => 'NOT VALID']]];
        $this->postJson('/api/pharmacy/batch-worksheets', $batch)->assertUnprocessable();
        $reviewer = $this->independentReviewer();
        $this->actingAs($reviewer, 'api');
        $this->postJson("/api/pharmacy/formulations/$f/review", ['version' => 1, 'action' => 'review', 'evidence' => 'Synthetic'])->assertOk();
        $this->actingAs($this->actor, 'api');
        $wrong = $batch;
        $wrong['ingredients'][0]['quantity'] = '2.001';
        $this->postJson('/api/pharmacy/batch-worksheets', $wrong)->assertUnprocessable();
        $wrong = $batch;
        $wrong['ingredients'][0]['unit'] = 'mg';
        $this->postJson('/api/pharmacy/batch-worksheets', $wrong)->assertUnprocessable();
        $wrong = $batch;
        $wrong['ingredients'][0]['expires_on'] = now()->subDay()->toDateString();
        $this->postJson('/api/pharmacy/batch-worksheets', $wrong)->assertUnprocessable();
        $wrong = $batch;
        $wrong['prescription_id'] = $this->rx(['compounded' => true, 'compound_type' => 'sterile']);
        $this->postJson('/api/pharmacy/batch-worksheets', $wrong)->assertUnprocessable();
        $wrong = $batch;
        $wrong['prescription_id'] = $this->rx(['compounded' => true, 'quantity' => '11.000']);
        $this->postJson('/api/pharmacy/batch-worksheets', $wrong)->assertUnprocessable();
        $b = $this->postJson('/api/pharmacy/batch-worksheets', $batch)->assertCreated()->json('data.id');
        DB::table('pharmacy_staff_assignments')->where('user_id', $this->actor->id)->where('location_id', $this->location)->update(['active' => false]);
        $this->getJson("/api/pharmacy/batch-worksheets/$b")->assertNotFound();
        $this->postJson('/api/pharmacy/batch-worksheets', $batch)->assertNotFound();
        $this->getJson('/api/pharmacy/batch-worksheets')->assertOk()->assertJsonPath('data.total', 0);
        $this->actingAs($reviewer, 'api');
        $this->postJson("/api/pharmacy/formulations/$f/review", ['version' => 2, 'action' => 'retire', 'evidence' => 'Synthetic retirement'])->assertOk();
        $this->postJson("/api/pharmacy/batch-worksheets/$b/review", ['version' => 1, 'action' => 'review', 'evidence' => 'Synthetic'])->assertUnprocessable();
        $retry = $batch;
        $retry['request_id'] = (string) Str::uuid();
        $retry['batch_number'] = 'SYN-RETIRED';
        $this->postJson('/api/pharmacy/batch-worksheets', $retry)->assertUnprocessable();
        $revision = $this->postJson('/api/pharmacy/formulations', $this->formulationBody(['preparation_type' => 'sterile', 'aseptic_process' => 'SYNTHETIC NOT FOR USE', 'hazardous' => true, 'hazard_controls' => 'SYNTHETIC NOT FOR USE']))->assertCreated();
        $revision->assertJsonPath('data.revision', 2)->assertJsonPath('data.status', 'draft');
        $this->assertSame(1, DB::table('pharmacy_batch_worksheets')->count());
        $this->assertSame(1, DB::table('pharmacy_compounding_events')->where('batch_id', $b)->count());
        $reviewer->organization_id = 2;
        $this->getJson("/api/pharmacy/formulations/$f")->assertForbidden();
        $foreign = DB::table('pharmacy_locations')->insertGetId(['organization_id' => 2, 'name' => 'Other tenant synthetic', 'address' => 'NOT REAL', 'license_reference' => 'NOT VALID']);
        DB::table('pharmacy_staff_assignments')->insert(['location_id' => $foreign, 'user_id' => $reviewer->id, 'active' => true, 'valid_until' => now()->addYear()->toDateString()]);
        $this->getJson("/api/pharmacy/formulations/$f")->assertNotFound();
        $this->getJson("/api/pharmacy/batch-worksheets/$b")->assertNotFound();
        $this->getJson('/api/pharmacy/formulations')->assertOk()->assertJsonPath('data.total', 0);
    }

    private function ingredientReceipt(array $overrides = []): array
    {
        return array_replace(['request_id' => (string) Str::uuid(), 'location_id' => $this->location, 'ingredient_name' => 'Synthetic ingredient', 'supplier' => 'Synthetic', 'lot_number' => 'SYN',
            'quantity_unit' => 'g', 'quantity' => '5.000', 'expires_on' => now()->addMonth()->toDateString(), 'specification' => 'NOT FOR USE', 'certificate_reference' => 'NOT VALID', 'receipt_reference' => 'SYN RECEIPT'], $overrides);
    }

    private function reviewedWorksheet(bool $twoIngredients = false): int
    {
        $body = $this->formulationBody();
        if ($twoIngredients) {
            $body['ingredients'][] = array_replace($body['ingredients'][0], ['key' => 'B']);
        }
        $f = $this->postJson('/api/pharmacy/formulations', $body)->assertCreated()->json('data.id');
        $reviewer = $this->independentReviewer();
        $this->actingAs($reviewer, 'api');
        $this->postJson("/api/pharmacy/formulations/$f/review", ['version' => 1, 'action' => 'review', 'evidence' => 'Synthetic'])->assertOk();
        $this->actingAs($this->actor, 'api');
        $rx = $this->rx(['compounded' => true]);
        $line = ['key' => 'A', 'supplier' => 'Synthetic', 'lot' => 'SYN', 'expires_on' => now()->addMonth()->toDateString(), 'quantity' => '2.000', 'unit' => 'g', 'certificate_reference' => 'NOT VALID'];
        $b = $this->postJson('/api/pharmacy/batch-worksheets', ['request_id' => (string) Str::uuid(), 'prescription_id' => $rx, 'formulation_id' => $f, 'batch_number' => 'SYN-'.Str::uuid(),
            'planned_on' => now()->toDateString(), 'calculation_reference' => 'Synthetic', 'prescription_match_reference' => 'Synthetic', 'site_process_reference' => 'Synthetic',
            'ingredients' => $twoIngredients ? [$line, array_replace($line, ['key' => 'B'])] : [$line]])->assertCreated()->json('data.id');
        $this->actingAs($reviewer, 'api');
        $this->postJson("/api/pharmacy/batch-worksheets/$b/review", ['version' => 1, 'action' => 'review', 'evidence' => 'Synthetic'])->assertOk();
        $this->actingAs($this->actor, 'api');

        return $b;
    }

    public function test_ingredient_custody_reservations_and_release_are_exact_and_replay_safe(): void
    {
        $b = $this->reviewedWorksheet();
        $body = $this->ingredientReceipt();
        $lot = $this->postJson('/api/pharmacy/ingredient-lots', $body)->assertCreated()->assertJsonPath('data.status', 'quarantined')->json('data.id');
        $this->postJson('/api/pharmacy/ingredient-lots', $body)->assertOk()->assertJsonPath('data.id', $lot);
        $this->postJson('/api/pharmacy/ingredient-lots', array_replace($body, ['request_id' => (string) Str::uuid()]))->assertStatus(409);
        $this->postJson('/api/pharmacy/ingredient-lots', array_replace($body, ['quantity' => '6.000']))->assertStatus(409);
        $reserve = ['version' => 2, 'action' => 'reserve', 'evidence' => 'Synthetic reconciliation', 'lots' => [['key' => 'A', 'lot_id' => $lot]]];
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", $reserve)->assertUnprocessable();
        $status = ['version' => 1, 'status' => 'available', 'evidence' => 'Synthetic receiving review'];
        $this->actor->role = 'pharmacy_technician';
        $this->postJson("/api/pharmacy/ingredient-lots/$lot/status", $status)->assertForbidden();
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", $reserve)->assertForbidden();
        $this->actor->role = 'pharmacist';
        $this->postJson("/api/pharmacy/ingredient-lots/$lot/status", $status)->assertOk();
        $this->postJson("/api/pharmacy/ingredient-lots/$lot/status", $status)->assertStatus(409);
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", $reserve)->assertOk()->assertJsonPath('data.version', 3)->assertJsonPath('data.production_release_enabled', false);
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", $reserve)->assertStatus(409);
        $this->assertEquals(2, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('reserved'));
        $this->assertEquals(5, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('on_hand'));
        $this->postJson("/api/pharmacy/ingredient-lots/$lot/status", ['version' => 3, 'status' => 'quarantined', 'evidence' => 'Synthetic hold'])->assertOk();
        $this->getJson("/api/pharmacy/batch-worksheets/$b")->assertOk()->assertJsonPath('data.allocations.0.lot_status', 'quarantined');
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", ['version' => 3, 'action' => 'release', 'evidence' => 'Synthetic cancelled plan'])->assertOk()->assertJsonPath('data.allocations.0.status', 'released');
        $this->assertEquals(0, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('reserved'));
        $this->assertEquals(5, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('on_hand'));
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", array_replace($reserve, ['version' => 4]))->assertUnprocessable();
        $this->assertSame(1, DB::table('pharmacy_ingredient_events')->where('action', 'reserved')->count());
        $this->assertSame(1, DB::table('pharmacy_ingredient_events')->where('action', 'reservation_released')->count());
        $this->assertSame(0, DB::table('pharmacy_stock_lots')->count());
        $this->assertSame(0, DB::table('pharmacy_fills')->count());
    }

    public function test_ingredient_allocation_rolls_back_all_lines_on_shortage_and_checks_scope(): void
    {
        $b = $this->reviewedWorksheet(true);
        $lot = $this->postJson('/api/pharmacy/ingredient-lots', $this->ingredientReceipt(['quantity' => '3.999']))->assertCreated()->json('data.id');
        $this->postJson("/api/pharmacy/ingredient-lots/$lot/status", ['version' => 1, 'status' => 'available', 'evidence' => 'Synthetic'])->assertOk();
        $body = ['version' => 2, 'action' => 'reserve', 'evidence' => 'Synthetic', 'lots' => [['key' => 'A', 'lot_id' => $lot], ['key' => 'B', 'lot_id' => $lot]]];
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", $body)->assertUnprocessable();
        $this->assertEquals(0, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('reserved'));
        $this->assertSame(0, DB::table('pharmacy_ingredient_allocations')->count());
        $this->assertSame(0, DB::table('pharmacy_ingredient_events')->where('action', 'reserved')->count());
        $this->assertEquals(2, DB::table('pharmacy_batch_worksheets')->where('id', $b)->value('version'));
        $foreign = $this->postJson('/api/pharmacy/ingredient-lots', $this->ingredientReceipt(['location_id' => $this->otherLocation]))->assertCreated()->json('data.id');
        $body['lots'][0]['lot_id'] = $foreign;
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", $body)->assertNotFound();
        DB::table('pharmacy_staff_assignments')->where('user_id', $this->actor->id)->where('location_id', $this->location)->update(['active' => false]);
        $this->getJson("/api/pharmacy/ingredient-lots/$lot")->assertNotFound();
        $this->getJson('/api/pharmacy/ingredient-lots')->assertOk()->assertJsonPath('data.total', 1);
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", $body)->assertNotFound();
    }

    public function test_ingredient_mismatch_and_expiration_cannot_be_reserved(): void
    {
        $b = $this->reviewedWorksheet();
        $lot = $this->postJson('/api/pharmacy/ingredient-lots', $this->ingredientReceipt(['specification' => 'Different grade']))->assertCreated()->json('data.id');
        $this->postJson("/api/pharmacy/ingredient-lots/$lot/status", ['version' => 1, 'status' => 'available', 'evidence' => 'Synthetic'])->assertOk();
        $body = ['version' => 2, 'action' => 'reserve', 'evidence' => 'Synthetic', 'lots' => [['key' => 'A', 'lot_id' => $lot]]];
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", $body)->assertUnprocessable();
        $good = $this->postJson('/api/pharmacy/ingredient-lots', $this->ingredientReceipt(['receipt_reference' => 'SYN SECOND']))->assertCreated()->json('data.id');
        DB::table('pharmacy_ingredient_lots')->where('id', $good)->update(['expires_on' => now()->subDay()->toDateString()]);
        $this->postJson("/api/pharmacy/ingredient-lots/$good/status", ['version' => 1, 'status' => 'available', 'evidence' => 'Synthetic'])->assertUnprocessable();
        $this->travel(2)->days();
        $this->actor->withAccessToken(new Token(['expires_at' => now()->addHour()]));
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", $body)->assertUnprocessable();
        $this->travelBack();
        $this->assertSame(0, DB::table('pharmacy_ingredient_allocations')->count());
    }
}
