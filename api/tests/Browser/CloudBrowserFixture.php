<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/** Shared safeguards for explicitly opted-in, disposable cloud browser seeds. */
trait CloudBrowserFixture
{
    protected function setUp(): void
    {
        if (getenv('FAYDAMED_BROWSER_FIXTURE') !== '1' || getenv('APP_ENV') !== 'testing'
            || getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:'
            || getenv('FAYDAMED_MYSQL_CI') === '1') {
            throw new \LogicException('Browser fixture requires the explicit in-memory synthetic test configuration.');
        }
        parent::setUp();
    }

    private function browserDirectory(string $database): string
    {
        $directory = realpath((string) getenv('FAYDAMED_BROWSER_FIXTURE_DIR'));
        $this->assertIsString($directory);
        $this->assertStringStartsWith('/workspace/.cloud-tools/', $directory.'/');
        foreach ([$database, 'sessions.json'] as $file) $this->assertFileDoesNotExist($directory.'/'.$file);
        return $directory;
    }

    /** Only fixture users receive seeded MFA-attested sessions; no login is tested. */
    private function browserSessions(array $users): array
    {
        $provenance = '/workspace/.cloud-tools/synthetic-passport-public.sha256';
        $this->assertFileExists($provenance);
        $this->assertSame(trim(file_get_contents($provenance)), hash_file('sha256', storage_path('oauth-public.key')));
        config(['passport.public_key' => file_get_contents(storage_path('oauth-public.key')),
            'passport.private_key' => file_get_contents(storage_path('oauth-private.key'))]);
        Artisan::call('passport:client', ['--personal' => true, '--name' => 'SYNTHETIC cloud browser only', '--no-interaction' => true]);
        $sessions = [];
        foreach ($users as $name => $user) {
            $this->assertInstanceOf(User::class, $user);
            $user->forceFill(['two_factor_enabled' => true])->save();
            $issued = $user->createToken('SYNTHETIC browser acceptance', ['api']);
            $issued->token->forceFill(['mfa_verified_at' => now()])->save();
            $sessions[$name] = ['user' => $user->fresh()->toArray(), 'token' => ['access_token' => $issued->accessToken,
                'refresh_token' => '', 'token_type' => 'Bearer', 'expires_in' => 3600], 'expiresAt' => now()->addHour()->getTimestampMs()];
        }
        return $sessions;
    }

    private function exportBrowserDatabase(string $directory, string $database, array $evidence): void
    {
        $evidence['source_hashes'] = [];
        $paths = [__FILE__, (new \ReflectionClass($this))->getFileName(), base_path('tests/Feature/PharmacyWorkflowTest.php')];
        // Bind browser evidence to the complete application source, excluding dependencies and secrets.
        foreach (['api/app', 'api/routes', 'admin-panel/app', 'admin-panel/components', 'admin-panel/store', 'admin-panel/hooks', 'admin-panel/lib'] as $source) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(base_path()).'/'.$source, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) if ($file->isFile() && in_array($file->getExtension(), ['php', 'ts', 'tsx', 'js', 'mjs'], true)) $paths[] = $file->getPathname();
        }
        sort($paths);
        foreach ($paths as $path) {
            $relative = substr(realpath($path), strlen(dirname(base_path()).'/'));
            $evidence['source_hashes'][$relative] = hash_file('sha256', $path);
        }
        umask(0077);
        DB::statement('VACUUM INTO ?', [$directory.'/'.$database]);
        file_put_contents($directory.'/sessions.json', json_encode($evidence, JSON_THROW_ON_ERROR));
        chmod($directory.'/sessions.json', 0600);
        chmod($directory.'/'.$database, 0600);
        $this->assertFileExists($directory.'/'.$database);
    }
}
