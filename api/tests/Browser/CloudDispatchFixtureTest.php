<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\PharmacyWorkflowTest;

/** Manual synthetic browser fixture; intentionally outside the default suites. */
class CloudDispatchFixtureTest extends PharmacyWorkflowTest
{
    protected function setUp(): void
    {
        // Reject unsafe configuration before the inherited fixture migrates.
        if (getenv('FAYDAMED_BROWSER_FIXTURE') !== '1' || getenv('APP_ENV') !== 'testing'
            || getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:'
            || getenv('FAYDAMED_MYSQL_CI') === '1') {
            throw new \LogicException('Browser fixture requires the explicit in-memory synthetic test configuration.');
        }
        parent::setUp();
    }

    public function test_seed_cloud_dispatch_browser_fixture(): void
    {
        $this->assertSame('1', getenv('FAYDAMED_BROWSER_FIXTURE'));
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $directory = realpath((string) getenv('FAYDAMED_BROWSER_FIXTURE_DIR'));
        $this->assertIsString($directory);
        $this->assertStringStartsWith('/workspace/.cloud-tools/', $directory.'/');
        $this->assertFileDoesNotExist($directory.'/dispatch.sqlite');
        $this->assertFileDoesNotExist($directory.'/sessions.json');

        // Reuse the source-bound request fixture rather than inventing ledger rows.
        $fixture = new \ReflectionMethod(PharmacyWorkflowTest::class, 'resolvedLossFixture');
        [$prescription, $lot, $fill] = $fixture->invoke($this);
        $property = new \ReflectionProperty(PharmacyWorkflowTest::class, 'actor');
        $author = $property->getValue($this);
        $reviewer = (new \ReflectionMethod(PharmacyWorkflowTest::class, 'independentReviewer'))->invoke($this);
        $this->assertSame('6.000', $fill['dispatch']['remaining_loss_quantity']);
        $this->assertEquals(40, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));

        // Only cloud-generated synthetic keys are used. These seeded sessions
        // do not constitute browser login or MFA acceptance.
        $provenance = '/workspace/.cloud-tools/synthetic-passport-public.sha256';
        $this->assertFileExists($provenance);
        $this->assertSame(trim(file_get_contents($provenance)), hash_file('sha256', storage_path('oauth-public.key')));
        config(['passport.public_key' => file_get_contents(storage_path('oauth-public.key')),
            'passport.private_key' => file_get_contents(storage_path('oauth-private.key'))]);
        Artisan::call('passport:client', ['--personal' => true, '--name' => 'SYNTHETIC cloud browser only', '--no-interaction' => true]);
        $sessions = [];
        foreach (['author' => $author, 'reviewer' => $reviewer] as $name => $user) {
            $this->assertInstanceOf(User::class, $user);
            $token = $user->createToken('SYNTHETIC browser acceptance', ['api']);
            $sessions[$name] = ['user' => $user->fresh()->toArray(), 'token' => [
                'access_token' => $token->accessToken, 'refresh_token' => '', 'token_type' => 'Bearer', 'expires_in' => 3600],
                'expiresAt' => now()->addHour()->getTimestampMs()];
        }
        $evidence = ['prescription_id' => $prescription, 'source_lot_id' => $lot, 'fill_id' => $fill['id'],
            'label_code' => (new \ReflectionMethod(PharmacyWorkflowTest::class, 'dispatchReturnEvidence'))->invoke($this, $fill)['label_code'],
            'sessions' => $sessions];
        DB::statement('VACUUM INTO ?', [$directory.'/dispatch.sqlite']);
        file_put_contents($directory.'/sessions.json', json_encode($evidence, JSON_THROW_ON_ERROR));
        chmod($directory.'/sessions.json', 0600);
        chmod($directory.'/dispatch.sqlite', 0600);
        $this->assertFileExists($directory.'/dispatch.sqlite');
    }
}
