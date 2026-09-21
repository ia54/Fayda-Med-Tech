"use client";

import React from "react";
import { useRouter } from "next/navigation";
import { useAppSelector } from "@/store/hooks";
import { RoleType } from "@/lib/roleConstants";
import { redirectToDashboard } from "@/lib/roleUtils";

type AuthGuardProps = {
  children: React.ReactNode;
  /** Optional array of roles allowed to access this section */
  roles?: RoleType[];
  redirectTo?: string;
};

/**
 * AuthGuard — Primary layout-level authentication and role check
 * 
 * Checks for valid auth token. If `roles` is provided, also verifies
 * the user has one of the allowed roles, redirecting unauthorized users
 * to their appropriate dashboard.
 */
export function AuthGuard({ children, roles, redirectTo = "/" }: AuthGuardProps) {
  const router = useRouter();
  const token = useAppSelector((s) => s.auth.token);
  const user = useAppSelector((s) => s.auth.user);

  React.useEffect(() => {
    if (!token) {
      router.replace(redirectTo);
      return;
    }

    // If roles are specified, check if the user has one of them
    if (roles && roles.length > 0 && user) {
      const userRole = user.role as RoleType;
      if (!roles.includes(userRole)) {
        // Redirect to the user's correct dashboard
        redirectToDashboard(user, router);
      }
    }
  }, [token, roles, user, redirectTo, router]);

  if (!token) return null;

  // If roles are specified and user doesn't have one, show nothing while redirecting
  if (roles && roles.length > 0 && user) {
    const userRole = user.role as RoleType;
    if (!roles.includes(userRole)) return null;
  }

  return <>{children}</>;
}
