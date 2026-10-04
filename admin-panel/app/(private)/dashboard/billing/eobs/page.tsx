"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Input } from "@/components/ui/input"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { 
  FileCheck, Search, MoreVertical,
  CheckCircle, AlertCircle, Loader2, Plus 
} from "lucide-react"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import { cn } from "@/lib/utils"
import { 
  type Eob,
  useGetEobsQuery, 
  useGetEobStatsQuery, 
  useCreateEobMutation,
  useUpdateEobMutation 
} from "@/store/api/eobApiSlice"
import { useToast } from "@/hooks/use-toast"
import { format } from "date-fns"

export default function EobProcessingPage() {
  const [searchTerm, setSearchTerm] = useState("")
  const [reviewRecord, setReviewRecord] = useState<Eob | null>(null)
  const [statusFilter, setStatusFilter] = useState("")
  const [reviewError, setReviewError] = useState("")
  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const { toast } = useToast()

  // Queries
  const { data: eobsData, isLoading: isEobsLoading, isError: eobsError, refetch: reloadEobs } = useGetEobsQuery({ search: searchTerm, status: statusFilter || undefined })
  const { data: statsData, isLoading: isStatsLoading, isError: statsError } = useGetEobStatsQuery()
  const [createEob, { isLoading: isCreating }] = useCreateEobMutation()
  const [updateEob, { isLoading: isUpdating }] = useUpdateEobMutation()

  const eobs = eobsData?.data?.data || []
  const stats = statsData?.data || {
    total_processed: 0,
    pending_review: 0,
    avg_confidence: 0,
    total_paid_amount: 0
  }

  // Manual EOB entry; no external extraction provider is connected.
  const [formData, setFormData] = useState({
    patient_name: "",
    payer_name: "",
    provider_name: "",
    billed_amount: "",
    paid_amount: "",
    status: "pending"
  })

  const handleCreateEob = async () => {
    try {
      await createEob({
        ...formData,
        billed_amount: parseFloat(formData.billed_amount),
        paid_amount: parseFloat(formData.paid_amount),
        status: formData.status as any
      }).unwrap()

      toast({
        title: "Success",
        description: "EOB record created successfully.",
      })
      setIsDialogOpen(false)
    } catch (error) {
      toast({
        title: "Error",
        description: "Failed to create EOB record.",
        variant: "destructive"
      })
    }
  }

  const saveReview = async (status: 'processed' | 'rejected') => {
    if (!reviewRecord || isUpdating) return
    setReviewError("")
    try {
      await updateEob({ id: reviewRecord.id, status }).unwrap()
      toast({ title: "Review saved", description: "EOB status updated. No payment or insurer submission was made." })
      setReviewRecord(null)
    } catch {
      setReviewError("Review not saved. Check your connection and retry; the record remains open.")
      toast({ title: "Review not saved", description: "Please retry. The record remains open for review.", variant: "destructive" })
    }
  }

  const getStatusBadge = (status: string) => {
    switch (status.toLowerCase()) {
      case 'processed': return <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 font-bold uppercase text-[10px]">Processed</Badge>
      case 'pending': return <Badge variant="outline" className="bg-amber-50 text-amber-700 border-amber-200 font-bold uppercase text-[10px]">Review Required</Badge>
      case 'matched': return <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200 font-bold uppercase text-[10px]">Matched</Badge>
      case 'rejected': return <Badge variant="outline" className="bg-rose-50 text-rose-700 border-rose-200 font-bold uppercase text-[10px]">Rejected</Badge>
      default: return <Badge variant="outline">{status}</Badge>
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold tracking-tight text-primary">EOB Processing</h1>
          <p className="text-muted-foreground">Record and review Explanation of Benefits information. Manual entries require verification.</p>
        </div>
        <div className="flex gap-3">
            <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
              <DialogTrigger asChild>
                <Button className="bg-primary hover:bg-primary/90 text-white shadow-lg shadow-primary/20">
                    <Plus className="h-4 w-4 mr-2" />
                    Add EOB record
                </Button>
              </DialogTrigger>
              <DialogContent>
                <DialogHeader>
                  <DialogTitle>Add New EOB Record</DialogTitle>
                  <DialogDescription>
                    Enter details from the original EOB for human review. Automated extraction and reconciliation are not connected.
                  </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 py-4">
                  <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                      <Label htmlFor="patient">Patient Name *</Label>
                      <Input 
                        id="patient" 
                        value={formData.patient_name}
                        onChange={(e) => setFormData({...formData, patient_name: e.target.value})}
                      />
                    </div>
                    <div className="grid gap-2">
                      <Label htmlFor="payer">Payer Name *</Label>
                      <Input 
                        id="payer" 
                        value={formData.payer_name}
                        onChange={(e) => setFormData({...formData, payer_name: e.target.value})}
                      />
                    </div>
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="provider">Provider Name *</Label>
                    <Input 
                      id="provider" 
                      value={formData.provider_name}
                      onChange={(e) => setFormData({...formData, provider_name: e.target.value})}
                    />
                  </div>
                  <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                      <Label htmlFor="billed">Billed Amount ($) *</Label>
                      <Input 
                        id="billed" 
                        type="number"
                        value={formData.billed_amount}
                        onChange={(e) => setFormData({...formData, billed_amount: e.target.value})}
                      />
                    </div>
                    <div className="grid gap-2">
                      <Label htmlFor="paid">Paid Amount ($) *</Label>
                      <Input 
                        id="paid" 
                        type="number"
                        value={formData.paid_amount}
                        onChange={(e) => setFormData({...formData, paid_amount: e.target.value})}
                      />
                    </div>
                  </div>
                </div>
                <DialogFooter>
                  <Button variant="outline" onClick={() => setIsDialogOpen(false)}>Cancel</Button>
                  <Button 
                    onClick={handleCreateEob} 
                    className="bg-primary text-white"
                    disabled={isCreating}
                  >
                    {isCreating ? <Loader2 className="h-4 w-4 animate-spin mr-2" /> : null}
                    Save EOB
                  </Button>
                </DialogFooter>
              </DialogContent>
            </Dialog>
        </div>
      </div>

      <Dialog open={reviewRecord !== null} onOpenChange={(open) => { if (!open && !isUpdating) setReviewRecord(null) }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Review EOB record</DialogTitle>
            <DialogDescription>Compare the recorded details with the original EOB. Marking reviewed does not verify coverage, submit a claim, or record a payment.</DialogDescription>
          </DialogHeader>
          {reviewError && <p role="alert" className="text-sm text-destructive">{reviewError}</p>}
          {reviewRecord && <dl className="grid grid-cols-2 gap-3 text-sm">
            <dt>Patient</dt><dd>{reviewRecord.patient_name}</dd>
            <dt>Payer</dt><dd>{reviewRecord.payer_name}</dd>
            <dt>Provider</dt><dd>{reviewRecord.provider_name}</dd>
            <dt>Billed amount</dt><dd>${Number(reviewRecord.billed_amount).toFixed(2)}</dd>
            <dt>Allowed amount</dt><dd>{reviewRecord.allowed_amount == null ? "Not recorded" : `$${Number(reviewRecord.allowed_amount).toFixed(2)}`}</dd>
            <dt>Reported paid amount</dt><dd>${Number(reviewRecord.paid_amount ?? 0).toFixed(2)}</dd>
            <dt>Patient responsibility</dt><dd>{reviewRecord.patient_responsibility == null ? "Not recorded" : `$${Number(reviewRecord.patient_responsibility).toFixed(2)}`}</dd>
            <dt>Service date</dt><dd>{reviewRecord.service_date || "Not recorded"}</dd>
            <dt>EOB date</dt><dd>{reviewRecord.eob_date || "Not recorded"}</dd>
            <dt>Notes</dt><dd>{reviewRecord.notes || "None recorded"}</dd>
            <dt>Status</dt><dd>{reviewRecord.status}</dd>
          </dl>}
          <DialogFooter>
            <Button variant="outline" disabled={isUpdating} onClick={() => saveReview('rejected')}><AlertCircle className="h-4 w-4 mr-2" />Reject record</Button>
            <Button disabled={isUpdating} onClick={() => saveReview('processed')}><CheckCircle className="h-4 w-4 mr-2" />Mark reviewed</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <div className="grid gap-6 md:grid-cols-3">
        <Card className="bg-emerald-50/30 border-emerald-100">
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-emerald-900">Total Processed</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-bold text-emerald-700">
                  {isStatsLoading ? <Skeleton className="h-8 w-16" /> : statsError ? "Unavailable" : stats.total_processed.toLocaleString()}
                </div>
                <p className="text-xs text-emerald-600/70 mt-1">Records marked processed or matched</p>
            </CardContent>
        </Card>
        <Card className="bg-amber-50/30 border-amber-100">
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-amber-900">Pending Review</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-bold text-amber-700">
                  {isStatsLoading ? <Skeleton className="h-8 w-16" /> : statsError ? "Unavailable" : stats.pending_review}
                </div>
                <p className="text-xs text-amber-600/70 mt-1">Requires human verification</p>
            </CardContent>
        </Card>
        <Card className="bg-blue-50/30 border-blue-100">
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-blue-900">Extraction confidence</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-bold text-blue-700">
                   Not verified
                </div>
                <p className="text-xs text-blue-600/70 mt-1">Manual entries have no model confidence score</p>
            </CardContent>
        </Card>
      </div>

      <Card className="bg-card/50 backdrop-blur-sm border-border/50">
        <CardHeader className="pb-3">
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div className="flex items-center gap-2">
              <FileCheck className="h-5 w-5 text-primary" />
              <CardTitle className="text-lg">EOB review queue</CardTitle>
            </div>
            <div className="flex items-center gap-2">
              <div className="relative">
                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder="Search by patient or payer..."
                  className="pl-9 bg-background/50 border-border/50 h-9 w-[200px] lg:w-[300px]"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                />
              </div>
              <select aria-label="Filter EOB status" className="h-9 rounded-md border bg-background px-2 text-sm" value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)}>
                <option value="">All statuses</option>
                <option value="pending">Review required</option>
                <option value="processed">Processed</option>
                <option value="matched">Matched</option>
                <option value="rejected">Rejected</option>
              </select>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div className="rounded-xl border border-border/50 overflow-hidden">
            <Table>
              <TableHeader className="bg-muted/30">
                <TableRow>
                  <TableHead className="font-bold">Date</TableHead>
                  <TableHead className="font-bold">Patient</TableHead>
                  <TableHead className="font-bold">Payer</TableHead>
                  <TableHead className="font-bold">Amount Paid</TableHead>
                  <TableHead className="font-bold">Extraction confidence</TableHead>
                  <TableHead className="font-bold">Status</TableHead>
                  <TableHead className="text-right font-bold">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {isEobsLoading ? (
                  <TableRow>
                    <TableCell colSpan={7} className="text-center py-10">
                      <Loader2 className="h-8 w-8 animate-spin mx-auto text-primary" />
                    </TableCell>
                  </TableRow>
                ) : eobsError ? (
                  <TableRow><TableCell colSpan={7} className="text-center py-10">
                    <p role="alert">EOB records could not be loaded.</p>
                    <Button variant="outline" className="mt-3" onClick={() => reloadEobs()}>Retry</Button>
                  </TableCell></TableRow>
                ) : eobs.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={7} className="text-center py-10 text-muted-foreground">
                      No EOB records found.
                    </TableCell>
                  </TableRow>
                ) : (
                  eobs.map((eob: Eob) => (
                    <TableRow key={eob.id} className="hover:bg-primary/5 transition-colors">
                      <TableCell className="text-sm text-muted-foreground">
                        {format(new Date(eob.created_at), 'MMM dd, yyyy')}
                      </TableCell>
                      <TableCell className="font-medium">{eob.patient_name}</TableCell>
                      <TableCell>{eob.payer_name}</TableCell>
                      <TableCell className="font-semibold">${eob.paid_amount.toLocaleString()}</TableCell>
                      <TableCell>
                          <span className="text-sm text-muted-foreground">
                            {eob.ai_confidence == null ? "Not measured" : "Historical score — unverified"}
                          </span>
                      </TableCell>
                      <TableCell>{getStatusBadge(eob.status)}</TableCell>
                      <TableCell className="text-right">
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon" aria-label={`Actions for EOB ${eob.id}`}>
                              <MoreVertical className="h-4 w-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end" className="bg-card/95 backdrop-blur-md">
                            <DropdownMenuItem onClick={() => { setReviewError(""); setReviewRecord(eob) }}>
                              <FileCheck className="h-4 w-4 mr-2" />Review record
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

function Skeleton({ className }: { className?: string }) {
  return <div className={cn("animate-pulse rounded-md bg-muted", className)} />
}
