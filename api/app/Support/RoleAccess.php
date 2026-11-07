<?php

namespace App\Support;

final class RoleAccess
{
    public const ROLES = ['admin', 'firm_admin', 'attorney', 'medical_biller', 'provider_staff', 'pharmacist', 'pharmacy_technician', 'client'];

    /** Laravel passes role:admin,firm_admin as two plain middleware parameters. */
    public static function allows(?string $role, array $allowedRoles): bool
    {
        if (!in_array($role, self::ROLES, true) || $allowedRoles === []) {
            return false;
        }
        foreach ($allowedRoles as $allowedRole) {
            if (!in_array($allowedRole, self::ROLES, true)) {
                return false;
            }
        }
        return in_array($role, $allowedRoles, true);
    }
}
