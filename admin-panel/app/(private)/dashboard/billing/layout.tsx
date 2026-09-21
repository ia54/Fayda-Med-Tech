import { ProtectedRoute } from "@/components/protected-route"
import { ROLES } from "@/lib/roleConstants"

export default function BillingLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <ProtectedRoute requiredRole={[ROLES.MEDICAL_BILLER, ROLES.FIRM_ADMIN, ROLES.PROVIDER_STAFF]}>
      {children}
    </ProtectedRoute>
  )
}