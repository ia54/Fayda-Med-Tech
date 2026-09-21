import { User } from '@/store/slices/authSlice';

/**
 * Get user initials from full name
 * @param fullName - The user's full name
 * @returns The user's initials
 */
export function getUserInitials(fullName?: string | null): string {
  if (!fullName) return 'U';
  
  const names = fullName.trim().split(' ');
  if (names.length === 0) return 'U';
  
  if (names.length === 1) {
    return names[0].charAt(0).toUpperCase();
  }
  
  return (names[0].charAt(0) + names[names.length - 1].charAt(0)).toUpperCase();
}

/**
 * Get status color class based on user status
 * @param status - The user's status
 * @returns Tailwind CSS class for the status color
 */
export function getStatusColor(status?: 'online' | 'away' | 'busy' | 'offline'): string {
  switch (status) {
    case 'online':
      return 'bg-green-500';
    case 'away':
      return 'bg-yellow-500';
    case 'busy':
      return 'bg-red-500';
    case 'offline':
      return 'bg-gray-500';
    default:
      return 'bg-gray-500';
  }
}

/**
 * Format user roles for display
 * @param roles - Array of user roles
 * @returns Formatted roles string
 */
export function formatUserRoles(roles?: string[]): string {
  if (!roles || roles.length === 0) return 'User';
  return roles.join(', ');
}

/**
 * Get image URL with fallback
 * @param url - The image URL
 * @returns The validated image URL or undefined for fallback
 */
export function getImageUrl(url?: string | null): string | undefined {
  if (!url) return undefined;
  return url;
}