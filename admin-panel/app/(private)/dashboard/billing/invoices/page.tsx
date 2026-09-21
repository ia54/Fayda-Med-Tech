"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { FileText, Plus, Search, Filter, Download, Trash2, Loader2 } from "lucide-react"
import { Input } from "@/components/ui/input"
import { useGetInvoicesQuery, useCreateInvoiceMutation, useDeleteInvoiceMutation } from "@/store/api/billingApiSlice"
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
  const [searchTerm, setSearchTerm] = useState("")
  const [isModalOpen, setIsModalOpen] = useState(false)
  
  // Form State
  const [selectedCase, setSelectedCase] = useState("")
  const [amount, setAmount] = useState("")
  const [dueDate, setDueDate] = useState("")

  const { data: invoicesData, isLoading } = useGetInvoicesQuery({ search: searchTerm })
  const { data: casesData } = useGetCasesQuery({})
  const [createInvoice, { isLoading: isCreating }] = useCreateInvoiceMutation()
  const [deleteInvoice] = useDeleteInvoiceMutation()
  const user = useSelector((state: RootState) => state.auth.user)
  const isClient = user?.role === 'client'

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
          <Button variant="outline">
            <Download className="h-4 w-4 mr-2" />
            Export
          </Button>
          
          {!isClient && (
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
                      <TableCell>{invoice.due_date}</TableCell>
                      <TableCell>
                        <Badge variant="outline" className={getStatusColor(invoice.status)}>
                          {invoice.status.toUpperCase()}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-right">
                        <Button variant="ghost" size="sm">View</Button>
                        {isClient && invoice.status !== 'paid' && (
                          <Button size="sm" className="bg-emerald-600 hover:bg-emerald-700 ml-2">Pay Now</Button>
                        )}
                        {!isClient && (
                          <Button variant="ghost" size="sm" className="text-red-600 hover:text-red-700 hover:bg-red-50 ml-2" onClick={() => handleDelete(invoice.id)}>
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
    </div>
  )
}
