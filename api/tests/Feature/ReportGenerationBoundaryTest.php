<?php
namespace Tests\Feature;

use App\Models\{User, CaseModel, CaseParty, Provider, Invoice, Payment, AuditLog};
use Illuminate\Support\Facades\{Artisan, DB};
use Laravel\Passport\Token;
use Tests\TestCase;

class ReportGenerationBoundaryTest extends TestCase
{
    private User $actor;
    private array $cases = [];
    private array $providers = [];
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\TwoFactorMiddleware::class);
        Artisan::call('migrate', ['--force'=>true]);
        foreach ([1,2] as $id) DB::table('organizations')->insert(['id'=>$id,'org_name'=>'Synthetic '.$id,'org_type'=>'provider','subscription_plan'=>'test','email'=>"generation$id@example.invalid"]);
        $this->actor=User::create(['first_name'=>'Synthetic','last_name'=>'Reporter','email'=>'generation@example.invalid','password'=>'synthetic-only','role'=>'admin','organization_id'=>1,'status'=>'active']);
        foreach ([1,2] as $org) {
            $this->cases[$org]=CaseModel::withoutEvents(fn()=>CaseModel::create(['organization_id'=>$org,'case_number'=>'GEN-'.$org,'title'=>'Synthetic '.$org,'created_by'=>$this->actor->id]));
            $this->providers[$org]=Provider::withoutEvents(fn()=>Provider::create(['organization_id'=>$org,'name'=>'Synthetic provider '.$org]));
            Invoice::withoutEvents(fn()=>Invoice::create(['organization_id'=>$org,'case_id'=>$this->cases[$org]->id,'invoice_number'=>'GEN-'.$org,'amount'=>$org===1?125.35:999,'status'=>'sent','metadata'=>['provider_id'=>$this->providers[1]->id]]));
            AuditLog::create(['organization_id'=>$org,'user_id'=>$this->actor->id,'event'=>'PHI_ACCESS','auditable_type'=>'App\\Models\\Document','auditable_id'=>$org]);
        }
        foreach ([70.25,-70.25,60.25] as $i=>$amount) Payment::create(['organization_id'=>1,'invoice_id'=>1,'amount'=>$amount,'payment_method'=>'check','payment_date'=>'2026-09-24','transaction_id'=>'GEN-'.$i]);
        Payment::create(['organization_id'=>2,'invoice_id'=>2,'amount'=>999,'payment_method'=>'check','payment_date'=>'2026-09-24','transaction_id'=>'FOREIGN']);
        $this->actor->withAccessToken(new Token(['expires_at'=>now()->addHour()]));
        $this->actingAs($this->actor,'api');
    }
    public function test_provider_billing_uses_invoice_links_and_net_receipts_within_the_organization(): void
    {
        foreach (['admin','firm_admin','medical_biller','provider_staff'] as $role) {
            $this->actor->role=$role;
            $r=$this->getJson('/api/reports/provider-billing')->assertOk()->assertJsonCount(1,'data.result_summary.billing');
            $row=$r->json('data.result_summary.billing.0');
            $this->assertEquals(125.35,$row['total_billed']);
            $this->assertEquals(60.25,$row['total_collected']);
            $this->assertEquals(65.10,$row['outstanding']);
            $this->assertSame(1,$row['invoice_count']);
        }
    }
    public function test_platform_admin_reports_use_active_organization_and_reject_missing_context(): void
    {
        $this->getJson('/api/reports/case-status')->assertOk()->assertJsonPath('data.result_summary.total_cases',1);
        $this->getJson('/api/reports/referral-source')->assertOk()->assertJsonPath('data.result_summary.total_cases',1);
        $this->getJson('/api/reports/revenue-by-period')->assertOk()->assertJsonPath('data.result_summary.invoice_count',1)->assertJsonPath('data.result_summary.payment_count',3);
        $this->getJson('/api/reports/collection-rate')->assertOk()->assertJsonPath('data.result_summary.invoice_count',1);
        foreach (['user-activity-log','document-audit-trail','hipaa-compliance-log'] as $name) $this->getJson('/api/reports/'.$name)->assertOk()->assertJsonPath('data.total',1);
        $this->actor->organization_id=null;
        foreach (['case-status','referral-source','revenue-by-period','collection-rate','provider-billing','insurance-aging','lien-summary','ocr-processing-log','signature-activity','user-activity-log','document-audit-trail','hipaa-compliance-log'] as $name) $this->getJson('/api/reports/'.$name)->assertForbidden();
    }
    public function test_case_and_referral_reports_respect_attorney_assignment(): void
    {
        $this->actor->role='attorney';
        $unassigned=CaseModel::withoutEvents(fn()=>CaseModel::create(['organization_id'=>1,'case_number'=>'GEN-UNASSIGNED','title'=>'Unassigned synthetic','created_by'=>$this->actor->id]));
        CaseParty::create(['case_id'=>$this->cases[1]->id,'user_id'=>$this->actor->id,'role_in_case'=>'attorney']);
        $this->getJson('/api/reports/case-status')->assertOk()->assertJsonPath('data.result_summary.total_cases',1)->assertJsonPath('data.result_summary.cases.0.id',$this->cases[1]->id);
        $this->getJson('/api/reports/case-status?attorney_id=999')->assertOk()->assertJsonPath('data.result_summary.total_cases',0);
        $this->getJson('/api/reports/referral-source')->assertOk()->assertJsonPath('data.result_summary.total_cases',1);
    }
}
