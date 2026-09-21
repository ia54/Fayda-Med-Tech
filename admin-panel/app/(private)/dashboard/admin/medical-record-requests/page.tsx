"use client"

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { useGetMedicalRecordRequestsQuery } from "@/store/api/apiSlice"
import { Badge } from "@/components/ui/badge"

export default function MedicalRecordRequestsPage() {
  const { data, isLoading } = useGetMedicalRecordRequestsQuery({ per_page: 20 })

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Medical Record Requests</h1>
          <p className="text-muted-foreground">Track requests for medical records from providers, hospitals, and clinics (PDF Section 8)</p>
        </div>
        <Button>New Request</Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Record Requests</CardTitle>
          <CardDescription>Manage outgoing medical record requests and track their status</CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <p className="text-muted-foreground">Loading...</p>
          ) : data?.data?.data?.length > 0 ? (
            <div className="space-y-2">
              {data.data.data.map((req: any) => (
                <div key={req.id} className="flex items-center justify-between p-3 bg-muted/30 rounded-lg">
                  <div className="space-y-1">
                    <div className="font-medium">{req.provider_name}</div>
                    <div className="text-sm text-muted-foreground">
                      Requested: {req.request_date} • Follow-up: {req.followup_date || 'N/A'} • Records: {req.records_requested?.substring(0, 60) || 'General'}
                    </div>
                  </div>
                  <Badge>{req.status}</Badge>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-muted-foreground">No medical record requests found. Create a new request to get started.</p>
          )}
        </CardContent>
      </Card>
    </div>
  )
}