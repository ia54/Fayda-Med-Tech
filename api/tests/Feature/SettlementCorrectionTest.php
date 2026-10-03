<?php
namespace Tests\Feature;
use App\Models\{User, CaseModel, CaseSettlement};
use Illuminate\Support\Facades\{Artisan, DB};
use Laravel\Passport\Token;
use Tests\TestCase;

class SettlementCorrectionTest extends TestCase
{
    public function test_correction_preserves_original_replays_once_and_reports_current_completed_records(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\TwoFactorMiddleware::class);
        Artisan::call('migrate', ['--force'=>true]);
        foreach ([1,2] as $id) DB::table('organizations')->insert(['id'=>$id,'org_name'=>'Synthetic '.$id,'org_type'=>'law_firm','subscription_plan'=>'test','email'=>"correction$id@example.invalid"]);
        $actor=User::create(['first_name'=>'Synthetic','last_name'=>'Admin','email'=>'correct@example.invalid','password'=>'synthetic-only','role'=>'firm_admin','organization_id'=>1,'status'=>'active']);
        $actor->withAccessToken(new Token(['expires_at'=>now()->addHour()]));
        $this->actingAs($actor,'api');
        $case=CaseModel::withoutEvents(fn()=>CaseModel::create(['organization_id'=>1,'case_number'=>'CORRECTION-1','title'=>'Synthetic correction','created_by'=>$actor->id,'status'=>'New']));
        $base=['case_id'=>$case->id,'settlement_amount'=>'100.25','settlement_date'=>'2026-09-23','status'=>'completed','attorney_fees'=>'20.00','costs'=>'5.00','other_deductions'=>'5.00','notes'=>'Original'];
        $original=$this->postJson('/api/settlements',$base)->assertCreated()->json('data.id');
        $before=CaseSettlement::findOrFail($original)->getAttributes();
        $second=$this->postJson('/api/settlements',array_replace($base,['settlement_amount'=>'50.00','attorney_fees'=>'0','costs'=>'0','other_deductions'=>'0']))->assertCreated()->json('data.id');
        $this->assertDatabaseHas('cases',['id'=>$case->id,'total_case_value'=>150.25]);
        $pending=$this->postJson('/api/settlements',array_replace($base,['status'=>'pending','settlement_amount'=>'999.00']))->assertCreated()->json('data.id');
        $case->update(['status'=>'Closed']);
        $body=['request_id'=>'8cd7ac30-90d7-452c-a766-8403c7b45c54','correction_reason'=>'Correct synthetic transcription','settlement_amount'=>'90.25','settlement_date'=>'2026-09-22','attorney_fees'=>'15.00','costs'=>'5.00','other_deductions'=>'10.00','notes'=>'Corrected'];
        $url='/api/settlements/'.$original.'/corrections';
        $this->postJson($url,array_replace($body,['other_deductions'=>'100']))->assertUnprocessable();
        $this->postJson($url,array_replace($body,['correction_reason'=>'']))->assertUnprocessable();
        $this->postJson($url,array_replace($body,['attorney_fees'=>'1.111']))->assertUnprocessable();
        $replacement=$this->postJson($url,array_replace($body,['organization_id'=>2,'case_id'=>999,'status'=>'pending','created_by'=>999]))->assertCreated()->assertJsonPath('data.net_to_client','60.25')->assertJsonPath('data.status','completed')->assertJsonPath('data.supersedes_id',$original)->json('data.id');
        $this->assertSame($before,CaseSettlement::findOrFail($original)->getAttributes());
        $this->assertDatabaseHas('cases',['id'=>$case->id,'total_case_value'=>140.25,'status'=>'Closed']);
        $this->assertDatabaseHas('case_settlements',['id'=>$replacement,'organization_id'=>1,'created_by'=>$actor->id,'case_id'=>$case->id]);
        $count=DB::table('case_timeline')->count();
        $this->postJson($url,$body)->assertCreated()->assertJsonPath('data.id',$replacement);
        $this->assertSame($count,DB::table('case_timeline')->count());
        $this->postJson($url,array_replace($body,['settlement_amount'=>'91.00']))->assertStatus(409);
        $newKey=['request_id'=>'9cba5452-f6b9-4934-9867-48d1bda9c4a3'];
        $this->postJson($url,array_replace($body,$newKey))->assertStatus(409);
        $this->postJson('/api/settlements/'.$pending.'/corrections',array_replace($body,$newKey))->assertStatus(409);
        $this->putJson('/api/settlements/'.$replacement,['notes'=>'Overwrite'])->assertStatus(409);
        $this->deleteJson('/api/settlements/'.$replacement)->assertStatus(409);
        $this->getJson('/api/settlements')->assertOk()->assertJsonPath('data.total',3);
        $this->getJson('/api/settlements?include_history=1')->assertOk()->assertJsonPath('data.total',4);
        $this->getJson('/api/settlements/'.$original)->assertOk()->assertJsonPath('data.correction.id',$replacement);
        $attorney=User::create(['first_name'=>'Synthetic','last_name'=>'Attorney','email'=>'production@example.invalid','password'=>'synthetic-only','role'=>'attorney','organization_id'=>1,'status'=>'active']);
        \App\Models\CaseParty::create(['case_id'=>$case->id,'user_id'=>$attorney->id,'role_in_case'=>'attorney']);
        $production=$this->getJson('/api/reports/attorney-production')->assertOk()->assertJsonPath('data.result_summary.production.0.closed_cases',1)->json('data.result_summary.production');
        $this->assertEquals(15,$production[0]['fees_generated']);
        $report=$this->getJson('/api/reports/settlement-summary')->assertOk()->json('data.result_summary');
        $this->assertSame(2,$report['total_settlements']);
        $this->assertEquals(140.25,$report['total_gross_settlement']);
        $this->assertEquals(110.25,$report['total_net_to_client']);
        $this->assertEquals(10,$report['total_other_deductions']);
        // A correction can itself be corrected, but each original has only one direct successor.
        $next=$this->postJson('/api/settlements/'.$replacement.'/corrections',array_replace($body,$newKey,['settlement_amount'=>'80.25']))->assertCreated()->json('data.id');
        $this->assertDatabaseHas('cases',['id'=>$case->id,'total_case_value'=>130.25,'status'=>'Closed']);
        $this->assertDatabaseCount('case_settlements',5);
        // Legacy allocations stay unknown rather than being assumed to be zero.
        $legacy=$base; unset($legacy['attorney_fees'],$legacy['costs'],$legacy['other_deductions']);
        $this->postJson('/api/settlements',$legacy)->assertCreated();
        $this->getJson('/api/reports/settlement-summary')->assertOk()->assertJsonPath('data.result_summary.total_net_to_client',null)->assertJsonPath('data.result_summary.unknown_allocation_count',1);
        $actor->role='attorney';
        $this->postJson('/api/settlements/'.$next.'/corrections',$body)->assertForbidden();
        $this->getJson('/api/reports/settlement-summary')->assertOk()->assertJsonPath('data.result_summary.total_settlements',0);
        $actor->role='admin'; $actor->organization_id=2;
        $this->postJson('/api/settlements/'.$next.'/corrections',$body)->assertNotFound();
        $this->getJson('/api/reports/settlement-summary')->assertOk()->assertJsonPath('data.result_summary.total_settlements',0);
        $actor->organization_id=null;
        $this->postJson('/api/settlements/'.$next.'/corrections',$body)->assertForbidden();
        $this->getJson('/api/reports/settlement-summary')->assertForbidden();
    }
}
