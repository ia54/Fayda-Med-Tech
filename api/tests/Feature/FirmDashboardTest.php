<?php
namespace Tests\Feature;
use App\Models\{User, CaseModel, CaseParty, AuditLog};
use Illuminate\Support\Facades\{Artisan, DB};
use Laravel\Passport\Token;
use Tests\TestCase;
class FirmDashboardTest extends TestCase
{
    public function test_dashboard_scopes_activity_and_workload_and_does_not_invent_growth(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\TwoFactorMiddleware::class);
        Artisan::call('migrate', ['--force' => true]);
        foreach ([1,2] as $id) DB::table('organizations')->insert(['id'=>$id,'org_name'=>'Synthetic '.$id,'org_type'=>'law_firm','subscription_plan'=>'test','email'=>"org$id@example.invalid"]);
        $actor=User::create(['first_name'=>'Synthetic','last_name'=>'Attorney','email'=>'legal@example.invalid','password'=>'synthetic-only','role'=>'attorney','organization_id'=>1,'status'=>'active']);
        $actor->withAccessToken(new Token(['expires_at'=>now()->addHour()]));
        $this->actingAs($actor,'api');
        $cases=[];
        foreach ([1,1,2] as $i=>$org) {
            $cases[$i]=CaseModel::withoutEvents(fn()=>CaseModel::create(['organization_id'=>$org,'case_number'=>'LEGAL-'.$i,'title'=>'Synthetic '.$i,'created_by'=>$actor->id,'status'=>'Active','total_case_value'=>100]));
            AuditLog::create(['organization_id'=>$org,'user_id'=>$actor->id,'event'=>'Activity '.$i,'auditable_type'=>CaseModel::class,'auditable_id'=>$cases[$i]->id]);
        }
        CaseParty::create(['case_id'=>$cases[0]->id,'user_id'=>$actor->id,'role_in_case'=>'attorney']);
        CaseParty::create(['case_id'=>$cases[0]->id,'user_id'=>$actor->id,'role_in_case'=>'attorney']);
        $archived=CaseModel::withoutEvents(fn()=>CaseModel::create(['organization_id'=>1,'case_number'=>'ARCHIVED','title'=>'Archived','created_by'=>$actor->id,'status'=>'Active']));
        CaseParty::create(['case_id'=>$archived->id,'user_id'=>$actor->id,'role_in_case'=>'attorney']);
        $archived->delete();
        $this->getJson('/api/firm/stats')->assertOk()->assertJsonPath('data.stats.active_cases',1)->assertJsonCount(1,'data.recent_activity')->assertJsonPath('data.team_workload.0.cases',1)->assertJsonPath('data.recovery_growth',null);
        $actor->role='firm_admin';
        $response=$this->getJson('/api/firm/stats')->assertOk()->assertJsonPath('data.stats.active_cases',2);
        $this->assertNotContains('Activity 2',array_column($response->json('data.recent_activity'),'event'));
        $actor->organization_id=null;
        $this->getJson('/api/firm/stats')->assertForbidden();
    }
}
