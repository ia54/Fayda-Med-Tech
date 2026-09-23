"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { DollarSign, Search, Plus, Loader2, Download } from "lucide-react"
import { Input } from "@/components/ui/input"
import { useGetPaymentsQuery, useGetInvoicesQuery, useCreatePaymentMutation } from "@/store/api/billingApiSlice"
import { Skeleton } from "@/components/ui/skeleton"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { useToast } from "@/hooks/use-toast"

export default function PaymentsPage() {
  const { toast } = useToast()
  const [error, setError] = useState("")
  const [page, setPage] = useState(1)
  const [invoiceSearch, setInvoiceSearch] = useState("")
  const [searchTerm, setSearchTerm] = useState("")
  const [isModalOpen, setIsModalOpen] = useState(false)

  // Form State
  const [selectedInvoice, setSelectedInvoice] = useState("")
  const [amount, setAmount] = useState("")
  const [method, setMethod] = useState("check")
  const [transactionId, setTransactionId] = useState("")
  const [paymentDate, setPaymentDate] = useState(new Date().toISOString().split('T')[0])

  const { currentData: paymentsData, isFetching: isLoading, isError, refetch } = useGetPaymentsQuery({ search: searchTerm, page })
  const { data: invoicesData, isFetching: invoicesLoading, isError: invoicesFailed, refetch: retryInvoices } = useGetInvoicesQuery({ status: "sent,denied", search: invoiceSearch, per_page: 50 })
  const [recordPayment, { isLoading: isRecording }] = useCreatePaymentMutation()

  const handleRecordPayment = async () => {
    setError("")
    if (!selectedInvoice || !/^\d+(\.\d{1,2})?$/.test(amount) || Number(amount) <= 0 || !paymentDate || !transactionId.trim()) {
      setError("Select an invoice and enter a positive amount, payment date and reference.")
      return
    }

    try {
      await recordPayment({
        invoice_id: Number(selectedInvoice),
        amount: Number(amount),
        payment_method: method,
        transaction_id: transactionId.trim(),
        payment_date: paymentDate
      }).unwrap()

      setIsModalOpen(false)
      setSelectedInvoice("")
      setAmount("")
      setTransactionId("")
      toast({ title: "Success", description: "Payment recorded successfully" })
    } catch (err: any) {
      setError(Object.values(err.data?.errors || {}).flat().join(" ") || err.data?.message || "Failed to record payment")
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 className="text-3xl font-bold text-primary">Payments</h1>
          <p className="text-muted-foreground">Track and reconcile all incoming payments</p>
        </div>
        <div className="flex gap-2">
          <Dialog open={isModalOpen} onOpenChange={setIsModalOpen}>
            <DialogTrigger asChild>
              <Button>
                <Plus className="h-4 w-4 mr-2" />
                Record Payment
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Record New Payment</DialogTitle>
                <DialogDescription>Record money already received outside this platform. This does not charge a card or transfer funds.</DialogDescription>
              </DialogHeader>
              <div className="space-y-4 py-4">
                <div className="space-y-2">
                  <Label htmlFor="payment-invoice-search">Find an invoice</Label>
                  <Input id="payment-invoice-search" value={invoiceSearch} onChange={e => setInvoiceSearch(e.target.value)} placeholder="Invoice number, patient or case" />
                  <Label htmlFor="payment-invoice">Select Invoice</Label>
                  <select id="payment-invoice" className="w-full rounded-md border bg-background p-2" value={selectedInvoice} onChange={e => setSelectedInvoice(e.target.value)} disabled={invoicesLoading || invoicesFailed}>
                    <option value="">Choose an open invoice</option>
                    {selectedInvoice && !invoicesData?.data.data.some(i => String(i.id) === selectedInvoice) && <option value={selectedInvoice}>Selected invoice #{selectedInvoice}</option>}
                    {invoicesData?.data.data.map(inv => <option key={inv.id} value={inv.id}>{inv.invoice_number} — ${(Number(inv.amount) - Number(inv.total_paid || 0)).toFixed(2)} outstanding</option>)}
                  </select>
                  {invoicesFailed && <p role="alert">Could not load invoices. <Button onClick={() => retryInvoices()}>Retry</Button></p>}
                  {!invoicesLoading && !invoicesFailed && invoicesData?.data.data.length === 0 && <p>No matching open invoices.</p>}
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label htmlFor="payment-amount">Amount ($)</Label>
                    <Input id="payment-amount" type="number" step="0.01" placeholder="0.00" value={amount} onChange={(e) => setAmount(e.target.value)} />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="payment-date">Payment Date</Label>
                    <Input id="payment-date" type="date" value={paymentDate} onChange={(e) => setPaymentDate(e.target.value)} />
                  </div>
                </div>
                <div className="space-y-2">
                  <Label>Payment Method</Label>
                  <Select value={method} onValueChange={setMethod}>
                    <SelectTrigger>
                      <SelectValue placeholder="Method" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="check">Check</SelectItem>
                      <SelectItem value="wire">Wire Transfer</SelectItem>
                      <SelectItem value="credit_card">Credit Card</SelectItem>
                      <SelectItem value="cash">Cash</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="payment-reference">Receipt / Transaction ID / Check #</Label>
                  <Input id="payment-reference" placeholder="TXN-123..." value={transactionId} onChange={(e) => setTransactionId(e.target.value)} />
                </div>
              </div>
              {error && <p role="alert" className="text-destructive">{error}</p>}
              <DialogFooter>
                <Button variant="outline" onClick={() => setIsModalOpen(false)}>Cancel</Button>
                <Button onClick={handleRecordPayment} disabled={isRecording}>
                  {isRecording && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                  Record Payment
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </div>
      </div>

      <Card className="bg-card/80 backdrop-blur-sm">
        <CardHeader>
          <div className="flex flex-col md:flex-row md:items-center gap-4">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search by transaction ID..."
                className="pl-10"
                value={searchTerm}
                onChange={(e) => { setSearchTerm(e.target.value); setPage(1) }}
              />
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {isError ? <p role="alert">Could not load payments. <Button onClick={() => refetch()}>Try again</Button></p> : isLoading ? (
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
                    <TableHead>Date</TableHead>
                    <TableHead>Invoice #</TableHead>
                    <TableHead>Amount</TableHead>
                    <TableHead>Method</TableHead>
                    <TableHead>Transaction ID</TableHead>

                  </TableRow>
                </TableHeader>
                <TableBody>
                  {paymentsData?.data?.data?.map((payment) => (
                    <TableRow key={payment.id}>
                      <TableCell>{payment.payment_date.slice(0, 10)}</TableCell>
                      <TableCell className="font-medium">{payment.invoice?.invoice_number || "N/A"}</TableCell>
                      <TableCell className="text-emerald-600 font-semibold">${Number(payment.amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</TableCell>
                      <TableCell>
                        <Badge variant="secondary" className="capitalize">
                          {payment.payment_method.replace('_', ' ')}
                        </Badge>
                      </TableCell>
                      <TableCell className="font-mono text-xs">{payment.transaction_id || "-"}</TableCell>

                    </TableRow>
                  ))}
                  {paymentsData?.data?.data?.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={5} className="h-24 text-center">No payments recorded yet.</TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>
      <div className="flex justify-between items-center"><Button variant="outline" disabled={page <= 1 || isLoading} onClick={() => setPage(p => p-1)}>Previous</Button><span>Page {page} of {paymentsData?.data.last_page || 1}</span><Button variant="outline" disabled={isLoading || isError || !paymentsData || page >= paymentsData.data.last_page} onClick={() => setPage(p => p+1)}>Next</Button></div>
    </div>
  )
}
