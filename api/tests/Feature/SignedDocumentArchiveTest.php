<?php
namespace Tests\Feature;

use App\Models\{Document, DocumentSigner, Signature, User};
use App\Services\DocuSignService;
use Illuminate\Support\Facades\{Artisan, DB, Http, Storage};
use Laravel\Passport\Token;
use Tests\TestCase;

class SignedDocumentArchiveTest extends TestCase
{
    private User $actor;
    private Document $document;
    private const PDF = "%PDF-1.4\nSynthetic signed PDF\n%%EOF\n";
    private const CERTIFICATE = "%PDF-1.4\nSynthetic completion certificate\n%%EOF\n";
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate',['--force'=>true,'--no-interaction'=>true]);
        $this->withoutMiddleware(\App\Http\Middleware\TwoFactorMiddleware::class);
        Storage::fake('documents'); Storage::fake('public'); Http::preventStrayRequests();
        foreach ([1,2] as $id) DB::table('organizations')->insert(['id'=>$id,'org_name'=>'Synthetic '.$id,'org_type'=>'provider','subscription_plan'=>'test','email'=>"org$id@example.invalid"]);
        $this->actor = User::create(['first_name'=>'Synthetic','last_name'=>'Signer','email'=>'actor@example.invalid','password'=>'synthetic-only','organization_id'=>1,'role'=>'client','status'=>'active']);
        $this->document = Document::create(['organization_id'=>1,'uploaded_by'=>$this->actor->id,'title'=>'Synthetic','original_name'=>'source.pdf','filename'=>'source.pdf','path'=>'source.pdf','storage_disk'=>'documents','mime_type'=>'application/pdf','signature_status'=>'completed','docusign_envelope_id'=>'synthetic-envelope']);
        Storage::disk('documents')->put('source.pdf','UNSIGNED ORIGINAL');
        DocumentSigner::create(['organization_id'=>1,'document_id'=>$this->document->id,'user_id'=>$this->actor->id,'name'=>'Synthetic','email'=>$this->actor->email,'status'=>'signed']);
        $this->acting();
        $this->mock(DocuSignService::class,function ($mock) {
            $mock->shouldReceive('getAccessToken')->with(1)->andReturn('synthetic-token');
            $mock->shouldReceive('getBaseUri')->andReturn('https://demo.docusign.net');
            $mock->shouldReceive('getAccountId')->andReturn('synthetic-account');
        });
    }
    private function acting(string $role = 'client'): void
    {
        $this->actor->role=$role;
        $this->actor->withAccessToken(new Token(['expires_at'=>now()->addHour()]));
        $this->actingAs($this->actor,'api');
    }
    private function fakeProvider($certificate = null): void
    {
        Http::fake([
            '*/documents/combined*'=>fn () => Http::response(self::PDF,200,['Content-Type'=>'application/pdf']),
            '*/documents/certificate'=>fn () => $certificate ?? Http::response(self::CERTIFICATE,200,['Content-Type'=>'application/pdf']),
            '*/envelopes/synthetic-envelope'=>Http::response(['envelopeId'=>'synthetic-envelope','status'=>'completed']),
        ]);
    }
    private function preview()
    {
        return $this->get('/api/documents/'.$this->document->id.'/preview');
    }
    public function test_signed_pdf_and_certificate_are_private_cached_and_available_to_all_authorized_roles(): void
    {
        $this->fakeProvider();
        foreach (User::getAvailableRoles() as $role) {
            $this->acting($role);
            if (in_array($role, ['pharmacist', 'pharmacy_technician'], true)) {
                $this->preview()->assertForbidden();
                $this->get('/api/documents/'.$this->document->id.'/completion-certificate')->assertForbidden();
                continue;
            }
            $response=$this->preview()->assertOk()->assertHeader('X-Document-Version','signed')->assertHeader('X-Completion-Certificate','true');
            $this->assertSame(self::PDF,$response->streamedContent());
            $certificate=$this->get('/api/documents/'.$this->document->id.'/completion-certificate')->assertOk()->assertHeader('X-Document-Version','certificate');
            $this->assertSame(self::CERTIFICATE,$certificate->streamedContent());
        }
        Http::assertSentCount(3);
        $this->assertSame(1,Signature::count());
        $archive=Signature::first();
        $this->assertSame('private',Storage::disk('documents')->getVisibility($archive->signed_file_path));
        $this->assertSame([],Storage::disk('public')->allFiles());
        $this->assertStringNotContainsString('signed/docusign/', $this->getJson('/api/documents/'.$this->document->id)->getContent());
        $this->assertSame(hash('sha256',self::PDF),$archive->provider_payload['pdf_sha256']);
        $this->assertSame('UNSIGNED ORIGINAL',Storage::disk('documents')->get('source.pdf'));
    }
    public function test_archiving_does_not_inflate_signature_activity_reports(): void
    {
        $this->fakeProvider(); $this->preview()->assertOk();
        Signature::create(['organization_id'=>1,'document_id'=>$this->document->id,'provider'=>'docusign','provider_event'=>'webhook:synthetic','status'=>'completed']);
        $this->acting('firm_admin');
        $this->getJson('/api/reports/signature-activity')->assertOk()
            ->assertJsonPath('data.result_summary.total',1)->assertJsonPath('data.result_summary.completed',1);
    }
    public function test_provider_failure_does_not_return_original_or_publish_partial_archive(): void
    {
        $this->fakeProvider(Http::response('PRIVATE provider detail',503));
        $this->preview()->assertStatus(503)->assertDontSee('PRIVATE')->assertDontSee('UNSIGNED ORIGINAL');
        $this->assertSame(0,Signature::count());
        $this->assertSame(['source.pdf'],Storage::disk('documents')->allFiles());
    }
    public function test_incomplete_or_non_pdf_or_oversized_file_is_rejected(): void
    {
        foreach ([Http::response('<html>PRIVATE</html>',200,['Content-Type'=>'text/html']),Http::response('%PDF-1.4 truncated',200,['Content-Type'=>'application/pdf']),Http::response(self::CERTIFICATE,200,['Content-Type'=>'application/pdf','Content-Length'=>6*1024*1024])] as $bad) {
            $this->fakeProvider($bad);
            $this->preview()->assertStatus(503)->assertDontSee('PRIVATE');
            $this->assertSame(0,Signature::count());
        }
        $this->assertSame(['source.pdf'],Storage::disk('documents')->allFiles());
    }
    public function test_provider_completion_and_envelope_identity_are_verified_before_download(): void
    {
        foreach ([['envelopeId'=>'different','status'=>'completed'],['envelopeId'=>'synthetic-envelope','status'=>'sent']] as $status) {
            Http::fake(['*'=>Http::response($status)]);
            $this->preview()->assertStatus(503);
            Http::assertSentCount(1);
        }
        $this->assertSame(0,Signature::count());
    }
    public function test_cross_tenant_and_unassigned_client_requests_never_contact_provider(): void
    {
        Http::fake();
        $this->document->update(['organization_id'=>2]);
        $this->preview()->assertNotFound();
        $this->getJson('/api/documents/'.$this->document->id.'/completion-certificate')->assertNotFound();
        $this->document->update(['organization_id'=>1,'uploaded_by'=>null]);
        $this->document->signers()->delete();
        $this->preview()->assertNotFound();
        $this->getJson('/api/documents/'.$this->document->id.'/completion-certificate')->assertNotFound();
        Http::assertNothingSent();
    }
    public function test_pending_envelopes_cannot_supply_a_completion_certificate(): void
    {
        Http::fake(); $this->document->update(['signature_status'=>'pending']);
        $this->getJson('/api/documents/'.$this->document->id.'/completion-certificate')->assertStatus(409);
        $this->preview()->assertOk()->assertHeader('X-Document-Version','original');
        Http::assertNothingSent();
    }
    public function test_damaged_cache_is_refetched_instead_of_served(): void
    {
        $this->fakeProvider(); $this->preview()->assertOk();
        Storage::disk('documents')->put(Signature::first()->signed_file_path,'CORRUPT');
        $response=$this->preview()->assertOk();
        $this->assertSame(self::PDF,$response->streamedContent());
        Http::assertSentCount(6);
    }
    public function test_overlapping_downloads_publish_one_archive_and_remove_unused_files(): void
    {
        $nested = false;
        Http::fake([
            '*/documents/combined*'=>function () use (&$nested) {
                if (!$nested) { $nested=true; $this->preview()->assertOk(); }
                return Http::response(self::PDF,200,['Content-Type'=>'application/pdf']);
            },
            '*/documents/certificate'=>fn () => Http::response(self::CERTIFICATE,200,['Content-Type'=>'application/pdf']),
            '*/envelopes/synthetic-envelope'=>Http::response(['envelopeId'=>'synthetic-envelope','status'=>'completed']),
        ]);
        $this->preview()->assertOk();
        $this->assertSame(1,Signature::count());
        $this->assertCount(3,Storage::disk('documents')->allFiles());
        Http::assertSentCount(6);
    }
    public function test_failure_during_manifest_write_cleans_up_both_private_files(): void
    {
        $this->fakeProvider();
        $dispatcher = Signature::getEventDispatcher();
        Signature::setEventDispatcher(clone $dispatcher);
        Signature::creating(fn () => throw new \RuntimeException('PRIVATE database failure'));
        try {
            $this->preview()->assertStatus(503)->assertDontSee('PRIVATE');
            $this->assertSame(['source.pdf'],Storage::disk('documents')->allFiles());
            $this->assertSame(0,Signature::count());
        } finally { Signature::setEventDispatcher($dispatcher); }
    }
}
