"use client"

import { useRef, useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Input } from "@/components/ui/input"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import {
  FileText, Download, Search, Filter, Eye, CheckCircle,
  Clock, AlertTriangle, Loader2, Plus, Trash2, Edit, MoreVertical
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import {
  useGetLienProviderOptionsQuery,
  useGetLiensQuery,
  useCreateLienMutation,
  useUpdateLienMutation,
  useDeleteLienMutation,
  type Lien
} from "@/store/api/liensApiSlice"
import { useGetCasesQuery } from "@/store/api/casesApiSlice"
import { useToast } from "@/hooks/use-toast"
import { format } from "date-fns"

export function LienRecords({ caseId }: { caseId?: number }) {
  const [searchTerm, setSearchTerm] = useState("")
  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const { toast } = useToast()
  const [page, setPage] = useState(1)
  const [statusFilter, setStatusFilter] = useState("all")
  const [selected, setSelected] = useState<Lien | null>(null)
  const [saveError, setSaveError] = useState("")
  const [caseSearch, setCaseSearch] = useState("")
  const [providerSearch, setProviderSearch] = useState("")
  const saving = useRef(false)

  // Queries
  const { currentData: liensData, isFetching: isLiensLoading, isError, refetch } = useGetLiensQuery({ case_id: caseId, search: searchTerm, page, per_page: 15, status: statusFilter === "all" ? undefined : statusFilter })
  const { data: casesData, isError: casesError } = useGetCasesQuery({ per_page: 100, search: caseSearch }, { skip: !!caseId })
  const { data: providersData, isError: providersError } = useGetLienProviderOptionsQuery({ search: providerSearch })

  const [createLien, { isLoading: isCreating }] = useCreateLienMutation()
  const [updateLien, { isLoading: isUpdating }] = useUpdateLienMutation()
  const [deleteLien, { isLoading: isDeleting }] = useDeleteLienMutation()

  const liens: Lien[] = liensData?.data?.data || []
  const cases = casesData?.data || []
  const providers = providersData?.data || []

  // Form State
  const [formData, setFormData] = useState({
    case_id: "",
    provider_id: "",
    lien_type: "medical",
    amount: "",
    reduction_amount: "",
    status: "pending",
    notes: ""
  })

  const openRecord = (record: Lien | null) => {
    setSelected(record)
    setSaveError("")
    setCaseSearch("")
    setFormData({ case_id: record ? String(record.case_id) : caseId ? String(caseId) : "", provider_id: record?.provider_id ? String(record.provider_id) : "",
      lien_type: record?.lien_type || "medical", amount: record ? String(record.amount) : "", reduction_amount: record?.reduction_amount == null ? "" : String(record.reduction_amount), status: record?.status || "pending", notes: record?.notes || "" })
    setIsDialogOpen(true)
  }

  const handleCreateLien = async () => {
    if (saving.current) return
    setSaveError("")
    if (!formData.case_id || !/^\d+(\.\d{1,2})?$/.test(formData.amount)) {
      setSaveError("Choose a case and enter a non-negative amount with at most two decimal places.")
      return
    }
    saving.current = true
    try {
      const body = { case_id: Number(formData.case_id), provider_id: formData.provider_id ? Number(formData.provider_id) : null,
        lien_type: formData.lien_type as Lien["lien_type"], amount: formData.amount, reduction_amount: formData.reduction_amount === "" ? null : formData.reduction_amount,
        status: formData.status as Lien["status"], notes: formData.notes }
      if (selected) await updateLien({ id: selected.id, ...body }).unwrap()
      else await createLien(body).unwrap()
      toast({ title: "Saved", description: "Lien tracking record saved. No payment or legal release was performed." })
      setIsDialogOpen(false)
    } catch (error: any) {
      setSaveError(Object.values(error.data?.errors || {}).flat().join(" ") || error.data?.message || "Could not save the lien. Review the details and try again.")
    } finally { saving.current = false }
  }

  const handleDeleteLien = async (id: number) => {
    if (!confirm("Are you sure you want to delete this lien?")) return
    try {
      await deleteLien(id).unwrap()
      toast({
        title: "Deleted",
        description: "Lien record deleted successfully.",
      })
    } catch (error: any) {
      toast({
        title: "Error",
        description: error.data?.message || "Failed to delete lien record.",
        variant: "destructive"
      })
    }
  }

  const getStatusBadge = (status: string) => {
    switch (status.toLowerCase()) {
      case 'settled':
        return <Badge variant="outline">Settled</Badge>
      case 'released': return <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 font-bold uppercase text-[10px]">Released</Badge>
      case 'pending': return <Badge variant="outline" className="bg-amber-50 text-amber-700 border-amber-200 font-bold uppercase text-[10px]">Pending</Badge>
      case 'negotiated': return <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200 font-bold uppercase text-[10px]">Negotiated</Badge>
      case 'expired': return <Badge variant="outline" className="bg-rose-50 text-rose-700 border-rose-200 font-bold uppercase text-[10px]">Expired</Badge>
      default: return <Badge variant="outline">{status}</Badge>
    }
  }

  const recordActions = (lien: Lien) => (
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button aria-label={`Actions for lien ${lien.id}`} variant="ghost" size="icon">
                                <MoreVertical className="h-4 w-4" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="bg-card/95 backdrop-blur-md">
                              <DropdownMenuItem onClick={() => openRecord(lien)}>
                                <Eye className="h-4 w-4 mr-2" />
                                View / Edit Details
                              </DropdownMenuItem>
                              <DropdownMenuItem disabled>
                                <Download className="h-4 w-4 mr-2" />
                                Export unavailable
                              </DropdownMenuItem>
                              <DropdownMenuItem
                                disabled={isDeleting || lien.status === "settled" || lien.status === "released"} className="text-rose-600"
                                onClick={() => handleDeleteLien(lien.id)}
                              >
                                <Trash2 className="h-4 w-4 mr-2" />
                                Delete Lien
                              </DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
  )

  return (
    <div className={caseId ? "space-y-6" : "min-h-screen bg-gradient-to-br from-emerald-50/50 via-white to-green-50/30 dark:from-emerald-950/20 dark:via-slate-950 dark:to-green-950/20 p-6"}>
      <div className="max-w-7xl mx-auto space-y-8">
        {/* Header */}
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold text-gray-900 dark:text-white text-balance">Lien Records</h1>
            <p className="text-gray-600 dark:text-slate-300 mt-2">Track reported lien amounts and status. These records do not confirm payment or legal release.</p>
          </div>
          <div className="flex flex-wrap gap-3">
            <Dialog open={isDialogOpen} onOpenChange={open => { if (!saving.current) setIsDialogOpen(open) }}>
              <DialogTrigger asChild>
                <Button onClick={() => openRecord(null)} className="bg-emerald-600 hover:bg-emerald-700 text-white shadow-lg shadow-emerald-200">
                  <Plus className="h-4 w-4 mr-2" />
                  Create New Lien
                </Button>
              </DialogTrigger>
              <DialogContent className="sm:max-w-[500px] max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                  <DialogTitle>{selected ? "Edit Lien Record" : "Create New Lien Record"}</DialogTitle>
                  <DialogDescription>
                    Record information for an authorized case. Status changes do not execute a payment, signature or release.
                  </DialogDescription>
                </DialogHeader>
                {saveError && <p role="alert" className="text-destructive">{saveError}</p>}
                <fieldset disabled={isCreating || isUpdating} className="grid gap-4 py-4">
                  {!caseId && <div className="grid gap-2">
                    <Label htmlFor="case">Case *</Label>
                    {!selected && <Input aria-label="Search cases" placeholder="Search by case number or title" value={caseSearch} onChange={e => setCaseSearch(e.target.value)} />}
                    {casesError && <p role="alert">Cases could not be loaded. Reopen the form to retry.</p>}
                    <Select disabled={!!selected || isCreating || isUpdating}
                      value={formData.case_id}
                      onValueChange={(value) => setFormData({...formData, case_id: value})}
                    >
                      <SelectTrigger id="case">
                        <SelectValue placeholder="Select a case" />
                      </SelectTrigger>
                      <SelectContent>
                        {selected && !cases.some(c => c.id === selected.case_id) && <SelectItem value={String(selected.case_id)}>{selected.case?.case_number || `Case ${selected.case_id}`}</SelectItem>}
                        {cases.map((c) => (
                          <SelectItem key={c.id} value={c.id.toString()}>
                            {c.case_number} - {c.title}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>}
                  <div className="grid gap-2">
                    <Label htmlFor="provider">Provider</Label>
                    <Input aria-label="Search providers" placeholder="Search provider names" value={providerSearch} onChange={e => setProviderSearch(e.target.value)} />
                    {providersError && <p role="alert">Providers could not be loaded.</p>}
                    <Select
                      disabled={isCreating || isUpdating} value={formData.provider_id || "none"}
                      onValueChange={(value) => setFormData({...formData, provider_id: value === "none" ? "" : value})}
                    >
                      <SelectTrigger id="provider">
                        <SelectValue placeholder="Select a provider (optional)" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="none">No provider selected</SelectItem>
                        {selected?.provider_id && !providers.some((p: any) => p.id === selected.provider_id) && <SelectItem value={String(selected.provider_id)}>{selected.provider?.name || "Linked provider"}</SelectItem>}
                        {providers.map((p: any) => (
                          <SelectItem key={p.id} value={p.id.toString()}>
                            {p.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="grid gap-2">
                      <Label htmlFor="type">Lien Type *</Label>
                      <Select disabled={isCreating || isUpdating}
                        value={formData.lien_type}
                        onValueChange={(value) => setFormData({...formData, lien_type: value})}
                      >
                        <SelectTrigger id="type">
                          <SelectValue placeholder="Select type" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="medical">Medical</SelectItem>
                          <SelectItem value="attorney">Attorney</SelectItem>
                          <SelectItem value="government_medicare">Medicare</SelectItem>
                          <SelectItem value="government_medicaid">Medicaid</SelectItem>
                          <SelectItem value="health_insurance">Health Insurance</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="grid gap-2">
                      <Label htmlFor="amount">Lien Amount ($) *</Label>
                      <Input
                        id="amount"
                        type="number" min="0" max="99999999.99" step="0.01"
                        placeholder="0.00"
                        value={formData.amount}
                        onChange={(e) => setFormData({...formData, amount: e.target.value})}
                      />
                    </div>
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="lien-reduction">Recorded reduction ($)</Label>
                    <Input id="lien-reduction" type="number" min="0" step="0.01" max={formData.amount || "99999999.99"} placeholder="Not recorded" value={formData.reduction_amount} onChange={e => setFormData({...formData, reduction_amount: e.target.value})} />
                    <p className="text-xs text-muted-foreground">Leave blank when unknown. A recorded reduction does not confirm payment.</p>
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="lien-status">Recorded status</Label>
                    <Select disabled={isCreating || isUpdating} value={formData.status} onValueChange={status => setFormData({...formData, status})}>
                      <SelectTrigger id="lien-status"><SelectValue /></SelectTrigger>
                      <SelectContent>{(selected?.status === "released" ? ["released"] : selected?.status === "settled" ? ["settled", "released"] : ["pending", "negotiated", "settled", "released"]).map(status => <SelectItem key={status} value={status}>{status.charAt(0).toUpperCase() + status.slice(1)}</SelectItem>)}</SelectContent>
                    </Select>
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="notes">Notes</Label>
                    <Input
                      id="notes"
                      placeholder="Additional details..."
                      value={formData.notes}
                      onChange={(e) => setFormData({...formData, notes: e.target.value})}
                    />
                  </div>
                </fieldset>
                <DialogFooter>
                  <Button disabled={isCreating || isUpdating} variant="outline" onClick={() => setIsDialogOpen(false)}>Cancel</Button>
                  <Button
                    onClick={handleCreateLien}
                    className="bg-emerald-600 hover:bg-emerald-700 text-white"
                    disabled={isCreating || isUpdating}
                  >
                    {isCreating || isUpdating ? <Loader2 className="h-4 w-4 animate-spin mr-2" /> : null}
                    {selected ? "Save Changes" : "Create Lien"}
                  </Button>
                </DialogFooter>
              </DialogContent>
            </Dialog>

            <Button disabled title="Document export is not available" variant="outline" className="border-emerald-200 dark:border-emerald-800 hover:bg-emerald-50 dark:hover:bg-emerald-900/50 bg-transparent text-emerald-700 dark:text-white">
              <Download className="w-4 h-4 mr-2" />
              Export unavailable
            </Button>
          </div>
        </div>

        {/* Overview Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 dark:text-white flex items-center gap-2">
                <FileText className="w-4 h-4 text-emerald-600 dark:text-slate-300" />
                Liens on This Page
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-900 dark:text-white">
                {isError || isLiensLoading ? "..." : liens.length}
              </div>
              <p className="text-xs text-emerald-600 dark:text-slate-300 mt-1">Current filtered page</p>
            </CardContent>
          </Card>

          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 dark:text-white flex items-center gap-2">
                <CheckCircle className="w-4 h-4 text-green-600 dark:text-green-400" />
                Settled or Released
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-900 dark:text-white">
                {isError || isLiensLoading ? "..." : liens.filter((l: any) => l.status === 'settled' || l.status === 'released').length}
              </div>
              <p className="text-xs text-green-600 dark:text-green-400 mt-1">Records on this page</p>
            </CardContent>
          </Card>

          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 dark:text-white flex items-center gap-2">
                <Clock className="w-4 h-4 text-amber-600 dark:text-amber-400" />
                Pending Liens
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-900 dark:text-white">
                {isError || isLiensLoading ? "..." : liens.filter((l: any) => l.status === 'pending').length}
              </div>
              <p className="text-xs text-amber-600 dark:text-amber-400 mt-1">Records on this page</p>
            </CardContent>
          </Card>

          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 dark:text-white flex items-center gap-2">
                <AlertTriangle className="w-4 h-4 text-red-600 dark:text-red-400" />
                Recorded Amount on This Page
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-900 dark:text-white">
                {isError || isLiensLoading ? "—" : liens.reduce((acc, l) => acc + Number(l.amount), 0).toLocaleString(undefined, { style: "currency", currency: "USD" })}
              </div>
              <p className="text-xs text-red-600 dark:text-red-400 mt-1">Includes all displayed statuses; not an outstanding balance</p>
            </CardContent>
          </Card>
        </div>

        {/* Lien Records */}
        <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50">
          <CardHeader>
            <CardTitle className="text-xl font-semibold text-gray-900 dark:text-white">Lien Records</CardTitle>
            <CardDescription className="dark:text-white">Review and update the reported lien details</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="flex flex-col md:flex-row gap-4 mb-6">
              <div className="relative flex-1">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <Input
                  aria-label="Search liens" placeholder="Search liens..."
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
                  <SelectItem value="settled">Settled</SelectItem>
                  <SelectItem value="released">Released</SelectItem>
                  <SelectItem value="negotiated">Negotiated</SelectItem>
                  <SelectItem value="pending">Pending</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-4 md:hidden">
              {isError ? <div><p role="alert">Could not load liens.</p><Button onClick={() => refetch()}>Try again</Button></div> : isLiensLoading ? <p role="status">Loading liens…</p> : liens.length === 0 ? <p>No lien records found.</p> : liens.map(lien => (
                <article key={lien.id} aria-label={`Lien ${lien.id}`} className="rounded-lg border p-4 space-y-3 min-w-0">
                  <div className="flex flex-wrap justify-between items-center gap-2"><p className="font-medium break-all">{lien.case?.case_number || "N/A"}</p>{getStatusBadge(lien.status)}</div>
                  <p className="capitalize text-sm">{lien.lien_type.replaceAll('_', ' ')}</p>
                  <p className="text-sm break-words">Provider: {lien.provider?.name || "Not specified"}</p>
                  <p className="font-medium">{Number(lien.amount).toLocaleString(undefined, { style: "currency", currency: "USD" })}</p>
                  <p className="text-sm">Reduction: {lien.reduction_amount == null ? "Not recorded" : `$${Number(lien.reduction_amount).toFixed(2)}`}</p>
                  {lien.notes && <p className="text-sm line-clamp-3 break-words">{lien.notes}</p>}
                  <div className="flex items-center justify-between gap-2"><Button variant="outline" onClick={() => openRecord(lien)}>View / Edit</Button>{recordActions(lien)}</div>
                </article>
              ))}
            </div>
            <div className="hidden md:block overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Case Number</TableHead>
                    <TableHead>Lien Type</TableHead>
                    <TableHead>Provider</TableHead>
                    <TableHead>Amount / reduction</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Created Date</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {isError ? (
                    <TableRow><TableCell colSpan={7}><p role="alert">Could not load liens.</p><Button onClick={() => refetch()}>Try again</Button></TableCell></TableRow>
                  ) : isLiensLoading ? (
                    <TableRow>
                      <TableCell colSpan={7} className="text-center py-10">
                        <Loader2 className="h-8 w-8 animate-spin mx-auto text-emerald-600" />
                      </TableCell>
                    </TableRow>
                  ) : liens.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={7} className="text-center py-10 text-muted-foreground">
                        No lien records found.
                      </TableCell>
                    </TableRow>
                  ) : (
                    liens.map((lien: any) => (
                      <TableRow key={lien.id} className="hover:bg-emerald-50/50">
                        <TableCell className="font-medium">{lien.case?.case_number || "N/A"}</TableCell>
                        <TableCell className="capitalize">{lien.lien_type.replaceAll('_', ' ')}</TableCell>
                        <TableCell>{lien.provider?.name || "Not specified"}</TableCell>
                        <TableCell className="font-medium">${Number(lien.amount).toFixed(2)}<p className="text-xs text-muted-foreground">Reduction: {lien.reduction_amount == null ? "Not recorded" : `$${Number(lien.reduction_amount).toFixed(2)}`}</p></TableCell>
                        <TableCell>
                          {getStatusBadge(lien.status)}
                        </TableCell>
                        <TableCell>{format(new Date(lien.created_at), 'MMM dd, yyyy')}</TableCell>
                        <TableCell className="text-right">
                          {recordActions(lien)}
                        </TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </div>
            <div className="flex items-center justify-between gap-3 mt-4">
              <Button variant="outline" disabled={page <= 1 || isLiensLoading} onClick={() => setPage(p => p - 1)}>Previous</Button>
              <span className="text-sm">Page {page} of {liensData?.data.last_page || 1}</span>
              <Button variant="outline" disabled={isLiensLoading || !liensData?.data.next_page_url} onClick={() => setPage(p => p + 1)}>Next</Button>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
