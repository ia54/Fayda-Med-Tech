<?php
namespace Tests\Feature;

use Tests\TestCase;

class StagingCheckTest extends TestCase
{
    private function stagingConfig(): void
    {
        config([
            'app.env' => 'staging', 'app.debug' => false,
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
            ->expectsOutput('FAIL: Dedicated named MySQL database and non-root account configured')
            ->expectsOutput('FAIL: Mail captured without delivery and initial queues synchronous')
            ->expectsOutput('FAIL: Explicit HTTPS staging API and frontend origins')
            ->assertFailed();
    }

    public function test_placeholder_hosts_database_url_override_and_wildcard_cors_fail(): void
    {
        $this->stagingConfig();
        config(['app.url' => 'https://staging-api.example.invalid', 'database.connections.mysql.url' => 'mysql://hidden', 'cors.allowed_origins' => ['*']]);
        $this->artisan('staging:check')->assertFailed();
    }
}
