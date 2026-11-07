<?php
namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/** CI-only MySQL/MariaDB service rehearsal. No caller-supplied server, database or paths. */
final class SyntheticMysqlDocumentRecovery
{
    public const TARGET = 'faydamed_ci_document_restore';
    private string $root;
    private string $container;
    private string $client;
    private bool $ownsTarget = false;

    public function __construct()
    {
        $this->container = getenv('FAYDAMED_CI_DB_CONTAINER') ?: '';
        if (getenv('GITHUB_ACTIONS') !== 'true' || getenv('FAYDAMED_MYSQL_CI') !== '1'
            || !app()->environment('testing') || config('database.default') !== 'mysql'
            || config('database.connections.mysql.host') !== '127.0.0.1'
            || config('database.connections.mysql.database') !== 'faydamed_ci'
            || !preg_match('/\A[a-f0-9]{12,64}\z/D', $this->container)) {
            throw new RuntimeException('Disposable CI MySQL service only.');
        }
        $this->client = $this->command(['sh', '-c', 'if command -v mariadb >/dev/null 2>&1; then echo mariadb; else echo mysql; fi']);
        $this->client = trim($this->client);
        if (!in_array($this->client, ['mysql', 'mariadb'], true)) throw new RuntimeException('Unknown service client.');
        $this->root = '/tmp/faydamed-mysql-document-recovery-'.bin2hex(random_bytes(16));
        if (!mkdir($this->root, 0700) || !mkdir($this->root.'/source', 0700)) throw new RuntimeException('Cannot create disposable private root.');
    }
    public function root(): string { return $this->root; }
    public function sourceRoot(): string { return $this->root.'/source'; }
    public function targetExists(): bool
    {
        return DB::connection('mysql')->selectOne('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [self::TARGET]) !== null;
    }
    private function command(array $args, ?string $input = null): string
    {
        $process = new Process(array_merge(['docker', '--host=unix:///var/run/docker.sock', 'exec', '-i', '-e', 'MYSQL_PWD', $this->container], $args), null,
            ['MYSQL_PWD' => (string) config('database.connections.mysql.password'), 'DOCKER_HOST' => false, 'DOCKER_CONTEXT' => false, 'DOCKER_TLS' => false, 'DOCKER_TLS_VERIFY' => false, 'DOCKER_CERT_PATH' => false], $input, 60);
        $process->mustRun();
        return $process->getOutput();
    }
    public function inventory(string $connection): array
    {
        $rows = DB::connection($connection)->table('pharmacy_source_documents')->orderBy('id')->limit(17)
            ->get(['id', 'prescription_id', 'created_by', 'path', 'size', 'sha256'])->map(fn ($r) => (array) $r)->all();
        if (!$rows || count($rows) > 16) throw new RuntimeException('Source count outside synthetic bounds.');
        $total = 0; $seen = [];
        foreach ($rows as &$row) {
            foreach (['id', 'prescription_id', 'created_by', 'size'] as $key) $row[$key] = (int) $row[$key];
            $this->relative($row['path']);
            if ($row['size'] < 1 || $row['size'] > 1048576 || !preg_match('/\A[0-9a-f]{64}\z/D', $row['sha256']) || isset($seen[$row['path']])) throw new RuntimeException('Invalid private source reference.');
            $seen[$row['path']] = true; $total += $row['size'];
        }
        if ($total > 8388608) throw new RuntimeException('Synthetic private bytes exceeded.');
        return $rows;
    }
    private function relative(string $path): void
    {
        if (strlen($path) > 240 || !preg_match('/\A[a-zA-Z0-9_.-]+(?:\/[a-zA-Z0-9_.-]+)*\z/D', $path)
            || array_intersect(explode('/', $path), ['.', '..'])) throw new RuntimeException('Unsafe private path.');
    }
    private function bytes(string $root, array $row): string
    {
        $this->relative($row['path']); $path = $root;
        if (is_link($root) || realpath($root) !== $root || !str_starts_with($root.'/', $this->root.'/')) throw new RuntimeException('Disposable roots only.');
        foreach (explode('/', $row['path']) as $part) { $path .= '/'.$part; if (is_link($path)) throw new RuntimeException('Private links refused.'); }
        if (!is_file($path) || filesize($path) !== $row['size']) throw new RuntimeException('Missing or mismatched private file.');
        $bytes = file_get_contents($path, false, null, 0, 1048577);
        if ($bytes === false || strlen($bytes) !== $row['size'] || !hash_equals($row['sha256'], hash('sha256', $bytes))) throw new RuntimeException('Private file hash mismatch.');
        return $bytes;
    }
    private function copy(string $root, array $row, string $bytes): void
    {
        $path = $root.'/'.$row['path'];
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
        $file = fopen($path, 'xb');
        if (!$file) throw new RuntimeException('Private target already exists.');
        try { chmod($path, 0600); if (fwrite($file, $bytes) !== strlen($bytes)) throw new RuntimeException('Incomplete private copy.'); }
        finally { fclose($file); }
    }
    public function snapshot(): string
    {
        // Fixture writers are stopped: test runs synchronously, no HTTP listeners/workers.
        $rows = $this->inventory('mysql'); $bundle = $this->root.'/backup';
        if (!mkdir($bundle, 0700) || !mkdir($bundle.'/files', 0700)) throw new RuntimeException('New bundle required.');
        $dump = $this->client === 'mariadb' ? 'mariadb-dump' : 'mysqldump';
        $options = $this->client === 'mysql' ? ['--set-gtid-purged=OFF', '--column-statistics=0'] : [];
        $sql = $this->command(array_merge([$dump, '-uroot', '--single-transaction', '--quick', '--hex-blob', '--no-tablespaces'], $options, ['faydamed_ci']));
        if (strlen($sql) < 1 || strlen($sql) > 33554432) throw new RuntimeException('Database outside synthetic bounds.');
        file_put_contents($bundle.'/database.sql', $sql); chmod($bundle.'/database.sql', 0600);
        foreach ($rows as $row) $this->copy($bundle.'/files', $row, $this->bytes($this->sourceRoot(), $row));
        file_put_contents($bundle.'/manifest.json', json_encode(['schema' => 1, 'database_sha256' => hash('sha256', $sql), 'files' => $rows], JSON_THROW_ON_ERROR));
        chmod($bundle.'/manifest.json', 0600);
        return hash_file('sha256', $bundle.'/manifest.json');
    }
    public function restore(string $trustedDigest): string
    {
        if ($this->targetExists() || file_exists($this->root.'/restored')) throw new RuntimeException('Existing target refused.');
        $bundle = $this->root.'/backup'; $manifestBytes = file_get_contents($bundle.'/manifest.json');
        if (!hash_equals($trustedDigest, hash('sha256', $manifestBytes))) throw new RuntimeException('Manifest digest mismatch.');
        $manifest = json_decode($manifestBytes, true, 32, JSON_THROW_ON_ERROR);
        $sql = file_get_contents($bundle.'/database.sql');
        if (($manifest['schema'] ?? null) !== 1 || strlen($sql) > 33554432 || !hash_equals($manifest['database_sha256'], hash('sha256', $sql))) throw new RuntimeException('Database digest mismatch.');
        // Validate against actual source inventory, then again against imported database.
        if ($this->inventory('mysql') !== $manifest['files']) throw new RuntimeException('Reference inventory mismatch.');
        foreach ($manifest['files'] as $row) $this->bytes($bundle.'/files', $row);
        try {
            DB::connection('mysql')->statement('CREATE DATABASE '.self::TARGET); $this->ownsTarget = true;
            $this->command([$this->client, '-uroot', self::TARGET], $sql);
            config(['database.connections.document_restore' => array_replace(config('database.connections.mysql'), ['database' => self::TARGET])]);
            DB::purge('document_restore');
            if ($this->inventory('document_restore') !== $manifest['files']) throw new RuntimeException('Restored reference inventory mismatch.');
            mkdir($this->root.'/restored', 0700);
            foreach ($manifest['files'] as $row) $this->copy($this->root.'/restored', $row, $this->bytes($bundle.'/files', $row));
            return $this->root.'/restored';
        } catch (Throwable $error) { $this->rollback(); throw $error; }
    }
    public function rollback(): void
    {
        DB::purge('document_restore');
        if ($this->ownsTarget) { DB::connection('mysql')->statement('DROP DATABASE '.self::TARGET); $this->ownsTarget = false; }
        $this->remove($this->root.'/restored');
    }
    public function destroy(): void { $this->rollback(); $this->remove($this->root); }
    private function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) { unlink($path); return; }
        if (!is_dir($path)) return;
        foreach (new \FilesystemIterator($path) as $entry) $this->remove($entry->getPathname());
        rmdir($path);
    }
}
