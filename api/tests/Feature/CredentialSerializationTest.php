<?php
namespace Tests\Feature;

use App\Models\ApiCredential;
use Tests\TestCase;

class CredentialSerializationTest extends TestCase
{
    public function test_credentials_are_excluded_from_json_even_when_present_in_storage(): void
    {
        $credential = new ApiCredential;
        $credential->setRawAttributes([
            'id' => 1,
            'provider' => 'synthetic',
            'name' => 'Synthetic integration',
            'key' => 'never-serialize-this-key',
            'value' => 'never-serialize-this-secret',
            'is_active' => true,
        ]);
        $data = $credential->toArray();
        $this->assertSame('synthetic', $data['provider']);
        $this->assertArrayNotHasKey('key', $data);
        $this->assertArrayNotHasKey('value', $data);
        $this->assertStringNotContainsString('never-serialize', $credential->toJson());
    }
    public function test_environment_status_never_serializes_secrets_and_browser_writes_are_retired(): void
    {
        config(['mail.mailers.smtp.host'=>'private-host', 'mail.mailers.smtp.username'=>'private-user', 'mail.mailers.smtp.password'=>'private-password', 'mail.from.address'=>'private-sender']);
        $controller = app(\App\Http\Controllers\AppSettingController::class);
        $response = $controller->getEnvValues();
        $this->assertStringNotContainsString('private-', $response->getContent());
        $this->assertTrue($response->getData(true)['mail']['credentials_configured']);
        $this->assertFalse($response->getData(true)['payments']['online_collection_enabled']);
        $request = \Illuminate\Http\Request::create('/api/setting-env-update', 'POST', ['APP_DEBUG'=>'true', 'DB_PASSWORD'=>'replacement']);
        $this->assertSame(410, $controller->settingEnvUpdate($request)->getStatusCode());
    }

}
