<?php
// CLI only. Read-only, no DB connection, networking, key output or host modification.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
$required = ['ctype','curl','dom','fileinfo','filter','hash','intl','json','mbstring','openssl','pcre','pdo','pdo_mysql','session','sodium','tokenizer','xml','zip','bcmath'];
$extensions = [];
foreach ($required as $name) $extensions[$name] = extension_loaded($name);
$versionOk = PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 3;
$ok = $versionOk && !in_array(false, $extensions, true);
echo json_encode(['php_version'=>PHP_VERSION,'php_83'=>$versionOk,'sapi'=>PHP_SAPI,'extensions'=>$extensions,
    'memory_limit'=>ini_get('memory_limit'),'upload_max_filesize'=>ini_get('upload_max_filesize'),
    'post_max_size'=>ini_get('post_max_size'),'max_execution_time'=>ini_get('max_execution_time'),
    'passed'=>$ok,'not_verified'=>['web PHP matches CLI','MySQL version and grants','cron and worker availability','private storage access denial','managed Node 22, injected PORT and memory','TLS and approved origins']], JSON_PRETTY_PRINT), "\n";
exit($ok ? 0 : 1);
