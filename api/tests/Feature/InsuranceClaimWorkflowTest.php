<?php
namespace Tests\Feature;

use App\Models\{User, CaseModel, CaseParty, InsuranceCompany, InsuranceAdjuster, InsuranceClaim, CaseTimeline};
use Illuminate\Support\Facades\{Artisan, DB};
use Laravel\Passport\Token;
use Tests\TestCase;

class InsuranceClaimWorkflowTest extends TestCase
{
    public function test_coverage_links_assignment_unknown_amounts_and_history(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\TwoFactorMiddleware::class);
        Artisan::call('migrate', ['--force'=>true]);
        foreach ([1,2] as $id) DB::table('organizations')->insert(['id'=>$id,'org_name'=>'Synthetic '.$id,'org_type'=>'law_firm','subscription_plan'=>'test','email'=>"coverage$id@example.invalid"]);
        $actor=User::create(['first_name'=>'Synthetic','last_name'=>'Attorney','email'=>'coverage@example.invalid','password'=>'synthetic-only','role'=>'attorney','organization_id'=>1,'status'=>'active']);
        $actor->withAccessToken(new Token(['expires_at'=>now()->addHour()]));
        $this->actingAs($actor,'api');
        $cases=[];
        foreach ([1,1,2] as $i=>$org) $cases[$i]=CaseModel::withoutEvents(fn()=>CaseModel::create(['organization_id'=>$org,'case_number'=>'COVER-'.$i,'title'=>'Synthetic coverage','created_by'=>$actor->id]));
        CaseParty::create(['case_id'=>$cases[0]->id,'user_id'=>$actor->id,'role_in_case'=>'attorney']);
        $company=InsuranceCompany::create(['organization_id'=>1,'name'=>'Allowed','email'=>'allowed@example.invalid']);
        $other=InsuranceCompany::create(['organization_id'=>2,'name'=>'Other','email'=>'hidden@example.invalid']);
        $adjuster=InsuranceAdjuster::create(['insurance_company_id'=>$other->id,'name'=>'Foreign adjuster']);
        $this->getJson('/api/insurance/companies?search=hidden')->assertOk()->assertJsonPath('data.total',0);
        $this->getJson('/api/insurance/companies')->assertOk()->assertJsonPath('data.total',1);
        $body=['case_id'=>$cases[0]->id,'insurance_company_id'=>$company->id,'claim_number'=>'SYN-COVER','coverage_type'=>'liability','coverage_limit'=>null];
        foreach ([1,2] as $i) $this->postJson('/api/insurance/claims',array_replace($body,['case_id'=>$cases[$i]->id]))->assertNotFound();
        $this->postJson('/api/insurance/claims',array_replace($body,['insurance_company_id'=>$other->id]))->assertNotFound();
        $this->postJson('/api/insurance/claims',array_replace($body,['adjuster_id'=>$adjuster->id]))->assertNotFound();
        foreach (['-1','1.234','100000000'] as $amount) $this->postJson('/api/insurance/claims',array_replace($body,['coverage_limit'=>$amount]))->assertUnprocessable();
        $id=$this->postJson('/api/insurance/claims',array_replace($body,['organization_id'=>2]))->assertCreated()->assertJsonPath('data.organization_id',1)->assertJsonPath('data.claim_status','open')->assertJsonPath('data.coverage_limit',null)->json('data.id');
        $url='/api/insurance/claims/'.$id;
        $this->postJson('/api/insurance/claims',$body)->assertUnprocessable();
        $this->putJson($url,['coverage_limit'=>'125.35'])->assertOk()->assertJsonPath('data.coverage_limit','125.35');
        $this->putJson($url,['case_id'=>$cases[1]->id])->assertUnprocessable();
        $this->putJson($url,['insurance_company_id'=>$other->id])->assertNotFound();
        $this->putJson($url,['claim_status'=>null])->assertUnprocessable();
        $this->putJson($url,['coverage_limit'=>null])->assertOk()->assertJsonPath('data.coverage_limit',null);
        $event=CaseTimeline::where('title','Insurance Claim Updated')->latest('id')->firstOrFail();
        $this->assertEquals(125.35,$event->metadata['previous_details']['coverage_limit']);
        $this->assertNull($event->metadata['details']['coverage_limit']);
        foreach ([1,2] as $i) {
            $hidden=InsuranceClaim::create(array_replace($body,['claim_number'=>'HIDDEN-'.$i,'case_id'=>$cases[$i]->id,'organization_id'=>1]));
            $this->getJson('/api/insurance/claims/'.$hidden->id)->assertNotFound();
            $this->putJson('/api/insurance/claims/'.$hidden->id,['coverage_limit'=>1])->assertNotFound();
        }
        $this->getJson('/api/insurance/claims')->assertOk()->assertJsonPath('data.total',1);
        foreach (['per_page=101','page=0','status=invalid'] as $params) $this->getJson('/api/insurance/claims?'.$params)->assertUnprocessable();
        $contact=['claim_id'=>$id,'request_id'=>'6a20e683-08ae-4db2-bbec-dc8f054db1ee','channel'=>'phone','occurred_on'=>now()->toDateString(),'note'=>'Synthetic contact only'];
        $entry=$this->postJson('/api/insurance/correspondence',$contact)->assertCreated()->json('data.id');
        $this->postJson('/api/insurance/correspondence',$contact)->assertOk()->assertJsonPath('data.id',$entry);
        $this->postJson('/api/insurance/correspondence',array_replace($contact,['note'=>'Changed']))->assertStatus(409);
        $this->getJson('/api/insurance/correspondence?claim_id='.$id)->assertOk()->assertJsonPath('data.total',1)->assertJsonPath('data.data.0.note','Synthetic contact only');
        $this->postJson('/api/insurance/correspondence',array_replace($contact,['occurred_on'=>now()->addDay()->toDateString()]))->assertUnprocessable();
        $this->getJson('/api/insurance/correspondence?claim_id='.$hidden->id)->assertNotFound();
        $this->postJson('/api/insurance/correspondence',array_replace($contact,['claim_id'=>$hidden->id]))->assertNotFound();
        $this->assertDatabaseHas('case_timeline',['title'=>'Insurance Correspondence Recorded','case_id'=>$cases[0]->id]);
        $claim=InsuranceClaim::findOrFail($id);
        $claim->update(['correspondence_log'=>['legacy'=>'retained']]);
        $this->postJson('/api/insurance/correspondence',$contact)->assertStatus(409);
        $this->assertSame(['legacy'=>'retained'],$claim->fresh()->correspondence_log);
        $this->postJson('/api/insurance/companies',['name'=>'Attorney cannot manage carriers'])->assertForbidden();
        $actor->role='firm_admin';
        $this->getJson('/api/insurance/claims')->assertOk()->assertJsonPath('data.total',2);
        $this->deleteJson('/api/insurance/companies/'.$company->id)->assertStatus(409);
        $this->assertDatabaseHas('insurance_claims',['id'=>$id]);
        $this->deleteJson('/api/insurance/companies/'.$other->id)->assertNotFound();
        $empty=$this->postJson('/api/insurance/companies',['name'=>'Unused synthetic','organization_id'=>2])->assertCreated()->assertJsonPath('data.organization_id',1)->json('data.id');
        $this->putJson('/api/insurance/companies/'.$empty,['name'=>'Updated synthetic'])->assertOk();
        $this->deleteJson('/api/insurance/companies/'.$empty)->assertOk();
        $actor->organization_id=null;
        $this->getJson('/api/insurance/claims')->assertForbidden();
        $actor->organization_id=1;
        foreach (['client','provider_staff','medical_biller'] as $role) {
            $actor->role=$role;
            $this->getJson('/api/insurance/claims')->assertForbidden();
        }
    }
}
