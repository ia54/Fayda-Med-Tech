<?php

namespace Tests\Feature;

use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Token;
use Tests\TestCase;

class PaymentReconciliationTest extends TestCase
{
    private User $actor;
    private array $cases = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\TwoFactorMiddleware::class);
        Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
        foreach ([1, 2] as $id) {
            DB::table('organizations')->insert(['id' => $id, 'org_name' => 'Synthetic '.$id, 'org_type' => 'provider', 'subscription_plan' => 'test', 'email' => "org$id@example.invalid"]);
        }
        $this->actor = User::create(['first_name' => 'Synthetic', 'last_name' => 'Provider', 'email' => 'actor@example.invalid', 'password' => 'synthetic-only', 'role' => 'provider_staff', 'organization_id' => 1, 'status' => 'active']);
        foreach ([1, 2] as $org) {
            $this->cases[$org] = CaseModel::withoutEvents(fn () => CaseModel::create(['organization_id' => $org, 'case_number' => 'SYN-'.$org, 'title' => 'Synthetic case', 'created_by' => $this->actor->id]));
        }
        $this->actor->withAccessToken(new Token(['expires_at' => now()->addHour()]));
        $this->actingAs($this->actor, 'api');
    }

    public function test_partial_full_duplicate_and_excess_payments_reconcile_without_moving_money(): void
    {
        $this->actor->role = 'medical_biller';
        $id = $this->postJson('/api/invoices', ['case_id' => $this->cases[1]->id, 'amount' => 100.25, 'status' => 'sent'])->assertCreated()->json('data.id');
        $this->getJson('/api/invoices?status=sent,denied')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/invoices?status=invalid')->assertUnprocessable();
        $body = ['invoice_id' => $id, 'amount' => 40.10, 'payment_method' => 'check', 'transaction_id' => 'QA-ONE', 'payment_date' => now()->toDateString()];
        $payment = $this->postJson('/api/payments', $body)->assertCreated()->json('data.id');
        $this->assertDatabaseHas('invoices', ['id' => $id, 'status' => 'sent']);
        $this->postJson('/api/payments', $body)->assertStatus(409);
        $this->postJson('/api/payments', array_replace($body, ['transaction_id' => 'QA-TWO', 'amount' => 61]))->assertUnprocessable();
        $this->postJson('/api/payments', array_replace($body, ['transaction_id' => 'QA-TWO', 'amount' => 1.234]))->assertUnprocessable();
        $this->postJson('/api/payments', array_replace($body, ['transaction_id' => 'QA-TWO', 'amount' => 60.15]))->assertCreated();
        $this->assertDatabaseHas('invoices', ['id' => $id, 'status' => 'paid']);
        $this->getJson('/api/invoices/'.$id)->assertOk()->assertJsonPath('data.total_paid', 100.25);
        $this->getJson('/api/payments?search=QA-ONE')->assertOk()->assertJsonPath('data.total', 1);
        $this->deleteJson('/api/payments/'.$payment)->assertStatus(409);
        $this->deleteJson('/api/invoices/'.$id)->assertStatus(409);
        $this->putJson('/api/invoices/'.$id, ['amount' => 200])->assertStatus(409);
        $this->putJson('/api/invoices/'.$id, ['status' => 'voided'])->assertStatus(409);
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_payment_roles_organization_and_invoice_ownership_are_enforced(): void
    {
        $this->actor->role = 'admin';
        $id = $this->postJson('/api/invoices', ['case_id' => $this->cases[2]->id, 'amount' => 100, 'status' => 'sent'])->assertCreated()->json('data.id');
        $body = ['invoice_id' => $id, 'amount' => 10, 'payment_method' => 'cash', 'transaction_id' => 'QA-ORG', 'payment_date' => now()->toDateString()];
        $payment = $this->postJson('/api/payments', $body)->assertCreated()->assertJsonPath('data.organization_id', 2)->json('data.id');
        $this->actor->role = 'medical_biller';
        $this->postJson('/api/payments', $body)->assertNotFound();
        $this->getJson('/api/payments/'.$payment)->assertNotFound();
        $this->getJson('/api/payments')->assertOk()->assertJsonPath('data.total', 0);
        $this->actor->organization_id = null;
        $this->postJson('/api/payments', $body)->assertForbidden();
        $this->getJson('/api/payments')->assertForbidden();
        $this->actor->organization_id = 2;
        foreach (['client', 'provider_staff', 'attorney'] as $role) {
            $this->actor->role = $role;
            $this->postJson('/api/payments', $body)->assertForbidden();
        }
        $this->actor->role = 'client';
        $this->postJson('/api/client/payments', $body)->assertForbidden();
        $this->getJson('/api/payments/'.$payment)->assertNotFound();
    }
}
