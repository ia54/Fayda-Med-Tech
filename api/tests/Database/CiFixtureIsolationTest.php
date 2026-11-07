<?php
namespace Tests\Database;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CiFixtureIsolationTest extends TestCase
{
    public static function resetModes(): array
    {
        return [['leave row'], ['delete row'], ['after deletion']];
    }

    #[DataProvider('resetModes')]
    public function test_each_fixture_starts_empty_with_reset_ids_and_enabled_constraints(string $mode): void
    {
        $this->assertSame('1', (string) DB::selectOne('SELECT @@FOREIGN_KEY_CHECKS AS enabled')->enabled);
        $this->assertSame(0, DB::table('organizations')->count());
        $this->assertGreaterThan(0, DB::table('migrations')->count());
        $id = DB::table('organizations')->insertGetId([
            'org_name'=>'Disposable CI fixture', 'org_type'=>'provider',
            'subscription_plan'=>'test', 'email'=>'isolation@example.invalid',
        ]);
        $this->assertSame(1, (int) $id);
        if ($mode === 'delete row') DB::table('organizations')->where('id', $id)->delete();
    }
}
