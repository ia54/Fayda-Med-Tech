"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { FileText, Plus, Search, Filter, Download, Trash2, Loader2 } from "lucide-react"
import { Input } from "@/components/ui/input"
import { Invoice, useGetInvoicesQuery, useCreateInvoiceMutation, useDeleteInvoiceMutation, useReviewInvoiceMutation } from "@/store/api/billingApiSlice"
import { useGetCasesQuery } from "@/store/api/casesApiSlice"
import { useSelector } from "react-redux"
import { RootState } from "@/store/store"
import { Skeleton } from "@/components/ui/skeleton"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { useToast } from "@/hooks/use-toast"

export default function InvoicesPage() {
  const { toast } = useToast()
  const [selected, setSelected] = useState<Invoice | null>(null)
  const [page, setPage] = useState(1)
  const [status, setStatus] = useState("all")
  const [searchTerm, setSearchTerm] = useState("")
  const [isModalOpen, setIsModalOpen] = useState(false)

  // Form State
  const [selectedCase, setSelectedCase] = useState("")
  const [amount, setAmount] = useState("")
  const [dueDate, setDueDate] = useState("")

  const { currentData: invoicesData, isFetching: isLoading, isError, refetch } = useGetInvoicesQuery({ search: searchTerm, page, status: status === "all" ? undefined : status })
  const { data: casesData } = useGetCasesQuery({})
  const [createInvoice, { isLoading: isCreating }] = useCreateInvoiceMutation()
  const [deleteInvoice] = useDeleteInvoiceMutation()
  const user = useSelector((state: RootState) => state.auth.user)

  const canReview = ['admin', 'firm_admin', 'medical_biller'].includes(user?.role || '')

  const handleCreateInvoice = async () => {
    if (!selectedCase || !amount || !dueDate) {
      toast({ title: "Validation Error", description: "All fields are required", variant: "destructive" })
      return
    }

    try {
      await createInvoice({
        case_id: Number(selectedCase),
        amount: Number(amount),
        due_date: dueDate,
        status: 'draft'
      }).unwrap()
      
      setIsModalOpen(false)
      setSelectedCase("")
      setAmount("")
      setDueDate("")
      toast({ title: "Success", description: "Invoice created successfully" })
    } catch (err: any) {
      toast({ title: "Error", description: err.data?.message || "Failed to create invoice", variant: "destructive" })
    }
  }

  const handleDelete = async (id: number) => {
    if (confirm("Are you sure you want to delete this invoice?")) {
      try {
        await deleteInvoice(id).unwrap()
        toast({ title: "Deleted", description: "Invoice removed successfully" })
      } catch (err: any) {
        toast({ title: "Error", description: "Failed to delete invoice", variant: "destructive" })
      }
    }
  }

  const getStatusColor = (status: string) => {
    switch (status) {
      case "paid": return "bg-emerald-100 text-emerald-700 border-emerald-200"
      case "sent": return "bg-blue-100 text-blue-700 border-blue-200"
      case "denied": return "bg-red-100 text-red-700 border-red-200"
      case "draft": return "bg-slate-100 text-slate-700 border-slate-200"
      default: return "bg-slate-100 text-slate-700"
    }
  }

  return (
    <div className="space-y-6">

      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 className="text-3xl font-bold text-primary">Invoices</h1>
          <p className="text-muted-foreground">Manage and track all billing invoices</p>
        </div>
        <div className="flex gap-2">
          {canReview && (
            <Dialog open={isModalOpen} onOpenChange={setIsModalOpen}>
              <DialogTrigger asChild>
                <Button>
                  <Plus className="h-4 w-4 mr-2" />
                  Create Invoice
                </Button>
              </DialogTrigger>
              <DialogContent>
                <DialogHeader>
                  <DialogTitle>Create New Invoice</DialogTitle>
                  <DialogDescription>Generate a new invoice for an existing legal case.</DialogDescription>
                </DialogHeader>
                <div className="space-y-4 py-4">
                  <div className="space-y-2">
                    <Label>Select Legal Case</Label>
                    <Select value={selectedCase} onValueChange={setSelectedCase}>
                      <SelectTrigger>
                        <SelectValue placeholder="Choose a case" />
                      </SelectTrigger>
                      <SelectContent>
                        {casesData?.data?.map((c: any) => (
                          <SelectItem key={c.id} value={String(c.id)}>{c.title}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label>Amount ($)</Label>
                    <Input type="number" placeholder="0.00" value={amount} onChange={(e) => setAmount(e.target.value)} />
                  </div>
                  <div className="space-y-2">
                    <Label>Due Date</Label>
                    <Input type="date" value={dueDate} onChange={(e) => setDueDate(e.target.value)} />
                  </div>
                </div>
                <DialogFooter>
                  <Button variant="outline" onClick={() => setIsModalOpen(false)}>Cancel</Button>
                  <Button onClick={handleCreateInvoice} disabled={isCreating}>
                    {isCreating && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                    Create Invoice
                  </Button>
                </DialogFooter>
              </DialogContent>
            </Dialog>
          )}
        </div>
      </div>

      <Card className="bg-card/80 backdrop-blur-sm">
        <CardHeader>
          <div className="flex flex-col md:flex-row md:items-center gap-4">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search by invoice number..."
                className="pl-10"
                value={searchTerm}
                onChange={(e) => { setSearchTerm(e.target.value); setPage(1) }}
              />
            </div>
            <Select value={status} onValueChange={value => { setStatus(value); setPage(1) }}><SelectTrigger aria-label="Invoice status" className="w-48"><SelectValue /></SelectTrigger><SelectContent><SelectItem value="all">All records</SelectItem><SelectItem value="sent">Billing review</SelectItem><SelectItem value="draft">Drafts</SelectItem><SelectItem value="paid">Paid</SelectItem></SelectContent></Select>
          </div>
        </CardHeader>
        <CardContent>
          {isError ? <p role="alert">Could not load invoices. <Button variant="outline" onClick={() => refetch()}>Try again</Button></p> : isLoading ? (
            <div className="space-y-4">
              {[1, 2, 3, 4, 5].map((i) => (
                <Skeleton key={i} className="h-12 w-full" />
              ))}
            </div>
          ) : (
            <div className="rounded-md border">
              <Table>
                <TableHeader className="bg-muted/50">
                  <TableRow>
                    <TableHead>Invoice #</TableHead>
                    <TableHead>Legal Case</TableHead>
                    <TableHead>Amount</TableHead>
                    <TableHead>Due Date</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {invoicesData?.data?.data?.map((invoice) => (
                    <TableRow key={invoice.id}>
                      <TableCell className="font-medium">{invoice.invoice_number}</TableCell>
                      <TableCell className="max-w-[200px] truncate">{invoice.case?.title || "N/A"}</TableCell>
                      <TableCell>${Number(invoice.amount).toLocaleString()}</TableCell>
                      <TableCell>{invoice.due_date?.slice(0, 10) || 'Not set'}</TableCell>
                      <TableCell>
                        <Badge variant="outline" className={getStatusColor(invoice.status)}>
                          {invoice.status === 'sent' && invoice.metadata?.billing_review?.state === 'reviewed' ? 'REVIEWED' : invoice.status === 'sent' ? 'BILLING REVIEW' : invoice.status.toUpperCase()}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-right">
                        <Button variant="ghost" size="sm" onClick={() => setSelected(invoice)} aria-label={`Review ${invoice.invoice_number}`}>View / Review</Button>
                        {canReview && (
                          <Button aria-label={`Archive ${invoice.invoice_number}`} variant="ghost" size="sm" className="text-red-600 hover:text-red-700 hover:bg-red-50 ml-2" onClick={() => handleDelete(invoice.id)}>
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                  {invoicesData?.data?.data?.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={6} className="h-24 text-center">No invoices found.</TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>
      <div className="flex justify-between items-center gap-3"><Button variant="outline" disabled={page <= 1 || isLoading} onClick={() => setPage(p => p-1)}>Previous</Button><span>Page {page} of {invoicesData?.data.last_page || 1}</span><Button variant="outline" disabled={isLoading || isError || !invoicesData || page >= invoicesData.data.last_page} onClick={() => setPage(p => p+1)}>Next</Button></div>
      <Dialog open={!!selected} onOpenChange={open => { if (!open) setSelected(null) }}><DialogContent className="max-h-[85vh] overflow-y-auto"><DialogHeader><DialogTitle>{selected?.invoice_number}</DialogTitle><DialogDescription>Internal review only. This does not submit an insurance claim or record a payment.</DialogDescription></DialogHeader>{selected && <InvoiceReview key={selected.id} invoice={selected} canReview={canReview} onSaved={() => setSelected(null)} />}</DialogContent></Dialog>
    </div>
  )
}


function InvoiceReview({invoice, canReview, onSaved}: {invoice: Invoice; canReview: boolean; onSaved: () => void}) {
  const [note, setNote] = useState('')
  const [error, setError] = useState('')
  const [review, {isLoading}] = useReviewInvoiceMutation()
  async function submit(action: 'reviewed' | 'return') {
    setError('')
    if (!note.trim()) { setError('Enter a review note explaining your decision.'); return }
    try { await review({id: invoice.id, action, note: note.trim()}).unwrap(); onSaved() }
    catch (err: any) { setError(err.data?.message || 'Could not save the review. Please try again.') }
  }
  return <div className="space-y-4">
    <dl className="space-y-2 break-words">{Object.entries({Patient: invoice.metadata?.patient_name, Case: invoice.case?.title, Amount: `$${Number(invoice.amount).toFixed(2)}`, 'Service date': invoice.metadata?.service_date, Payer: invoice.metadata?.payer, 'CPT codes': invoice.metadata?.cpt_codes, 'Diagnosis codes': invoice.metadata?.diagnosis_codes, Notes: invoice.metadata?.notes || invoice.notes}).map(([label,value]) => <div key={label}><dt className="text-sm text-muted-foreground">{label}</dt><dd>{value || 'Not recorded'}</dd></div>)}</dl>
    {invoice.metadata?.billing_review && <p>Last review: {invoice.metadata.billing_review.state} — {invoice.metadata.billing_review.note}</p>}
    {canReview && invoice.status === 'sent' && invoice.metadata?.billing_review?.state !== 'reviewed' && <><Label htmlFor="review-note">Review note</Label><Input id="review-note" value={note} onChange={e => setNote(e.target.value)} disabled={isLoading} />{error && <p role="alert">{error}</p>}<div className="flex flex-wrap gap-2"><Button disabled={isLoading} onClick={() => submit('reviewed')}>Mark reviewed</Button><Button variant="outline" disabled={isLoading} onClick={() => submit('return')}>Return for correction</Button></div></>}
  </div>
}
