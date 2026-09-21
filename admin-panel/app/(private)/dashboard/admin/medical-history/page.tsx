"use client"

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { useGetMedicalHistoriesQuery } from "@/store/api/apiSlice"

export default function MedicalHistoryPage() {
  const { data, isLoading } = useGetMedicalHistoriesQuery({ per_page: 20 })

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Medical History</h1>
          <p className="text-muted-foreground">Track patient medical history, diagnoses, and treatments (PDF Section 5)</p>
        </div>
        <Button>Add Record</Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Medical Records</CardTitle>
          <CardDescription>Pre-existing conditions, treatments, surgeries, medications</CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <p className="text-muted-foreground">Loading...</p>
          ) : data?.data?.data?.length > 0 ? (
            <div className="space-y-2">
              {data.data.data.map((record: any) => (
                <div key={record.id} className="flex items-center justify-between p-3 bg-muted/30 rounded-lg">
                  <div>
                    <div className="font-medium">{record.diagnosis || 'No diagnosis'}</div>
                    <div className="text-sm text-muted-foreground">
                      {record.record_type} • {record.provider_name || 'Unknown provider'} • {record.record_date}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-muted-foreground">No medical history records found.</p>
          )}
        </CardContent>
      </Card>
    </div>
  )
}