<?php
require __DIR__.'/../../../api/app/Support/HostedPharmacyPreview.php';
use App\Support\HostedPharmacyPreview;

$valid = ['environment'=>'production', 'synthetic'=>true, 'enabled'=>true,
    'gateway_verified'=>true, 'debug'=>false, 'connection'=>'mysql', 'host'=>'localhost',
    'database'=>'faydamed_pilot', 'url'=>'https://pilot-api.faydamed.tech',
    'mail'=>'array', 'queue'=>'sync', 'broadcast'=>'null'];
$checks = 0;
$check = function (bool $actual, bool $expected, string $label) use (&$checks): void {
    if ($actual !== $expected) { throw new RuntimeException($label); }
    $checks++;
};
$check(HostedPharmacyPreview::allows($valid), true, 'Complete isolated configuration');
$check(HostedPharmacyPreview::allows([]), false, 'Empty configuration');
foreach ($valid as $key=>$value) {
    $missing=$valid; unset($missing[$key]);
    $check(HostedPharmacyPreview::allows($missing), false, 'Missing '.$key);
    $wrong=$valid; $wrong[$key]=is_bool($value) ? !$value : 'unapproved';
    $check(HostedPharmacyPreview::allows($wrong), false, 'Wrong '.$key);
}
foreach (['synthetic', 'enabled', 'gateway_verified'] as $key) {
    $wrong=$valid; $wrong[$key]='true';
    $check(HostedPharmacyPreview::allows($wrong), false, 'Untrusted string '.$key);
}
echo "Hosted pharmacy preview boundary: $checks checks passed.\n";
