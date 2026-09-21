import { ROLES, DASHBOARD_PATHS, RoleType } from './roleConstants';

/**
 * Utility functions for role-based navigation
 */

export interface UserRole {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  role: string;
  organization: string;
  email_verified_at: string | null;
  created_at: string;
  updated_at: string;
}

/**
 * Get the dashboard path for a given user role
 * @param role - The user's role
 * @returns The path to the appropriate dashboard
 */
export function getDashboardPath(role: string): string {
  // Normalize the role value to handle any whitespace
  const normalizedRole = role.trim().toLowerCase() as RoleType;
  
  // Use the constants for mapping
  return DASHBOARD_PATHS[normalizedRole] || DASHBOARD_PATHS[ROLES.PROVIDER_STAFF];
}

/**
 * Redirect user to their appropriate dashboard based on role
 * @param user - The user object containing role information
 * @param router - Next.js router instance
 */
export function redirectToDashboard(user: UserRole, router: any): void {
  const dashboardPath = getDashboardPath(user.role);
  
  // Check if router.push is a function
  if (typeof router.push !== 'function') {
    console.error('router.push is not a function:', typeof router.push);
    return;
  }
  
  // Add a small delay to ensure state is properly set
  setTimeout(() => {
    router.push(dashboardPath);
  }, 100);
}