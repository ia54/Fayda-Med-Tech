<?php
namespace Tests\Recovery;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Http, Storage};
use Illuminate\Support\Str;
use Laravel\Passport\Token;
use PDO;
use RuntimeException;
use Tests\Feature\PharmacyWorkflowTest;
use Tests\Support\SyntheticDocumentRecovery;

/** Explicitly run with --filter test_coordinated_; reuses the existing synthetic pharmacy fixture. */
class CoordinatedDocumentRecoveryTest extends PharmacyWorkflowTest
{
    private SyntheticDocumentRecovery $recovery;
    private PDO $original;
    private array $sources = [];
    private array $actors = [];
    private int $rx;
    private string|false $oldOptIn;
    private const BYTES = "%PDF-1.4\n% SYNTHETIC recovery ONLY, no clinical authority\n%%EOF\n";

    protected function setUp(): void
    {
        parent::setUp(); Http::preventStrayRequests();
        $this->oldOptIn = getenv('FAYDAMED_COORDINATED_RECOVERY'); putenv('FAYDAMED_COORDINATED_RECOVERY=1');
        $this->original = DB::connection()->getPdo();
        $this->recovery = new SyntheticDocumentRecovery($this->original);
        $this->storage($this->recovery->sourceRoot());
        $actor = (new \ReflectionProperty(PharmacyWorkflowTest::class, 'actor'))->getValue($this);
        $location = (new \ReflectionProperty(PharmacyWorkflowTest::class, 'location'))->getValue($this);
        $this->actors['pharmacist'] = $actor->id;
        foreach (['technician' => ['pharmacy_technician', 1], 'biller' => ['medical_biller', 1], 'attorney' => ['attorney', 1],
            'firm' => ['firm_admin', 1], 'provider' => ['provider_staff', 1], 'foreign' => ['pharmacist', 2], 'unassigned' => ['pharmacist', 1]] as $name => [$role, $org]) {
            $user = User::create(['first_name' => 'Synthetic', 'last_name' => $name, 'email' => $name.'@example.invalid',
                'password' => 'synthetic-only', 'role' => $role, 'organization_id' => $org, 'status' => 'active']);
            $this->actors[$name] = $user->id;
            if ($name === 'technician') DB::table('pharmacy_staff_assignments')->insert(['location_id' => $location,
                'user_id' => $user->id, 'active' => true, 'valid_until' => now()->addYear()->toDateString()]);
        }
        $this->rx = (new \ReflectionMethod(PharmacyWorkflowTest::class, 'rx'))->invoke($this);
        foreach (['original', 'correction'] as $name) {
            $id = $this->post("/api/pharmacy/prescriptions/{$this->rx}/sources", ['request_id' => (string) Str::uuid(),
                'reference' => 'SYNTHETIC '.$name, 'file' => UploadedFile::fake()->createWithContent($name.'.pdf', self::BYTES.$name)],
                ['Accept' => 'application/json'])->assertCreated()->json('data.id');
            $this->sources[] = (array) DB::table('pharmacy_source_documents')->find($id);
        }
    }

    protected function tearDown(): void
    {
        config(['database.default' => 'sqlite']); DB::purge('recovery_rehearsal');
        if (isset($this->recovery)) $this->recovery->destroy();
        if (isset($this->oldOptIn)) putenv($this->oldOptIn === false ? 'FAYDAMED_COORDINATED_RECOVERY' : 'FAYDAMED_COORDINATED_RECOVERY='.$this->oldOptIn);
        parent::tearDown();
    }

    private function storage(string $root): void { config(['filesystems.disks.documents.root' => $root]); Storage::forgetDisk('documents'); }
    private function digest(PDO $pdo): string
    {
        $schema = $pdo->query('SELECT type, name, tbl_name, sql FROM sqlite_master ORDER BY type, name')->fetchAll(PDO::FETCH_ASSOC); $tables = [];
        foreach ($schema as $entry) if ($entry['type'] === 'table') {
            $rows = $pdo->query('SELECT * FROM "'.str_replace('"', '""', $entry['name']).'"')->fetchAll(PDO::FETCH_ASSOC);
            $rows = array_map(fn ($r) => hash('sha256', serialize($r)), $rows); sort($rows); $tables[$entry['name']] = $rows;
        }
        return hash('sha256', serialize([$schema, $tables]));
    }
    private function boundaries(): void
    {
        $url = "/api/pharmacy/prescriptions/{$this->rx}/sources/{$this->sources[0]['id']}/file";
        foreach (['pharmacist' => 200, 'technician' => 200, 'biller' => 403, 'attorney' => 403, 'firm' => 403,
            'provider' => 403, 'foreign' => 404, 'unassigned' => 404] as $name => $status) {
            $user = User::findOrFail($this->actors[$name]); $user->withAccessToken(new Token(['expires_at' => now()->addHour()]));
            $this->actingAs($user, 'api'); $response = $this->getJson($url)->assertStatus($status);
            if ($status === 200) $this->assertSame(self::BYTES.'original', $response->streamedContent());
        }
        app('auth')->forgetGuards(); $this->getJson($url)->assertUnauthorized();
    }

