<?php
namespace Tests\Feature;

use Tests\TestCase;

class StagingCheckTest extends TestCase
{
    private function stagingConfig(): void
    {
        config([
            'app.env' => 'staging', 'app.debug' => false, 'app.public_url' => null,
            'app.url' => 'https://staging-api.example.com',
            'app.frontend_url' => 'https://staging-admin.example.com',
            'cors.allowed_origins' => ['https://staging-admin.example.com'],
            'cors.allowed_origins_patterns' => [],
            'database.default' => 'mysql', 'database.connections.mysql.url' => null,
            'database.connections.mysql.database' => 'faydamed_staging',
            'database.connections.mysql.username' => 'faydamed_staging',
            'database.connections.mysql.password' => 'synthetic-only',
            'mail.default' => 'array', 'queue.default' => 'sync',
            'session.secure' => true, 'session.cookie' => 'faydamed_staging_session',
        ]);
    }

    public function test_staging_configuration_passes_without_connecting_to_database(): void
    {
        $this->stagingConfig();
        config(['database.connections.mysql.host' => 'unreachable.example.invalid']);
        $this->artisan('staging:check')->assertSuccessful();
    }

    public function test_production_database_delivery_and_origin_are_rejected(): void
    {
        $this->stagingConfig();
        config(['database.connections.mysql.database' => 'live', 'mail.default' => 'smtp', 'app.url' => 'https://api.faydamed.tech']);
        $this->artisan('staging:check')
            ->expectsOutput('FAIL: Explicit HTTPS staging API and frontend origins')
            ->expectsOutput('FAIL: Dedicated named MySQL database and non-root account configured')
            ->expectsOutput('FAIL: Mail captured without delivery and initial queues synchronous')
            ->assertFailed();
    }

    public function test_placeholder_hosts_database_url_override_and_wildcard_cors_fail(): void
    {
        $this->stagingConfig();
        config(['app.url' => 'https://staging-api.example.invalid', 'database.connections.mysql.url' => 'mysql://hidden', 'cors.allowed_origins' => ['*']]);
        $this->artisan('staging:check')->assertFailed();
    }
    public function test_ssh_mode_requires_explicit_opt_in_and_accepts_both_frontends(): void
    {
        $this->stagingConfig();
        config([
            'app.url' => 'http://127.0.0.1:18080',
            'app.frontend_url' => 'http://127.0.0.1:13005',
            'app.public_url' => 'http://127.0.0.1:18081',
            'cors.allowed_origins' => ['http://127.0.0.1:18081', 'http://127.0.0.1:13005'],
            'session.secure' => false,
        ]);
        $this->artisan('staging:check')->assertFailed();
        $this->artisan('staging:check', ['--loopback' => true])->assertSuccessful();
        config(['cors.allowed_origins' => ['http://127.0.0.1:13005', '*']]);
        $this->artisan('staging:check', ['--loopback' => true])->assertFailed();
    }

    public function test_ssh_mode_rejects_public_hosts_credentials_and_url_paths(): void
    {
        $this->stagingConfig();
        config(['app.frontend_url' => 'http://127.0.0.1:13005', 'cors.allowed_origins' => ['http://127.0.0.1:13005'], 'session.secure' => false]);
        foreach (['http://0.0.0.0:18080', 'http://144.126.132.98:18080', 'http://example.com:18080', 'http://user@127.0.0.1:18080', 'http://127.0.0.1:18080/api'] as $origin) {
            config(['app.url' => $origin]);
            $this->artisan('staging:check', ['--loopback' => true])->assertFailed();
        }
    }
}
