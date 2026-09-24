<?php
namespace Tests\Feature;

use App\Models\{User, CaseModel, CaseParty, Provider, Invoice, Payment, AuditLog, Lien};
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
    public function test_insurance_aging_uses_net_balances_and_calendar_day_buckets(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-24 15:00:00'));
        Invoice::withoutGlobalScopes()->whereKey(1)->update(['created_at'=>now()->subDays(30)->startOfDay()]);
        foreach ([31,60,61,90,91] as $days) {
            $invoice=Invoice::withoutEvents(fn()=>Invoice::create(['organization_id'=>1,'case_id'=>$this->cases[1]->id,'invoice_number'=>'AGE-'.$days,'amount'=>10.01,'status'=>'sent']));
            DB::table('invoices')->where('id',$invoice->id)->update(['created_at'=>now()->subDays($days)->startOfDay()]);
        }
        $paid=Invoice::withoutEvents(fn()=>Invoice::create(['organization_id'=>1,'case_id'=>$this->cases[1]->id,'invoice_number'=>'AGE-ZERO','amount'=>25,'status'=>'sent','metadata'=>['payer'=>'Fully paid']]));
        Payment::create(['organization_id'=>1,'invoice_id'=>$paid->id,'amount'=>25,'payment_method'=>'check','payment_date'=>'2026-09-24','transaction_id'=>'AGE-ZERO']);
        // A mismatched foreign receipt must not reduce this organization's balance.
        Payment::create(['organization_id'=>2,'invoice_id'=>1,'amount'=>50,'payment_method'=>'check','payment_date'=>'2026-09-24','transaction_id'=>'AGE-FOREIGN']);
        $aging=$this->getJson('/api/reports/insurance-aging')->assertOk()->json('data.result_summary.aging');
        $this->assertCount(1,$aging);
        $this->assertEquals(['0-30'=>65.10,'31-60'=>20.02,'61-90'=>20.02,'90+'=>10.01,'total'=>115.15],$aging['Unknown']);
        $this->travelBack();
    }

    public function test_revenue_separates_period_receipts_from_invoice_cohort_balances(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-24 15:00:00'));
        DB::table('invoices')->where('id',1)->update(['created_at'=>'2026-09-24 23:59:59']);
        DB::table('payments')->where('organization_id',1)->update(['created_at'=>'2026-10-01 12:00:00']);
        $old=Invoice::withoutEvents(fn()=>Invoice::create(['organization_id'=>1,'case_id'=>$this->cases[1]->id,'invoice_number'=>'REVENUE-OLD','amount'=>500,'status'=>'sent']));
        DB::table('invoices')->where('id',$old->id)->update(['created_at'=>'2026-08-01 12:00:00']);
        Payment::create(['organization_id'=>1,'invoice_id'=>$old->id,'amount'=>200,'payment_method'=>'check','payment_date'=>'2026-09-24','transaction_id'=>'OLD-RECEIPT']);
        Payment::create(['organization_id'=>1,'invoice_id'=>1,'amount'=>10,'payment_method'=>'check','payment_date'=>'2026-09-25','transaction_id'=>'AFTER-CUTOFF']);
        $r=$this->getJson('/api/reports/revenue-by-period?date_from=2026-09-01&date_to=2026-09-24')->assertOk();
        $summary=$r->json('data.result_summary');
        $this->assertEquals(125.35,$summary['total_invoiced']);
        $this->assertEquals(260.25,$summary['total_collected']);
        $this->assertEquals(60.25,$summary['collected_against_period_invoices']);
        $this->assertEquals(65.10,$summary['total_outstanding']);
        $this->assertSame(1,$summary['invoice_count']);
        $this->assertSame(4,$summary['payment_count']);
        $this->travelBack();
    }
    public function test_revenue_rejects_invalid_or_reversed_dates_without_saving_a_report(): void
    {
        $before=DB::table('report_generations')->count();
        foreach (['date_from=invalid','date_to=2026-02-30','date_from=2026-09-24&date_to=2026-09-01','period=arbitrary'] as $query) {
            $this->getJson('/api/reports/revenue-by-period?'.$query)->assertUnprocessable();
        }
        $this->assertSame($before,DB::table('report_generations')->count());
    }

    public function test_lien_summary_matches_assigned_case_access(): void
    {
        $unassigned=CaseModel::withoutEvents(fn()=>CaseModel::create(['organization_id'=>1,'case_number'=>'LIEN-UNASSIGNED','title'=>'Synthetic unassigned','created_by'=>$this->actor->id]));
        foreach ([[$this->cases[1],1,100.25],[$unassigned,1,200],[$this->cases[2],2,900]] as [$case,$org,$amount]) {
            Lien::withoutEvents(fn()=>Lien::create(['organization_id'=>$org,'case_id'=>$case->id,'lien_type'=>'medical','amount'=>$amount,'reduction_amount'=>10,'status'=>'pending']));
        }
        $this->actor->role='attorney';
        CaseParty::create(['case_id'=>$this->cases[1]->id,'user_id'=>$this->actor->id,'role_in_case'=>'attorney']);
        $result=$this->getJson('/api/reports/lien-summary')->assertOk()->assertJsonPath('data.result_summary.total_liens',1)->json('data.result_summary');
        $this->assertEquals(100.25,$result['total_lien_amount']);
        $this->assertEquals(90.25,$result['total_outstanding']);
        $this->actor->role='firm_admin';
        $this->getJson('/api/reports/lien-summary')->assertOk()->assertJsonPath('data.result_summary.total_liens',2);
    }

    public function test_lien_summary_distinguishes_unknown_conflicting_and_closed_records(): void
    {
        $make=fn($values)=>Lien::withoutEvents(fn()=>Lien::create(array_merge(['organization_id'=>1,'case_id'=>$this->cases[1]->id,'lien_type'=>'medical','amount'=>100,'status'=>'pending'],$values)));
        $make(['amount'=>100.25,'negotiated_amount'=>60.10,'status'=>'negotiated']);
        $make(['amount'=>30.10,'reduction_amount'=>0]);
        $make(['amount'=>50,'negotiated_amount'=>0,'status'=>'negotiated']);
        $make(['amount'=>200,'reduction_amount'=>20,'status'=>'settled']);
        $make(['amount'=>300,'reduction_amount'=>0,'status'=>'released']);
        $summary=$this->getJson('/api/reports/lien-summary')->assertOk()->json('data.result_summary');
        $this->assertEquals(90.20,$summary['total_outstanding']);
        $this->assertEquals(110.15,$summary['total_negotiated_reductions']);
        $this->assertSame(2,$summary['closed_record_count']);
        $unknown=$make(['amount'=>10]);
        $conflict=$make(['amount'=>100,'negotiated_amount'=>80,'reduction_amount'=>30]);
        $summary=$this->getJson('/api/reports/lien-summary')->assertOk()->json('data.result_summary');
        $this->assertNull($summary['total_outstanding']);
        $this->assertNull($summary['total_negotiated_reductions']);
        $this->assertSame(2,$summary['unknown_open_amount_count']);
        $this->assertSame(1,$summary['conflicting_amount_count']);
        $this->assertEquals(90.20,$summary['known_open_amount']);
        $unknown->delete(); $conflict->delete();
        Lien::query()->delete();
        $this->getJson('/api/reports/lien-summary')->assertOk()->assertJsonPath('data.result_summary.total_outstanding',0);
    }

}
