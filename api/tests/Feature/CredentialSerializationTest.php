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
}
