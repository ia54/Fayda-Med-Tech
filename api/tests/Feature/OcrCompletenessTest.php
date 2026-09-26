<?php
namespace Tests\Feature;

use App\Models\ApiCredential;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Token;
use Tests\TestCase;

class OcrCompletenessTest extends TestCase
{
    private Document $document;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\TwoFactorMiddleware::class);
        Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
        DB::table('organizations')->insert(['id'=>1,'org_name'=>'Synthetic','org_type'=>'provider','subscription_plan'=>'test','email'=>'ocr@example.invalid']);
        $user = User::create(['first_name'=>'Synthetic','last_name'=>'OCR','email'=>'actor@example.invalid','password'=>'synthetic-only','role'=>'medical_biller','organization_id'=>1,'status'=>'active']);
        $user->withAccessToken(new Token(['expires_at'=>now()->addHour()]));
        $this->actingAs($user, 'api');
        Storage::fake('documents');
        $this->document = Document::create(['organization_id'=>1,'uploaded_by'=>$user->id,'title'=>'Synthetic','original_name'=>'test.pdf','filename'=>'test.pdf','path'=>'test.pdf','mime_type'=>'application/pdf','storage_disk'=>'documents']);
        Storage::disk('documents')->put('test.pdf', '%PDF-1.4 synthetic no page objects');
        ApiCredential::create(['organization_id'=>1,'provider'=>'google_vision','name'=>'API_KEY','key'=>'synthetic-not-a-provider-key','is_active'=>true]);
        Http::preventStrayRequests();
    }
    private function response(int $total, array $pages): array
    {
        return ['responses'=>[['totalPages'=>$total,'responses'=>array_map(fn($page)=>['context'=>['pageNumber'=>$page],'fullTextAnnotation'=>['text'=>'Page '.$page]], $pages)]]];
    }
    private function runOcr()
    {
        return $this->postJson('/api/ocr/documents/'.$this->document->id.'/process', ['language_hints'=>['en']]);
    }
    public function test_provider_page_count_drives_complete_extraction_and_language_hints(): void
    {
        Http::fake(['vision.googleapis.com/*'=>Http::sequence()->push($this->response(7,[1]))->push($this->response(7,[2,3,4,5,6]))->push($this->response(7,[7]))]);
        $this->runOcr()->assertOk()->assertJsonPath('data.page_count',7)->assertJsonPath('data.extracted_text',"Page 1\nPage 2\nPage 3\nPage 4\nPage 5\nPage 6\nPage 7");
        Http::assertSentCount(3);
        Http::assertSent(fn($r)=>$r['requests'][0]['pages']===[2,3,4,5,6] && $r['requests'][0]['imageContext']['languageHints']===['en']);
        $this->assertDatabaseHas('ocr_results',['document_id'=>$this->document->id,'organization_id'=>1,'status'=>'processed']);
    }
    public function test_failure_after_first_page_never_saves_partial_success_or_provider_secrets(): void
    {
        Http::fake(['vision.googleapis.com/*'=>Http::sequence()->push($this->response(7,[1]))->push(['error'=>['message'=>'PRIVATE PROVIDER DETAIL']],503)]);
        $this->runOcr()->assertStatus(500)->assertDontSee('PRIVATE PROVIDER DETAIL');
        $this->assertDatabaseHas('ocr_results',['document_id'=>$this->document->id,'status'=>'failed','extracted_text'=>null]);
        $this->assertDatabaseHas('documents',['id'=>$this->document->id,'ocr_status'=>'failed']);
    }
    public function test_missing_error_or_misordered_page_responses_fail_closed(): void
    {
        foreach ([['responses'=>[['error'=>['message'=>'private']]]], $this->response(2,[]), $this->response(2,[2]), $this->response(101,[1]), $this->response(0,[1])] as $body) {
            Http::fake(['vision.googleapis.com/*'=>Http::response($body)]);
            $this->runOcr()->assertStatus(500);
        }
        $this->assertSame(0, DB::table('ocr_results')->where('status','processed')->count());
    }
    public function test_cross_tenant_and_client_requests_make_no_provider_call(): void
    {
        Http::fake();
        DB::table('organizations')->insert(['id'=>2,'org_name'=>'Other synthetic','org_type'=>'provider','subscription_plan'=>'test','email'=>'other@example.invalid']);
        $this->document->organization_id=2; $this->document->save();
        $this->runOcr()->assertNotFound();
        auth('api')->user()->role='client';
        $this->runOcr()->assertForbidden();
        Http::assertNothingSent();
    }
}
