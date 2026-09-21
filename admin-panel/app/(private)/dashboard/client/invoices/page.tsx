"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Skeleton } from "@/components/ui/skeleton"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from "@/components/ui/dialog"
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/components/ui/select"
import { FileText, Search, DollarSign, CreditCard, Download, Loader2, ExternalLink } from "lucide-react"
import { useGetClientInvoicesQuery, useCreateClientPaymentMutation } from "@/store/api/billingApiSlice"
import { useToast } from "@/hooks/use-toast"
import { cn } from "@/lib/utils"
import Link from "next/link"

const statusColors: Record<string, string> = {
  paid: "bg-emerald-100 text-emerald-700 border-emerald-200",
  sent: "bg-blue-100 text-blue-700 border-blue-200",
  denied: "bg-red-100 text-red-700 border-red-200",
  draft: "bg-slate-100 text-slate-600 border-slate-200",
  voided: "bg-slate-100 text-slate-500 border-slate-200",
}

export default function ClientInvoicesPage() {
  const { toast } = useToast()
  const [searchTerm, setSearchTerm] = useState("")
  const [payModalOpen, setPayModalOpen] = useState(false)
  const [selectedInvoice, setSelectedInvoice] = useState<any>(null)
  const [paymentMethod, setPaymentMethod] = useState("credit_card")
  const [paymentAmount, setPaymentAmount] = useState("")

  const { data: invoicesData, isLoading } = useGetClientInvoicesQuery({ search: searchTerm })
  const [createPayment, { isLoading: isPaying }] = useCreateClientPaymentMutation()

  const invoices = invoicesData?.data?.data || []

  const openPayModal = (invoice: any) => {
    setSelectedInvoice(invoice)
    setPaymentAmount(String(invoice.amount))
    setPayModalOpen(true)
  }

  const handlePay = async () => {
    if (!selectedInvoice || !paymentAmount) return

    try {
      await createPayment({
        invoice_id: selectedInvoice.id,
        amount: Number(paymentAmount),
        payment_method: paymentMethod,
        payment_date: new Date().toISOString().split("T")[0],
      }).unwrap()

      toast({ title: "Payment Successful", description: `Payment of $${Number(paymentAmount).toFixed(2)} recorded.` })
      setPayModalOpen(false)
      setSelectedInvoice(null)
    } catch (err: any) {
      toast({
        title: "Payment Failed",
        description: err.data?.message || "An error occurred processing your payment.",
        variant: "destructive",
      })
    }
  }

  return (
    <div className="space-y-6 animate-in fade-in duration-500">
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-3">
            <DollarSign className="h-8 w-8 text-primary" />
            My Invoices
          </h1>
          <p className="text-muted-foreground mt-1">
            View billing invoices and make payments for your legal and medical services.
          </p>
        </div>
      </div>

      <Card className="bg-card/80 backdrop-blur-sm border-border/50">
        <CardHeader>
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Search invoices..."
              className="pl-10"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-4">
              {[1, 2, 3, 4].map((i) => <Skeleton key={i} className="h-14 w-full" />)}
            </div>
          ) : invoices.length === 0 ? (
            <div className="text-center py-16">
              <FileText className="h-12 w-12 mx-auto text-slate-300 mb-4" />
              <h3 className="text-lg font-semibold text-slate-600 dark:text-slate-300">No invoices found</h3>
              <p className="text-sm text-slate-400 mt-1">Your billing invoices will appear here.</p>
            </div>
          ) : (
            <div className="rounded-md border">
              <Table>
                <TableHeader className="bg-muted/50">
                  <TableRow>
                    <TableHead>Invoice #</TableHead>
                    <TableHead>Case</TableHead>
                    <TableHead>Amount</TableHead>
                    <TableHead>Due Date</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {invoices.map((invoice: any) => (
                    <TableRow key={invoice.id} className="hover:bg-muted/30">
                      <TableCell className="font-bold">{invoice.invoice_number}</TableCell>
                      <TableCell className="max-w-[200px] truncate text-muted-foreground">
                        {invoice.case?.title || "N/A"}
                      </TableCell>
                      <TableCell className="font-black">${Number(invoice.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}</TableCell>
                      <TableCell className="text-muted-foreground">
                        {invoice.due_date ? new Date(invoice.due_date).toLocaleDateString() : "N/A"}
                      </TableCell>
                      <TableCell>
                        <Badge variant="outline" className={cn("uppercase text-[10px] font-bold", statusColors[invoice.status] || "")}>
                          {invoice.status}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-right">
                        <div className="flex items-center justify-end gap-2">
                          <Button variant="ghost" size="sm" asChild>
                            <Link href={`/dashboard/client/invoices/${invoice.id}`}>
                              <ExternalLink className="h-3 w-3 mr-1" /> View
                            </Link>
                          </Button>
                          {invoice.status !== "paid" && invoice.status !== "voided" && (
                            <Button
                              size="sm"
                              className="bg-emerald-600 hover:bg-emerald-700 shadow-lg shadow-emerald-600/20"
                              onClick={() => openPayModal(invoice)}
                            >
                              <CreditCard className="h-3 w-3 mr-1" /> Pay Now
                            </Button>
                          )}
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Payment Modal */}
      <Dialog open={payModalOpen} onOpenChange={setPayModalOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Make Payment</DialogTitle>
            <DialogDescription>
              Pay invoice {selectedInvoice?.invoice_number} — ${Number(selectedInvoice?.amount || 0).toFixed(2)}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label>Payment Amount ($)</Label>
              <Input
                type="number"
                step="0.01"
                placeholder="0.00"
                value={paymentAmount}
                onChange={(e) => setPaymentAmount(e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label>Payment Method</Label>
              <Select value={paymentMethod} onValueChange={setPaymentMethod}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="credit_card">Credit Card</SelectItem>
                  <SelectItem value="debit_card">Debit Card</SelectItem>
                  <SelectItem value="bank_transfer">Bank Transfer</SelectItem>
                  <SelectItem value="check">Check</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setPayModalOpen(false)}>Cancel</Button>
            <Button
              className="bg-emerald-600 hover:bg-emerald-700"
              onClick={handlePay}
              disabled={isPaying || !paymentAmount}
            >
              {isPaying ? <><Loader2 className="h-4 w-4 mr-2 animate-spin" /> Processing...</> : <>Confirm Payment</>}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
