import { ProtectedRoute } from "@/components/protected-route"
import { ROLES } from "@/lib/roleConstants"

export default function LegalLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <ProtectedRoute requiredRole={[ROLES.FIRM_ADMIN, ROLES.ATTORNEY, ROLES.MEDICAL_BILLER, ROLES.PROVIDER_STAFF]}>
      {children}
    </ProtectedRoute>
  )
}