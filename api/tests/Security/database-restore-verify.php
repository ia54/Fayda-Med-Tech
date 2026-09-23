<?php
// Compare every table and row without outputting any record contents.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
try {
    if (getenv('GITHUB_ACTIONS') !== 'true' || !app()->environment('testing')
        || config('database.default') !== 'mysql'
        || config('database.connections.mysql.host') !== '127.0.0.1'
        || config('database.connections.mysql.database') !== 'faydamed_ci') {
        throw new RuntimeException('Disposable CI database only.');
    }
    $mode = $argv[1] ?? '';
    $path = $argv[2] ?? '';
    if (!in_array($mode, ['snapshot', 'verify'], true) || $path === '') {
        throw new RuntimeException('Expected snapshot or verify and a manifest path.');
    }
    $connection = DB::connection();
    if ($mode === 'verify') {
        config(['database.connections.recovery' => array_replace(config('database.connections.mysql'), ['database' => 'faydamed_ci_restore'])]);
        $connection = DB::connection('recovery');
    }
    $manifest = [];
    foreach ($connection->select('SHOW TABLES') as $table) {
        $name = (string) array_values((array) $table)[0];
        $rows = [];
        foreach ($connection->table($name)->get() as $row) {
            $data = (array) $row;
            ksort($data);
            $rows[] = hash('sha256', serialize($data));
        }
        sort($rows, SORT_STRING);
        $manifest[$name] = ['count' => count($rows), 'sha256' => hash('sha256', implode("\n", $rows))];
    }
    ksort($manifest);
    if (count($manifest) < 10 || ($manifest['case_settlements']['count'] ?? 0) < 1
        || ($manifest['payments']['count'] ?? 0) < 1) {
        throw new RuntimeException('Recovery fixture lacks populated financial tables.');
    }
    if ($mode === 'snapshot') {
        if (file_put_contents($path, json_encode($manifest, JSON_THROW_ON_ERROR)) === false) {
            throw new RuntimeException('Could not save recovery manifest.');
        }
    } else {
        $expected = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if ($manifest !== $expected) throw new RuntimeException('Restored database differs from the snapshot.');
        // All constraints and column definitions must also survive the dump.
        foreach (array_keys($manifest) as $name) {
            $quoted = '`'.str_replace('`', '``', $name).'`';
            $original = (array) DB::connection()->selectOne('SHOW CREATE TABLE '.$quoted);
            $restored = (array) $connection->selectOne('SHOW CREATE TABLE '.$quoted);
            if (array_values($original)[1] !== array_values($restored)[1]) {
                throw new RuntimeException('Restored schema differs: '.$name);
            }
        }
        echo 'PASS: '.count($manifest)." restored tables match every row and schema definition.\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: '.$error->getMessage()."\n");
    exit(1);
}
