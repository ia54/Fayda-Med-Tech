/**
 * Role constants for the application
 * Centralized place to manage all role strings to avoid hardcoding
 * Updated to match PDF specification (Version 1.0, 2025)
 */

// Role Constants - PDF Standard Roles
export const ROLES = {
  ADMIN: "admin",           // Super Admin - FaydaTech platform team (Full system)
  FIRM_ADMIN: "firm_admin", // Firm Admin - Law firm owner/manager (Full tenant)
  ATTORNEY: "attorney",     // Attorney - Lawyer/paralegal (Case-focused)
  MEDICAL_BILLER: "medical_biller", // Medical Biller - Billing specialist (Billing-focused)
  PROVIDER_STAFF: "provider_staff", // Provider Staff - Doctor's office staff (Provider portal)
  CLIENT: "client",         // Client (Patient) - Injured patient/claimant (Self-service)
} as const;

// Type for role values
export type RoleType = typeof ROLES[keyof typeof ROLES];

// Role Display Names (PDF Section 2)
export const ROLE_DISPLAY_NAMES: Record<RoleType, string> = {
  [ROLES.ADMIN]: "Super Admin",
  [ROLES.FIRM_ADMIN]: "Firm Admin",
  [ROLES.ATTORNEY]: "Attorney",
  [ROLES.MEDICAL_BILLER]: "Medical Biller",
  [ROLES.PROVIDER_STAFF]: "Provider Staff",
  [ROLES.CLIENT]: "Client (Patient)",
};

// Role Descriptions (PDF Section 2)
export const ROLE_DESCRIPTIONS: Record<RoleType, string> = {
  [ROLES.ADMIN]: "FaydaTech platform team - Full system access",
  [ROLES.FIRM_ADMIN]: "Law firm owner/office manager - Full tenant access",
  [ROLES.ATTORNEY]: "Lawyer/paralegal - Case-focused access",
  [ROLES.MEDICAL_BILLER]: "Billing specialist - Billing-focused access",
  [ROLES.PROVIDER_STAFF]: "Doctor's office staff - Provider portal access",
  [ROLES.CLIENT]: "Injured patient/claimant - Self-service portal access",
};

// Dashboard Paths (PDF Section 3)
export const DASHBOARD_PATHS: Record<RoleType, string> = {
  [ROLES.ADMIN]: "/dashboard/admin",
  [ROLES.FIRM_ADMIN]: "/dashboard/legal",
  [ROLES.ATTORNEY]: "/dashboard/legal",
  [ROLES.MEDICAL_BILLER]: "/dashboard/billing",
  [ROLES.PROVIDER_STAFF]: "/dashboard/provider",
  [ROLES.CLIENT]: "/dashboard/client",
};

// Permission levels for each role (PDF Section 2 - Permission Matrix Legend)
export const PERMISSION_LEVELS = {
  FULL_ACCESS: "full_access",     // Can view, create, edit, and delete
  READ_ONLY: "read_only",         // Can view but not modify
  NO_ACCESS: "no_access",        // Feature hidden from this role
  CONFIGURABLE: "configurable",  // Access set by Firm Admin per user
} as const;

