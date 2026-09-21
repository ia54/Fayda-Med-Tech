import { usePathname } from 'next/navigation';
import { useMemo } from 'react';

interface BreadcrumbItem {
  label: string;
  href?: string;
  fullLabel?: string; // For showing full ID in tooltip when truncated
}

const routeLabels: Record<string, string> = {
  dashboard: 'Dashboard',
  admin: 'Admin',
  billing: 'Billing',
  legal: 'Legal',
  provider: 'Provider',

  users: 'Users',
  roles: 'Roles',
  organizations: 'Organizations',
  settings: 'Settings',
  profile: 'Profile',
  'forgot-password': 'Forgot Password',
  'reset-password': 'Reset Password',
  signup: 'Sign Up',
  login: 'Login',
  audit: 'Audit',
  blog: 'Blog',
  content: 'Content',
  features: 'Features',
  integrations: 'Integrations',
  landing: 'Landing',
  security: 'Security',
  analytics: 'Analytics',
  appeals: 'Appeals',
  queue: 'Queue',
  upload: 'Upload',
  validation: 'Validation',
  cases: 'Cases',
  liens: 'Liens',
  notifications: 'Notifications',
  settlements: 'Settlements',
  claims: 'Claims',
  communications: 'Communications',
  documents: 'Documents',
  payments: 'Payments',
  reports: 'Reports',
  insurance: 'Insurance',

};

/**
 * Check if a segment is a UUID or long ID
 * UUIDs are typically 36 characters with dashes (8-4-4-4-12)
 * Or any segment longer than 20 characters
 */
const isIdSegment = (segment: string): boolean => {
  // UUID pattern
  const uuidPattern = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
  
  // Long segment (likely an ID)
  return uuidPattern.test(segment) || segment.length > 20;
};

/**
 * Truncate ID to first 5 characters + "..."
 */
const truncateId = (id: string): string => {
  return `${id.substring(0, 5)}...`;
};

export function useBreadcrumbs(): BreadcrumbItem[] {
  const pathname = usePathname();

  return useMemo(() => {
    const segments = pathname.split('/').filter(Boolean);
    const breadcrumbs: BreadcrumbItem[] = [
      { label: 'Home', href: '/dashboard' }
    ];

    let currentPath = '';
    segments.forEach((segment, index) => {
      currentPath += `/${segment}`;
      const isLast = index === segments.length - 1;

      // Determine the label for this segment
      let label: string;
      let fullLabel: string | undefined;

      if (routeLabels[segment]) {
        // Use predefined label
        label = routeLabels[segment];
      } else if (isIdSegment(segment)) {
        // Truncate IDs to first 5 characters
        label = truncateId(segment);
        fullLabel = segment; // Store full ID for tooltip
      } else {
        // Capitalize first letter for unknown segments
        label = segment.charAt(0).toUpperCase() + segment.slice(1);
      }

      breadcrumbs.push({
        label,
        href: isLast ? undefined : currentPath,
        fullLabel,
      });
    });

    return breadcrumbs;
  }, [pathname]);
}