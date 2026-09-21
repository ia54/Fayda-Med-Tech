<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * Supported options:
     * - role:admin,firm_admin (checks user role)
     * - permission:manage_users (checks user permissions JSON)
     * - role:admin,firm_admin|permission:manage_users (either role OR permission)
     * - role:admin,firm_admin&permission:manage_users (both role AND permission)
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ...$params): Response
    {
        if (!$request->user()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Parse parameters for roles and permissions
        $roles = [];
        $permissions = [];
        $operator = 'or'; // Default: OR between role and permission checks

        foreach ($params as $param) {
            if (str_starts_with($param, 'role:')) {
                $roles = explode(',', substr($param, 5));
            } elseif (str_starts_with($param, 'permission:')) {
                $permissions = explode(',', substr($param, 11));
            } elseif ($param === '&' || $param === 'AND') {
                $operator = 'and';
            }
        }

        // Check roles
        $roleCheck = empty($roles) || in_array($request->user()->role, $roles);

        // Check permissions (granular)
        $permissionCheck = empty($permissions) || $this->hasAnyPermission($request->user(), $permissions);

        // Evaluate based on operator
        if ($operator === 'and') {
            $hasAccess = $roleCheck && $permissionCheck;
        } else {
            $hasAccess = $roleCheck || $permissionCheck;
        }

        if ($hasAccess) {
            return $next($request);
        }

        return response()->json([
            'status' => false,
            'message' => 'You do not have the required permissions to access this resource'
        ], 403);
    }

    /**
     * Check if user has any of the specified permissions
     */
    private function hasAnyPermission($user, array $requiredPermissions): bool
    {
        $userPermissions = $user->permissions ?? [];
        
        if (empty($userPermissions)) {
            return false;
        }

        foreach ($requiredPermissions as $permission) {
            if (in_array($permission, $userPermissions)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the specified permissions
     */
    private function hasAllPermissions($user, array $requiredPermissions): bool
    {
        $userPermissions = $user->permissions ?? [];
        
        if (empty($userPermissions)) {
            return false;
        }

        foreach ($requiredPermissions as $permission) {
            if (!in_array($permission, $userPermissions)) {
                return false;
            }
        }

        return true;
    }
}
