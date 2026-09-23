<?php
namespace Tests\Feature;

use App\Models\{User, CaseModel, CaseParty, Provider, Lien, CaseTimeline};
use Illuminate\Support\Facades\{Artisan, DB};
use Laravel\Passport\Token;
use Tests\TestCase;

class LienWorkflowTest extends TestCase
{
    public function test_lien_links_assignment_filters_and_history_are_enforced(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\TwoFactorMiddleware::class);
        Artisan::call('migrate', ['--force' => true]);
        foreach ([1,2] as $id) DB::table('organizations')->insert(['id'=>$id,'org_name'=>'Synthetic '.$id,'org_type'=>'law_firm','subscription_plan'=>'test','email'=>"lien$id@example.invalid"]);
        $actor = User::create(['first_name'=>'Synthetic','last_name'=>'Attorney','email'=>'lien@example.invalid','password'=>'synthetic-only','role'=>'attorney','organization_id'=>1,'status'=>'active']);
        $actor->withAccessToken(new Token(['expires_at'=>now()->addHour()]));
        $this->actingAs($actor, 'api');
        $cases = [];
        foreach ([1,1,2] as $i=>$org) $cases[$i]=CaseModel::withoutEvents(fn()=>CaseModel::create(['organization_id'=>$org,'case_number'=>'LIEN-'.$i,'title'=>'Synthetic lien case','created_by'=>$actor->id]));
        CaseParty::create(['case_id'=>$cases[0]->id,'user_id'=>$actor->id,'role_in_case'=>'attorney']);
        $provider = Provider::create(['organization_id'=>1,'name'=>'Synthetic allowed']);
        $otherProvider = Provider::create(['organization_id'=>2,'name'=>'Synthetic denied']);
        $this->getJson('/api/liens/provider-options')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.name','Synthetic allowed')->assertJsonMissingPath('data.0.tax_id');
        $this->getJson('/api/liens/provider-options?search=denied')->assertOk()->assertJsonCount(0,'data');
        $body = ['case_id'=>$cases[0]->id,'provider_id'=>$provider->id,'lien_type'=>'medical','amount'=>'100.25','notes'=>'Synthetic searchable'];
        foreach ([1,2] as $i) $this->postJson('/api/liens', array_replace($body,['case_id'=>$cases[$i]->id]))->assertNotFound();
        $this->postJson('/api/liens',array_replace($body,['provider_id'=>$otherProvider->id]))->assertNotFound();
        $this->postJson('/api/liens',array_replace($body,['amount'=>'1.234']))->assertUnprocessable();
        $this->postJson('/api/liens',array_replace($body,['amount'=>'100000000.00']))->assertUnprocessable();
        $this->postJson('/api/liens',array_replace($body,['status'=>null]))->assertUnprocessable();
        $id = $this->postJson('/api/liens',array_replace($body,['organization_id'=>2]))->assertCreated()->assertJsonPath('data.status','pending')->assertJsonPath('data.organization_id',1)->assertJsonPath('data.amount','100.25')->json('data.id');
        $url='/api/liens/'.$id;
        $other = Lien::create(array_replace($body,['case_id'=>$cases[1]->id,'organization_id'=>1]));
        $foreign = Lien::create(array_replace($body,['case_id'=>$cases[2]->id,'organization_id'=>2]));
        // A legacy mismatched case link must not widen tenant access.
        $malformed = Lien::create(array_replace($body,['case_id'=>$cases[2]->id,'organization_id'=>1]));
        $this->getJson('/api/liens')->assertOk()->assertJsonPath('data.total',1);
        foreach ([$other->id,$foreign->id,$malformed->id] as $denied) {
            $this->getJson('/api/liens/'.$denied)->assertNotFound();
            $this->putJson('/api/liens/'.$denied,['notes'=>'Denied'])->assertNotFound();
            $this->deleteJson('/api/liens/'.$denied)->assertNotFound();
        }
        $this->getJson('/api/liens?search=LIEN-0&status=pending&per_page=1')->assertOk()->assertJsonPath('data.total',1)->assertJsonPath('data.data.0.provider.name','Synthetic allowed');
        $this->getJson('/api/liens?search=no-match')->assertOk()->assertJsonPath('data.total',0);
        foreach (['per_page=0','per_page=101','page=0','status=signed'] as $params) $this->getJson('/api/liens?'.$params)->assertUnprocessable();
        $this->putJson($url,['case_id'=>$cases[1]->id])->assertUnprocessable();
        $this->putJson($url,['provider_id'=>$otherProvider->id])->assertNotFound();
        $this->putJson($url,['release_document_url'=>'javascript:alert(1)'])->assertUnprocessable();
        $this->putJson($url,['status'=>'negotiated','notes'=>'Reviewed synthetic','organization_id'=>2])->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('data.case_id',$cases[0]->id)->assertJsonPath('data.organization_id',1)->assertJsonPath('data.notes','Reviewed synthetic');
        $event=CaseTimeline::where('title','Lien Updated')->latest('id')->firstOrFail();
        $this->assertSame('Synthetic searchable',$event->metadata['previous_details']['notes']);
        $this->assertSame('Reviewed synthetic',$event->metadata['details']['notes']);
        $count=CaseTimeline::count();
        $this->putJson($url,['notes'=>'Reviewed synthetic'])->assertOk();
        $this->assertSame($count,CaseTimeline::count());
        $this->putJson($url,['status'=>'released'])->assertOk();
        $this->deleteJson($url)->assertStatus(409);
        $this->putJson($url,['status'=>'pending'])->assertStatus(409);
        $this->assertDatabaseHas('liens',['id'=>$id,'status'=>'released']);
        $draft=$this->postJson('/api/liens',$body)->assertCreated()->json('data.id');
        $this->deleteJson('/api/liens/'.$draft)->assertOk();
        $this->assertDatabaseMissing('liens',['id'=>$draft]);
        $this->assertDatabaseHas('case_timeline',['case_id'=>$cases[0]->id,'title'=>'Lien Deleted']);
        $actor->role='firm_admin';
        $this->getJson('/api/liens')->assertOk()->assertJsonPath('data.total',2);
        $actor->organization_id=null;
        $this->getJson('/api/liens')->assertForbidden();
        $actor->organization_id=1;
        foreach (['client','provider_staff','medical_biller'] as $role) {
            $actor->role=$role;
            $this->getJson('/api/liens')->assertForbidden();
            $this->postJson('/api/liens',$body)->assertForbidden();
        }
    }
}