// Features accessible by each role (PDF Section 17 - Complete Role-Based Menu Reference)
export const ROLE_FEATURES: Record<RoleType, Record<string, string>> = {
  [ROLES.ADMIN]: {
    dashboard: PERMISSION_LEVELS.FULL_ACCESS,
    cases: PERMISSION_LEVELS.FULL_ACCESS,
    clients: PERMISSION_LEVELS.FULL_ACCESS,
    billing: PERMISSION_LEVELS.FULL_ACCESS,
    insurance: PERMISSION_LEVELS.FULL_ACCESS,
    documents: PERMISSION_LEVELS.FULL_ACCESS,
    signatures: PERMISSION_LEVELS.FULL_ACCESS,
    providers: PERMISSION_LEVELS.FULL_ACCESS,
    liens: PERMISSION_LEVELS.FULL_ACCESS,
    reports: PERMISSION_LEVELS.FULL_ACCESS,
    users: PERMISSION_LEVELS.FULL_ACCESS,
    roles: PERMISSION_LEVELS.FULL_ACCESS,
    settings: PERMISSION_LEVELS.FULL_ACCESS,
    platform: PERMISSION_LEVELS.FULL_ACCESS,
  },
  [ROLES.FIRM_ADMIN]: {
    dashboard: PERMISSION_LEVELS.FULL_ACCESS,
    cases: PERMISSION_LEVELS.FULL_ACCESS,
    clients: PERMISSION_LEVELS.FULL_ACCESS,
    billing: PERMISSION_LEVELS.FULL_ACCESS,
    insurance: PERMISSION_LEVELS.FULL_ACCESS,
    documents: PERMISSION_LEVELS.FULL_ACCESS,
    signatures: PERMISSION_LEVELS.FULL_ACCESS,
    providers: PERMISSION_LEVELS.FULL_ACCESS,
    liens: PERMISSION_LEVELS.FULL_ACCESS,
    reports: PERMISSION_LEVELS.FULL_ACCESS,
    users: PERMISSION_LEVELS.FULL_ACCESS,
    roles: PERMISSION_LEVELS.CONFIGURABLE,
    settings: PERMISSION_LEVELS.FULL_ACCESS,
  },
  [ROLES.ATTORNEY]: {
    dashboard: PERMISSION_LEVELS.FULL_ACCESS,
    cases: PERMISSION_LEVELS.FULL_ACCESS,
    clients: PERMISSION_LEVELS.READ_ONLY,
    billing: PERMISSION_LEVELS.READ_ONLY,
    insurance: PERMISSION_LEVELS.FULL_ACCESS,
    documents: PERMISSION_LEVELS.FULL_ACCESS,
    signatures: PERMISSION_LEVELS.FULL_ACCESS,
    providers: PERMISSION_LEVELS.READ_ONLY,
    liens: PERMISSION_LEVELS.FULL_ACCESS,
    reports: PERMISSION_LEVELS.READ_ONLY,
    users: PERMISSION_LEVELS.NO_ACCESS,
    roles: PERMISSION_LEVELS.NO_ACCESS,
    settings: PERMISSION_LEVELS.NO_ACCESS,
  },
  [ROLES.MEDICAL_BILLER]: {
    dashboard: PERMISSION_LEVELS.FULL_ACCESS,
    cases: PERMISSION_LEVELS.READ_ONLY,
    clients: PERMISSION_LEVELS.READ_ONLY,
    billing: PERMISSION_LEVELS.FULL_ACCESS,
    insurance: PERMISSION_LEVELS.FULL_ACCESS,
    documents: PERMISSION_LEVELS.FULL_ACCESS,
    signatures: PERMISSION_LEVELS.FULL_ACCESS,
    providers: PERMISSION_LEVELS.READ_ONLY,
    liens: PERMISSION_LEVELS.FULL_ACCESS,
    reports: PERMISSION_LEVELS.FULL_ACCESS,
    users: PERMISSION_LEVELS.NO_ACCESS,
    roles: PERMISSION_LEVELS.NO_ACCESS,
    settings: PERMISSION_LEVELS.NO_ACCESS,
  },
  [ROLES.PROVIDER_STAFF]: {
    dashboard: PERMISSION_LEVELS.FULL_ACCESS,
    cases: PERMISSION_LEVELS.READ_ONLY,
    clients: PERMISSION_LEVELS.NO_ACCESS,
    billing: PERMISSION_LEVELS.FULL_ACCESS,
    insurance: PERMISSION_LEVELS.READ_ONLY,
    documents: PERMISSION_LEVELS.FULL_ACCESS,
    signatures: PERMISSION_LEVELS.FULL_ACCESS,
    providers: PERMISSION_LEVELS.READ_ONLY,
    liens: PERMISSION_LEVELS.READ_ONLY,
    reports: PERMISSION_LEVELS.READ_ONLY,
    users: PERMISSION_LEVELS.NO_ACCESS,
    roles: PERMISSION_LEVELS.NO_ACCESS,
    settings: PERMISSION_LEVELS.NO_ACCESS,
  },
  [ROLES.CLIENT]: {
    dashboard: PERMISSION_LEVELS.FULL_ACCESS,
    cases: PERMISSION_LEVELS.READ_ONLY,
    clients: PERMISSION_LEVELS.READ_ONLY,
    billing: PERMISSION_LEVELS.READ_ONLY,
    insurance: PERMISSION_LEVELS.NO_ACCESS,
    documents: PERMISSION_LEVELS.FULL_ACCESS,
    signatures: PERMISSION_LEVELS.FULL_ACCESS,
    providers: PERMISSION_LEVELS.NO_ACCESS,
    liens: PERMISSION_LEVELS.NO_ACCESS,
    reports: PERMISSION_LEVELS.NO_ACCESS,
    users: PERMISSION_LEVELS.NO_ACCESS,
    roles: PERMISSION_LEVELS.NO_ACCESS,
    settings: PERMISSION_LEVELS.NO_ACCESS,
  },
};

