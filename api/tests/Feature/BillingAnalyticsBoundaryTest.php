<?php
namespace Tests\Feature;

use App\Models\{Invoice, Payment, ClaimAppeal, CaseModel, User};
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Token;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class BillingAnalyticsBoundaryTest extends TestCase
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

    private function actor() { return $this->actor; }
    private function cases() { return $this->cases; }

    public function test_analytics_denies_clients_other_roles_and_missing_organization_context(): void
    {
        $actor = $this->actor();
        foreach (['client','attorney','pharmacist','pharmacy_technician'] as $role) {
            $actor->role = $role;
            $this->getJson('/api/billing/analytics')->assertForbidden();
        }
        foreach (['provider_staff','medical_biller','firm_admin','admin'] as $role) {
            $actor->role = $role; $actor->organization_id = 1;
            $this->getJson('/api/billing/analytics')->assertOk()->assertJsonPath('data.counts.invoices', 0)
                ->assertJsonPath('data.metrics.denial_rate', null)->assertJsonPath('data.metrics.appeal_success_rate', null)
                ->assertJsonPath('data.metrics.avg_process_time', null)->assertJsonCount(0, 'data.payer_trends');
            $actor->organization_id = null;
            $this->getJson('/api/billing/analytics')->assertForbidden();
        }
    }

    public function test_client_cannot_read_staff_billing_and_appeals_cannot_cross_invoice_boundaries(): void
    {
        $this->actor->role = 'client';
        foreach (['stats', 'appeals', 'validation'] as $path) {
            $this->getJson('/api/billing/'.$path)->assertForbidden();
        }
        $this->actor->role = 'medical_biller';
        $foreign = Invoice::withoutEvents(fn () => Invoice::create(['organization_id' => 2, 'case_id' => $this->cases[2]->id, 'invoice_number' => 'SYN-FOREIGN-APPEAL', 'amount' => 100, 'status' => 'denied']));
        $inconsistent = Invoice::withoutEvents(fn () => Invoice::create(['organization_id' => 1, 'case_id' => $this->cases[2]->id, 'invoice_number' => 'SYN-BAD-APPEAL', 'amount' => 100, 'status' => 'denied']));
        foreach ([$foreign, $inconsistent] as $invoice) {
            $this->postJson('/api/billing/appeals/generate', ['invoice_id' => $invoice->id, 'reason_category' => 'synthetic'])->assertNotFound();
            ClaimAppeal::create(['organization_id' => 1, 'invoice_id' => $invoice->id, 'appeal_number' => 'SYN-HIDDEN-'.$invoice->id, 'reason_category' => 'synthetic', 'content' => 'SYNTHETIC', 'status' => 'draft']);
        }
        $this->getJson('/api/billing/appeals')->assertOk()->assertJsonPath('data.total', 0);
        $this->assertSame(2, ClaimAppeal::count());
    }

    public function test_organization_context_is_required_and_validation_rejects_foreign_and_inconsistent_invoices(): void
    {
        $this->actor->role = 'admin'; $this->actor->organization_id = null;
        foreach (['stats', 'appeals', 'validation'] as $path) $this->getJson('/api/billing/'.$path)->assertForbidden();
        $this->actor->organization_id = 1;
        foreach ([[1,1],[2,2],[1,2]] as $i => [$org,$caseOrg]) {
            $invoice = Invoice::withoutEvents(fn () => Invoice::create(['organization_id'=>$org,'case_id'=>$this->cases[$caseOrg]->id,'invoice_number'=>'SYN-VALIDATION-'.$i,'amount'=>100,'status'=>'denied']));
            $issue = \App\Models\ValidationIssue::create(['organization_id'=>$org,'invoice_id'=>$invoice->id,'issue_type'=>'synthetic','severity'=>'high','description'=>'SYNTHETIC','status'=>'pending']);
            if ($i > 0) $this->putJson('/api/billing/validation/'.$issue->id, ['status'=>'resolved'])->assertNotFound();
        }
        $this->getJson('/api/billing/validation')->assertOk()->assertJsonPath('data.issues.total',1)->assertJsonPath('data.stats.total',1);
    }

    public function test_analytics_scopes_rates_and_signed_receipts_to_consistent_current_organization_and_year(): void
    {
        $this->travelTo(now()->setDate(2026, 1, 15)->startOfDay());
        $this->actor()->withAccessToken(new \Laravel\Passport\Token(['expires_at' => now()->addHour()]));
        $cases = $this->cases();
        $create = fn ($org,$case,$number,$status) => Invoice::withoutEvents(fn () => Invoice::create(['organization_id'=>$org,'case_id'=>$case,'invoice_number'=>$number,'amount'=>1000,'status'=>$status]));
        $own = $create(1,$cases[1]->id,'SYN-OWN','denied');
        $create(1,$cases[1]->id,'SYN-SENT','sent');
        $foreign = $create(2,$cases[2]->id,'SYN-FOREIGN','paid');
        $bad = $create(1,$cases[2]->id,'SYN-BAD-CASE','paid');
        foreach ([[$own,1,'accepted'],[$own,1,'draft'],[$foreign,2,'accepted'],[$foreign,1,'accepted'],[$bad,1,'accepted']] as $i=>[$invoice,$org,$status]) {
            ClaimAppeal::create(['organization_id'=>$org,'invoice_id'=>$invoice->id,'appeal_number'=>'SYN-AUDIT-'.$i,'reason_category'=>'synthetic','content'=>'SYNTHETIC','status'=>$status]);
        }
        foreach ([[$own,1,'0.10','2026-01-01'],[$own,1,'0.20','2026-01-31'],[$own,1,'-0.10','2026-01-20'],[$own,1,'90.00','2025-01-20'],[$own,1,'90.00','2026-02-01'],[$foreign,1,'90.00','2026-01-20'],[$own,2,'90.00','2026-01-20'],[$bad,1,'90.00','2026-01-20']] as $i=>[$invoice,$org,$amount,$date]) {
            Payment::create(['organization_id'=>$org,'invoice_id'=>$invoice->id,'amount'=>$amount,'payment_method'=>'check','transaction_id'=>'SYN-RECEIPT-'.$i,'payment_date'=>$date]);
        }
        $before = DB::table('payments')->get()->toJson();
        foreach (['provider_staff','firm_admin','admin'] as $role) {
            $this->actor()->role = $role;
            $this->getJson('/api/billing/analytics')->assertOk()->assertJsonPath('data.counts.invoices',2)
                ->assertJsonPath('data.counts.appeals',2)->assertJsonPath('data.metrics.denial_rate',50)
                ->assertJsonPath('data.metrics.appeal_success_rate',50)->assertJsonPath('data.metrics.revenue_recovery',0.2)
                ->assertJsonPath('data.denial_reasons.0.count',2)->assertJsonPath('data.basis.period_start','2026-01-01')
                ->assertJsonPath('data.basis.period_end_exclusive','2026-02-01');
        }
        $this->assertSame($before, DB::table('payments')->get()->toJson());
    }
}
