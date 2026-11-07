"use client"

import { ProtectedRoute } from "@/components/protected-route"
import { ROLES } from "@/lib/roleConstants"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { useGetFirmOrganizationQuery } from "@/store/api/firmApiSlice"

export default function FirmBillingManagementPage() {
  return <ProtectedRoute requiredRole={ROLES.FIRM_ADMIN}><RecordedPlan /></ProtectedRoute>
}

function RecordedPlan() {
  const { currentData, isFetching, isError, refetch } = useGetFirmOrganizationQuery()
  const org = currentData?.data
  return <div className="space-y-6">
    <h1 className="text-3xl font-bold">Billing &amp; Plans</h1>
    {isError ? <p role="alert">Organization details are unavailable. <Button onClick={() => refetch()}>Retry</Button></p>
      : isFetching ? <p role="status">Loading organization…</p>
      : !org ? <p role="alert">Organization details are unavailable.</p> : <>
        <Card><CardHeader><CardTitle>Recorded organization plan</CardTitle></CardHeader><CardContent className="space-y-2">
          <p>{org.org_name}</p><p>Plan: {org.subscription_plan || "Not recorded"}</p>
          <p>Allocated team members: {org.no_of_employees ?? "Not recorded"}</p>
        </CardContent></Card>
        <Card><CardHeader><CardTitle>Subscription billing information</CardTitle></CardHeader><CardContent>
          <p>Verified subscription prices, billing dates and transaction history are unavailable here. Contact your organization administrator for billing records.</p>
        </CardContent></Card>
      </>}
  </div>
}
