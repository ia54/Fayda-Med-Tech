"use client"

import { useRef, useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
  DialogFooter,
} from "@/components/ui/dialog"
import { DollarSign, Calendar, FileText, Search, Edit, CheckCircle, Loader2 } from "lucide-react"
import {
  useGetSettlementsQuery,
  useCreateSettlementMutation,
  useUpdateSettlementMutation
} from "@/store/api/apiSlice"
import { useGetCasesQuery } from "@/store/api/casesApiSlice"
import { useToast } from "@/hooks/use-toast"
import { format } from "date-fns"

interface Case {
  id: number;
  case_number: string;
  title: string;
}

interface Settlement {
  id: number;
  case_id: number;
  settlement_amount: string;
  settlement_date: string;
  status: string;
  notes: string;
  case?: Case;
  attorney_fees?: string | null;
  costs?: string | null;
  other_deductions?: string | null;
  net_to_client?: string | null;
}

export default function SettlementsPage() {
  const [searchTerm, setSearchTerm] = useState("")
  const [statusFilter, setStatusFilter] = useState("all")
  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const { toast } = useToast()
  const [page, setPage] = useState(1)
  const [caseSearch, setCaseSearch] = useState("")
  const [selected, setSelected] = useState<Settlement | null>(null)
  const [saveError, setSaveError] = useState("")
  const saveReference = useRef<{ body: string; id: string } | null>(null)
  const saving = useRef(false)
  const readOnly = selected?.status === "completed"

  // Queries
  const { currentData: settlementsData, isFetching: isSettlementsLoading, isError, refetch } = useGetSettlementsQuery({
    page, per_page: 15, search: searchTerm,
    status: statusFilter === "all" ? undefined : statusFilter
  })
  const { data: casesData, isError: casesError } = useGetCasesQuery({ per_page: 100, search: caseSearch })

  const [createSettlement, { isLoading: isCreating }] = useCreateSettlementMutation()
  const [updateSettlement, { isLoading: isUpdating }] = useUpdateSettlementMutation()

  const settlements = (settlementsData?.data?.data || []) as Settlement[]
  const cases = (casesData?.data || []) as Case[]

  // Form State
  const [formData, setFormData] = useState({
    case_id: "",
    settlement_amount: "",
    settlement_date: format(new Date(), "yyyy-MM-dd"),
    status: "pending",
    notes: ""
  })

  const openRecord = (record: Settlement | null) => {
    setSelected(record)
    setSaveError("")
    saveReference.current = null
    setFormData({
      case_id: record ? String(record.case_id) : "",
      settlement_amount: record?.settlement_amount || "",
      settlement_date: record?.settlement_date.slice(0, 10) || format(new Date(), "yyyy-MM-dd"),
      status: record?.status || "pending",
      notes: record?.notes || "",
    })
    setIsDialogOpen(true)
  }

  const handleMarkSettlement = async () => {
    if (saving.current || readOnly) return
    setSaveError("")
    if (!formData.case_id || !/^\d+(\.\d{1,2})?$/.test(formData.settlement_amount) || !formData.settlement_date) {
      setSaveError("Choose a case, date and a non-negative amount with at most two decimal places.")
      return
    }
    saving.current = true
    try {
      const payload = {
        settlement_amount: formData.settlement_amount,
        settlement_date: formData.settlement_date,
        status: formData.status,
        notes: formData.notes,
      }
      if (selected) {
        await updateSettlement({ id: selected.id, ...payload }).unwrap()
      } else {
        const body = JSON.stringify({ case_id: Number(formData.case_id), ...payload })
        if (saveReference.current?.body !== body) saveReference.current = { body, id: crypto.randomUUID() }
        await createSettlement({ case_id: Number(formData.case_id), ...payload, request_id: saveReference.current.id }).unwrap()
      }
      toast({ title: "Saved", description: "Settlement record saved. No funds have been transferred." })
      setIsDialogOpen(false)
    } catch (error: any) {
      setSaveError(Object.values(error.data?.errors || {}).flat().join(" ") || error.data?.message || "Could not save this record. Review the details and try again.")
    } finally {
      saving.current = false
    }
  }

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'completed': return <Badge className="bg-emerald-600">Completed</Badge>
      case 'pending': return <Badge variant="secondary">Pending</Badge>
      case 'in-negotiation': return <Badge variant="outline">In Negotiation</Badge>
      default: return <Badge variant="outline">{status}</Badge>
    }
  }

  const totalSettlementValue = settlements.reduce((acc: number, s: Settlement) => acc + parseFloat(s.settlement_amount), 0)

  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50/50 via-white to-green-50/30 dark:from-emerald-950/20 dark:via-slate-950 dark:to-green-950/20 p-6">
      <div className="max-w-7xl mx-auto space-y-8">
        {/* Header */}
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold text-gray-900 dark:text-white text-balance">Settlement Notes</h1>
            <p className="text-gray-600 dark:text-slate-300 mt-2">Record settlement status, amounts and notes. These records do not confirm payment.</p>
          </div>
          <div className="flex gap-3">
            <Dialog open={isDialogOpen} onOpenChange={(open) => { if (!saving.current) setIsDialogOpen(open) }}>
              <DialogTrigger asChild>
                <Button onClick={() => openRecord(null)} className="bg-emerald-600 hover:bg-emerald-700 text-white">
                  <CheckCircle className="w-4 h-4 mr-2" />
                  Record Settlement
                </Button>
              </DialogTrigger>
              <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                  <DialogTitle>{readOnly ? "Completed Settlement" : selected ? "Edit Settlement" : "Record Settlement"}</DialogTitle>
                  <DialogDescription>{readOnly ? "Completed records are read-only. An auditable financial correction workflow is not yet available." : "Save a pending or negotiated record. Mark completed only after the settlement has been finalized; completed records cannot be edited here."}</DialogDescription>
                </DialogHeader>
                {saveError && <p role="alert" className="text-destructive">{saveError}</p>}
                {selected && <p className="text-sm">{selected.other_deductions != null ? `Saved allocations: fees $${selected.attorney_fees}; costs $${selected.costs}; other deductions $${selected.other_deductions}; net $${selected.net_to_client}. Amount edits must cover these deductions.` : "Allocation breakdown has not been recorded."} Use the case settlement calculator for new detailed allocations.</p>}
                <fieldset disabled={readOnly || isCreating || isUpdating} className="space-y-4">
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="space-y-2">
                      <Label htmlFor="case-number">Case *</Label>
                      {!selected && <Input aria-label="Search cases" placeholder="Search by case number or title" value={caseSearch} onChange={e => setCaseSearch(e.target.value)} />}
                      {casesError && <p role="alert">Cases could not be loaded. Try reopening this form.</p>}
                      <Select disabled={!!selected || readOnly || isCreating || isUpdating}
                        value={formData.case_id}
                        onValueChange={(value) => setFormData({...formData, case_id: value})}
                      >
                        <SelectTrigger id="case-number">
                          <SelectValue placeholder="Select case" />
                        </SelectTrigger>
                        <SelectContent>
                          {selected && !cases.some(c => c.id === selected.case_id) && <SelectItem value={String(selected.case_id)}>{selected.case?.case_number || `Case ${selected.case_id}`}</SelectItem>}
                          {cases.map((c: Case) => (
                            <SelectItem key={c.id} value={c.id.toString()}>{c.case_number} - {c.title}</SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="settlement-date">Settlement Date *</Label>
                      <Input
                        id="settlement-date"
                        type="date"
                        value={formData.settlement_date}
                        onChange={(e) => setFormData({...formData, settlement_date: e.target.value})}
                      />
                    </div>
                  </div>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="space-y-2">
                      <Label htmlFor="settlement-amount">Settlement Amount ($) *</Label>
                      <Input
                        id="settlement-amount"
                        type="number" min="0" step="0.01"
                        placeholder="0.00"
                        value={formData.settlement_amount}
                        onChange={(e) => setFormData({...formData, settlement_amount: e.target.value})}
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="status">Status *</Label>
                      <Select disabled={readOnly || isCreating || isUpdating}
                        value={formData.status}
                        onValueChange={(value) => setFormData({...formData, status: value})}
                      >
                        <SelectTrigger id="status">
                          <SelectValue placeholder="Select status" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="completed">Completed</SelectItem>
                          <SelectItem value="pending">Pending</SelectItem>
                          <SelectItem value="in-negotiation">In Negotiation</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="settlement-notes">Settlement Notes</Label>
                    <Textarea
                      id="settlement-notes"
                      placeholder="Enter detailed notes about the settlement..."
                      rows={6}
                      value={formData.notes}
                      onChange={(e) => setFormData({...formData, notes: e.target.value})}
                    />
                  </div>
                </fieldset>
                  <DialogFooter>
                    <Button disabled={isCreating || isUpdating} variant="outline" onClick={() => setIsDialogOpen(false)}>{readOnly ? "Close" : "Cancel"}</Button>
                    {!readOnly && <Button
                      className="bg-emerald-600 hover:bg-emerald-700"
                      onClick={handleMarkSettlement}
                      disabled={isCreating || isUpdating}
                    >
                      {(isCreating || isUpdating) && <Loader2 className="w-4 h-4 mr-2 animate-spin" />}
                      Save Settlement
                    </Button>}
                  </DialogFooter>
              </DialogContent>
            </Dialog>
          </div>
        </div>

        {/* Overview Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 dark:text-white flex items-center gap-2">
                <DollarSign className="w-4 h-4 text-emerald-600 dark:text-white" />
                Gross Amount on This Page
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-900 dark:text-white">
                {isError || isSettlementsLoading ? "—" : totalSettlementValue.toLocaleString(undefined, { style: "currency", currency: "USD" })}
              </div>
              <p className="text-xs text-emerald-600 dark:text-white mt-1">Current filtered page</p>
            </CardContent>
          </Card>

          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 dark:text-white flex items-center gap-2">
                <CheckCircle className="w-4 h-4 text-green-600 dark:text-green-400" />
                Completed Settlements
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-900 dark:text-white">
                {isError || isSettlementsLoading ? '—' : settlements.filter(s => s.status === 'completed').length}
              </div>
              <p className="text-xs text-green-600 dark:text-green-400 mt-1">Records on this page; not case closures</p>
            </CardContent>
          </Card>

          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 dark:text-white flex items-center gap-2">
                <Calendar className="w-4 h-4 text-blue-600 dark:text-blue-400" />
                Pending Settlements
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-900 dark:text-white">
                {isError || isSettlementsLoading ? '—' : settlements.filter(s => s.status === 'pending').length}
              </div>
              <p className="text-xs text-blue-600 dark:text-blue-400 mt-1">Records on this page</p>
            </CardContent>
          </Card>

          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 dark:text-white flex items-center gap-2">
                <FileText className="w-4 h-4 text-amber-600 dark:text-amber-400" />
                Active Negotiations
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-900 dark:text-white">
                {isError || isSettlementsLoading ? '—' : settlements.filter(s => s.status === 'in-negotiation').length}
              </div>
              <p className="text-xs text-amber-600 dark:text-amber-400 mt-1">Records on this page</p>
            </CardContent>
          </Card>
        </div>

        {/* Settlements Table */}
        <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50">
          <CardHeader>
            <CardTitle className="text-xl font-semibold text-gray-900 dark:text-white">Settlement Records</CardTitle>
            <CardDescription className="dark:text-slate-300">Track all case settlements and their details</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="flex flex-col md:flex-row gap-4 mb-6">
              <div className="relative flex-1">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <Input
                  placeholder="Search settlements..."
                  className="pl-10"
                  value={searchTerm}
                  onChange={(e) => { setSearchTerm(e.target.value); setPage(1) }}
                />
              </div>
              <Select value={statusFilter} onValueChange={value => { setStatusFilter(value); setPage(1) }}>
                <SelectTrigger className="w-full md:w-48">
                  <SelectValue placeholder="Filter by status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Status</SelectItem>
                  <SelectItem value="completed">Completed</SelectItem>
                  <SelectItem value="pending">Pending</SelectItem>
                  <SelectItem value="in-negotiation">In Negotiation</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Case Number</TableHead>
                    <TableHead>Case Title</TableHead>
                    <TableHead>Settlement Amount</TableHead>
                    <TableHead>Settlement Date</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Notes</TableHead>
                    <TableHead>Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {isError ? (
                    <TableRow><TableCell colSpan={7}><p role="alert">Could not load settlements.</p><Button onClick={() => refetch()}>Try again</Button></TableCell></TableRow>
                  ) : isSettlementsLoading ? (
                    <TableRow>
                      <TableCell colSpan={7} className="text-center py-10">
                        <Loader2 className="w-8 h-8 animate-spin mx-auto text-emerald-600" />
                      </TableCell>
                    </TableRow>
                  ) : settlements.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={7} className="text-center py-10 text-muted-foreground">
                        No settlement records found.
                      </TableCell>
                    </TableRow>
                  ) : (
                    settlements.map((settlement) => (
                      <TableRow key={settlement.id} className="hover:bg-emerald-50/50 dark:hover:bg-emerald-900/20 border-emerald-100 dark:border-emerald-900/30">
                        <TableCell className="font-medium">{settlement.case?.case_number || "N/A"}</TableCell>
                        <TableCell>{settlement.case?.title || "N/A"}</TableCell>
                        <TableCell className="font-medium text-green-600">
                          ${parseFloat(settlement.settlement_amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </TableCell>
                        <TableCell>{format(new Date(`${settlement.settlement_date.slice(0, 10)}T12:00:00`), "MMM dd, yyyy")}</TableCell>
                        <TableCell>
                          {getStatusBadge(settlement.status)}
                        </TableCell>
                        <TableCell className="max-w-xs truncate">{settlement.notes}</TableCell>
                        <TableCell>
                          <Button variant="ghost" size="sm" onClick={() => openRecord(settlement)} aria-label={`${settlement.status === "completed" ? "View" : "Edit"} settlement ${settlement.id}`}>
                            <Edit className="w-4 h-4 mr-2" />{settlement.status === "completed" ? "View" : "Edit"}
                          </Button>
                        </TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </div>
            <div className="flex items-center justify-between gap-3 mt-4">
              <Button variant="outline" disabled={page <= 1 || isSettlementsLoading} onClick={() => setPage(p => p - 1)}>Previous</Button>
              <span className="text-sm">Page {page} of {settlementsData?.data?.last_page || 1}</span>
              <Button variant="outline" disabled={isSettlementsLoading || !settlementsData?.data?.next_page_url} onClick={() => setPage(p => p + 1)}>Next</Button>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
