"use client";

import { ProtectedRoute } from "@/components/protected-route"
import { ROLES } from "@/lib/roleConstants"
import { useAuth } from "@/hooks/useAuth"
import { cn } from "@/lib/utils"

export default function AdminLayout({
  children,
}: {
  children: React.ReactNode
}) {
  const { user } = useAuth()
  const isFirmAdmin = user?.role === ROLES.FIRM_ADMIN

  return (
    <ProtectedRoute requiredRole={[ROLES.ADMIN, ROLES.FIRM_ADMIN, ROLES.ATTORNEY, ROLES.MEDICAL_BILLER]}>
      <div
        className={cn(
          "min-h-screen overflow-x-hidden relative",
          isFirmAdmin
            ? "bg-gradient-to-br from-emerald-50/30 via-white to-green-50/20"
            : "bg-gradient-to-br from-background via-muted/30 to-accent/10"
        )}
      >
        {!isFirmAdmin && (
          <div className="fixed inset-0 bg-[url('/medical-pharmacy-background-pattern.png')] opacity-5 pointer-events-none" />
        )}

        {isFirmAdmin && (
          <div className="fixed inset-0 bg-[url('/medical-pharmacy-background-pattern.png')] opacity-[0.02] pointer-events-none" />
        )}

        <div className="relative z-10">{children}</div>
      </div>
    </ProtectedRoute>
  )
}