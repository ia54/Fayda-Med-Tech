<?php
namespace Tests\Feature;
use App\Models\{User,Invoice,CaseModel,Document};
use Illuminate\Support\Facades\{Artisan,DB};
use Laravel\Passport\Token;
use Tests\TestCase;
class EobAssociationTest extends TestCase
{
 public function test_manual_eob_cannot_link_another_organizations_records(): void
 {
  $this->withoutMiddleware(\App\Http\Middleware\TwoFactorMiddleware::class);
  Artisan::call('migrate',['--force'=>true]);
  foreach([1,2] as $id) DB::table('organizations')->insert(['id'=>$id,'org_name'=>'Synthetic '.$id,'org_type'=>'provider','subscription_plan'=>'test','email'=>"org$id@example.invalid"]);
  $u=User::create(['first_name'=>'Synthetic','last_name'=>'Biller','email'=>'eob@example.invalid','password'=>'test','role'=>'medical_biller','organization_id'=>1,'status'=>'active']);
  $u->withAccessToken(new Token(['expires_at'=>now()->addHour()]));$this->actingAs($u,'api');
  $otherCase=CaseModel::withoutEvents(fn()=>CaseModel::create(['organization_id'=>2,'case_number'=>'SYN-OTHER','title'=>'Synthetic other','status'=>'New','created_by'=>$u->id]));
  $otherInvoice=Invoice::create(['organization_id'=>2,'invoice_number'=>'SYN-OTHER','amount'=>25,'status'=>'sent']);
  $otherDocument=Document::create(['organization_id'=>2,'uploaded_by'=>$u->id,'title'=>'Synthetic','original_name'=>'test.pdf','filename'=>'test.pdf','path'=>'synthetic/test.pdf','mime_type'=>'application/pdf','storage_disk'=>'documents']);
  $body=['provider_name'=>'Synthetic Provider','patient_name'=>'Synthetic Patient','payer_name'=>'Synthetic Payer','billed_amount'=>25,'ai_confidence'=>100];
  foreach(['case_id'=>$otherCase->id,'invoice_id'=>$otherInvoice->id,'document_id'=>$otherDocument->id] as $key=>$id) $this->postJson('/api/eobs', $body+[$key=>$id])->assertUnprocessable()->assertJsonValidationErrors($key);
  $this->assertDatabaseCount('eobs',0);
  $ownCase=CaseModel::withoutEvents(fn()=>CaseModel::create(['organization_id'=>1,'case_number'=>'SYN-OWN','title'=>'Synthetic own','status'=>'New','created_by'=>$u->id]));
  $ownInvoice=Invoice::create(['organization_id'=>1,'invoice_number'=>'SYN-OWN','amount'=>25,'status'=>'sent']);
  $ownDocument=Document::create(['organization_id'=>1,'uploaded_by'=>$u->id,'title'=>'Synthetic own','original_name'=>'own.pdf','filename'=>'own.pdf','path'=>'synthetic/own.pdf','mime_type'=>'application/pdf','storage_disk'=>'documents']);
  $eob=$this->postJson('/api/eobs',$body+['case_id'=>$ownCase->id,'invoice_id'=>$ownInvoice->id,'document_id'=>$ownDocument->id])->assertCreated()->json('data.id');
  foreach(['case_id'=>$otherCase->id,'invoice_id'=>$otherInvoice->id] as $key=>$id) $this->putJson('/api/eobs/'.$eob,[$key=>$id])->assertUnprocessable()->assertJsonValidationErrors($key);
  $this->assertDatabaseHas('eobs',['id'=>$eob,'case_id'=>$ownCase->id,'invoice_id'=>$ownInvoice->id,'document_id'=>$ownDocument->id]);
  $this->assertDatabaseHas('eobs',['id'=>$eob,'ai_confidence'=>null]);
  $this->putJson('/api/eobs/'.$eob,['notes'=>'Manual correction','ai_confidence'=>100])->assertOk()->assertJsonPath('data.ai_confidence',null);
  $ownDocument->delete();
  $this->postJson('/api/eobs',$body+['document_id'=>$ownDocument->id])->assertUnprocessable()->assertJsonValidationErrors('document_id');
  $this->assertDatabaseCount('eobs',1);
 }
}
