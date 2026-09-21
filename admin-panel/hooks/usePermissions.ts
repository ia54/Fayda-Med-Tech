import { useSelector } from "react-redux";
import { RootState } from "@/store/store";
import { ROLES, RoleType, FeatureKey, PermissionLevel, PERMISSION_LEVELS } from "@/lib/roleConstants";
import { canAccess, canWrite, getPermissionLevel, isRouteAllowed } from "@/lib/permissions";
import { usePathname } from "next/navigation";

/**
 * Hook to check if the current user is an admin or firm admin (manager)
 * @returns boolean indicating if user has admin or firm admin permissions
 */
export function useIsAdminOrManager(): boolean {
  const user = useSelector((state: RootState) => state.auth.user);

  if (!user) return false;

  // Check if user has admin or firm admin role (PDF spec roles)
  return user.role === ROLES.ADMIN || user.role === ROLES.FIRM_ADMIN;
}

/**
 * Hook to check if the current user has a specific role
 * @param role - The role to check for
 * @returns boolean indicating if user has the specified role
 */
export function useHasRole(role: string): boolean {
  const user = useSelector((state: RootState) => state.auth.user);

  if (!user) return false;

  return user.role === role;
}

/**
 * Hook to check if the current user has any of the specified roles
 * @param roles - Array of roles to check for
 * @returns boolean indicating if user has any of the specified roles
 */
export function useHasAnyRole(roles: string[]): boolean {
  const user = useSelector((state: RootState) => state.auth.user);

  if (!user || !roles.length) return false;

  return roles.includes(user.role);
}

/**
 * Hook to get the current user's role as a typed RoleType
 * @returns The current user's role, or null if not authenticated
 */
export function useCurrentRole(): RoleType | null {
  const user = useSelector((state: RootState) => state.auth.user);

  if (!user) return null;

  return user.role as RoleType;
}

/**
 * Hook to check if the current user can access a specific feature
 * @param feature - The feature to check access for
 * @param requiredLevel - Minimum required permission level (defaults to read_only)
 * @returns boolean indicating if the user has access
 */
export function useCanAccess(
  feature: FeatureKey,
  requiredLevel: PermissionLevel = PERMISSION_LEVELS.READ_ONLY
): boolean {
  const user = useSelector((state: RootState) => state.auth.user);

  if (!user) return false;

  return canAccess(user.role as RoleType, feature, requiredLevel);
}

/**
 * Hook to check if the current user can write (create/edit/delete) for a feature
 * @param feature - The feature to check
 * @returns boolean indicating if the user has full access
 */
export function useCanWrite(feature: FeatureKey): boolean {
  const user = useSelector((state: RootState) => state.auth.user);

  if (!user) return false;

  return canWrite(user.role as RoleType, feature);
}

/**
 * Hook to get the current user's permission level for a feature
 * @param feature - The feature to check
 * @returns The permission level (full_access, read_only, no_access, configurable)
 */
export function usePermissionLevel(feature: FeatureKey): PermissionLevel {
  const user = useSelector((state: RootState) => state.auth.user);

  if (!user) return PERMISSION_LEVELS.NO_ACCESS;

  return getPermissionLevel(user.role as RoleType, feature);
}

/**
 * Hook to check if the current route is allowed for the user
 * @returns boolean indicating if the current route is allowed
 */
export function useIsRouteAllowed(): boolean {
  const user = useSelector((state: RootState) => state.auth.user);
  const pathname = usePathname();

  if (!user) return false;

  return isRouteAllowed(user.role as RoleType, pathname);
}