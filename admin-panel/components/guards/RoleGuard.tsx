"use client";

import React from "react";
import { useRouter } from "next/navigation";
import { useSelector } from "react-redux";
import { RootState } from "@/store/store";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ShieldAlert, ArrowLeft } from "lucide-react";
import {
  RoleType,
  FeatureKey,
  PermissionLevel,
  ROLE_DISPLAY_NAMES,
} from "@/lib/roleConstants";
import { canAccess } from "@/lib/permissions";

interface RoleGuardProps {
  children: React.ReactNode;
  /** Array of roles that are allowed to access this content */
  allowedRoles?: RoleType[];
  /** Optional feature-level permission requirement */
  requiredPermission?: {
    feature: FeatureKey;
    level?: PermissionLevel;
  };
  /** Custom fallback UI to show when access is denied */
  fallback?: React.ReactNode;
  /** Optional redirect URL when access is denied */
  redirectTo?: string;
}

/**
 * RoleGuard — Generic role-based access guard component
 * 
 * Supports two modes of access checking:
 * 1. Role-based: Pass `allowedRoles` to restrict to specific roles
 * 2. Feature-based: Pass `requiredPermission` to check feature-level access
 * Both can be combined for fine-grained control.
 *
 * Usage:
 * ```tsx
 * <RoleGuard allowedRoles={[ROLES.ADMIN, ROLES.FIRM_ADMIN]}>
 *   <AdminContent />
 * </RoleGuard>
 * 
 * <RoleGuard requiredPermission={{ feature: 'cases', level: 'full_access' }}>
 *   <CaseEditForm />
 * </RoleGuard>
 * ```
 */
export function RoleGuard({
  children,
  allowedRoles,
  requiredPermission,
  fallback,
  redirectTo,
}: RoleGuardProps) {
  const router = useRouter();
  const user = useSelector((state: RootState) => state.auth.user);

  const hasAccess = React.useMemo(() => {
    if (!user) return false;

    const userRole = user.role as RoleType;

    // Check role-based access
    if (allowedRoles && allowedRoles.length > 0) {
      if (!allowedRoles.includes(userRole)) return false;
    }

    // Check feature-level permission
    if (requiredPermission) {
      return canAccess(
        userRole,
        requiredPermission.feature,
        requiredPermission.level
      );
    }

    // If only allowedRoles was specified and passed, allow
    if (allowedRoles) return true;

    // If neither was specified, allow (no restrictions)
    return true;
  }, [user, allowedRoles, requiredPermission]);

  React.useEffect(() => {
    if (!hasAccess && redirectTo) {
      router.replace(redirectTo);
    }
  }, [hasAccess, redirectTo, router]);

  if (!hasAccess) {
    if (fallback) {
      return <>{fallback}</>;
    }

    const roleName = user
      ? ROLE_DISPLAY_NAMES[user.role as RoleType] || user.role
      : "Unknown";

    // Default access denied UI
    return (
      <div className="container mx-auto p-6 max-w-2xl">
        <Card className="border-destructive/50">
          <CardHeader className="border-b">
            <CardTitle className="flex items-center gap-2 text-destructive">
              <ShieldAlert className="w-6 h-6" />
              Access Denied
            </CardTitle>
          </CardHeader>
          <CardContent className="pt-6">
            <div className="text-center py-8">
              <ShieldAlert className="w-16 h-16 text-destructive mx-auto mb-4 opacity-50" />
              <h3 className="text-xl font-semibold text-foreground mb-2">
                Insufficient Permissions
              </h3>
              <p className="text-muted-foreground mb-2">
                Your role ({roleName}) does not have access to this page.
              </p>
              <p className="text-muted-foreground mb-6 text-sm">
                Please contact your administrator if you need access.
              </p>
              <Button
                onClick={() => router.back()}
                variant="outline"
                className="gap-2"
              >
                <ArrowLeft className="w-4 h-4" />
                Go Back
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  return <>{children}</>;
}
