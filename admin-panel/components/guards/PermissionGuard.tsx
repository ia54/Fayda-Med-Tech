"use client";

import React from "react";
import { useSelector } from "react-redux";
import { RootState } from "@/store/store";
import { RoleType, FeatureKey, PermissionLevel, PERMISSION_LEVELS } from "@/lib/roleConstants";
import { canAccess } from "@/lib/permissions";

interface PermissionGuardProps {
  children: React.ReactNode;
  /** The feature to check permission for */
  feature: FeatureKey;
  /** Minimum required permission level (defaults to read_only) */
  level?: PermissionLevel;
  /** Optional fallback to render when permission is denied (defaults to nothing) */
  fallback?: React.ReactNode;
}

/**
 * PermissionGuard — Lightweight inline permission guard
 *
 * Hides UI elements the user doesn't have permission for.
 * No redirect — just renders nothing (or a fallback) if denied.
 *
 * Usage:
 * ```tsx
 * <PermissionGuard feature="billing" level="full_access">
 *   <Button>Create Invoice</Button>
 * </PermissionGuard>
 *
 * <PermissionGuard feature="cases" level="read_only">
 *   <CaseListView />
 * </PermissionGuard>
 * ```
 */
export function PermissionGuard({
  children,
  feature,
  level = PERMISSION_LEVELS.READ_ONLY,
  fallback = null,
}: PermissionGuardProps) {
  const user = useSelector((state: RootState) => state.auth.user);

  if (!user) return <>{fallback}</>;

  const userRole = user.role as RoleType;
  const hasPermission = canAccess(userRole, feature, level);

  if (!hasPermission) {
    return <>{fallback}</>;
  }

  return <>{children}</>;
}
