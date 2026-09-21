"use client"

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { useGetHipaaAuthorizationsQuery } from "@/store/api/apiSlice"
import { Badge } from "@/components/ui/badge"

export default function HipaaAuthorizationsPage() {
  const { data, isLoading } = useGetHipaaAuthorizationsQuery({ per_page: 20 })

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">HIPAA Authorizations</h1>
          <p className="text-muted-foreground">Manage HIPAA release forms, medical records authorizations, and patient consent (PDF Section 5)</p>
        </div>
        <Button>New Authorization</Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Authorizations</CardTitle>
          <CardDescription>HIPAA release forms, treatment consents, medical record authorizations</CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <p className="text-muted-foreground">Loading...</p>
          ) : data?.data?.data?.length > 0 ? (
            <div className="space-y-2">
              {data.data.data.map((auth: any) => (
                <div key={auth.id} className="flex items-center justify-between p-3 bg-muted/30 rounded-lg">
                  <div className="space-y-1">
                    <div className="font-medium">{auth.authorization_type?.replace(/_/g, ' ').toUpperCase()}</div>
                    <div className="text-sm text-muted-foreground">
                      Recipient: {auth.recipient_name || 'N/A'} • {auth.issue_date ? `Issued: ${auth.issue_date}` : ''} {auth.expiry_date ? `• Expires: ${auth.expiry_date}` : ''}
                    </div>
                  </div>
                  <Badge variant={auth.status === 'signed' ? 'default' : auth.status === 'expired' ? 'destructive' : 'secondary'}>
                    {auth.status}
                  </Badge>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-muted-foreground">No authorizations found. Create a new authorization to get started.</p>
          )}
        </CardContent>
      </Card>
    </div>
  )
}