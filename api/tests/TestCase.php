<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    private static bool $mysqlSchemaReady = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('FAYDAMED_MYSQL_CI') === '1') {
            if (getenv('GITHUB_ACTIONS') !== 'true' || !app()->environment('testing') || config('database.default') !== 'mysql'
                || config('database.connections.mysql.database') !== 'faydamed_ci' || config('database.connections.mysql.host') !== '127.0.0.1') {
                throw new \RuntimeException('Refusing to reset a database outside disposable MySQL CI.');
            }
            if (!self::$mysqlSchemaReady) {
                if (\Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]) !== 0) {
                    throw new \RuntimeException('Disposable MySQL schema reset failed.');
                }
                self::$mysqlSchemaReady = true;
            } else {
                // Preserve real commits/rollback behavior. Rebuild the schema once,
                // then reset only populated fixture tables, including their IDs.
                \Illuminate\Support\Facades\Schema::withoutForeignKeyConstraints(function () {
                    foreach (\Illuminate\Support\Facades\DB::select('SHOW TABLE STATUS') as $table) {
                        if ($table->Name === 'migrations') continue;
                        $query = \Illuminate\Support\Facades\DB::table($table->Name);
                        if (($table->Auto_increment ?? 0) > 1 || $query->exists()) $query->truncate();
                    }
                });
            }
        }
        // Public-only fixture. Its private key is discarded; no deployment
        // signing keys are read and this fixture cannot mint access tokens.
        config(['passport.public_key' => file_get_contents(__DIR__ . '/Fixtures/passport-public.pem')]);
    }
}
