"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/hooks/useAuth";
import { LoadingSpinner } from "@/components/loading-spinner";
import { redirectToDashboard } from "@/lib/roleUtils";
import { ROLES } from "@/lib/roleConstants";

interface ProtectedRouteProps {
  children: React.ReactNode;
  requiredRole?: string | string[];
}

export function ProtectedRoute({
  children,
  requiredRole,
}: ProtectedRouteProps) {
  const router = useRouter();
  const { isAuthenticated, isTokenExpired, user } = useAuth();

  const hasRequiredRole = () => {
    if (!requiredRole) return true;
    if (!user) return false;

    // Super Admin can access everything
    if (user.role === ROLES.ADMIN) return true;

    if (Array.isArray(requiredRole)) {
      return requiredRole.includes(user.role);
    }

    return user.role === requiredRole;
  };

  useEffect(() => {
    // Redirect to login if not authenticated or token expired
    if (!isAuthenticated || isTokenExpired) {
      router.push("/auth/login");
    }

    // Redirect if user doesn't have required role
    if (user && !hasRequiredRole()) {
      redirectToDashboard(user, router);
    }
  }, [isAuthenticated, isTokenExpired, user, requiredRole, router]);

  // Show loading spinner while checking auth status
  if (!isAuthenticated || isTokenExpired) {
    return (
      <div className="flex items-center justify-center h-screen">
        <LoadingSpinner message="Checking authentication..." />
      </div>
    );
  }

  // Check role requirement
  if (user && !hasRequiredRole()) {
    return (
      <div className="flex items-center justify-center h-screen">
        <LoadingSpinner message="Redirecting..." />
      </div>
    );
  }

  return <>{children}</>;
}
