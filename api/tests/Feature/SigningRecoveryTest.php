<?php
namespace Tests\Feature;

use App\Models\{Document, DocumentSigner, Signature, User};
use App\Services\{DocuSignService, SigningRecovery};
use Illuminate\Support\Facades\{Artisan, DB, Http, Storage};
use Tests\TestCase;

class SigningRecoveryTest extends TestCase
{
    private Document $document;
    private User $actor;
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate',['--force'=>true,'--no-interaction'=>true]);
        Storage::fake('documents'); Http::preventStrayRequests();
        DB::table('organizations')->insert(['id'=>1,'org_name'=>'Synthetic','org_type'=>'provider','subscription_plan'=>'test','email'=>'org@example.invalid']);
        $this->actor=User::create(['first_name'=>'Synthetic','last_name'=>'Admin','email'=>'admin@example.invalid','password'=>'synthetic-only','organization_id'=>1,'role'=>'firm_admin','status'=>'active']);
        $this->document=Document::create(['organization_id'=>1,'uploaded_by'=>$this->actor->id,'title'=>'Synthetic','original_name'=>'source.pdf','filename'=>'source.pdf','path'=>'source.pdf','storage_disk'=>'documents','mime_type'=>'application/pdf']);
        DocumentSigner::create(['organization_id'=>1,'document_id'=>$this->document->id,'name'=>'Synthetic Signer','email'=>'signer@example.invalid','signing_order'=>1]);
        Storage::disk('documents')->put('source.pdf','synthetic source bytes');
        $this->document->forceFill(['docusign_dispatch_id'=>'11111111-1111-4111-8111-111111111111','docusign_dispatch_snapshot'=>SigningRecovery::snapshot($this->document,'synthetic-account','https://demo.docusign.net','synthetic source bytes')])->save();
        $this->mock(DocuSignService::class,function($mock){
            $mock->shouldReceive('getAccessToken')->with(1)->andReturn('synthetic-token');
            $mock->shouldReceive('getAccountId')->andReturn('synthetic-account');
            $mock->shouldReceive('getBaseUri')->andReturn('https://demo.docusign.net');
        });
    }
    private function fakeProvider(array $overrides=[]): void
    {
        Http::fake($overrides + [
            '*envelopes?*'=>Http::response(['envelopes'=>[['envelopeId'=>'synthetic-envelope']]]),
            '*envelopes/synthetic-envelope'=>Http::response(['envelopeId'=>'synthetic-envelope','status'=>'completed']),
            '*/recipients'=>Http::response(['signers'=>[['recipientId'=>'1','routingOrder'=>'1','email'=>'signer@example.invalid','name'=>'Synthetic Signer','status'=>'completed']]]),
            '*/documents'=>Http::response(['envelopeDocuments'=>[['documentId'=>'1','name'=>'source.pdf'],['documentId'=>'certificate','name'=>'Summary']]]),
        ]);
    }
    private function runRecovery(bool $apply=false,array $options=[]): int
    {
        $args=['document'=>$this->document->id];
        if ($apply) $args+=['--apply'=>true,'--envelope'=>'synthetic-envelope','--actor'=>$this->actor->id,'--reason'=>'Synthetic acceptance'];
        return Artisan::call('signing:reconcile',array_replace($args,$options));
    }
    public function test_inspection_is_read_only_and_provider_calls_never_send_an_envelope(): void
    {
        $this->fakeProvider();
        $this->assertSame(0,$this->runRecovery());
        $this->assertNull($this->document->fresh()->docusign_envelope_id);
        $this->assertSame(0,Signature::count());
        $this->assertStringNotContainsString('signer@example.invalid',Artisan::output());
        Http::assertSentCount(4);
        foreach(Http::recorded() as [$request]) $this->assertSame('GET',$request->method());
        Http::assertSent(fn($request)=>str_contains($request->url(),'transaction_ids='.$this->document->docusign_dispatch_id));
    }
    public function test_explicit_apply_links_once_updates_signers_and_records_the_actor(): void
    {
        $this->fakeProvider();
        $this->assertSame(0,$this->runRecovery(true));
        $this->assertDatabaseHas('documents',['id'=>$this->document->id,'docusign_envelope_id'=>'synthetic-envelope','signature_status'=>'completed','document_status'=>'signed']);
        $this->assertNotNull($this->document->fresh()->docusign_dispatch_id);
        $this->assertSame('signed',$this->document->signers()->first()->status);
        $this->assertSame($this->actor->id,Signature::first()->provider_payload['actor_id']);
        $this->assertSame(0,$this->runRecovery(true));
        $this->assertSame(1,Signature::count());
        Http::assertSentCount(4);
        $this->assertArrayNotHasKey('docusign_dispatch_snapshot',$this->document->fresh()->toArray());
    }
    public function test_empty_or_multiple_matches_keep_the_guard_and_do_not_link(): void
    {
        foreach ([[],[['envelopeId'=>'one'],['envelopeId'=>'two']]] as $matches) {
            Http::fake(['*'=>Http::response(['envelopes'=>$matches])]);
            $this->assertSame(1,$this->runRecovery(true));
            $this->assertNull($this->document->fresh()->docusign_envelope_id);
            $this->assertNotNull($this->document->fresh()->docusign_dispatch_id);
        }
    }
    public function test_missing_snapshot_changed_source_or_changed_signers_fail_before_provider_contact(): void
    {
        Http::fake();
        $snapshot=$this->document->docusign_dispatch_snapshot;
        $this->document->forceFill(['docusign_dispatch_snapshot'=>null])->save();
        $this->assertSame(1,$this->runRecovery(true));
        $this->document->forceFill(['docusign_dispatch_snapshot'=>$snapshot])->save();
        Storage::disk('documents')->put('source.pdf','changed');
        $this->assertSame(1,$this->runRecovery(true));
        Storage::disk('documents')->put('source.pdf','synthetic source bytes');
        $this->document->signers()->update(['email'=>'changed@example.invalid']);
        $this->assertSame(1,$this->runRecovery(true));
        Http::assertNothingSent();
    }
    public function test_wrong_actor_or_missing_apply_arguments_fail_without_provider_calls(): void
    {
        Http::fake();
        foreach ([['--envelope'=>''],['--reason'=>''],['--actor'=>999999]] as $options) $this->assertSame(1,$this->runRecovery(true,$options));
        $this->actor->update(['role'=>'client']);
        $this->assertSame(1,$this->runRecovery(true));
        $this->actor->update(['role'=>'firm_admin','status'=>'inactive']);
        $this->assertSame(1,$this->runRecovery(true));
        Http::assertNothingSent();
    }
    public function test_provider_identity_or_recipient_mismatch_prevents_linking(): void
    {
        foreach ([
            ['*envelopes/synthetic-envelope'=>Http::response(['envelopeId'=>'wrong','status'=>'completed'])],
            ['*/recipients'=>Http::response(['signers'=>[['recipientId'=>'1','routingOrder'=>'1','email'=>'wrong@example.invalid','name'=>'Synthetic Signer','status'=>'completed']]])],
            ['*/documents'=>Http::response(['envelopeDocuments'=>[['documentId'=>'1','name'=>'wrong.pdf']]])],
        ] as $override) {
            $this->fakeProvider($override);
            $this->assertSame(1,$this->runRecovery(true));
            $this->assertNull($this->document->fresh()->docusign_envelope_id);
        }
    }
    public function test_account_change_or_wrong_expected_envelope_never_links(): void
    {
        $this->fakeProvider();
        $this->assertSame(1,$this->runRecovery(true,['--envelope'=>'wrong-envelope']));
        $snapshot=$this->document->docusign_dispatch_snapshot;
        $snapshot['account_id']='different-account';
        $this->document->forceFill(['docusign_dispatch_snapshot'=>$snapshot])->save();
        Http::fake();
        $this->assertSame(1,$this->runRecovery(true));
        Http::assertNothingSent();
        $this->assertNull($this->document->fresh()->docusign_envelope_id);
    }
    public function test_other_organization_administrator_cannot_apply(): void
    {
        DB::table('organizations')->insert(['id'=>2,'org_name'=>'Other','org_type'=>'provider','subscription_plan'=>'test','email'=>'other@example.invalid']);
        $this->actor->update(['organization_id'=>2]);
        Http::fake();
        $this->assertSame(1,$this->runRecovery(true));
        Http::assertNothingSent();
        $this->assertNull($this->document->fresh()->docusign_envelope_id);
    }
    public function test_local_change_during_provider_lookup_is_rejected_at_commit(): void
    {
        $this->fakeProvider(['*/documents'=>function () {
            Storage::disk('documents')->put('source.pdf','changed during lookup');
            return Http::response(['envelopeDocuments'=>[['documentId'=>'1','name'=>'source.pdf']]]);
        }]);
        $this->assertSame(1,$this->runRecovery(true));
        $this->assertNull($this->document->fresh()->docusign_envelope_id);
        $this->assertSame(0,Signature::count());
    }
    public function test_provider_errors_are_private_and_never_clear_the_send_guard(): void
    {
        Http::fake(['*'=>fn()=>throw new \Illuminate\Http\Client\ConnectionException('PRIVATE token diagnostic')]);
        $this->assertSame(1,$this->runRecovery(true));
        $this->assertStringNotContainsString('PRIVATE',Artisan::output());
        $this->assertNotNull($this->document->fresh()->docusign_dispatch_id);
        $this->assertSame(0,Signature::count());
    }
    public function test_transaction_rolls_back_if_recovery_audit_cannot_be_saved(): void
    {
        $this->fakeProvider();
        $dispatcher=Signature::getEventDispatcher(); Signature::setEventDispatcher(clone $dispatcher);
        Signature::creating(fn()=>throw new \RuntimeException('synthetic write failure'));
        try {
            $this->assertSame(1,$this->runRecovery(true));
            $this->assertNull($this->document->fresh()->docusign_envelope_id);
            $this->assertSame('pending',$this->document->signers()->first()->status);
            $this->assertSame(0,Signature::count());
        } finally { Signature::setEventDispatcher($dispatcher); }
    }
}
