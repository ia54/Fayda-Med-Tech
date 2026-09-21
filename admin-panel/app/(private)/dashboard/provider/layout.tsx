import { ProtectedRoute } from "@/components/protected-route"
import { ROLES } from "@/lib/roleConstants"

export default function ProviderLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <ProtectedRoute requiredRole={[ROLES.PROVIDER_STAFF, ROLES.FIRM_ADMIN, ROLES.MEDICAL_BILLER]}>
      {children}
    </ProtectedRoute>
  )
}