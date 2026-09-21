"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Input } from "@/components/ui/input"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { 
  FileCheck, Zap, Upload, Search, Filter, MoreVertical, 
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
import { Progress } from "@/components/ui/progress"
import { cn } from "@/lib/utils"
import { 
  useGetEobsQuery, 
  useGetEobStatsQuery, 
  useCreateEobMutation,
  useUpdateEobMutation 
} from "@/store/api/eobApiSlice"
import { useToast } from "@/hooks/use-toast"
import { format } from "date-fns"

export default function EobProcessingPage() {
  const [searchTerm, setSearchTerm] = useState("")
  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const { toast } = useToast()

  // Queries
  const { data: eobsData, isLoading: isEobsLoading } = useGetEobsQuery({ search: searchTerm })
  const { data: statsData, isLoading: isStatsLoading } = useGetEobStatsQuery()
  const [createEob, { isLoading: isCreating }] = useCreateEobMutation()
  const [updateEob] = useUpdateEobMutation()

  const eobs = eobsData?.data?.data || []
  const stats = statsData?.data || {
    total_processed: 0,
    pending_review: 0,
    avg_confidence: 0,
    total_paid_amount: 0
  }

  // Form State for manual entry (to make button "working")
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
        status: formData.status as any,
        ai_confidence: 100 // Manual entry is 100% confident
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

  const handleAutoReconcile = () => {
    toast({
      title: "Auto-Reconcile",
      description: "Simulation: AI is matching EOBs with invoices...",
    })
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
          <p className="text-muted-foreground">AI-powered extraction and reconciliation of Explanation of Benefits.</p>
        </div>
        <div className="flex gap-3">
            <Button variant="outline" className="border-primary/20 text-primary" onClick={handleAutoReconcile}>
                <Zap className="h-4 w-4 mr-2" />
                Auto-Reconcile
            </Button>
            
            <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
              <DialogTrigger asChild>
                <Button className="bg-primary hover:bg-primary/90 text-white shadow-lg shadow-primary/20">
                    <Upload className="h-4 w-4 mr-2" />
                    Upload New EOB
                </Button>
              </DialogTrigger>
              <DialogContent>
                <DialogHeader>
                  <DialogTitle>Add New EOB Record</DialogTitle>
                  <DialogDescription>
                    Manually enter EOB details or upload a document for AI extraction.
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

      <div className="grid gap-6 md:grid-cols-3">
        <Card className="bg-emerald-50/30 border-emerald-100">
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-emerald-900">Total Processed</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-bold text-emerald-700">
                  {isStatsLoading ? <Skeleton className="h-8 w-16" /> : stats.total_processed.toLocaleString()}
                </div>
                <p className="text-xs text-emerald-600/70 mt-1">Successfully extracted</p>
            </CardContent>
        </Card>
        <Card className="bg-amber-50/30 border-amber-100">
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-amber-900">Pending Review</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-bold text-amber-700">
                  {isStatsLoading ? <Skeleton className="h-8 w-16" /> : stats.pending_review}
                </div>
                <p className="text-xs text-amber-600/70 mt-1">Requires human verification</p>
            </CardContent>
        </Card>
        <Card className="bg-blue-50/30 border-blue-100">
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-blue-900">AI Confidence</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-bold text-blue-700">
                   {isStatsLoading ? <Skeleton className="h-8 w-16" /> : `${stats.avg_confidence}%`}
                </div>
                <p className="text-xs text-blue-600/70 mt-1">Average across all extractions</p>
            </CardContent>
        </Card>
      </div>

      <Card className="bg-card/50 backdrop-blur-sm border-border/50">
        <CardHeader className="pb-3">
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div className="flex items-center gap-2">
              <FileCheck className="h-5 w-5 text-primary" />
              <CardTitle className="text-lg">Extraction Queue</CardTitle>
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
              <Button variant="outline" size="sm" className="h-9">
                <Filter className="h-4 w-4 mr-2" />
                Filter
              </Button>
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
                  <TableHead className="font-bold">AI Confidence</TableHead>
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
                ) : eobs.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={7} className="text-center py-10 text-muted-foreground">
                      No EOB records found.
                    </TableCell>
                  </TableRow>
                ) : (
                  eobs.map((eob: any) => (
                    <TableRow key={eob.id} className="hover:bg-primary/5 transition-colors">
                      <TableCell className="text-sm text-muted-foreground">
                        {format(new Date(eob.created_at), 'MMM dd, yyyy')}
                      </TableCell>
                      <TableCell className="font-medium">{eob.patient_name}</TableCell>
                      <TableCell>{eob.payer_name}</TableCell>
                      <TableCell className="font-semibold">${eob.paid_amount.toLocaleString()}</TableCell>
                      <TableCell>
                          <div className="flex flex-col gap-1 w-24">
                              <div className="flex justify-between text-[10px]">
                                  <span>Confidence</span>
                                  <span className={cn(
                                      "font-bold",
                                      eob.ai_confidence > 90 ? "text-emerald-600" : "text-amber-600"
                                  )}>{eob.ai_confidence}%</span>
                              </div>
                              <Progress value={eob.ai_confidence} className="h-1" indicatorClassName={cn(
                                  eob.ai_confidence > 90 ? "bg-emerald-500" : "bg-amber-500"
                              )} />
                          </div>
                      </TableCell>
                      <TableCell>{getStatusBadge(eob.status)}</TableCell>
                      <TableCell className="text-right">
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon">
                              <MoreVertical className="h-4 w-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end" className="bg-card/95 backdrop-blur-md">
                            <DropdownMenuItem className="font-bold">
                              <Zap className="h-4 w-4 mr-2 text-primary" />
                              Review Extraction
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => updateEob({ id: eob.id, status: 'processed' })}>
                              <CheckCircle className="h-4 w-4 mr-2" />
                              Approve Data
                            </DropdownMenuItem>
                            <DropdownMenuItem className="text-rose-600">
                              <AlertCircle className="h-4 w-4 mr-2" />
                              Flag Issue
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