    public function test_coordinated_complete_restore_preserves_every_table_file_and_role_boundary(): void
    {
        $before = $this->digest($this->original); $this->boundaries(); $snapshot = $this->recovery->snapshot();
        $this->assertSame(2, $snapshot['files']); $target = $this->recovery->restore('backup', 'restored', $snapshot['manifest_sha256']);
        $this->assertSame($before, $this->digest(new PDO('sqlite:'.$target.'/database.sqlite')));
        $this->assertSame(0700, fileperms($target) & 0777);
        $this->assertSame(0600, fileperms($target.'/database.sqlite') & 0777);
        foreach ($this->sources as $source) {
            $this->assertSame(0600, fileperms($target.'/files/'.$source['path']) & 0777);
            $this->assertSame($source['sha256'], hash_file('sha256', $target.'/files/'.$source['path']));
            $this->assertSame($source['size'], filesize($target.'/files/'.$source['path']));
        }
        config(['database.connections.recovery_rehearsal' => array_replace(config('database.connections.sqlite'), ['database' => $target.'/database.sqlite']), 'database.default' => 'recovery_rehearsal']);
        $this->storage($target.'/files'); $this->boundaries(); $this->assertSame($before, $this->digest($this->original));
        $this->assertSame(0, DB::table('payments')->count()); $this->assertSame(0, DB::table('pharmacy_fills')->count()); Http::assertNothingSent();
    }
    public function test_coordinated_missing_tampered_and_linked_files_fail_without_partial_restore(): void
    {
        $before = $this->digest($this->original);
        foreach (['missing', 'tampered', 'linked'] as $kind) {
            $snapshot = $this->recovery->snapshot($kind); $path = $this->recovery->directory($kind).'/files/'.$this->sources[1]['path']; unlink($path);
            if ($kind === 'tampered') file_put_contents($path, str_repeat('X', $this->sources[1]['size']));
            if ($kind === 'linked') symlink($this->recovery->sourceRoot().'/'.$this->sources[1]['path'], $path);
            try { $this->recovery->restore($kind, $kind.'-restore', $snapshot['manifest_sha256']); $this->fail('Damaged source accepted.'); }
            catch (RuntimeException $error) { $this->assertMatchesRegularExpression('/Missing|hash mismatch|links/', $error->getMessage()); }
            $this->assertDirectoryDoesNotExist($this->recovery->directory($kind.'-restore')); $this->assertSame($before, $this->digest($this->original));
            foreach ($this->sources as $source) $this->assertSame($source['sha256'], hash_file('sha256', $this->recovery->sourceRoot().'/'.$source['path']));
        }
    }
    public function test_coordinated_manifest_database_and_inventory_tampering_are_rejected(): void
    {
        foreach (['manifest', 'database', 'inventory'] as $kind) {
            $snapshot = $this->recovery->snapshot($kind); $bundle = $this->recovery->directory($kind);
            if ($kind === 'database') file_put_contents($bundle.'/database.sqlite', 'SYNTHETIC damaged database');
            else { $manifest = json_decode(file_get_contents($bundle.'/manifest.json'), true); array_pop($manifest['files']); file_put_contents($bundle.'/manifest.json', json_encode($manifest));
                if ($kind === 'inventory') $snapshot['manifest_sha256'] = hash_file('sha256', $bundle.'/manifest.json'); }
            try { $this->recovery->restore($kind, $kind.'-restore', $snapshot['manifest_sha256']); $this->fail('Mismatched snapshot accepted.'); }
            catch (RuntimeException $error) { $this->assertMatchesRegularExpression('/digest mismatch|inventory mismatch/', $error->getMessage()); }
            $this->assertDirectoryDoesNotExist($this->recovery->directory($kind.'-restore'));
        }
    }
    public function test_coordinated_existing_targets_and_external_paths_are_denied(): void
    {
        $snapshot = $this->recovery->snapshot(); $target = $this->recovery->restore('backup', 'restored', $snapshot['manifest_sha256']); $before = hash_file('sha256', $target.'/database.sqlite');
        try { $this->recovery->restore('backup', 'restored', $snapshot['manifest_sha256']); $this->fail('Existing restore overwritten.'); }
        catch (RuntimeException $error) { $this->assertStringContainsString('must not already exist', $error->getMessage()); }
        $this->assertSame($before, hash_file('sha256', $target.'/database.sqlite'));
        foreach (['/etc/passwd', '../private', 'pharmacy-sources/../../private'] as $path) {
            DB::table('pharmacy_source_documents')->where('id', $this->sources[0]['id'])->update(['path' => $path]);
            try { $this->recovery->snapshot('unsafe-'.md5($path)); $this->fail('External source path accepted.'); }
            catch (RuntimeException $error) { $this->assertStringContainsString('Unsafe private source path', $error->getMessage()); }
        }
        DB::table('pharmacy_source_documents')->where('id', $this->sources[0]['id'])->update(['path' => $this->sources[0]['path']]);
        try { $this->recovery->snapshot('../outside'); $this->fail('External output accepted.'); }
        catch (RuntimeException $error) { $this->assertStringContainsString('no supplied paths', $error->getMessage()); }
    }
    public function test_coordinated_source_capture_checks_missing_corrupt_and_oversized_files(): void
    {
        $source = $this->sources[0]; $path = $this->recovery->sourceRoot().'/'.$source['path']; $bytes = file_get_contents($path);
        foreach (['missing', 'tampered', 'oversized'] as $kind) {
            if ($kind === 'missing') unlink($path);
            if ($kind === 'tampered') file_put_contents($path, str_repeat('X', strlen($bytes)));
            if ($kind === 'oversized') DB::table('pharmacy_source_documents')->where('id', $source['id'])->update(['size' => SyntheticDocumentRecovery::MAX_FILE_BYTES + 1]);
            try { $this->recovery->snapshot($kind); $this->fail('Invalid source captured.'); }
            catch (RuntimeException $error) { $this->assertMatchesRegularExpression('/Missing|hash mismatch|Invalid/', $error->getMessage()); }
            finally { file_put_contents($path, $bytes); DB::table('pharmacy_source_documents')->where('id', $source['id'])->update(['size' => $source['size']]); }
            $this->assertDirectoryDoesNotExist($this->recovery->directory($kind));
        }
    }
    public function test_coordinated_production_non_opted_in_and_file_backed_databases_are_denied(): void
    {
        foreach (['FAYDAMED_COORDINATED_RECOVERY' => '0', 'APP_ENV' => 'production'] as $key => $value) {
            $original = getenv($key); putenv($key.'='.$value);
            try { new SyntheticDocumentRecovery($this->original); $this->fail('Unsafe context accepted.'); }
            catch (RuntimeException $error) { $this->assertStringContainsString('synthetic in-memory', $error->getMessage()); }
            finally { putenv($original === false ? $key : $key.'='.$original); }
        }
        $pdo = new PDO('sqlite:'.$this->recovery->directory('other-database'));
        try { new SyntheticDocumentRecovery($pdo); $this->fail('File-backed database accepted.'); }
        catch (RuntimeException $error) { $this->assertStringContainsString('File-backed', $error->getMessage()); }
    }
    public function test_coordinated_file_count_database_size_and_attached_database_are_bounded(): void
    {
        $ids = [];
        for ($n = 0; $n < SyntheticDocumentRecovery::MAX_FILES; $n++) {
            $row = $this->sources[0]; unset($row['id']); $row['request_id'] = (string) Str::uuid();
            $row['path'] = 'synthetic-over-limit-'.$n.'.pdf'; $ids[] = DB::table('pharmacy_source_documents')->insertGetId($row);
        }
        try { $this->recovery->snapshot('over-count'); $this->fail('Unbounded file count accepted.'); }
        catch (RuntimeException $error) { $this->assertStringContainsString('count outside rehearsal bounds', $error->getMessage()); }
        finally { DB::table('pharmacy_source_documents')->whereIn('id', $ids)->delete(); }
        $ids = [];
        for ($n = 0; $n < 9; $n++) {
            $row = $this->sources[0]; unset($row['id']); $row['request_id'] = (string) Str::uuid();
            $row['path'] = 'synthetic-total-'.$n.'.pdf'; $row['size'] = SyntheticDocumentRecovery::MAX_FILE_BYTES;
            $ids[] = DB::table('pharmacy_source_documents')->insertGetId($row);
        }
        try { $this->recovery->snapshot('over-total'); $this->fail('Unbounded total file bytes accepted.'); }
        catch (RuntimeException $error) { $this->assertStringContainsString('bytes outside rehearsal bounds', $error->getMessage()); }
        finally { DB::table('pharmacy_source_documents')->whereIn('id', $ids)->delete(); }
        $other = $this->recovery->directory('attached-database');
        $this->original->exec('ATTACH DATABASE '.$this->original->quote($other).' AS external_fixture');
        try { new SyntheticDocumentRecovery($this->original); $this->fail('Attached database accepted.'); }
        catch (RuntimeException $error) { $this->assertStringContainsString('attached', $error->getMessage()); }
        finally { $this->original->exec('DETACH DATABASE external_fixture'); }
        $this->original->exec('CREATE TABLE oversized_fixture (bytes BLOB)');
        $this->original->exec('INSERT INTO oversized_fixture VALUES (zeroblob('.(SyntheticDocumentRecovery::MAX_DATABASE_BYTES + 8192).'))');
        try { $this->recovery->snapshot('over-database'); $this->fail('Oversized database copied.'); }
        catch (RuntimeException $error) { $this->assertStringContainsString('Database outside rehearsal bounds', $error->getMessage()); }
        $this->assertDirectoryDoesNotExist($this->recovery->directory('over-database'));
    }

}
