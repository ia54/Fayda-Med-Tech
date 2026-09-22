<?php
namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('schema-install')]
class FreshDatabaseTest extends TestCase
{
    public function test_all_migrations_build_a_fresh_synthetic_database(): void
    {
        $exitCode = \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
        $this->assertSame(0, $exitCode);
        foreach (['users', 'organizations', 'cases', 'documents', 'invoices', 'payments', 'api_credentials'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }
    }
}