// Permission level type
export type PermissionLevel = typeof PERMISSION_LEVELS[keyof typeof PERMISSION_LEVELS];

// Feature type (keys from ROLE_FEATURES)
export type FeatureKey = keyof typeof ROLE_FEATURES[typeof ROLES.ADMIN];

/**
 * Menu item configuration for centralized sidebar navigation
 * Matches PDF Section 17 — Complete Role-Based Menu Reference
 */
export interface MenuItemConfig {
  title: string;
  href?: string;
  icon: string; // lucide icon name
  feature: FeatureKey; // which feature this maps to for permission checking
  roles: RoleType[]; // roles that can see this item
  permissionLevel?: PermissionLevel; // minimum permission level to show (defaults to read_only)
  section?: string; // visual section grouping
  children?: MenuItemConfig[];
}

// All 6 roles for convenience
const ALL_STAFF_ROLES: RoleType[] = [ROLES.ADMIN, ROLES.FIRM_ADMIN, ROLES.ATTORNEY, ROLES.MEDICAL_BILLER, ROLES.PROVIDER_STAFF];
const ALL_ROLES: RoleType[] = [...ALL_STAFF_ROLES, ROLES.CLIENT];

/**
 * Centralized menu configuration — PDF Section 17
 * Each item defines which roles can see it and what feature it maps to.
 * IMPORTANT: Each role appears in only ONE entry per concept to prevent duplicates.
 */
