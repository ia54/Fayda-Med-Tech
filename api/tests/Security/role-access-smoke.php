<?php
// Dependency-free regression check: php tests/Security/role-access-smoke.php
require __DIR__ . '/../../app/Support/RoleAccess.php';
use App\Support\RoleAccess;

$checks = 0;
$roles = RoleAccess::ROLES;
foreach (range(0, (1 << count($roles)) - 1) as $mask) {
    $allowed = [];
    foreach ($roles as $index => $role) {
        if ($mask & (1 << $index)) $allowed[] = $role;
    }
    foreach (array_merge($roles, [null, '', 'ADMIN', 'unknown']) as $role) {
        $expected = in_array($role, $allowed, true);
        if (RoleAccess::allows($role, $allowed) !== $expected) {
            throw new RuntimeException('Unexpected access decision for ' . json_encode([$role, $allowed]));
        }
        $checks++;
    }
}
foreach ([['admin', 'unknown'], ['role:admin'], ['permission:read'], ['*']] as $invalid) {
    foreach ($roles as $role) {
        if (RoleAccess::allows($role, $invalid)) throw new RuntimeException('Invalid route configuration allowed access');
        $checks++;
    }
}
echo "PASS: {$checks} role decisions, including all 64 role combinations and fail-closed configurations.\n";
