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
  const [searchTerm, setSearchTerm] = useState("")
  const [isModalOpen, setIsModalOpen] = useState(false)

  // Form State
  const [selectedInvoice, setSelectedInvoice] = useState("")
  const [amount, setAmount] = useState("")
  const [method, setMethod] = useState("check")
  const [transactionId, setTransactionId] = useState("")
  const [paymentDate, setPaymentDate] = useState(new Date().toISOString().split('T')[0])

  const { data: paymentsData, isLoading } = useGetPaymentsQuery({ search: searchTerm })
  const { data: invoicesData } = useGetInvoicesQuery({ status: "sent,denied" })
  const [recordPayment, { isLoading: isRecording }] = useCreatePaymentMutation()

  const handleRecordPayment = async () => {
    if (!selectedInvoice || !amount || !paymentDate) {
      toast({ title: "Validation Error", description: "All fields are required", variant: "destructive" })
      return
    }

    try {
      await recordPayment({
        invoice_id: Number(selectedInvoice),
        amount: Number(amount),
        payment_method: method,
        transaction_id: transactionId,
        payment_date: paymentDate
      }).unwrap()

      setIsModalOpen(false)
      setSelectedInvoice("")
      setAmount("")
      setTransactionId("")
      toast({ title: "Success", description: "Payment recorded successfully" })
    } catch (err: any) {
      toast({ title: "Error", description: err.data?.message || "Failed to record payment", variant: "destructive" })
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
          <Button variant="outline">
            <Download className="h-4 w-4 mr-2" />
            Export CSV
          </Button>
          
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
                <DialogDescription>Apply a payment to an existing open invoice.</DialogDescription>
              </DialogHeader>
              <div className="space-y-4 py-4">
                <div className="space-y-2">
                  <Label>Select Invoice</Label>
                  <Select value={selectedInvoice} onValueChange={setSelectedInvoice}>
                    <SelectTrigger>
                      <SelectValue placeholder="Choose an invoice" />
                    </SelectTrigger>
                    <SelectContent>
                      {invoicesData?.data?.data?.map((inv: any) => (
                        <SelectItem key={inv.id} value={String(inv.id)}>
                          {inv.invoice_number} - {inv.case?.title} (${inv.amount})
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label>Amount ($)</Label>
                    <Input type="number" placeholder="0.00" value={amount} onChange={(e) => setAmount(e.target.value)} />
                  </div>
                  <div className="space-y-2">
                    <Label>Payment Date</Label>
                    <Input type="date" value={paymentDate} onChange={(e) => setPaymentDate(e.target.value)} />
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
                  <Label>Transaction ID / Check #</Label>
                  <Input placeholder="TXN-123..." value={transactionId} onChange={(e) => setTransactionId(e.target.value)} />
                </div>
              </div>
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
                onChange={(e) => setSearchTerm(e.target.value)}
              />
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {isLoading ? (
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
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {paymentsData?.data?.data?.map((payment) => (
                    <TableRow key={payment.id}>
                      <TableCell>{payment.payment_date}</TableCell>
                      <TableCell className="font-medium">{payment.invoice?.invoice_number || "N/A"}</TableCell>
                      <TableCell className="text-emerald-600 font-semibold">${Number(payment.amount).toLocaleString()}</TableCell>
                      <TableCell>
                        <Badge variant="secondary" className="capitalize">
                          {payment.payment_method.replace('_', ' ')}
                        </Badge>
                      </TableCell>
                      <TableCell className="font-mono text-xs">{payment.transaction_id || "-"}</TableCell>
                      <TableCell className="text-right">
                        <Button variant="ghost" size="sm">Details</Button>
                      </TableCell>
                    </TableRow>
                  ))}
                  {paymentsData?.data?.data?.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={6} className="h-24 text-center">No payments recorded yet.</TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