export const MENU_CONFIG: MenuItemConfig[] = [
  // ═══════════════════════════════════════════════
  // DASHBOARD — One per role, no overlap
  // ═══════════════════════════════════════════════
  {
    title: "Dashboard",
    href: "/dashboard/admin",
    icon: "Home",
    feature: "dashboard",
    roles: [ROLES.ADMIN],
    section: "Main",
  },
  {
    title: "Legal Dashboard",
    href: "/dashboard/legal",
    icon: "Scale",
    feature: "dashboard",
    roles: [ROLES.FIRM_ADMIN, ROLES.ATTORNEY],
    section: "Main",
  },
  {
    title: "Billing Dashboard",
    href: "/dashboard/billing",
    icon: "Home",
    feature: "dashboard",
    roles: [ROLES.MEDICAL_BILLER],
    section: "Main",
  },
  {
    title: "Provider Portal",
    href: "/dashboard/provider",
    icon: "Building2",
    feature: "dashboard",
    roles: [ROLES.PROVIDER_STAFF],
    section: "Main",
  },
  {
    title: "Reports",
    href: "/dashboard/legal/reports",
    icon: "BarChart3",
    feature: "reports",
    roles: [ROLES.FIRM_ADMIN, ROLES.ATTORNEY],
    section: "Main",
  },
  {
    title: "Reports",
    href: "/dashboard/billing/reports",
    icon: "BarChart3",
    feature: "reports",
    roles: [ROLES.MEDICAL_BILLER],
    section: "Main",
  },
  {
    title: "Reports",
    href: "/dashboard/provider/reports",
    icon: "BarChart3",
    feature: "reports",
    roles: [ROLES.PROVIDER_STAFF],
    section: "Main",
  },
  {
    title: "Client Portal",
    href: "/dashboard/client",
    icon: "User",
    feature: "dashboard",
    roles: [ROLES.CLIENT],
    section: "Main",
  },

  // ═══════════════════════════════════════════════
  // CASES — Legal management for staff, My Cases for client
  // ═══════════════════════════════════════════════
  {
    title: "Legal Management",
    icon: "Briefcase",
    feature: "cases",
    roles: [ROLES.FIRM_ADMIN, ROLES.ATTORNEY],
    section: "Work",
    children: [
      {
        title: "Case Management",
        href: "/dashboard/legal/cases",
        icon: "FolderOpen",
        feature: "cases",
        roles: [ROLES.FIRM_ADMIN, ROLES.ATTORNEY],
      },
      {
        title: "Demand Letters",
        href: "/dashboard/legal/demand-letters",
        icon: "Send",
        feature: "cases",
        roles: [ROLES.FIRM_ADMIN, ROLES.ATTORNEY],
      },
      {
        title: "New Intake",
        href: "/dashboard/legal/cases/new",
        icon: "Upload",
        feature: "cases",
        roles: [ROLES.FIRM_ADMIN, ROLES.ATTORNEY],
        permissionLevel: PERMISSION_LEVELS.FULL_ACCESS,
      },
      {
        title: "Settlement Notes",
        href: "/dashboard/legal/settlements",
        icon: "CheckCircle",
        feature: "cases",
        roles: [ROLES.FIRM_ADMIN, ROLES.ATTORNEY],
      },
    ],
  },
  {
    title: "My Cases",
    href: "/dashboard/client/cases",
    icon: "FolderOpen",
    feature: "cases",
    roles: [ROLES.CLIENT],
    section: "Work",
  },

  // ═══════════════════════════════════════════════
  // BILLING — Staff billing vs Client billing (no role overlap)
  // ═══════════════════════════════════════════════
  {
    title: "Billing",
    icon: "DollarSign",
    feature: "billing",
    roles: [ROLES.FIRM_ADMIN, ROLES.MEDICAL_BILLER, ROLES.PROVIDER_STAFF],
    section: "Work",
    children: [
      {
        title: "Invoices",
        href: "/dashboard/billing/invoices",
        icon: "FileText",
        feature: "billing",
        roles: [ROLES.FIRM_ADMIN, ROLES.MEDICAL_BILLER, ROLES.PROVIDER_STAFF],
      },
      {
        title: "EOB Processing",
        href: "/dashboard/billing/eobs",
        icon: "FileCheck",
        feature: "billing",
        roles: [ROLES.FIRM_ADMIN, ROLES.MEDICAL_BILLER],
      },
      {
        title: "Payments",
        href: "/dashboard/billing/payments",
        icon: "CreditCard",
        feature: "billing",
        roles: [ROLES.FIRM_ADMIN, ROLES.MEDICAL_BILLER],
      },
      {
        title: "CPT Library",
        href: "/dashboard/provider/cpt-library",
        icon: "BookOpen",
        feature: "billing",
        roles: [ROLES.PROVIDER_STAFF, ROLES.FIRM_ADMIN, ROLES.MEDICAL_BILLER],
      },
    ],
  },
  {
    title: "Billing",
    icon: "DollarSign",
    feature: "billing",
    roles: [ROLES.CLIENT],
    section: "Work",
    children: [
      {
        title: "Invoices",
        href: "/dashboard/client/invoices",
        icon: "FileText",
        feature: "billing",
        roles: [ROLES.CLIENT],
      },
      {
        title: "Payments",
        href: "/dashboard/client/payments",
        icon: "CreditCard",
        feature: "billing",
        roles: [ROLES.CLIENT],
      },
    ],
  },

  // ═══════════════════════════════════════════════
  // INSURANCE — Claims, settlements
  // ═══════════════════════════════════════════════
  {
    title: "Insurance",
    href: "/dashboard/insurance",
    icon: "Shield",
    feature: "insurance",
    roles: [ROLES.FIRM_ADMIN, ROLES.ATTORNEY, ROLES.MEDICAL_BILLER, ROLES.PROVIDER_STAFF],
    section: "Work",
  },

  // ═══════════════════════════════════════════════
  // DOCUMENTS — Staff documents vs Client documents
  // ═══════════════════════════════════════════════
  {
    title: "Documents",
    icon: "FileText",
    feature: "documents",
    roles: [ROLES.FIRM_ADMIN, ROLES.ATTORNEY, ROLES.MEDICAL_BILLER, ROLES.PROVIDER_STAFF],
    section: "Work",
    children: [
      {
        title: "Upload",
        href: "/dashboard/legal/documents/upload",
        icon: "Upload",
        feature: "documents",
        roles: [ROLES.FIRM_ADMIN, ROLES.ATTORNEY, ROLES.PROVIDER_STAFF],
      },
      {
        title: "AI OCR Queue",
        href: "/dashboard/billing/ocr-queue",
        icon: "Zap",
        feature: "documents",
        roles: [ROLES.FIRM_ADMIN, ROLES.MEDICAL_BILLER, ROLES.PROVIDER_STAFF],
      },
    ],
  },
  {
    title: "My Documents",
    icon: "FileText",
    feature: "documents",
    roles: [ROLES.CLIENT],
    section: "Work",
    children: [
      {
        title: "All Documents",
        href: "/dashboard/client/documents",
        icon: "FolderOpen",
        feature: "documents",
        roles: [ROLES.CLIENT],
      },
      {
        title: "Upload",
        href: "/dashboard/client/documents/upload",
        icon: "Upload",
        feature: "documents",
        roles: [ROLES.CLIENT],
      },
    ],
  },

  // ═══════════════════════════════════════════════
  // SIGNATURES — Client only
  // ═══════════════════════════════════════════════
  {
    title: "Signatures",
    href: "/dashboard/client/signatures",
    icon: "Send",
    feature: "signatures",
    roles: [ROLES.CLIENT],
    section: "Work",
  },
  {
    title: "Send Signature",
    href: "/dashboard/admin/signatures/send",
    icon: "Send",
    feature: "signatures",
    roles: [ROLES.ADMIN, ROLES.FIRM_ADMIN, ROLES.ATTORNEY, ROLES.MEDICAL_BILLER],
    section: "Work",
  },

  // ═══════════════════════════════════════════════
  // LIENS
  // ═══════════════════════════════════════════════
  {
    title: "Liens",
    href: "/dashboard/legal/liens",
    icon: "Scale",
    feature: "liens",
    roles: [ROLES.FIRM_ADMIN, ROLES.ATTORNEY, ROLES.MEDICAL_BILLER, ROLES.PROVIDER_STAFF],
    section: "Work",
  },

  // ═══════════════════════════════════════════════
  // NOTIFICATIONS — All roles
  // ═══════════════════════════════════════════════
  {
    title: "Notifications",
    href: "/dashboard/notifications",
    icon: "Bell",
    feature: "dashboard",
    roles: ALL_ROLES,
    section: "General",
  },

  // ═══════════════════════════════════════════════
  // ADMINISTRATION — User/role management
  // ═══════════════════════════════════════════════
  {
    title: "Users & Roles",
    href: "/dashboard/admin/users",
    icon: "Users",
    feature: "users",
    roles: [ROLES.ADMIN],
    section: "Administration",
  },
  {
    title: "User Management",
    href: "/dashboard/legal/users",
    icon: "Users",
    feature: "users",
    roles: [ROLES.FIRM_ADMIN],
    section: "Administration",
  },
  {
    title: "Roles & Permissions",
    href: "/dashboard/admin/roles",
    icon: "Shield",
    feature: "roles",
    roles: [ROLES.ADMIN],
    section: "Administration",
  },

  // ═══════════════════════════════════════════════
  // PLATFORM — Super Admin only
  // ═══════════════════════════════════════════════
  {
    title: "Organizations",
    href: "/dashboard/admin/organizations",
    icon: "Building2",
    feature: "platform",
    roles: [ROLES.ADMIN],
    section: "Platform",
  },
  {
    title: "Blog Management",
    href: "/dashboard/admin/blog",
    icon: "Activity",
    feature: "platform",
    roles: [ROLES.ADMIN],
    section: "Platform",
  },
  {
    title: "Content Management",
    icon: "Settings",
    feature: "platform",
    roles: [ROLES.ADMIN],
    section: "Platform",
    children: [
      {
        title: "Home",
        href: "/dashboard/admin/application/home/banner",
        icon: "Home",
        feature: "platform",
        roles: [ROLES.ADMIN],
      },
      {
        title: "Solutions",
        href: "/dashboard/admin/application/solutions/solutionsBanner",
        icon: "Settings2",
        feature: "platform",
        roles: [ROLES.ADMIN],
      },
      {
        title: "About",
        href: "/dashboard/admin/application/about/aboutBanner",
        icon: "FileText",
        feature: "platform",
        roles: [ROLES.ADMIN],
      },
      {
        title: "Pricing",
        href: "/dashboard/admin/application/pricing/pricingBanner",
        icon: "DollarSign",
        feature: "platform",
        roles: [ROLES.ADMIN],
      },
      {
        title: "Contact",
        href: "/dashboard/admin/application/contact/contactBanner",
        icon: "MessageSquare",
        feature: "platform",
        roles: [ROLES.ADMIN],
      },
      {
        title: "Privacy Policy",
        href: "/dashboard/admin/application/privacy-policy/banner",
        icon: "Lock",
        feature: "platform",
        roles: [ROLES.ADMIN],
      },
      {
        title: "Terms & Conditions",
        href: "/dashboard/admin/application/terms-condition/banner",
        icon: "AlertTriangle",
        feature: "platform",
        roles: [ROLES.ADMIN],
      },
      {
        title: "Global Settings",
        href: "/dashboard/admin/application/global-settings/cta",
        icon: "Globe",
        feature: "platform",
        roles: [ROLES.ADMIN],
      },
    ],
  },
  {
    title: "Billing & Plans",
    href: "/dashboard/admin/billing",
    icon: "CreditCard",
    feature: "platform",
    roles: [ROLES.ADMIN],
    section: "Platform",
  },
  {
    title: "Audit Logs",
    href: "/dashboard/admin/audit",
    icon: "Flag",
    feature: "reports",
    roles: [ROLES.ADMIN, ROLES.FIRM_ADMIN],
    section: "Platform",
  },
  {
    title: "Feature Flags",
    href: "/dashboard/admin/features",
    icon: "Settings",
    feature: "platform",
    roles: [ROLES.ADMIN],
    section: "Platform",
  },

  // ═══════════════════════════════════════════════
  // SETTINGS — Admin settings vs Firm Admin settings (no overlap)
  // ═══════════════════════════════════════════════
  {
    title: "System Settings",
    href: "/dashboard/admin/settings",
    icon: "Settings",
    feature: "settings",
    roles: [ROLES.ADMIN],
    section: "Settings",
  },
  {
    title: "Integrations",
    href: "/dashboard/admin/integrations",
    icon: "Activity",
    feature: "settings",
    roles: [ROLES.ADMIN],
    section: "Settings",
  },
  {
    title: "Security Settings",
    href: "/dashboard/admin/security",
    icon: "Shield",
    feature: "settings",
    roles: [ROLES.ADMIN],
    section: "Settings",
  },
  {
    title: "Firm Settings",
    href: "/dashboard/legal/settings",
    icon: "Settings",
    feature: "settings",
    roles: [ROLES.FIRM_ADMIN],
    section: "Settings",
  },
  {
    title: "Billing & Plans",
    href: "/dashboard/legal/billing",
    icon: "CreditCard",
    feature: "billing",
    roles: [ROLES.FIRM_ADMIN],
    section: "Settings",
  },
  {
    title: "Integrations",
    href: "/dashboard/legal/integrations",
    icon: "Activity",
    feature: "settings",
    roles: [ROLES.FIRM_ADMIN],
    section: "Settings",
  },
  {
    title: "Security",
    href: "/dashboard/legal/security",
    icon: "Shield",
    feature: "settings",
    roles: [ROLES.FIRM_ADMIN],
    section: "Settings",
  },
];

