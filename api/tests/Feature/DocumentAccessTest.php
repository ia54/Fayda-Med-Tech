<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Token;
use Tests\TestCase;

class DocumentAccessTest extends TestCase
{
    private User $actor;
    private Document $own;
    private Document $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\TwoFactorMiddleware::class);
        Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
        Storage::fake('documents');
        Storage::fake('public');
        foreach ([1, 2] as $id) {
            DB::table('organizations')->insert(['id' => $id, 'org_name' => 'Synthetic '.$id, 'org_type' => 'provider', 'subscription_plan' => 'test', 'email' => "org$id@example.invalid"]);
        }
        $this->actor = User::create(['first_name' => 'Synthetic', 'last_name' => 'Tester', 'email' => 'actor@example.invalid', 'password' => 'synthetic-only', 'role' => 'client', 'organization_id' => 1, 'status' => 'active']);
        foreach ([1, 2] as $org) {
            $document = Document::withoutEvents(fn () => Document::create([
                'organization_id' => $org, 'uploaded_by' => null, 'title' => 'Synthetic document',
                'original_name' => 'test.pdf', 'filename' => "test$org.pdf", 'path' => "test$org.pdf",
                'url' => 'https://public.example.invalid/secret.pdf', 'mime_type' => 'application/pdf', 'storage_disk' => 'documents',
            ]));
            Storage::disk('documents')->put($document->path, '%PDF-1.4 synthetic original');
            if ($org === 1) { $this->own = $document; } else { $this->other = $document; }
        }
        DocumentSigner::create(['document_id' => $this->own->id, 'user_id' => $this->actor->id, 'name' => 'Synthetic Tester', 'email' => $this->actor->email]);
    }

    private function signIn(string $role): void
    {
        $this->actor->role = $role;
        $this->actor->withAccessToken(new Token(['expires_at' => now()->addHour()]));
        $this->actingAs($this->actor, 'api');
    }

    public function test_all_six_roles_can_read_authorized_documents_and_tenants_cannot_cross_boundaries(): void
    {
        foreach (User::getAvailableRoles() as $role) {
            $this->signIn($role);
            foreach (['', '/preview', '/signature-status'] as $suffix) {
                $this->getJson('/api/documents/'.$this->own->id.$suffix)->assertOk();
                $response = $this->getJson('/api/documents/'.$this->other->id.$suffix);
                $role === 'admin' ? $response->assertOk() : $response->assertNotFound();
            }
            $response = $this->getJson('/api/signatures/document/'.$this->other->id);
            $role === 'admin' ? $response->assertOk() : $response->assertNotFound();
        }
    }

    public function test_client_document_search_is_paginated_and_cannot_bypass_visibility(): void
    {
        $this->signIn('client');
        $this->own->update(['title' => 'Findable record']);
        $this->other->update(['title' => 'Findable record']);
        $this->getJson('/api/client/documents?search=Findable&per_page=1')->assertOk()->assertJsonPath('data.pagination.total', 1)->assertJsonPath('data.documents.0.id', $this->own->id);
        $this->getJson('/api/client/documents?search=test.pdf')->assertOk()->assertJsonPath('data.pagination.total', 1);
        $this->getJson('/api/client/documents?search=absent')->assertOk()->assertJsonCount(0, 'data.documents');
        $this->getJson('/api/client/documents?search=Findable&per_page=1&page=2')->assertOk()->assertJsonCount(0, 'data.documents');
    }

    public function test_email_alone_does_not_grant_client_or_attorney_access(): void
    {
        DB::table('document_signers')->update(['user_id' => null]);
        foreach (['client', 'attorney'] as $role) {
            $this->signIn($role);
            $this->getJson('/api/documents')->assertOk()->assertJsonCount(0, 'data.documents');
            $this->getJson('/api/documents/'.$this->own->id)->assertNotFound();
            $this->getJson('/api/client/documents/'.$this->own->id)->assertStatus($role === 'client' ? 404 : 403);
        }
    }

    public function test_lower_privilege_roles_cannot_change_signers_or_archive_documents(): void
    {
        foreach (['client', 'medical_biller', 'provider_staff'] as $role) {
            $this->signIn($role);
            $this->postJson('/api/documents/'.$this->own->id.'/signers', ['signers' => [['name' => 'Other', 'email' => 'other@example.invalid']]])->assertForbidden();
            $this->deleteJson('/api/documents/'.$this->own->id)->assertForbidden();
        }
    }

    public function test_signed_versions_preserve_original_and_reject_impersonation_and_replay(): void
    {
        $this->signIn('client');
        $url = '/api/documents/'.$this->own->id.'/sign-in-app';
        $this->postJson($url, ['file' => UploadedFile::fake()->create('signed.pdf', 1, 'application/pdf'), 'signer_email' => 'other@example.invalid'])->assertForbidden();
        $this->postJson($url, ['file' => UploadedFile::fake()->create('signed.pdf', 1, 'application/pdf')])->assertOk();
        $this->assertSame('test1.pdf', $this->own->fresh()->path);
        Storage::disk('documents')->assertExists('test1.pdf');
        $this->assertSame('%PDF-1.4 synthetic original', Storage::disk('documents')->get('test1.pdf'));
        $this->assertDatabaseCount('signatures', 1);
        $this->postJson($url, ['file' => UploadedFile::fake()->create('signed.pdf', 1, 'application/pdf')])->assertStatus(409);
        $this->assertDatabaseCount('signatures', 1);
    }

    public function test_upload_is_private_and_case_link_cannot_be_forged(): void
    {
        $this->signIn('provider_staff');
        $this->postJson('/api/documents', ['title' => 'Synthetic', 'file' => UploadedFile::fake()->create('record.pdf', 1, 'application/pdf'), 'metadata' => ['case_id' => 999]])->assertNotFound();
        $response = $this->postJson('/api/documents', ['title' => 'Synthetic', 'file' => UploadedFile::fake()->create('record.pdf', 1, 'application/pdf')])->assertCreated()->assertJsonMissingPath('data.path');
        $document = Document::findOrFail($response->json('data.id'));
        $this->assertSame('documents', $document->storage_disk);
        Storage::disk('documents')->assertExists($document->path);
        $this->assertCount(0, Storage::disk('public')->allFiles());
        $this->assertStringContainsString('/api/documents/', $response->json('data.url'));
    }

    public function test_provider_upload_links_only_an_available_case_in_its_organization(): void
    {
        $cases = [];
        foreach ([1, 2] as $org) {
            $cases[$org] = \App\Models\CaseModel::withoutEvents(fn () => \App\Models\CaseModel::create(['organization_id' => $org, 'case_number' => 'DOC-'.$org, 'title' => 'Synthetic case', 'created_by' => $this->actor->id]));
        }
        $this->signIn('provider_staff');
        $upload = fn ($caseId) => $this->postJson('/api/documents', ['title' => 'Linked synthetic', 'file' => UploadedFile::fake()->create('linked.pdf', 1, 'application/pdf'), 'metadata' => ['case_id' => $caseId, 'category' => 'clinical_record']]);
        $upload($cases[2]->id)->assertNotFound();
        $id = $upload($cases[1]->id)->assertCreated()->json('data.id');
        $this->assertDatabaseHas('documents', ['id' => $id, 'case_id' => $cases[1]->id, 'organization_id' => 1]);
        $this->getJson('/api/documents?case_id='.$cases[1]->id)->assertOk()->assertJsonCount(1, 'data.documents');
        $cases[1]->delete();
        $upload($cases[1]->id)->assertNotFound();
    }

    public function test_client_upload_and_case_details_follow_case_assignment(): void
    {
        $this->signIn('client');
        $cases = [];
        foreach (['Assigned', 'Unassigned'] as $title) {
            $cases[] = \App\Models\CaseModel::create(['organization_id' => 1, 'case_number' => $title, 'title' => $title, 'created_by' => $this->actor->id, 'metadata' => ['internal' => 'Private']]);
        }
        \App\Models\CaseParty::create(['case_id' => $cases[0]->id, 'user_id' => $this->actor->id, 'role_in_case' => 'Plaintiff']);
        $payload = fn ($id) => ['title' => 'Client record', 'file' => UploadedFile::fake()->create('client.pdf', 1, 'application/pdf'), 'metadata' => ['case_id' => $id]];
        $this->postJson('/api/client/documents', $payload($cases[1]->id))->assertNotFound();
        $this->postJson('/api/client/documents', $payload('none'))->assertUnprocessable();
        $id = $this->postJson('/api/client/documents', $payload($cases[0]->id))->assertCreated()->json('data.id');
        $this->getJson('/api/client/documents/'.$id.'/preview')->assertOk();
        $this->getJson('/api/client/cases/'.$cases[0]->id)->assertOk()->assertJsonMissingPath('metadata')->assertJsonPath('timeline.0.title', 'Document Uploaded')->assertJsonMissingPath('timeline.0.metadata')->assertJsonMissingPath('timeline.0.user');
        $this->getJson('/api/client/cases/'.$cases[1]->id)->assertNotFound();
        $this->postJson('/api/client/documents', $payload(null))->assertCreated();
    }

    public function test_archiving_retains_original_bytes_and_removes_api_access(): void
    {
        $this->signIn('firm_admin');
        $this->deleteJson('/api/documents/'.$this->own->id)->assertOk();
        Storage::disk('documents')->assertExists('test1.pdf');
        $this->assertSoftDeleted('documents', ['id' => $this->own->id]);
        $this->getJson('/api/documents/'.$this->own->id)->assertNotFound();
    }
    public function test_legacy_storage_migration_verifies_copy_and_requires_explicit_public_removal(): void
    {
        $this->own->update(['path' => 'documents/uploads/legacy.pdf', 'storage_disk' => 'public']);
        Storage::disk('public')->put('documents/uploads/legacy.pdf', 'synthetic legacy bytes');
        $this->assertSame(0, Artisan::call('documents:privatize'));
        $this->assertSame('public', $this->own->fresh()->storage_disk);
        $this->assertSame(0, Artisan::call('documents:privatize', ['--apply' => true]));
        $this->assertSame('documents', $this->own->fresh()->storage_disk);
        Storage::disk('public')->assertExists('documents/uploads/legacy.pdf');
        $this->assertSame(0, Artisan::call('documents:privatize', ['--apply' => true, '--remove-public' => true]));
        Storage::disk('public')->assertMissing('documents/uploads/legacy.pdf');
        $this->assertSame('synthetic legacy bytes', Storage::disk('documents')->get('documents/uploads/legacy.pdf'));
    }

    public function test_webhook_requires_a_configured_signature_over_the_exact_body(): void
    {
        $service = $this->mock(\App\Services\DocuSignService::class);
        $service->shouldReceive('getWebhookSecret')->andReturn('synthetic-webhook-secret');
        $this->postJson('/api/signatures/docusign/webhook', ['envelopeId' => 'synthetic'])->assertUnauthorized();
        $this->withHeader('X-Docusign-Secret', 'synthetic-webhook-secret')->postJson('/api/signatures/docusign/webhook', ['envelopeId' => 'synthetic'])->assertUnauthorized();
        $this->withHeader('X-Docusign-Signature-1', 'invalid')->postJson('/api/signatures/docusign/webhook', ['envelopeId' => 'synthetic'])->assertUnauthorized();
        $this->assertSame('not_sent', $this->own->fresh()->signature_status);
    }

}
