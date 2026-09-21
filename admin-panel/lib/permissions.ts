/**
 * Centralized permissions utility — PDF Section 2 & 17
 * Provides functions for checking feature-level and route-level access
 */

import {
  ROLES,
  RoleType,
  FeatureKey,
  PermissionLevel,
  PERMISSION_LEVELS,
  ROLE_FEATURES,
  MENU_CONFIG,
  ROUTE_PERMISSIONS,
  MenuItemConfig,
} from './roleConstants';

/**
 * Get the permission level a role has for a given feature
 * @param userRole - The user's role
 * @param feature - The feature to check
 * @returns The permission level (full_access, read_only, no_access, configurable)
 */
export function getPermissionLevel(userRole: RoleType, feature: FeatureKey): PermissionLevel {
  const roleFeatures = ROLE_FEATURES[userRole];
  if (!roleFeatures) return PERMISSION_LEVELS.NO_ACCESS;

  return (roleFeatures[feature] as PermissionLevel) || PERMISSION_LEVELS.NO_ACCESS;
}

/**
 * Check if a user role can access a feature at a given permission level
 * @param userRole - The user's role
 * @param feature - The feature to check
 * @param requiredLevel - Minimum required permission level (defaults to read_only)
 * @returns true if the user has sufficient access
 */
export function canAccess(
  userRole: RoleType,
  feature: FeatureKey,
  requiredLevel: PermissionLevel = PERMISSION_LEVELS.READ_ONLY
): boolean {
  // Super Admin always has full access
  if (userRole === ROLES.ADMIN) return true;

  const userLevel = getPermissionLevel(userRole, feature);

  if (userLevel === PERMISSION_LEVELS.NO_ACCESS) return false;
  if (userLevel === PERMISSION_LEVELS.CONFIGURABLE) return true; // configurable means potentially allowed
  if (userLevel === PERMISSION_LEVELS.FULL_ACCESS) return true;

  // read_only access only passes if required level is also read_only
  if (userLevel === PERMISSION_LEVELS.READ_ONLY) {
    return requiredLevel === PERMISSION_LEVELS.READ_ONLY;
  }

  return false;
}

/**
 * Check if a user role can perform write operations on a feature
 * @param userRole - The user's role
 * @param feature - The feature to check
 * @returns true if the user has full_access
 */
export function canWrite(userRole: RoleType, feature: FeatureKey): boolean {
  return canAccess(userRole, feature, PERMISSION_LEVELS.FULL_ACCESS);
}

/**
 * Filter menu items by the current user's role
 * Recursively filters children as well
 * @param menuConfig - The full menu configuration
 * @param userRole - The user's role
 * @returns Filtered menu items visible to this role
 */
export function filterMenuByRole(menuConfig: MenuItemConfig[], userRole: RoleType): MenuItemConfig[] {
  return menuConfig
    .filter((item) => {
      // Strictly check if the user's role is in the item's allowed roles array
      if (!item.roles.includes(userRole)) return false;

      // Check minimum permission level if specified
      if (item.permissionLevel && item.permissionLevel === PERMISSION_LEVELS.FULL_ACCESS) {
        const featureAccess = getPermissionLevel(userRole, item.feature);
        if (featureAccess === PERMISSION_LEVELS.READ_ONLY) return false;
      }

      return true;
    })
    .map((item) => {
      if (item.children) {
        const filteredChildren = filterMenuByRole(item.children, userRole);
        // Only include parent if it has visible children
        if (filteredChildren.length === 0) return null;
        return { ...item, children: filteredChildren };
      }
      return item;
    })
    .filter(Boolean) as MenuItemConfig[];
}

/**
 * Check if a route is allowed for a user role
 * Uses ROUTE_PERMISSIONS for exact match, then tries prefix matching
 * @param userRole - The user's role
 * @param pathname - The current route path
 * @returns true if the user can access this route
 */
export function isRouteAllowed(userRole: RoleType, pathname: string): boolean {
  // Super Admin can access everything
  if (userRole === ROLES.ADMIN) return true;

  // Try exact match first
  const exactMatch = ROUTE_PERMISSIONS[pathname];
  if (exactMatch) {
    return exactMatch.roles.includes(userRole);
  }

  // Try prefix match (for dynamic routes like /dashboard/legal/cases/[id])
  const sortedPaths = Object.keys(ROUTE_PERMISSIONS)
    .filter((p) => pathname.startsWith(p))
    .sort((a, b) => b.length - a.length); // longest match first

  if (sortedPaths.length > 0) {
    const bestMatch = ROUTE_PERMISSIONS[sortedPaths[0]];
    return bestMatch.roles.includes(userRole);
  }

  // If no route permission found, allow (the route might not need RBAC)
  return true;
}

/**
 * Get the route permission level for a user
 * @param userRole - The user's role
 * @param pathname - The current route path
 * @returns The permission level for this route, or null if no route config exists
 */
export function getRoutePermissionLevel(
  userRole: RoleType,
  pathname: string
): PermissionLevel | null {
  // Try exact match first
  const exactMatch = ROUTE_PERMISSIONS[pathname];
  if (exactMatch && exactMatch.roles.includes(userRole)) {
    return exactMatch.level;
  }

  // Try prefix match
  const sortedPaths = Object.keys(ROUTE_PERMISSIONS)
    .filter((p) => pathname.startsWith(p))
    .sort((a, b) => b.length - a.length);

  if (sortedPaths.length > 0) {
    const bestMatch = ROUTE_PERMISSIONS[sortedPaths[0]];
    if (bestMatch.roles.includes(userRole)) {
      return bestMatch.level;
    }
  }

  return null;
}
