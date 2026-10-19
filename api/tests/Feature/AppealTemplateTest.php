<?php
namespace Tests\Feature;
use App\Models\{User,Invoice};
use Illuminate\Support\Facades\{Artisan,DB,Http};
use Laravel\Passport\Token;
use Tests\TestCase;
class AppealTemplateTest extends TestCase
{
    public function test_saved_template_does_not_claim_clinical_verification_or_model_generation(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\TwoFactorMiddleware::class);
        Artisan::call('migrate',['--force'=>true]);
        Http::preventStrayRequests();
        DB::table('organizations')->insert(['id'=>1,'org_name'=>'Synthetic Pharmacy','org_type'=>'provider','subscription_plan'=>'test','email'=>'test@example.invalid']);
        $user=User::create(['first_name'=>'Synthetic','last_name'=>'Biller','email'=>'biller@example.invalid','password'=>'test-only','role'=>'medical_biller','organization_id'=>1,'status'=>'active']);
        $user->withAccessToken(new Token(['expires_at'=>now()->addHour()]));
        $this->actingAs($user,'api');
        $invoice=Invoice::create(['organization_id'=>1,'invoice_number'=>'SYN-APPEAL','amount'=>125.50,'status'=>'denied']);
        $response=$this->postJson('/api/billing/appeals/generate',['invoice_id'=>$invoice->id,'reason_category'=>'Documentation','additional_details'=>'Synthetic supporting reference']);
        $response->assertStatus(210)->assertJsonPath('data.status','draft')->assertJsonPath('data.metadata.generation_method','template')->assertJsonPath('data.metadata.requires_human_review',true);
        $content=$response->json('data.content');
        $this->assertStringContainsString('HUMAN REVIEW REQUIRED',$content);
        $this->assertStringContainsString('have not been verified',$content);
        $this->assertStringContainsString('Synthetic Pharmacy',$content);
        $this->assertStringNotContainsString('all services provided were medically necessary',$content);
        $this->assertStringNotContainsString('has been verified against clinical guidelines',$content);
        $this->assertDatabaseHas('claim_appeals',['invoice_id'=>$invoice->id,'content'=>$content,'status'=>'draft']);
        Http::assertNothingSent();
        $invoice->update(['status'=>'sent']);
        $this->postJson('/api/billing/appeals/generate',['invoice_id'=>$invoice->id,'reason_category'=>'Documentation'])->assertStatus(400);
        DB::table('organizations')->insert(['id'=>2,'org_name'=>'Other Synthetic Pharmacy','org_type'=>'provider','subscription_plan'=>'test','email'=>'other@example.invalid']);
        $other=Invoice::withoutGlobalScopes()->create(['organization_id'=>2,'invoice_number'=>'SYN-OTHER','amount'=>25,'status'=>'denied']);
        $this->postJson('/api/billing/appeals/generate',['invoice_id'=>$other->id,'reason_category'=>'Documentation'])->assertNotFound();
        $this->assertDatabaseCount('claim_appeals',1);
        \App\Models\ClaimAppeal::withoutGlobalScopes()->create(['organization_id'=>2,'invoice_id'=>$other->id,'appeal_number'=>'SYN-FOREIGN-APPEAL','reason_category'=>'Documentation','content'=>'Synthetic foreign draft','status'=>'draft']);
        $this->getJson('/api/billing/appeals?search=SYN-OTHER')->assertOk()->assertJsonCount(0,'data.data');
        $this->getJson('/api/billing/appeals?search=SYN-FOREIGN-APPEAL')->assertOk()->assertJsonCount(0,'data.data');
        $this->getJson('/api/billing/appeals?search=SYN-APPEAL&status=draft')->assertOk()->assertJsonCount(1,'data.data');
        $this->getJson('/api/billing/appeals?search=does-not-exist')->assertOk()->assertJsonCount(0,'data.data');
        $this->getJson('/api/billing/appeals?status=sent')->assertOk()->assertJsonCount(0,'data.data');
        $this->getJson('/api/billing/appeals?per_page=1')->assertOk()->assertJsonPath('data.total',1)->assertJsonPath('data.last_page',1);
    }
}
