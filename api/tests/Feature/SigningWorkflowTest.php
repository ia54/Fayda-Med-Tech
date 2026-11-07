<?php
namespace Tests\Feature;

use App\Models\{ApiCredential, Document, DocumentSigner, User};
use App\Services\DocuSignService;
use Illuminate\Support\Facades\{Artisan, DB, Http, Storage};
use Laravel\Passport\Token;
use Tests\TestCase;

class SigningWorkflowTest extends TestCase
{
    private Document $document;
    private User $actor;
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate',['--force'=>true,'--no-interaction'=>true]);
        $this->withoutMiddleware(\App\Http\Middleware\TwoFactorMiddleware::class);
        DB::table('organizations')->insert(['id'=>1,'org_name'=>'Synthetic','org_type'=>'law_firm','subscription_plan'=>'test','email'=>'org@example.invalid']);
        $this->actor = User::create(['first_name'=>'Synthetic','last_name'=>'Signing','email'=>'actor@example.invalid','password'=>'synthetic-only','role'=>'firm_admin','organization_id'=>1,'status'=>'active']);
        Storage::fake('documents');
        Storage::disk('documents')->put('synthetic.pdf','%PDF-1.4 synthetic /sn1/');
        $this->document = Document::create(['organization_id'=>1,'uploaded_by'=>$this->actor->id,'title'=>'Synthetic','original_name'=>'synthetic.pdf','filename'=>'synthetic.pdf','path'=>'synthetic.pdf','storage_disk'=>'documents','mime_type'=>'application/pdf']);
        DocumentSigner::create(['document_id'=>$this->document->id,'organization_id'=>1,'name'=>'Synthetic Signer','email'=>'signer@example.invalid','signing_order'=>1]);
        config(['services.docusign.webhook_secret'=>'system-synthetic-secret']);
        Http::preventStrayRequests();
    }
    private function acting(): void
    {
        $this->actor->withAccessToken(new Token(['expires_at'=>now()->addHour()]));
        $this->actingAs($this->actor,'api');
    }
    private function mockSigning(): void
    {
        $this->mock(DocuSignService::class, function ($mock) {
            $mock->shouldReceive('getAccessToken')->with(1)->andReturn('synthetic-token');
            $mock->shouldReceive('getAccountId')->andReturn('synthetic-account');
            $mock->shouldReceive('getBaseUri')->andReturn('https://demo.docusign.net');
        });
    }
    private function send()
    {
        return $this->postJson('/api/documents/'.$this->document->id.'/send-for-signature', []);
    }
    private function webhook(array $body, string $secret = 'system-synthetic-secret')
    {
        $json = json_encode($body);
        return $this->call('POST','/api/signatures/docusign/webhook',[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json','HTTP_X_DOCUSIGN_SIGNATURE_1'=>base64_encode(hash_hmac('sha256',$json,$secret,true))],$json);
    }
    private function envelope(string $status): array
    {
        return ['event'=>'envelope-'.$status,'data'=>['envelopeId'=>'synthetic-envelope','envelopeSummary'=>['status'=>$status,'recipients'=>['signers'=>[['email'=>'signer@example.invalid','status'=>$status,'recipientId'=>'1']]]]]];
    }
    public function test_successful_dispatch_is_sent_once_and_has_distinct_required_anchors(): void
    {
        $this->acting(); $this->mockSigning();
        DocumentSigner::create(['document_id'=>$this->document->id,'organization_id'=>1,'name'=>'Second','email'=>'second@example.invalid','signing_order'=>2]);
        Http::fake(['demo.docusign.net/*'=>Http::response(['envelopeId'=>'synthetic-envelope'],201)]);
        $this->send()->assertOk();
        $snapshot=$this->document->fresh()->docusign_dispatch_snapshot;
        $this->assertSame('synthetic-account',$snapshot['account_id']);
        $this->assertSame(hash('sha256',Storage::disk('documents')->get('synthetic.pdf')),$snapshot['source_sha256']);
        $this->send()->assertStatus(409);
        Http::assertSentCount(1);
        Http::assertSent(fn ($r)=>$r['transactionId'] === $this->document->fresh()->docusign_dispatch_id && $r['recipients']['signers'][1]['tabs']['signHereTabs'][0]['anchorString'] === '/sn2/' && $r['recipients']['signers'][0]['tabs']['signHereTabs'][0]['anchorIgnoreIfNotPresent'] === 'false');
        $this->assertDatabaseHas('documents',['id'=>$this->document->id,'docusign_envelope_id'=>'synthetic-envelope','signature_status'=>'pending']);
    }
    public function test_second_request_during_provider_call_cannot_dispatch_again(): void
    {
        $this->acting(); $this->mockSigning();
        Http::fake(['demo.docusign.net/*'=>function () {
            $this->send()->assertStatus(409);
            return Http::response(['envelopeId'=>'synthetic-envelope'],201);
        }]);
        $this->send()->assertOk();
        Http::assertSentCount(1);
    }
    public function test_provider_timeout_is_private_and_keeps_dispatch_guard(): void
    {
        $this->acting(); $this->mockSigning();
        Http::fake(['demo.docusign.net/*'=>function () { throw new \Illuminate\Http\Client\ConnectionException('PRIVATE transport detail'); }]);
        $this->send()->assertStatus(500)->assertDontSee('PRIVATE');
        $this->send()->assertStatus(409);
        $this->assertStringNotContainsString('PRIVATE',json_encode(DB::table('api_logs')->get()));
    }
    public function test_ambiguous_provider_failure_is_private_and_cannot_send_again(): void
    {
        $this->acting(); $this->mockSigning();
        Http::fake(['demo.docusign.net/*'=>Http::response(['message'=>'PRIVATE recipient and token'],503)]);
        $this->send()->assertStatus(500)->assertDontSee('PRIVATE');
        $this->send()->assertStatus(409);
        Http::assertSentCount(1);
        $this->assertNotNull($this->document->fresh()->docusign_dispatch_id);
        $this->assertNull($this->document->fresh()->docusign_envelope_id);
        $this->assertStringNotContainsString('PRIVATE', json_encode(DB::table('api_logs')->get()));
    }
    public function test_malformed_success_does_not_claim_document_was_sent(): void
    {
        $this->acting(); $this->mockSigning();
        Http::fake(['demo.docusign.net/*'=>Http::response([],201)]);
        $this->send()->assertStatus(500);
        $this->send()->assertStatus(409);
        $this->assertSame('not_sent', $this->document->fresh()->signature_status);
    }
    public function test_webhook_replays_and_old_events_do_not_regress_completion_or_add_duplicates(): void
    {
        $this->document->update(['docusign_envelope_id'=>'synthetic-envelope','signature_status'=>'pending']);
        $body = $this->envelope('completed');
        $body['data']['documents'] = [['url'=>'https://untrusted.example.invalid/file','uri'=>'/private/file']];
        $this->webhook($body)->assertOk();
        $signedAt = $this->document->fresh()->signed_at->toISOString();
        $this->webhook($body)->assertOk();
        $this->assertSame(1, DB::table('signatures')->count());
        $this->webhook($this->envelope('sent'))->assertOk();
        $this->webhook($this->envelope('declined'))->assertOk();
        $this->assertSame('completed',$this->document->fresh()->signature_status);
        $this->assertSame($signedAt,$this->document->fresh()->signed_at->toISOString());
        $this->assertSame('signed',$this->document->signers()->first()->status);
        $this->assertDatabaseMissing('signatures',['signed_file_url'=>'https://untrusted.example.invalid/file']);
        $this->assertDatabaseHas('signatures',['organization_id'=>1,'document_id'=>$this->document->id]);
    }
    public function test_webhook_uses_document_tenant_secret_and_rejects_other_secrets(): void
    {
        $this->document->update(['docusign_envelope_id'=>'synthetic-envelope','signature_status'=>'pending']);
        ApiCredential::create(['organization_id'=>1,'provider'=>'docusign','name'=>'WEBHOOK_SECRET','key'=>'tenant-synthetic-secret','is_active'=>true]);
        $this->webhook($this->envelope('completed'))->assertUnauthorized();
        $this->webhook($this->envelope('completed'),'tenant-synthetic-secret')->assertOk();
    }
    public function test_recipient_only_event_cannot_mark_an_envelope_complete(): void
    {
        $this->document->update(['docusign_envelope_id'=>'synthetic-envelope','signature_status'=>'pending']);
        $this->webhook(['event'=>'recipient-completed','data'=>['envelopeId'=>'synthetic-envelope','status'=>'completed']])->assertOk();
        $this->assertSame('pending',$this->document->fresh()->signature_status);
        $this->assertSame(0,DB::table('signatures')->count());
    }
}
