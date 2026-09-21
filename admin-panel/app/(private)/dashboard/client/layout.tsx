import { ProtectedRoute } from "@/components/protected-route"
import { ROLES } from "@/lib/roleConstants"

export default function ClientLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <ProtectedRoute requiredRole={ROLES.CLIENT}>
      {children}
    </ProtectedRoute>
  )
}
