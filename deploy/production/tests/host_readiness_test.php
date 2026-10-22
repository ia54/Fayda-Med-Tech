<?php
require __DIR__.'/../godaddy-pilot/host-readiness.php';
$check = function ($ok, $message) { if (!$ok) throw new RuntimeException($message); };
$base = sys_get_temp_dir().'/fayda-host-probe-'.bin2hex(random_bytes(6));
mkdir($base,0700);mkdir($base.'/api',0700);mkdir($base.'/api/public',0700);
try {
    foreach (['storage','storage/app','storage/app/private','storage/app/private/documents','storage/framework','storage/logs','bootstrap','bootstrap/cache'] as $p) mkdir($base.'/api/'.$p,0700);
    foreach (['.env','storage/oauth-private.key'] as $p) { file_put_contents($base.'/api/'.$p,'SYNTHETIC NOT A KEY');chmod($base.'/api/'.$p,0600); }
    $before = file_get_contents($base.'/api/.env');
    $r=PilotHostReadiness::filesystem($base.'/api',[$base.'/api/public']);$check($r['passed'],'Private fixture should pass');
    chmod($base.'/api/storage/app/private/documents',0755);$check(!PilotHostReadiness::filesystem($base.'/api',[$base.'/api/public'])['passed'],'World-readable directory must fail');chmod($base.'/api/storage/app/private/documents',0700);
    $check(!PilotHostReadiness::filesystem($base.'/api',[$base.'/api'])['passed'],'Serving API root must fail');
    symlink('../storage/app/private',$base.'/api/public/storage');$check(!PilotHostReadiness::filesystem($base.'/api',[$base.'/api/public'])['passed'],'Public storage symlink must fail');unlink($base.'/api/public/storage');
    $check(file_get_contents($base.'/api/.env')===$before,'Probe must not modify environment');
    foreach (['production','acct_other','acct_fayda_pilot_demo;DROP DATABASE x',''] as $bad) {
        try { PilotHostReadiness::expectedDatabase($bad,$bad);throw new RuntimeException('Bad database accepted'); } catch (PilotProbeFailure $e) {}
    }
    try { PilotHostReadiness::expectedDatabase('acct_fayda_pilot_demo','acct_fayda_pilot_other');throw new RuntimeException('Mismatch accepted'); } catch (PilotProbeFailure $e) {}
    PilotHostReadiness::expectedDatabase('acct_fayda_pilot_demo','acct_fayda_pilot_demo');
} finally {
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $p) { if ($p->isDir() && !$p->isLink()) rmdir($p->getPathname());else unlink($p->getPathname()); }rmdir($base);
}
class ProbeStatement extends PDOStatement {
    public function __construct(private array $rows) {}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args): array { return $this->rows; }
}
class ProbePDO extends PDO {
    public array $commands=[];
    public bool $fail=false;
    public function __construct() {}
    public function exec(string $statement): int|false { $this->commands[]=$statement;return 0; }
    public function beginTransaction(): bool { $this->commands[]='BEGIN';return true; }
    public function rollBack(): bool { $this->commands[]='ROLLBACK';return true; }
    public function query(string $query,?int $fetchMode=null,mixed ...$args): PDOStatement|false {
        $this->commands[]=$query;if($this->fail)throw new RuntimeException('Synthetic query failure');
        return new ProbeStatement(str_contains($query,'VERSION()')?[['version'=>'8.0 synthetic','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci']]:(str_contains($query,'ENGINE AS')?[['engine'=>'InnoDB','tables_count'=>141]]:[['count'=>12]]));
    }
}
$db=new ProbePDO;$r=PilotHostReadiness::sql($db);$check($r['read_only_transaction'] && !$r['data_rows_read'],'Read-only result');
$check($db->commands[0]==='SET SESSION TRANSACTION READ ONLY' && end($db->commands)==='ROLLBACK','Transaction safety');
foreach ($db->commands as $sql) $check((bool)preg_match('/^(SELECT |SET SESSION TRANSACTION READ ONLY$|BEGIN$|ROLLBACK$)/',$sql),'Unexpected SQL command');
$db=new ProbePDO;$db->fail=true;try{PilotHostReadiness::sql($db);throw new RuntimeException('Expected error');}catch(RuntimeException $e){$check(end($db->commands)==='ROLLBACK','Failed query must roll back');}
echo "PASS: private metadata/served-root/symlink/mode guards, exact isolated DB selector, read-only SQL and rollback on failure. No real DB contacted.\n";
