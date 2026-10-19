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

    public function test_search_pagination_and_details_preserve_organization_boundaries(): void
    {
        $first = $this->postJson('/api/invoices', ['case_id' => $this->cases[1]->id, 'amount' => 10, 'metadata' => ['patient_name' => 'Unique Patient']])->assertCreated()->json('data.id');
        $this->postJson('/api/invoices', ['case_id' => $this->cases[1]->id, 'amount' => 20])->assertCreated();
        $foreign = \App\Models\Invoice::withoutEvents(fn () => \App\Models\Invoice::create(['organization_id' => 2, 'case_id' => $this->cases[2]->id, 'invoice_number' => 'FOREIGN', 'amount' => 90, 'status' => 'draft', 'metadata' => ['patient_name' => 'Unique Patient']]));
        $this->getJson('/api/invoices?search=Unique')->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', $first);
        $this->getJson('/api/invoices?search=SYN-1')->assertOk()->assertJsonPath('data.total', 2);
        $this->getJson('/api/invoices?search=FOREIGN')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/invoices?per_page=1&page=2')->assertOk()->assertJsonPath('data.current_page', 2)->assertJsonPath('data.total', 2)->assertJsonCount(1, 'data.data');
        $this->getJson('/api/invoices?per_page=1000')->assertUnprocessable();
        $this->getJson('/api/invoices/'.$foreign->id)->assertNotFound();
        $this->actor->organization_id = null;
        $this->getJson('/api/invoices')->assertForbidden();
        $this->getJson('/api/invoices/'.$first)->assertForbidden();
    }

    public function test_provider_draft_can_be_edited_then_locked_for_review(): void
    {
        $id = $this->postJson('/api/invoices', ['case_id' => $this->cases[1]->id, 'amount' => 10])->assertCreated()->json('data.id');
        $body = ['amount' => 45.25, 'status' => 'draft', 'metadata' => ['patient_name' => 'Synthetic', 'service_date' => '2026-09-23']];
        $url = '/api/provider/invoices/'.$id.'/draft';
        $this->putJson($url, $body)->assertOk()->assertJsonPath('data.amount', '45.25');
        foreach ([['case_id' => $this->cases[2]->id], ['organization_id' => 2], ['paid_at' => '2026-09-23'], ['status' => 'paid'], ['amount' => 1.234]] as $invalid) {
            $this->putJson($url, array_replace($body, $invalid))->assertUnprocessable();
        }
        $this->putJson($url, array_replace($body, ['status' => 'sent']))->assertOk()->assertJsonPath('data.status', 'sent');
        $this->putJson($url, $body)->assertStatus(409);
        $this->assertDatabaseHas('invoices', ['id' => $id, 'organization_id' => 1, 'case_id' => $this->cases[1]->id, 'status' => 'sent', 'amount' => 45.25]);
    }

    public function test_provider_draft_endpoint_rejects_other_organizations_and_roles(): void
    {
        $invoice = \App\Models\Invoice::withoutEvents(fn () => \App\Models\Invoice::create(['organization_id' => 2, 'case_id' => $this->cases[2]->id, 'invoice_number' => 'OTHER-DRAFT', 'amount' => 10, 'status' => 'draft']));
        $body = ['amount' => 45, 'status' => 'draft', 'metadata' => ['patient_name' => 'Synthetic', 'service_date' => '2026-09-23']];
        $url = '/api/provider/invoices/'.$invoice->id.'/draft';
        $this->putJson($url, $body)->assertNotFound();
        $this->actor->organization_id = null;
        $this->putJson($url, $body)->assertForbidden();
        $this->actor->organization_id = 2;
        $this->actor->role = 'client';
        $this->putJson($url, $body)->assertForbidden();
    }

    public function test_review_return_resubmit_and_completion_preserve_history(): void
    {
        $id = $this->postJson('/api/invoices', ['case_id' => $this->cases[1]->id, 'amount' => 50, 'status' => 'sent'])->assertCreated()->json('data.id');
        $url = '/api/invoices/'.$id.'/review';
        foreach (['client', 'attorney', 'provider_staff'] as $role) {
            $this->actor->role = $role;
            $this->postJson($url, ['action' => 'reviewed', 'note' => 'Synthetic'])->assertForbidden();
        }
        $this->actor->role = 'medical_biller';
        $this->actor->organization_id = 2;
        $this->postJson($url, ['action' => 'reviewed', 'note' => 'Synthetic'])->assertNotFound();
        $this->actor->organization_id = 1;
        $this->postJson($url, ['action' => 'reviewed'])->assertUnprocessable();
        $this->postJson($url, ['action' => 'return', 'note' => 'Correct codes'])->assertOk()->assertJsonPath('data.status', 'draft');
        $this->postJson($url, ['action' => 'reviewed', 'note' => 'Premature'])->assertStatus(409);
        $this->actor->role = 'provider_staff';
        $this->putJson('/api/provider/invoices/'.$id.'/draft', ['amount' => 50, 'status' => 'sent', 'metadata' => ['patient_name' => 'Synthetic', 'service_date' => '2026-09-23']])->assertOk();
        $this->actor->role = 'medical_biller';
        $this->postJson($url, ['action' => 'reviewed', 'note' => 'Codes checked'])->assertOk()->assertJsonPath('data.metadata.billing_review.state', 'reviewed')->assertJsonCount(2, 'data.metadata.billing_review_history');
        $this->postJson($url, ['action' => 'reviewed', 'note' => 'Duplicate'])->assertStatus(409);
        $this->assertDatabaseHas('invoices', ['id' => $id, 'status' => 'sent']);
        $this->putJson('/api/invoices/'.$id, ['metadata' => ['payer' => 'Synthetic payer']])->assertOk()->assertJsonCount(2, 'data.metadata.billing_review_history');
        $this->putJson('/api/invoices/'.$id, ['metadata' => ['billing_review_history' => []]])->assertUnprocessable();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_review_state_cannot_be_forged_during_invoice_creation(): void
    {
        $this->postJson('/api/invoices', ['case_id' => $this->cases[1]->id, 'amount' => 10, 'metadata' => ['billing_review' => ['state' => 'reviewed']]])->assertUnprocessable();
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
