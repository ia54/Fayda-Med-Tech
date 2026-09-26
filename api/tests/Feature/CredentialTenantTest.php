<?php

namespace Tests\Feature;

use App\Models\ApiCredential;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Token;
use Tests\TestCase;

class CredentialTenantTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\TwoFactorMiddleware::class);
        Schema::create('organizations', function (Blueprint $table) { $table->id(); });
        DB::table('organizations')->insert([['id' => 1], ['id' => 2]]);
        $migration = require database_path('migrations/2026_04_25_232557_create_api_credentials_table.php');
        $migration->up();
        Schema::table('api_credentials', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable();
        });
        $migration = require database_path('migrations/2026_09_22_000001_scope_api_credential_uniqueness.php');
        $migration->up();
    }

    private function signIn(string $role, ?int $organization): void
    {
        $user = (new User)->forceFill(['id' => 100 + ($organization ?? 0), 'role' => $role, 'organization_id' => $organization]);
        $user->withAccessToken(new Token(['expires_at' => now()->addHour()]));
        $this->actingAs($user, 'api');
    }

    public function test_tenants_can_store_the_same_key_name_without_overwriting_each_other(): void
    {
        foreach ([1, 2] as $organization) {
            $this->signIn('firm_admin', $organization);
            $this->postJson('/api/api-credentials', [
                'provider' => 'synthetic', 'name' => 'API_KEY', 'key' => 'secret-' . $organization,
                'organization_id' => 999,
            ])->assertCreated()->assertJsonMissingPath('data.key')->assertJsonMissingPath('data.value');
        }
        $this->assertSame(2, DB::table('api_credentials')->count());
        $records = ApiCredential::withoutGlobalScopes()->orderBy('organization_id')->get();
        $this->assertSame([1, 2], $records->pluck('organization_id')->all());
        $this->assertSame(['secret-1', 'secret-2'], $records->pluck('key')->all());
    }

    public function test_tenant_cannot_list_or_edit_global_or_other_tenant_credentials(): void
    {
        $this->signIn('admin', null);
        $global = ApiCredential::create(['provider' => 'synthetic', 'name' => 'API_KEY', 'key' => 'global']);
        $other = ApiCredential::create(['organization_id' => 2, 'provider' => 'synthetic', 'name' => 'API_KEY', 'key' => 'other']);
        $this->signIn('firm_admin', 1);
        $this->getJson('/api/api-credentials')->assertOk()->assertJsonCount(0, 'data');
        foreach ([$global, $other] as $credential) {
            $this->getJson('/api/api-credentials/' . $credential->id)->assertNotFound();
            $this->putJson('/api/api-credentials/' . $credential->id, ['key' => 'replacement'])->assertNotFound();
            $this->deleteJson('/api/api-credentials/' . $credential->id)->assertNotFound();
        }
        $this->assertSame(2, DB::table('api_credentials')->count());
    }

    public function test_metadata_edit_preserves_secrets_and_ownership(): void
    {
        $this->signIn('firm_admin', 1);
        $credential = ApiCredential::create(['provider' => 'synthetic', 'name' => 'API_KEY', 'key' => 'original', 'value' => 'secondary']);
        $this->putJson('/api/api-credentials/' . $credential->id, [
            'name' => 'RENAMED', 'organization_id' => 2, 'is_active' => false,
        ])->assertOk()->assertJsonMissingPath('data.key')->assertJsonMissingPath('data.value');
        $credential->refresh();
        $this->assertSame('original', $credential->key);
        $this->assertSame('secondary', $credential->value);
        $this->assertSame(1, $credential->organization_id);
        $this->assertSame('RENAMED', $credential->name);
    }

    public function test_database_rejects_duplicate_platform_keys(): void
    {
        $this->signIn('admin', null);
        $data = ['provider' => 'synthetic', 'name' => 'API_KEY', 'key' => 'original'];
        ApiCredential::create($data);
        $this->expectException(\Illuminate\Database\QueryException::class);
        ApiCredential::create($data);
    }

    public function test_migration_rollback_preserves_nonoverlapping_records(): void
    {
        $this->signIn('admin', null);
        ApiCredential::create(['provider' => 'synthetic', 'name' => 'API_KEY', 'key' => 'original']);
        $migration = require database_path('migrations/2026_09_22_000001_scope_api_credential_uniqueness.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('api_credentials', 'credential_scope'));
        $this->assertSame(1, DB::table('api_credentials')->count());
        $migration->up();
        $this->assertSame('original', ApiCredential::first()->key);
    }

    public function test_migration_rollback_refuses_to_discard_overlapping_tenant_records(): void
    {
        $this->signIn('admin', null);
        foreach ([1, 2] as $organization) {
            ApiCredential::create(['organization_id' => $organization, 'provider' => 'synthetic', 'name' => 'API_KEY', 'key' => 'original']);
        }
        $migration = require database_path('migrations/2026_09_22_000001_scope_api_credential_uniqueness.php');
        try {
            $migration->down();
            $this->fail('Rollback should refuse overlapping tenant names');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Cannot restore global', $exception->getMessage());
            $this->assertSame(2, DB::table('api_credentials')->count());
            $this->assertEquals([1, 2], DB::table('api_credentials')->orderBy('organization_id')->pluck(DB::raw('credential_scope'))->all());
        }
    }

    public function test_tenant_administrator_cannot_read_global_environment_settings(): void
    {
        $this->signIn('firm_admin', 1);
        $this->getJson('/api/get-env-values')->assertForbidden();
        $this->getJson('/api/admin/security/settings')->assertForbidden();
    }
}
