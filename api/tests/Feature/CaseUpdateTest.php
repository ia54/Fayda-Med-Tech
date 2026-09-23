<?php
namespace Tests\Feature;
use App\Models\{User, CaseModel, CaseParty};
use Illuminate\Support\Facades\{Artisan, DB};
use Laravel\Passport\Token;
use Tests\TestCase;
class CaseUpdateTest extends TestCase
{
    public function test_attorney_edit_preserves_ownership_and_identifiers_and_requires_assignment(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\TwoFactorMiddleware::class);
        Artisan::call('migrate', ['--force'=>true]);
        foreach ([1,2] as $id) DB::table('organizations')->insert(['id'=>$id,'org_name'=>'Synthetic '.$id,'org_type'=>'law_firm','subscription_plan'=>'test','email'=>"org$id@example.invalid"]);
        $actor=User::create(['first_name'=>'Synthetic','last_name'=>'Attorney','email'=>'edit@example.invalid','password'=>'synthetic-only','role'=>'attorney','organization_id'=>1,'status'=>'active']);
        $actor->withAccessToken(new Token(['expires_at'=>now()->addHour()]));
        $this->actingAs($actor,'api');
        $cases=[];
        foreach ([1,1,2] as $i=>$org) $cases[$i]=CaseModel::withoutEvents(fn()=>CaseModel::create(['organization_id'=>$org,'case_number'=>'EDIT-'.$i,'title'=>'Synthetic case','created_by'=>$actor->id,'status'=>'New']));
        CaseParty::create(['case_id'=>$cases[0]->id,'user_id'=>$actor->id,'role_in_case'=>'attorney']);
        $url='/api/cases/'.$cases[0]->id;
        $this->putJson($url,['title'=>'Updated synthetic case','status'=>'Active','organization_id'=>2,'case_number'=>'FORGED','created_by'=>999])->assertOk();
        $this->assertDatabaseHas('cases',['id'=>$cases[0]->id,'organization_id'=>1,'case_number'=>'EDIT-0','created_by'=>$actor->id,'title'=>'Updated synthetic case','status'=>'Active']);
        $this->assertDatabaseHas('case_timeline',['case_id'=>$cases[0]->id,'title'=>'Status Changed: Active']);
        $this->putJson($url,['status'=>'Active'])->assertOk();
        $this->assertDatabaseCount('case_timeline',1);
        $this->putJson($url,['status'=>null])->assertUnprocessable();
        foreach ([1,2] as $i) $this->putJson('/api/cases/'.$cases[$i]->id,['title'=>'Denied'])->assertNotFound();
        $actor->organization_id=null;
        $this->putJson($url,['title'=>'Denied'])->assertForbidden();
        $actor->organization_id=1;
        $actor->role='client';
        $this->putJson($url,['title'=>'Denied'])->assertForbidden();
    }
}
