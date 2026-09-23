<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('FAYDAMED_MYSQL_CI') === '1') {
            if (getenv('GITHUB_ACTIONS') !== 'true' || !app()->environment('testing') || config('database.default') !== 'mysql'
                || config('database.connections.mysql.database') !== 'faydamed_ci' || config('database.connections.mysql.host') !== '127.0.0.1') {
                throw new \RuntimeException('Refusing to reset a database outside disposable MySQL CI.');
            }
            \Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]);
        }
        \Illuminate\Support\Facades\Schema::useNativeSchemaOperationsIfPossible();
        // Public-only fixture. Its private key is discarded; no deployment
        // signing keys are read and this fixture cannot mint access tokens.
        config(['passport.public_key' => file_get_contents(__DIR__ . '/Fixtures/passport-public.pem')]);
    }
}
