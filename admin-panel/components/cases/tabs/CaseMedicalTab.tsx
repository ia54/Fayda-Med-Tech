"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Badge } from "@/components/ui/badge"
import { 
  Stethoscope, 
  Plus, 
  FileCheck, 
  Clock, 
  Building2,
  CheckCircle2,
  Mail,
  AlertCircle
} from "lucide-react"
import { 
  useGetMedicalRecordRequestsQuery, 
  useCreateMedicalRecordRequestMutation,
  useGetHipaaAuthorizationsQuery
} from "@/store/api/apiSlice"
import { useToast } from "@/hooks/use-toast"
import { LoadingSpinner } from "@/components/loading-spinner"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { format } from "date-fns"

export function CaseMedicalTab({ caseId }: { caseId: number }) {
  const { data: requestsResponse, isLoading: reqLoading, refetch: refetchReq } = useGetMedicalRecordRequestsQuery({ case_id: caseId })
  const { data: hipaaResponse, isLoading: hipaaLoading } = useGetHipaaAuthorizationsQuery({ case_id: caseId })
  const [createRequest, { isLoading: isCreating }] = useCreateMedicalRecordRequestMutation()
  const { toast } = useToast()

  const requests = requestsResponse?.data?.data || []
  const hipaas = hipaaResponse?.data?.data || []

  if (reqLoading || hipaaLoading) return <LoadingSpinner />

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-lg font-bold text-emerald-900 dark:text-white">Medical Evidence Tracking</h3>
          <p className="text-sm text-slate-500">Track HIPAA forms and medical record request status</p>
        </div>
      </div>

      <Tabs defaultValue="requests" className="w-full">
        <TabsList className="bg-slate-100 p-1">
          <TabsTrigger value="requests" className="data-[state=active]:bg-white">Record Requests</TabsTrigger>
          <TabsTrigger value="hipaa" className="data-[state=active]:bg-white">HIPAA Authorizations</TabsTrigger>
        </TabsList>

        <TabsContent value="requests" className="mt-6 space-y-4">
          <div className="flex justify-between items-center mb-4">
             <h4 className="text-sm font-bold uppercase text-slate-500 tracking-wider">Pending & Received Records</h4>
             <Button size="sm" className="bg-emerald-600">
               <Plus className="w-3.5 h-3.5 mr-2" /> New Request
             </Button>
          </div>

          <div className="grid grid-cols-1 gap-4">
            {requests.length > 0 ? requests.map((req: any) => (
              <Card key={req.id} className="border-emerald-100 hover:shadow-md transition-shadow">
                <CardContent className="p-4 flex items-center justify-between">
                  <div className="flex items-center gap-4">
                    <div className={`p-2 rounded-lg ${req.status === 'received' ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-600'}`}>
                      {req.status === 'received' ? <CheckCircle2 className="w-5 h-5" /> : <Clock className="w-5 h-5" />}
                    </div>
                    <div>
                      <h5 className="font-bold text-slate-900">{req.provider_name}</h5>
                      <p className="text-xs text-slate-500">Requested: {format(new Date(req.request_date), "MMM dd, yyyy")}</p>
                    </div>
                  </div>
                  <div className="flex items-center gap-6">
                    <div className="text-right hidden md:block">
                      <p className="text-[10px] uppercase font-bold text-slate-400">Status</p>
                      <Badge variant={req.status === 'received' ? 'default' : 'secondary'} className="capitalize mt-1">
                        {req.status}
                      </Badge>
                    </div>
                    <Button variant="outline" size="sm" className="border-emerald-200 text-emerald-700">
                      View Documents
                    </Button>
                  </div>
                </CardContent>
              </Card>
            )) : (
              <div className="py-12 border-2 border-dashed border-slate-100 rounded-xl text-center">
                <Stethoscope className="h-10 w-10 text-slate-200 mx-auto mb-3" />
                <p className="text-sm text-slate-500">No medical record requests found</p>
              </div>
            )}
          </div>
        </TabsContent>

        <TabsContent value="hipaa" className="mt-6">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {hipaas.length > 0 ? hipaas.map((h: any) => (
              <Card key={h.id} className="border-emerald-100">
                <CardHeader className="pb-3">
                  <div className="flex items-center justify-between">
                    <div className="p-2 bg-blue-100 rounded-lg text-blue-600">
                      <FileCheck className="w-4 h-4" />
                    </div>
                    <Badge className={h.status === 'signed' ? 'bg-emerald-600' : 'bg-amber-500'}>
                      {h.status}
                    </Badge>
                  </div>
                  <CardTitle className="text-base mt-4">{h.provider_name || 'General HIPAA'}</CardTitle>
                </CardHeader>
                <CardContent>
                   <p className="text-xs text-slate-500">Expires: {h.expiry_date ? format(new Date(h.expiry_date), "MMM dd, yyyy") : 'No expiry'}</p>
                   <Button variant="ghost" size="sm" className="w-full mt-4 text-emerald-600 justify-between p-0">
                     View Signed PDF <Mail className="w-3.5 h-3.5" />
                   </Button>
                </CardContent>
              </Card>
            )) : (
              <div className="col-span-full py-12 border-2 border-dashed border-slate-100 rounded-xl text-center">
                <AlertCircle className="h-10 w-10 text-slate-200 mx-auto mb-3" />
                <p className="text-sm text-slate-500">No signed HIPAA authorizations on file</p>
                <Button variant="outline" className="mt-4 border-emerald-200 text-emerald-700">Send for Signature</Button>
              </div>
            )}
          </div>
        </TabsContent>
      </Tabs>
    </div>
  )
}
