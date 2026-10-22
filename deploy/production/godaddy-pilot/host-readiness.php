<?php
/** CLI-only, read-only probe. No Laravel boot, migrations, network HTTP or record output. */
final class PilotProbeFailure extends RuntimeException {}

final class PilotHostReadiness
{
    public static function contained(string $root, string $path): bool
    {
        return $path === $root || str_starts_with($path, $root.DIRECTORY_SEPARATOR);
    }

    public static function filesystem(string $api, array $served): array
    {
        $api = realpath($api) ?: throw new PilotProbeFailure('API root missing.');
        $roots = array_map(fn ($p) => realpath($p) ?: throw new PilotProbeFailure('Served root missing.'), $served);
        if (!$roots) throw new PilotProbeFailure('Explicit served roots required.');
        $checks = [];
        foreach (['.env','storage/oauth-private.key','storage/app/private/documents','storage/framework','storage/logs','bootstrap/cache'] as $relative) {
            $p = $api.'/'.$relative; $real = realpath($p); $secret = in_array($relative, ['.env','storage/oauth-private.key'], true);
            $symlink = false; $current = $api;
            foreach (explode('/', $relative) as $part) { $current .= '/'.$part; $symlink = $symlink || is_link($current); }
            $outside = $real !== false && !array_filter($roots, fn ($r) => self::contained($r, $real));
            $mode = $real !== false ? fileperms($real) & 0777 : null;
            $private = $mode !== null && ($mode & ($secret ? 0077 : 0007)) === 0;
            $checks[$relative] = ['exists'=>$real !== false,'outside_served_roots'=>$outside,'no_symlink'=>$real !== false && !$symlink,
                'restricted_mode'=>$private,'mode'=>$mode === null ? null : sprintf('%04o', $mode),'writable_by_cli'=>$real !== false && is_writable($real)];
        }
        return ['checks'=>$checks,'public_storage_link_absent'=>!is_link($api.'/public/storage'),'passed'=>!is_link($api.'/public/storage') && !array_filter($checks, fn ($c) => !$c['exists'] || !$c['outside_served_roots'] || !$c['no_symlink'] || !$c['restricted_mode']),
            'limits'=>['CLI identity only; web PHP identity/writes unverified','Filesystem metadata is not HTTP access-denial evidence','Other products/account quota are not scanned']];
    }

    public static function expectedDatabase(string $configured, string $expected): void
    {
        if ($configured !== $expected || strlen($expected) > 64 || !preg_match('/^[a-zA-Z0-9]+_fayda_pilot_[a-z0-9_]+$/D', $expected)) {
            throw new PilotProbeFailure('Explicit matching NEW account_fayda_pilot_name database required.');
        }
    }

    public static function sql(PDO $pdo): array
    {
        $pdo->exec('SET SESSION TRANSACTION READ ONLY');
        $pdo->beginTransaction();
        try {
            $version = $pdo->query('SELECT VERSION() AS version, @@character_set_database AS charset, @@collation_database AS collation')->fetchAll(PDO::FETCH_ASSOC)[0];
            $engines = $pdo->query('SELECT ENGINE AS engine, COUNT(*) AS tables_count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() GROUP BY ENGINE')->fetchAll(PDO::FETCH_ASSOC);
            $constraints = $pdo->query('SELECT COUNT(*) AS count FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()')->fetchAll(PDO::FETCH_ASSOC)[0];
            return ['connection'=>'passed','read_only_transaction'=>true,'version'=>$version,'table_engines'=>$engines,'foreign_key_count'=>(int)$constraints['count'],
                'migration_privileges'=>'not_verified; operator must inspect least-privilege grants separately','data_rows_read'=>false];
        } finally {
            $pdo->rollBack();
        }
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
    try {
        $o = getopt('', ['api-root:','served-root:','sql','expected-database:']);
        if (!isset($o['api-root'],$o['served-root'])) throw new PilotProbeFailure('Supply --api-root and one or more --served-root paths.');
        $root = realpath($o['api-root']) ?: throw new PilotProbeFailure('API root missing.');
        $markerFile = dirname($root).'/PILOT-MANIFEST.json';
        if (!is_file($markerFile)) throw new PilotProbeFailure('Synthetic pilot artifact marker missing.');
        $marker = json_decode(file_get_contents($markerFile), true);
        if (basename($root) !== 'api' || ($marker['kind'] ?? '') !== 'godaddy-cpanel-php-static-pilot' || ($marker['synthetic_only'] ?? false) !== true || ($marker['clinical_release'] ?? true) !== false) throw new PilotProbeFailure('Expected synthetic pilot artifact marker required.');
        $r = ['filesystem'=>PilotHostReadiness::filesystem($root, (array)$o['served-root']), 'sql'=>'not_requested'];
        if (isset($o['sql'])) {
            // Standard runtime dotenv load; values are used only for PDO and never printed.
            require $root.'/vendor/autoload.php';
            Dotenv\Dotenv::createImmutable($root)->safeLoad();
            $get = fn ($k, $default = '') => $_ENV[$k] ?? $_SERVER[$k] ?? getenv($k) ?: $default;
            if ($get('APP_ENV') !== 'production' || $get('SYNTHETIC_PILOT') !== 'true' || $get('DB_CONNECTION') !== 'mysql') throw new PilotProbeFailure('Configured production/synthetic/mysql mode required.');
            PilotHostReadiness::expectedDatabase($get('DB_DATABASE'), $o['expected-database'] ?? '');
            $host = $get('DB_HOST', 'localhost'); $port = $get('DB_PORT', '3306');
            if ($get('DATABASE_URL') !== '' || $get('DB_URL') !== '' || $get('DB_SOCKET') !== '') throw new PilotProbeFailure('Alternate database selectors require separate review.');
            if (!in_array($host, ['localhost','127.0.0.1'], true) || (string)$port !== '3306') throw new PilotProbeFailure('Only the existing local MySQL selector is in scope.');
            if (!preg_match('/^[a-zA-Z0-9._-]+$/D', $host) || !ctype_digit((string)$port)) throw new PilotProbeFailure('Invalid database selector.');
            $pdo = new PDO('mysql:host='.$host.';port='.$port.';dbname='.$get('DB_DATABASE').';charset=utf8mb4', $get('DB_USERNAME'), $get('DB_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>5]);
            $r['sql'] = PilotHostReadiness::sql($pdo);
        }
        echo json_encode($r, JSON_PRETTY_PRINT), "\n";
        exit($r['filesystem']['passed'] ? 0 : 1);
    } catch (Throwable $e) {
        // Do not print PDO errors/DSNs, runtime values or stack traces.
        fwrite(STDERR, json_encode(['passed'=>false,'reason'=>$e instanceof PilotProbeFailure ? $e->getMessage() : 'Runtime/SQL probe failed; inspect configuration privately.'])."\n");
        exit(1);
    }
}
