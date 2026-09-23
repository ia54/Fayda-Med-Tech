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
        $settlementBody=['case_id'=>$cases[0]->id,'settlement_amount'=>100.25,'settlement_date'=>now()->toDateString(),'status'=>'pending'];
        foreach ([1,2] as $i) $this->postJson('/api/settlements',array_replace($settlementBody,['case_id'=>$cases[$i]->id]))->assertNotFound();
        $this->postJson('/api/settlements',array_replace($settlementBody,['settlement_amount'=>1.234]))->assertUnprocessable();
        $settlementId=$this->postJson('/api/settlements',$settlementBody)->assertCreated()->json('data.id');
        $this->assertDatabaseCount('case_timeline',2);
        $other=\App\Models\CaseSettlement::withoutEvents(fn()=>\App\Models\CaseSettlement::create(array_replace($settlementBody,['case_id'=>$cases[1]->id,'organization_id'=>1,'created_by'=>$actor->id])));
        $this->getJson('/api/settlements')->assertOk()->assertJsonPath('data.total',1);
        $this->getJson('/api/settlements/'.$other->id)->assertNotFound();
        $this->putJson('/api/settlements/'.$other->id,['status'=>'completed'])->assertNotFound();
        $this->deleteJson('/api/settlements/'.$other->id)->assertNotFound();
        $this->getJson('/api/settlements/'.$settlementId)->assertOk()->assertJsonPath('data.net_to_client',null);
        $allocation=['attorney_fees'=>'25.06','costs'=>'10.10','other_deductions'=>'5.09'];
        $this->putJson('/api/settlements/'.$settlementId,$allocation)->assertOk()->assertJsonPath('data.net_to_client','60.00');
        $this->getJson('/api/settlements/'.$settlementId)->assertOk()->assertJsonPath('data.costs','10.10')->assertJsonPath('data.net_to_client','60.00');
        $change=\App\Models\CaseTimeline::where('case_id',$cases[0]->id)->where('title','Settlement Updated')->latest('id')->firstOrFail();
        $this->assertSame('25.06',$change->metadata['allocations']['attorney_fees']);
        $this->assertNull($change->metadata['previous_allocations']['other_deductions']);
        $this->putJson('/api/settlements/'.$settlementId,['settlement_amount'=>'40.24'])->assertUnprocessable();
        $this->assertDatabaseHas('case_settlements',['id'=>$settlementId,'settlement_amount'=>100.25]);
        $this->postJson('/api/settlements',array_merge($settlementBody,$allocation,['other_deductions'=>'99.99']))->assertUnprocessable();
        $this->postJson('/api/settlements',array_merge($settlementBody,['attorney_fees'=>'5.00']))->assertUnprocessable();
        $this->postJson('/api/settlements',array_merge($settlementBody,$allocation,['costs'=>'1.001']))->assertUnprocessable();
        $this->assertDatabaseCount('case_settlements',2);
        $this->putJson('/api/settlements/'.$settlementId,['status'=>'completed'])->assertOk();
        $this->putJson('/api/settlements/'.$settlementId,['settlement_amount'=>1])->assertStatus(409);
        $this->deleteJson('/api/settlements/'.$settlementId)->assertStatus(409);
        $actor->organization_id=null;
        $this->putJson($url,['title'=>'Denied'])->assertForbidden();
        $actor->organization_id=1;
        $actor->role='client';
        $this->putJson($url,['title'=>'Denied'])->assertForbidden();
    }
}
