<?php
namespace Tests\Recovery;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Http, Storage};
use Illuminate\Support\Str;
use Laravel\Passport\Token;
use RuntimeException;
use Tests\Feature\PharmacyWorkflowTest;
use Tests\Support\SyntheticMysqlDocumentRecovery;

/** Explicit --filter test_mysql_coordinated_; synthetic CI service only. */
class MysqlCoordinatedDocumentRecoveryTest extends PharmacyWorkflowTest
{
    use \Tests\Support\RecoveryBearerProof;

    private SyntheticMysqlDocumentRecovery $recovery;
    private array $actors = [];
    private array $source;
    private int $rx;
    private const BYTES = "%PDF-1.4\n% SYNTHETIC MySQL recovery ONLY\n%%EOF\n";
    protected function setUp(): void
    {
        parent::setUp(); Http::preventStrayRequests();
        $this->recovery = new SyntheticMysqlDocumentRecovery();
        $this->storage($this->recovery->sourceRoot());
        $actor = (new \ReflectionProperty(PharmacyWorkflowTest::class, 'actor'))->getValue($this);
        $location = (new \ReflectionProperty(PharmacyWorkflowTest::class, 'location'))->getValue($this);
        $this->actors['pharmacist'] = $actor->id;
        // Exercise a real encrypted cast: preserve APP_KEY, not plaintext SSN in SQL.
        $actor->ssn = 'SYNTHETIC-NOT-AN-SSN'; $actor->save();
        foreach (['technician' => ['pharmacy_technician', 1], 'biller' => ['medical_biller', 1], 'attorney' => ['attorney', 1],
            'firm' => ['firm_admin', 1], 'provider' => ['provider_staff', 1], 'foreign' => ['pharmacist', 2], 'unassigned' => ['pharmacist', 1]] as $name => [$role, $org]) {
            $user = User::create(['first_name' => 'Synthetic', 'last_name' => $name, 'email' => $name.'@example.invalid',
                'password' => 'synthetic-only', 'role' => $role, 'organization_id' => $org, 'status' => 'active']);
            $this->actors[$name] = $user->id;
            if ($name === 'technician') DB::table('pharmacy_staff_assignments')->insert(['location_id' => $location,
                'user_id' => $user->id, 'active' => true, 'valid_until' => now()->addYear()->toDateString()]);
        }
        $this->rx = (new \ReflectionMethod(PharmacyWorkflowTest::class, 'rx'))->invoke($this);
        $id = $this->post("/api/pharmacy/prescriptions/{$this->rx}/sources", ['request_id' => (string) Str::uuid(),
            'reference' => 'SYNTHETIC coordinated original', 'file' => UploadedFile::fake()->createWithContent('original.pdf', self::BYTES)],
            ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $this->source = (array) DB::table('pharmacy_source_documents')->find($id);
    }
    protected function tearDown(): void
    {
        config(['database.default' => 'mysql']);
        if (isset($this->recovery)) $this->recovery->destroy();
        Storage::forgetDisk('documents'); parent::tearDown();
    }
    private function storage(string $root): void { config(['filesystems.disks.documents.root' => $root]); Storage::forgetDisk('documents'); }
    private function digest(string $connection): array
    {
        $result = []; $db = DB::connection($connection);
        foreach ($db->select('SHOW TABLES') as $table) {
            $name = array_values((array) $table)[0]; $hashes = [];
            foreach ($db->table($name)->get() as $row) { $row = (array) $row; ksort($row); $hashes[] = hash('sha256', serialize($row)); }
            sort($hashes); $result[$name] = $hashes;
        }
        ksort($result); return $result;
    }
    private function boundaries(): void
    {
        $url = "/api/pharmacy/prescriptions/{$this->rx}/sources/{$this->source['id']}/file";
        foreach (['pharmacist' => 200, 'technician' => 200, 'biller' => 403, 'attorney' => 403, 'firm' => 403,
            'provider' => 403, 'foreign' => 404, 'unassigned' => 404] as $name => $status) {
            $user = User::findOrFail($this->actors[$name]); $user->withAccessToken(new Token(['expires_at' => now()->addHour()]));
            $this->actingAs($user, 'api'); $response = $this->getJson($url)->assertStatus($status);
            if ($status === 200) {
                $this->assertSame(self::BYTES, $response->streamedContent());
                $this->assertSame($this->source['sha256'], hash('sha256', $response->streamedContent()));
            }
        }
        app('auth')->forgetGuards(); $this->getJson($url)->assertUnauthorized();
    }
    public function test_mysql_coordinated_real_bearer_mfa_and_encryption_survive_restore(): void
    {
        $sessions = $this->recoveryBearerSessions($this->actors);
        $url = "/api/pharmacy/prescriptions/{$this->rx}/sources/{$this->source['id']}/file";
        $this->assertRecoveryBearerBoundaries($sessions, $url, self::BYTES);
        $before = $this->digest('mysql'); $trusted = $this->recovery->snapshot();
        $this->assertStringNotContainsString('SYNTHETIC-RECOVERY-MFA-STATE', file_get_contents($this->recovery->root().'/backup/database.sql'));
        $target = $this->recovery->restore($trusted);
        config(['database.default' => 'document_restore']); $this->storage($target);
        $this->assertRecoveryBearerBoundaries($sessions, $url, self::BYTES);
        $this->assertRecoveryBearerFailureModes($sessions, $url, self::BYTES, $this->actors['pharmacist']);
        config(['database.default' => 'mysql']); $this->storage($this->recovery->sourceRoot());
        $this->recovery->rollback(); $this->assertFalse($this->recovery->targetExists());
        $this->assertSame($before, $this->digest('mysql'));
        $this->assertRecoveryBearerBoundaries($sessions, $url, self::BYTES); Http::assertNothingSent();
    }

    public function test_mysql_coordinated_restore_retrieval_boundaries_and_failure_cleanup(): void
    {
        $before = $this->digest('mysql'); $this->boundaries(); $trusted = $this->recovery->snapshot();
        $root = $this->recovery->root(); $file = $root.'/backup/files/'.$this->source['path'];
        $manifest = file_get_contents($root.'/backup/manifest.json'); $sql = file_get_contents($root.'/backup/database.sql');
        $this->assertStringNotContainsString('SYNTHETIC-NOT-AN-SSN', $sql);
        foreach (['missing', 'tampered', 'linked', 'manifest', 'database', 'import'] as $kind) {
            $digest = $trusted;
            if (in_array($kind, ['missing', 'tampered', 'linked'], true)) {
                unlink($file);
                if ($kind === 'tampered') file_put_contents($file, str_repeat('X', strlen(self::BYTES)));
                if ($kind === 'linked') symlink($this->recovery->sourceRoot().'/'.$this->source['path'], $file);
            }
            if ($kind === 'manifest') file_put_contents($root.'/backup/manifest.json', '{}');
            if (in_array($kind, ['database', 'import'], true)) {
                file_put_contents($root.'/backup/database.sql', 'INVALID SYNTHETIC SQL;');
                if ($kind === 'import') {
                    $m = json_decode($manifest, true); $m['database_sha256'] = hash('sha256', 'INVALID SYNTHETIC SQL;');
                    file_put_contents($root.'/backup/manifest.json', json_encode($m)); $digest = hash_file('sha256', $root.'/backup/manifest.json');
                }
            }
            try { $this->recovery->restore($digest); $this->fail('Damaged recovery accepted: '.$kind); }
            catch (\Symfony\Component\Process\Exception\ProcessFailedException|RuntimeException $error) { $this->assertNotSame('', $error->getMessage()); }
            finally {
                if (is_link($file) || is_file($file)) unlink($file);
                file_put_contents($file, self::BYTES); chmod($file, 0600);
                file_put_contents($root.'/backup/database.sql', $sql); file_put_contents($root.'/backup/manifest.json', $manifest);
            }
            $this->assertFalse($this->recovery->targetExists(), $kind.' left target database');
            $this->assertDirectoryDoesNotExist($root.'/restored'); $this->assertSame($before, $this->digest('mysql'));
            $this->assertSame($this->source['sha256'], hash_file('sha256', $this->recovery->sourceRoot().'/'.$this->source['path']));
        }
        $restored = $this->recovery->restore($trusted);
        $this->assertSame($before, $this->digest('document_restore'));
        foreach (array_keys($before) as $table) {
            $quoted = '`'.str_replace('`', '``', $table).'`';
            $normalize = static fn (string $sql): string => str_replace(' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', ' COLLATE utf8mb4_unicode_ci', $sql);
            $originalSchema = array_values((array) DB::connection('mysql')->selectOne('SHOW CREATE TABLE '.$quoted))[1];
            $restoredSchema = array_values((array) DB::connection('document_restore')->selectOne('SHOW CREATE TABLE '.$quoted))[1];
            $this->assertSame($normalize($originalSchema), $normalize($restoredSchema), $table.' schema differs');
        }
        $this->assertSame(0700, fileperms($restored) & 0777);
        $this->assertSame(0600, fileperms($restored.'/'.$this->source['path']) & 0777);
        try { $this->recovery->restore($trusted); $this->fail('Existing target overwritten.'); }
        catch (RuntimeException $error) { $this->assertStringContainsString('Existing target refused', $error->getMessage()); }
        config(['database.default' => 'document_restore']); $this->storage($restored);
        $this->assertSame('SYNTHETIC-NOT-AN-SSN', User::findOrFail($this->actors['pharmacist'])->ssn);
        $this->boundaries();
        $pharmacist = User::findOrFail($this->actors['pharmacist']); $pharmacist->withAccessToken(new Token(['expires_at' => now()->addHour()])); $this->actingAs($pharmacist, 'api');
        $url = "/api/pharmacy/prescriptions/{$this->rx}/sources/{$this->source['id']}/file";
        unlink($restored.'/'.$this->source['path']); $this->getJson($url)->assertNotFound();
        file_put_contents($restored.'/'.$this->source['path'], str_repeat('X', strlen(self::BYTES))); $this->getJson($url)->assertStatus(409);
        config(['database.default' => 'mysql']); $this->storage($this->recovery->sourceRoot());
        $this->recovery->rollback(); $this->assertFalse($this->recovery->targetExists()); $this->assertDirectoryDoesNotExist($root.'/restored');
        $this->assertSame($before, $this->digest('mysql')); $this->boundaries();
        Http::assertNothingSent();
        $this->recovery->destroy(); $this->assertDirectoryDoesNotExist($root);
    }
}
