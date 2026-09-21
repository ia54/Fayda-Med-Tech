"use client"

import { useParams, useRouter } from "next/navigation"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { 
  FileText, 
  Scale, 
  Clock, 
  DollarSign, 
  User, 
  Calendar, 
  MapPin, 
  ArrowLeft,
  Edit,
  History,
  FileCheck,
  ShieldAlert,
  Plus
} from "lucide-react"
import { useGetCaseByIdQuery } from "@/store/api/casesApiSlice"
import { useGetDocumentsQuery } from "@/store/api/billingApiSlice"
import { Skeleton } from "@/components/ui/skeleton"
import { CaseInsuranceTab } from "@/components/cases/tabs/CaseInsuranceTab"
import { CaseLienTab } from "@/components/cases/tabs/CaseLienTab"
import { CaseSettlementTab } from "@/components/cases/tabs/CaseSettlementTab"
import { CaseMedicalTab } from "@/components/cases/tabs/CaseMedicalTab"
import { useSelector } from "react-redux"
import { RootState } from "@/store/store"
import { format } from "date-fns"
import { cn } from "@/lib/utils"
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { CaseForm } from "@/components/cases/CaseForm"
import { CasePartyForm } from "@/components/cases/CasePartyForm"
import { useState } from "react"

export default function CaseDetailsPage() {
  const params = useParams()
  const router = useRouter()
  const caseId = parseInt(params.id as string)
  const [isEditOpen, setIsEditOpen] = useState(false)
  const [isAddPartyOpen, setIsAddPartyOpen] = useState(false)
  
  const { data: caseData, isLoading, error, refetch } = useGetCaseByIdQuery(caseId)
  const { data: docsResponse, isLoading: isDocsLoading } = useGetDocumentsQuery({ case_id: caseId })
  const user = useSelector((state: RootState) => state.auth.user)
  const isClient = user?.role === 'client'
  
  const documents = docsResponse?.data?.documents || []

  if (isLoading) {
    return (
      <div className="p-6 space-y-6">
        <Skeleton className="h-10 w-1/4" />
        <div className="grid grid-cols-3 gap-6">
          <Skeleton className="h-40 col-span-2" />
          <Skeleton className="h-40" />
        </div>
      </div>
    )
  }

  if (error || !caseData) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[400px] space-y-4">
        <ShieldAlert className="h-12 w-12 text-destructive" />
        <h2 className="text-xl font-bold">Case Not Found</h2>
        <Button onClick={() => router.back()}>Go Back</Button>
      </div>
    )
  }

  const c = caseData

  if (!c) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[400px] space-y-4">
        <ShieldAlert className="h-12 w-12 text-destructive" />
        <h2 className="text-xl font-bold">Case Data Corrupted</h2>
        <Button onClick={() => router.back()}>Go Back</Button>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50/30 via-white to-green-50/20 dark:from-emerald-950/20 dark:via-slate-950 dark:to-green-950/20 p-6">
      <div className="max-w-7xl mx-auto space-y-6">
        {/* Top Navigation */}
        <div className="flex items-center justify-between">
          <Button variant="ghost" size="sm" onClick={() => router.back()} className="text-emerald-700 dark:text-emerald-300">
            <ArrowLeft className="w-4 h-4 mr-2" />
            Back to Dashboard
          </Button>
          <div className="flex gap-2">
            {!isClient && (
              <>
                <Button variant="outline" size="sm" onClick={() => setIsEditOpen(true)} className="border-emerald-200 text-emerald-700">
                  <Edit className="w-4 h-4 mr-2" />
                  Edit Case
                </Button>
                <Button size="sm" onClick={() => router.push('/dashboard/legal/demand-letters')} className="bg-emerald-600 hover:bg-emerald-700">
                  <FileCheck className="w-4 h-4 mr-2" />
                  Generate Demand
                </Button>
              </>
            )}
          </div>
        </div>

        {/* Edit Dialog */}
        <Dialog open={isEditOpen} onOpenChange={setIsEditOpen}>
          <DialogContent className="sm:max-w-[600px]">
            <DialogHeader>
              <DialogTitle>Edit Case Details</DialogTitle>
            </DialogHeader>
            <CaseForm
              initialData={c}
              onSuccess={() => {
                setIsEditOpen(false)
                refetch()
              }}
              onCancel={() => setIsEditOpen(false)}
            />
          </DialogContent>
        </Dialog>

        {/* Add Party Dialog */}
        <Dialog open={isAddPartyOpen} onOpenChange={setIsAddPartyOpen}>
          <DialogContent className="sm:max-w-[500px]">
            <DialogHeader>
              <DialogTitle>Add Party to Case</DialogTitle>
            </DialogHeader>
            <CasePartyForm
              caseId={caseId}
              onSuccess={() => {
                setIsAddPartyOpen(false)
                refetch()
              }}
              onCancel={() => setIsAddPartyOpen(false)}
            />
          </DialogContent>
        </Dialog>

        {/* Hero Section */}
        <div className="flex flex-col lg:flex-row gap-6">
          <Card className="flex-1 bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 shadow-sm">
            <CardHeader className="pb-4">
              <div className="flex items-start justify-between">
                <div className="space-y-1">
                  <div className="flex items-center gap-3">
                    <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200">
                      {c.case_number}
                    </Badge>
                    <Badge className={cn(
                      "capitalize",
                      c.status === 'active' ? "bg-emerald-600" : "bg-amber-500"
                    )}>
                      {c.status}
                    </Badge>
                  </div>
                  <CardTitle className="text-3xl font-bold text-emerald-900 dark:text-white mt-2">
                    {c.title}
                  </CardTitle>
                  <CardDescription className="flex items-center gap-2 text-emerald-700 dark:text-emerald-300">
                    <MapPin className="h-4 w-4" />
                    {c.jurisdiction || "No jurisdiction specified"}
                  </CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <p className="text-emerald-800 dark:text-slate-300 leading-relaxed">
                {c.description || "No description provided for this case."}
              </p>
            </CardContent>
          </Card>

          <Card className="lg:w-80 bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 shadow-sm">
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-semibold uppercase tracking-wider text-emerald-700">Key Dates</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-center gap-3">
                <div className="p-2 bg-emerald-100 rounded-lg">
                  <Calendar className="h-4 w-4 text-emerald-700" />
                </div>
                <div>
                  <p className="text-[10px] text-muted-foreground uppercase font-bold">Accident Date</p>
                  <p className="text-sm font-medium">{c.accident_date ? format(new Date(c.accident_date), "MMM dd, yyyy") : "N/A"}</p>
                </div>
              </div>
              <div className="flex items-center gap-3">
                <div className="p-2 bg-amber-100 rounded-lg">
                  <Clock className="h-4 w-4 text-amber-700" />
                </div>
                <div>
                  <p className="text-[10px] text-muted-foreground uppercase font-bold">Statute of Limitations</p>
                  <p className="text-sm font-medium text-amber-700">{c.sol_date ? format(new Date(c.sol_date), "MMM dd, yyyy") : "N/A"}</p>
                </div>
              </div>
              <div className="flex items-center gap-3 pt-2 border-t border-emerald-50">
                <div className="p-2 bg-blue-100 rounded-lg">
                  <DollarSign className="h-4 w-4 text-blue-700" />
                </div>
                <div>
                  <p className="text-[10px] text-muted-foreground uppercase font-bold">Total Case Value</p>
                  <p className="text-sm font-bold text-emerald-900">${(c.total_case_value || 0).toLocaleString()}</p>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Tabs Section */}
        <Tabs defaultValue="parties" className="w-full">
          <TabsList className="bg-emerald-50/50 p-1 border border-emerald-100">
            <TabsTrigger value="parties" className="data-[state=active]:bg-white data-[state=active]:text-emerald-700">
              <User className="h-4 w-4 mr-2" />
              Parties
            </TabsTrigger>
            <TabsTrigger value="documents" className="data-[state=active]:bg-white data-[state=active]:text-emerald-700">
              <FileText className="h-4 w-4 mr-2" />
              Documents
            </TabsTrigger>
            <TabsTrigger value="insurance" className="data-[state=active]:bg-white data-[state=active]:text-emerald-700">
              <ShieldAlert className="h-4 w-4 mr-2" />
              Insurance
            </TabsTrigger>
            <TabsTrigger value="liens" className="data-[state=active]:bg-white data-[state=active]:text-emerald-700">
              <Scale className="h-4 w-4 mr-2" />
              Liens
            </TabsTrigger>
            <TabsTrigger value="settlement" className="data-[state=active]:bg-white data-[state=active]:text-emerald-700">
              <DollarSign className="h-4 w-4 mr-2" />
              Settlement
            </TabsTrigger>
            <TabsTrigger value="medical" className="data-[state=active]:bg-white data-[state=active]:text-emerald-700">
              <History className="h-4 w-4 mr-2" />
              Medical
            </TabsTrigger>
            <TabsTrigger value="timeline" className="data-[state=active]:bg-white data-[state=active]:text-emerald-700">
              <Clock className="h-4 w-4 mr-2" />
              Timeline
            </TabsTrigger>
          </TabsList>

          <TabsContent value="parties" className="mt-6">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {/* Existing Parties */}
              {c.parties?.map((party: any) => (
                <Card key={party.id} className="border-emerald-100 hover:shadow-md transition-shadow relative group">
                  <CardHeader className="flex flex-row items-center gap-4 pb-2">
                    <div className="h-10 w-10 rounded-full bg-emerald-600 flex items-center justify-center text-white font-bold">
                      {(party.name || "U").charAt(0)}
                    </div>
                    <div className="flex-1 min-w-0">
                      <CardTitle className="text-base truncate">{party.name || "Unnamed Party"}</CardTitle>
                      <Badge variant="secondary" className="text-[10px] capitalize">
                        {party.role_in_case.replace('_', ' ')}
                      </Badge>
                    </div>
                  </CardHeader>
                  <CardContent className="space-y-1">
                    <p className="text-xs text-muted-foreground truncate">{party.email}</p>
                    {party.phone && <p className="text-xs text-muted-foreground">{party.phone}</p>}
                  </CardContent>
                </Card>
              ))}

              {/* Add Party Button Card */}
              {!isClient && (
                <Card 
                  className="border-dashed border-2 border-emerald-100 bg-emerald-50/10 hover:bg-emerald-50/30 transition-all flex flex-col items-center justify-center p-6 text-center cursor-pointer group"
                  onClick={() => setIsAddPartyOpen(true)}
                >
                  <div className="h-10 w-10 rounded-full bg-emerald-100 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                    <Plus className="h-5 w-5 text-emerald-600" />
                  </div>
                  <p className="text-sm font-semibold text-emerald-700">Add New Party</p>
                  <p className="text-[10px] text-emerald-600/60 mt-1">Assign Client, Defendant, or Witness</p>
                </Card>
              )}
            </div>
          </TabsContent>

          <TabsContent value="documents" className="mt-6">
            <Card className="border-emerald-100 bg-white/70 backdrop-blur-sm shadow-sm overflow-hidden">
              <CardHeader className="flex flex-row items-center justify-between pb-4">
                <div>
                  <CardTitle className="text-lg">Case Documents</CardTitle>
                  <CardDescription>Legal filings, medical records, and correspondence</CardDescription>
                </div>
                <Button 
                  size="sm" 
                  className="bg-emerald-600 hover:bg-emerald-700" 
                  onClick={() => router.push('/dashboard/legal/documents/upload')}
                >
                  <Plus className="h-4 w-4 mr-2" />
                  Upload Document
                </Button>
              </CardHeader>
              <CardContent className="p-0">
                {isDocsLoading ? (
                  <div className="p-8 space-y-4">
                    {[1, 2, 3].map(i => <Skeleton key={i} className="h-12 w-full" />)}
                  </div>
                ) : documents.length > 0 ? (
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm text-left">
                      <thead className="bg-emerald-50/50 text-emerald-900 font-semibold uppercase text-[10px] tracking-wider">
                        <tr>
                          <th className="px-6 py-3">Document Name</th>
                          <th className="px-6 py-3">Category</th>
                          <th className="px-6 py-3">Status</th>
                          <th className="px-6 py-3">Date</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-emerald-50">
                        {documents.map((doc: any) => (
                          <tr key={doc.id} className="hover:bg-emerald-50/30 transition-colors">
                            <td className="px-6 py-4">
                              <div className="flex items-center gap-3">
                                <FileText className="h-4 w-4 text-emerald-600" />
                                <span className="font-medium text-emerald-950">{doc.original_name}</span>
                              </div>
                            </td>
                            <td className="px-6 py-4">
                              <Badge variant="secondary" className="bg-slate-100 text-slate-700 capitalize">
                                {(doc.metadata?.category || 'General').replace('_', ' ')}
                              </Badge>
                            </td>
                            <td className="px-6 py-4">
                              <div className="flex items-center gap-2">
                                <div className={cn(
                                  "h-1.5 w-1.5 rounded-full",
                                  doc.document_status === 'signed' ? "bg-emerald-500" : "bg-amber-500"
                                )} />
                                <span className="capitalize">{doc.document_status || 'available'}</span>
                              </div>
                            </td>
                            <td className="px-6 py-4 text-muted-foreground whitespace-nowrap">
                              {format(new Date(doc.created_at), "MMM dd, yyyy")}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                ) : (
                  <div className="p-12 text-center space-y-4">
                    <FileText className="h-12 w-12 text-emerald-100 mx-auto" />
                    <div className="space-y-1">
                      <h3 className="font-semibold text-emerald-900">No Documents Found</h3>
                      <p className="text-sm text-muted-foreground">Upload medical records or filings to connect them with this case.</p>
                    </div>
                  </div>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="insurance" className="mt-6">
             <CaseInsuranceTab caseId={caseId} />
          </TabsContent>

          <TabsContent value="liens" className="mt-6">
             <CaseLienTab caseId={caseId} />
          </TabsContent>

          <TabsContent value="settlement" className="mt-6">
             <CaseSettlementTab caseId={caseId} />
          </TabsContent>

          <TabsContent value="medical" className="mt-6">
             <CaseMedicalTab caseId={caseId} />
          </TabsContent>

          <TabsContent value="timeline" className="mt-6">
            <Card className="border-emerald-100 bg-white/70 backdrop-blur-sm shadow-sm overflow-hidden">
              <CardContent className="p-6">
                {c.timeline && c.timeline.length > 0 ? (
                  <div className="space-y-8 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-emerald-100 before:to-transparent">
                    {c.timeline.map((event: any, idx: number) => {
                      const isLast = idx === (c.timeline?.length || 0) - 1;
                      const Icon = event.event_type === 'milestone' ? Scale : 
                                  event.event_type === 'document' ? FileText :
                                  event.event_type === 'legal' ? ShieldAlert : Clock;
                      
                      return (
                        <div key={event.id} className="relative flex items-center gap-6 group">
                          <div className={cn(
                            "flex items-center justify-center w-10 h-10 rounded-full border shadow-sm z-10 shrink-0 transition-transform group-hover:scale-110",
                            event.event_type === 'milestone' ? "bg-emerald-600 text-white border-emerald-400" :
                            event.event_type === 'document' ? "bg-blue-600 text-white border-blue-400" :
                            "bg-white text-emerald-600 border-emerald-100"
                          )}>
                            <Icon className="w-5 h-5" />
                          </div>
                          <div className="flex-1 p-4 rounded-xl border border-emerald-50 bg-white shadow-sm hover:shadow-md transition-shadow">
                            <div className="flex items-center justify-between gap-4 mb-1">
                              <h4 className="font-bold text-slate-900">{event.title}</h4>
                              <time className="text-[10px] font-bold text-emerald-500 uppercase tracking-wider whitespace-nowrap">
                                {format(new Date(event.created_at || event.timestamp), "MMM dd, yyyy")}
                              </time>
                            </div>
                            <p className="text-slate-500 text-sm">{event.description}</p>
                            {event.metadata && Object.keys(event.metadata).length > 0 && (
                              <div className="mt-3 pt-3 border-t border-slate-50 flex gap-2 overflow-x-auto">
                                {Object.entries(event.metadata).map(([key, value]: [string, any]) => (
                                  <Badge key={key} variant="outline" className="text-[10px] bg-slate-50/50">
                                    <span className="font-bold mr-1 opacity-70 uppercase">{key}:</span> {value}
                                  </Badge>
                                ))}
                              </div>
                            )}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                ) : (
                  <div className="p-12 text-center space-y-4">
                    <History className="h-12 w-12 text-emerald-100 mx-auto" />
                    <div className="space-y-1">
                      <h3 className="font-semibold text-emerald-900">Journey Just Beginning</h3>
                      <p className="text-sm text-muted-foreground">No timeline events have been recorded for this case yet.</p>
                    </div>
                  </div>
                )}
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </div>
    </div>
  )
}
