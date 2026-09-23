<?php

namespace Tests\Feature;

use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Token;
use Tests\TestCase;

class InvoiceCaseBoundaryTest extends TestCase
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

    public function test_provider_can_save_draft_but_cannot_forge_ownership_or_payment_status(): void
    {
        $this->postJson('/api/invoices', ['case_id' => $this->cases[1]->id, 'organization_id' => 2, 'amount' => 123.45, 'status' => 'draft', 'metadata' => ['patient_name' => 'Synthetic Patient']])->assertCreated()->assertJsonPath('data.organization_id', 1);
        $this->assertDatabaseHas('invoices', ['case_id' => $this->cases[1]->id, 'organization_id' => 1, 'amount' => 123.45, 'status' => 'draft']);
        foreach (['paid', 'voided', 'denied'] as $status) {
            $this->postJson('/api/invoices', ['case_id' => $this->cases[1]->id, 'amount' => 123.45, 'status' => $status])->assertUnprocessable();
        }
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_provider_dashboard_counts_saved_records_and_does_not_invent_a_payer(): void
    {
        $this->postJson('/api/invoices', ['case_id' => $this->cases[1]->id, 'amount' => 125.50, 'status' => 'draft'])->assertCreated();
        $this->getJson('/api/provider/stats')->assertOk()
            ->assertJsonPath('data.stats.total_claims', 1)
            ->assertJsonPath('data.stats.pending_claims', 1)
            ->assertJsonPath('data.recent_claims.0.payer', 'Not recorded');
    }

    public function test_other_organization_missing_and_archived_cases_are_rejected(): void
    {
        $this->cases[1]->delete();
        foreach ([$this->cases[1]->id, $this->cases[2]->id, 99999, ['invalid']] as $caseId) {
            $this->postJson('/api/invoices', ['case_id' => $caseId, 'amount' => 100])->assertUnprocessable();
        }
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_provider_without_organization_cannot_create_an_invoice(): void
    {
        $this->actor->organization_id = null;
        $this->postJson('/api/invoices', ['case_id' => $this->cases[1]->id, 'amount' => 100])->assertForbidden();
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_administrator_invoice_belongs_to_selected_case_organization(): void
    {
        $this->actor->role = 'admin';
        $this->postJson('/api/invoices', ['case_id' => $this->cases[2]->id, 'amount' => 100])->assertCreated()->assertJsonPath('data.organization_id', 2);
        $this->assertDatabaseHas('invoices', ['case_id' => $this->cases[2]->id, 'organization_id' => 2]);
    }
}
