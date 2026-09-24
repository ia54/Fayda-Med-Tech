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
    }

    private function body(array $overrides = []): array
    {
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
        $this->postJson("/api/pharmacy/prescriptions/$expired/fills",$this->fillBody($lot))->assertUnprocessable();
    }

    public function test_preview_is_unavailable_in_production_and_firm_admin_cannot_grant_pharmacy_role(): void
    {
        $this->actor->role = 'firm_admin';
        $this->postJson('/api/admin/users',['first_name' => 'Synthetic', 'last_name' => 'Denied', 'email' => 'blocked@example.invalid', 'password' => 'Synthetic-password-123!', 'role' => 'pharmacist'])->assertForbidden();
        $this->assertDatabaseMissing('users',['email' => 'blocked@example.invalid']);
        $this->actor->role = 'pharmacist';
        $this->app->instance('env','production');
        $this->getJson('/api/pharmacy/prescriptions')->assertStatus(503);
        $this->postJson('/api/pharmacy/prescriptions',$this->body())->assertStatus(503);
        $this->assertSame(0,DB::table('pharmacy_prescriptions')->count());
    }
}