/**
 * Route-level permission mapping
 * Maps route patterns to required roles and minimum permission levels
 */
export const ROUTE_PERMISSIONS: Record<string, { roles: RoleType[]; level: PermissionLevel }> = {
  // Admin routes
  '/dashboard/admin': { roles: [ROLES.ADMIN, ROLES.FIRM_ADMIN], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/admin/organizations': { roles: [ROLES.ADMIN], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/admin/users': { roles: [ROLES.ADMIN], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/admin/roles': { roles: [ROLES.ADMIN], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/admin/blog': { roles: [ROLES.ADMIN], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/admin/billing': { roles: [ROLES.ADMIN], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/admin/integrations': { roles: [ROLES.ADMIN], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/admin/security': { roles: [ROLES.ADMIN], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/admin/audit': { roles: [ROLES.ADMIN], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/admin/features': { roles: [ROLES.ADMIN], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/admin/settings': { roles: [ROLES.ADMIN], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/admin/application': { roles: [ROLES.ADMIN], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/admin/signatures/send': { roles: [ROLES.ADMIN, ROLES.FIRM_ADMIN, ROLES.ATTORNEY, ROLES.MEDICAL_BILLER], level: PERMISSION_LEVELS.FULL_ACCESS },

  // Legal routes
  '/dashboard/legal': { roles: [ROLES.FIRM_ADMIN, ROLES.ATTORNEY], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/legal/cases': { roles: [ROLES.FIRM_ADMIN, ROLES.ATTORNEY], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/legal/cases/new': { roles: [ROLES.FIRM_ADMIN, ROLES.ATTORNEY], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/legal/demand-letters': { roles: [ROLES.FIRM_ADMIN, ROLES.ATTORNEY], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/legal/documents': { roles: [ROLES.FIRM_ADMIN, ROLES.ATTORNEY, ROLES.PROVIDER_STAFF], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/legal/liens': { roles: [ROLES.FIRM_ADMIN, ROLES.ATTORNEY, ROLES.MEDICAL_BILLER], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/legal/settlements': { roles: [ROLES.FIRM_ADMIN, ROLES.ATTORNEY], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/legal/users': { roles: [ROLES.FIRM_ADMIN], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/legal/billing': { roles: [ROLES.FIRM_ADMIN], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/legal/integrations': { roles: [ROLES.FIRM_ADMIN], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/legal/security': { roles: [ROLES.FIRM_ADMIN], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/legal/settings': { roles: [ROLES.FIRM_ADMIN], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/legal/notifications': { roles: [ROLES.FIRM_ADMIN, ROLES.ATTORNEY], level: PERMISSION_LEVELS.READ_ONLY },

  // Billing routes
  '/dashboard/legal/reports': { roles: [ROLES.FIRM_ADMIN, ROLES.ATTORNEY], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/billing': { roles: [ROLES.MEDICAL_BILLER, ROLES.FIRM_ADMIN], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/billing/invoices': { roles: [ROLES.FIRM_ADMIN, ROLES.MEDICAL_BILLER, ROLES.PROVIDER_STAFF], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/billing/eobs': { roles: [ROLES.FIRM_ADMIN, ROLES.MEDICAL_BILLER], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/billing/payments': { roles: [ROLES.FIRM_ADMIN, ROLES.MEDICAL_BILLER], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/billing/queue': { roles: [ROLES.FIRM_ADMIN, ROLES.MEDICAL_BILLER], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/billing/ocr-queue': { roles: [ROLES.FIRM_ADMIN, ROLES.MEDICAL_BILLER, ROLES.PROVIDER_STAFF], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/billing/reports': { roles: [ROLES.MEDICAL_BILLER], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/billing/upload': { roles: [ROLES.FIRM_ADMIN, ROLES.MEDICAL_BILLER], level: PERMISSION_LEVELS.FULL_ACCESS },

  // Provider routes
  '/dashboard/provider': { roles: [ROLES.PROVIDER_STAFF], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/provider/reports': { roles: [ROLES.PROVIDER_STAFF], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/provider/cpt-library': { roles: [ROLES.PROVIDER_STAFF, ROLES.FIRM_ADMIN, ROLES.MEDICAL_BILLER], level: PERMISSION_LEVELS.FULL_ACCESS },

  // Client routes
  '/dashboard/client': { roles: [ROLES.CLIENT], level: PERMISSION_LEVELS.READ_ONLY },
  '/dashboard/client/cases': { roles: [ROLES.CLIENT], level: PERMISSION_LEVELS.READ_ONLY },
  '/dashboard/client/documents': { roles: [ROLES.CLIENT], level: PERMISSION_LEVELS.READ_ONLY },
  '/dashboard/client/documents/upload': { roles: [ROLES.CLIENT], level: PERMISSION_LEVELS.FULL_ACCESS },
  '/dashboard/client/invoices': { roles: [ROLES.CLIENT], level: PERMISSION_LEVELS.READ_ONLY },
  '/dashboard/client/payments': { roles: [ROLES.CLIENT], level: PERMISSION_LEVELS.READ_ONLY },
  '/dashboard/client/signatures': { roles: [ROLES.CLIENT], level: PERMISSION_LEVELS.FULL_ACCESS },

  // Insurance routes
  '/dashboard/insurance': { roles: [ROLES.FIRM_ADMIN, ROLES.ATTORNEY, ROLES.MEDICAL_BILLER], level: PERMISSION_LEVELS.FULL_ACCESS },


  // Shared routes
  '/dashboard/notifications': { roles: ALL_ROLES, level: PERMISSION_LEVELS.READ_ONLY },
  '/dashboard/profile': { roles: ALL_ROLES, level: PERMISSION_LEVELS.FULL_ACCESS },
};