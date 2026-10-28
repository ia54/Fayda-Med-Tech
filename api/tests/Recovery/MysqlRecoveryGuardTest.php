<?php
namespace Tests\Recovery;

use RuntimeException;
use Tests\Support\SyntheticMysqlDocumentRecovery;
use Tests\TestCase;

class MysqlRecoveryGuardTest extends TestCase
{
    public function test_mysql_recovery_refuses_non_ci_production_external_and_non_disposable_targets(): void
    {
        $keys = ['GITHUB_ACTIONS', 'FAYDAMED_MYSQL_CI', 'FAYDAMED_CI_DB_CONTAINER'];
        $old = array_combine($keys, array_map('getenv', $keys));
        $oldEnvironment = app()->environment();
        $config = ['app.env' => config('app.env'), 'database.default' => config('database.default'),
            'database.connections.mysql.host' => config('database.connections.mysql.host'),
            'database.connections.mysql.database' => config('database.connections.mysql.database')];
        try {
            foreach (['not-ci', 'no-opt-in', 'production', 'external', 'operational-db', 'bad-container'] as $kind) {
                app()->instance('env', 'testing');
                putenv('GITHUB_ACTIONS=true'); putenv('FAYDAMED_MYSQL_CI=1'); putenv('FAYDAMED_CI_DB_CONTAINER='.str_repeat('a', 64));
                config(['app.env' => 'testing', 'database.default' => 'mysql', 'database.connections.mysql.host' => '127.0.0.1', 'database.connections.mysql.database' => 'faydamed_ci']);
                if ($kind === 'not-ci') putenv('GITHUB_ACTIONS=false');
                if ($kind === 'no-opt-in') putenv('FAYDAMED_MYSQL_CI=0');
                if ($kind === 'production') app()->instance('env', 'production');
                if ($kind === 'external') config(['database.connections.mysql.host' => 'production.invalid']);
                if ($kind === 'operational-db') config(['database.connections.mysql.database' => 'operational']);
                if ($kind === 'bad-container') putenv('FAYDAMED_CI_DB_CONTAINER=untrusted');
                try { new SyntheticMysqlDocumentRecovery(); $this->fail('Unsafe rehearsal accepted: '.$kind); }
                catch (RuntimeException $error) { $this->assertSame('Disposable CI MySQL service only.', $error->getMessage()); }
            }
        } finally {
            app()->instance('env', $oldEnvironment);
            config($config);
            foreach ($old as $key => $value) putenv($value === false ? $key : $key.'='.$value);
        }
    }
}
