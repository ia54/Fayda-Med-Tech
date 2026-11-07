<?php

namespace Tests\Support;

use PDO;
use RuntimeException;
use Throwable;

/** Test-only, bounded SQLite/private-source rehearsal. Not an operational restore tool. */
final class SyntheticDocumentRecovery
{
    public const MAX_FILES = 16;
    public const MAX_FILE_BYTES = 1048576;
    public const MAX_TOTAL_BYTES = 8388608;
    public const MAX_DATABASE_BYTES = 33554432;
    private string $workspace;

    public function __construct(private PDO $source)
    {
        if (getenv('FAYDAMED_COORDINATED_RECOVERY') !== '1' || getenv('APP_ENV') !== 'testing'
            || getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:'
            || getenv('FAYDAMED_MYSQL_CI') === '1' || $source->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            throw new RuntimeException('Explicit synthetic in-memory recovery only.');
        }
        foreach ($source->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC) as $database) {
            if ($database['file'] !== '' || !in_array($database['name'], ['main', 'temp'], true)) {
                throw new RuntimeException('File-backed or attached databases are not permitted.');
            }
        }
        $this->workspace = '/tmp/faydamed-document-recovery-'.bin2hex(random_bytes(16));
        if (!mkdir($this->workspace, 0700)) throw new RuntimeException('Cannot create disposable workspace.');
        $this->newDirectory('source');
    }

    public function sourceRoot(): string { return $this->workspace.'/source'; }
    public function directory(string $name): string
    {
        if (!preg_match('/\A[a-z][a-z0-9-]{0,47}\z/D', $name)) throw new RuntimeException('Disposable names only; no supplied paths.');
        return $this->workspace.'/'.$name;
    }

    /** Capture a consistent SQLite snapshot, then bind immutable files to its rows. */
    public function snapshot(string $name = 'backup'): array
    {
        $bundle = $this->newDirectory($name);
        try {
            if ($this->source->inTransaction()) throw new RuntimeException('Finish fixture writes before snapshot.');
            $pages = (int) $this->source->query('PRAGMA page_count')->fetchColumn();
            $pageBytes = (int) $this->source->query('PRAGMA page_size')->fetchColumn();
            if ($pages * $pageBytes > self::MAX_DATABASE_BYTES) throw new RuntimeException('Database outside rehearsal bounds.');
            $this->source->exec('VACUUM INTO '.$this->source->quote($bundle.'/database.sqlite'));
            chmod($bundle.'/database.sqlite', 0600);
            $database = $this->readFile($bundle, 'database.sqlite', self::MAX_DATABASE_BYTES);
            $rows = $this->references($this->database($bundle.'/database.sqlite'));
            mkdir($bundle.'/files', 0700);
            foreach ($rows as $row) $this->writeFile($bundle.'/files', $row['path'], $this->sourceBytes($this->sourceRoot(), $row));
            $manifest = ['schema' => 1, 'purpose' => 'synthetic-pharmacy-source-recovery',
                'database_sha256' => hash('sha256', $database), 'files' => $rows];
            $bytes = json_encode($manifest, JSON_THROW_ON_ERROR);
            $this->writeFile($bundle, 'manifest.json', $bytes);
            return ['bundle' => $name, 'manifest_sha256' => hash('sha256', $bytes), 'files' => count($rows),
                'database_sha256' => $manifest['database_sha256']];
        } catch (Throwable $error) { $this->remove($bundle); throw $error; }
    }

    /** Require an independently retained manifest digest; never overwrite a target. */
    public function restore(string $bundleName, string $targetName, string $manifestDigest): string
    {
        $bundle = $this->directory($bundleName);
        $target = $this->newDirectory($targetName); // Outside try: existing targets must never be removed.
        try {
            $bytes = $this->readFile($bundle, 'manifest.json', 262144);
            if (!preg_match('/\A[0-9a-f]{64}\z/D', $manifestDigest) || !hash_equals($manifestDigest, hash('sha256', $bytes))) {
                throw new RuntimeException('Recovery manifest digest mismatch.');
            }
            $manifest = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($manifest) || ($manifest['schema'] ?? null) !== 1
                || ($manifest['purpose'] ?? null) !== 'synthetic-pharmacy-source-recovery'
                || !is_string($manifest['database_sha256'] ?? null) || !is_array($manifest['files'] ?? null)) {
                throw new RuntimeException('Invalid synthetic recovery manifest.');
            }
            $database = $this->readFile($bundle, 'database.sqlite', self::MAX_DATABASE_BYTES);
            if (!hash_equals($manifest['database_sha256'], hash('sha256', $database))) throw new RuntimeException('Recovery database digest mismatch.');
            $this->writeFile($target, 'database.sqlite', $database);
            // Compare the actual restored references, not merely the manifest's claims.
            $rows = $this->references($this->database($target.'/database.sqlite'));
            if ($rows !== $manifest['files']) throw new RuntimeException('Database-to-file reference inventory mismatch.');
            mkdir($target.'/files', 0700);
            foreach ($rows as $row) $this->writeFile($target.'/files', $row['path'], $this->sourceBytes($bundle.'/files', $row));
            return $target;
        } catch (Throwable $error) { $this->remove($target); throw $error; }
    }

    public function destroy(): void { $this->remove($this->workspace); }

    private function references(PDO $database): array
    {
        $rows = $database->query('SELECT id, prescription_id, created_by, path, size, sha256 FROM pharmacy_source_documents ORDER BY id LIMIT '.(self::MAX_FILES + 1))->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows || count($rows) > self::MAX_FILES) throw new RuntimeException('Synthetic source count outside rehearsal bounds.');
        $total = 0; $seen = [];
        foreach ($rows as &$row) {
            foreach (['id', 'prescription_id', 'created_by', 'size'] as $key) $row[$key] = (int) $row[$key];
            $this->safeRelative($row['path']);
            if ($row['size'] < 1 || $row['size'] > self::MAX_FILE_BYTES || !preg_match('/\A[0-9a-f]{64}\z/D', $row['sha256'])
                || isset($seen[$row['path']])) throw new RuntimeException('Invalid or duplicate private source reference.');
            $seen[$row['path']] = true; $total += $row['size'];
        }
        if ($total > self::MAX_TOTAL_BYTES) throw new RuntimeException('Synthetic source bytes outside rehearsal bounds.');
        return $rows;
    }

    private function database(string $path): PDO
    {
        $pdo = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('PRAGMA query_only=ON');
        $pdo->exec('PRAGMA trusted_schema=OFF');
        return $pdo;
    }

    private function sourceBytes(string $root, array $row): string
    {
        $bytes = $this->readFile($root, $row['path'], self::MAX_FILE_BYTES);
        if (strlen($bytes) !== $row['size'] || !hash_equals($row['sha256'], hash('sha256', $bytes))) {
            throw new RuntimeException('Database-referenced private source size/hash mismatch.');
        }
        return $bytes;
    }

    private function safeRelative(string $path): void
    {
        if (strlen($path) > 240 || !preg_match('/\A[a-zA-Z0-9_.-]+(?:\/[a-zA-Z0-9_.-]+)*\z/D', $path)
            || array_intersect(explode('/', $path), ['.', '..'])) throw new RuntimeException('Unsafe private source path.');
    }

    private function safeRoot(string $root): void
    {
        if (!is_dir($root) || is_link($root) || !str_starts_with($root.'/', $this->workspace.'/')
            || realpath($root) !== $root) throw new RuntimeException('Disposable private roots only.');
    }

    private function readFile(string $root, string $path, int $maximum): string
    {
        $this->safeRoot($root); $this->safeRelative($path);
        $full = $root;
        foreach (explode('/', $path) as $part) {
            $full .= '/'.$part;
            if (is_link($full)) throw new RuntimeException('Private source links are not permitted.');
        }
        if (!is_file($full) || filesize($full) > $maximum) throw new RuntimeException('Missing or oversized private recovery file.');
        $bytes = file_get_contents($full, false, null, 0, $maximum + 1);
        if ($bytes === false || strlen($bytes) > $maximum) throw new RuntimeException('Cannot read bounded private recovery file.');
        return $bytes;
    }

    private function writeFile(string $root, string $path, string $bytes): void
    {
        $this->safeRoot($root); $this->safeRelative($path);
        $parent = dirname($root.'/'.$path);
        if (!is_dir($parent) && !mkdir($parent, 0700, true)) throw new RuntimeException('Cannot create private recovery folder.');
        $this->safeRoot($parent);
        $file = fopen($root.'/'.$path, 'xb');
        if (!$file) throw new RuntimeException('Refusing to replace private recovery file.');
        try {
            chmod($root.'/'.$path, 0600);
            if (fwrite($file, $bytes) !== strlen($bytes)) throw new RuntimeException('Incomplete private recovery write.');
        } finally { fclose($file); }
    }

    private function newDirectory(string $name): string
    {
        $path = $this->directory($name);
        if (file_exists($path) || is_link($path)) throw new RuntimeException('Recovery target must not already exist.');
        if (!mkdir($path, 0700)) throw new RuntimeException('Cannot create recovery target.');
        return $path;
    }

    private function remove(string $path): void
    {
        if (!file_exists($path) && !is_link($path)) return;
        if (is_link($path) || !is_dir($path)) { unlink($path); return; }
        foreach (new \FilesystemIterator($path) as $entry) $this->remove($entry->getPathname());
        rmdir($path);
    }
}
