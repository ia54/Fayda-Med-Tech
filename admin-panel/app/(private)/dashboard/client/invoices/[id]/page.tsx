"use client"

import { useParams } from "next/navigation"
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { ArrowLeft, FileText, DollarSign, CreditCard, Calendar, CheckCircle2 } from "lucide-react"
import { useGetClientInvoiceDetailQuery } from "@/store/api/billingApiSlice"
import Link from "next/link"
import { cn } from "@/lib/utils"

const statusColors: Record<string, string> = {
  paid: "bg-emerald-100 text-emerald-700 border-emerald-200",
  sent: "bg-blue-100 text-blue-700 border-blue-200",
  denied: "bg-red-100 text-red-700 border-red-200",
  draft: "bg-slate-100 text-slate-600 border-slate-200",
  voided: "bg-slate-100 text-slate-500 border-slate-200",
}

export default function ClientInvoiceDetailPage() {
  const params = useParams()
  const invoiceId = Number(params.id)
  const { data, isLoading, error } = useGetClientInvoiceDetailQuery(invoiceId)

  if (isLoading) {
    return (
      <div className="space-y-6 max-w-3xl mx-auto">
        <Skeleton className="h-10 w-48" />
        <Skeleton className="h-64 w-full rounded-xl" />
      </div>
    )
  }

  const invoice = data?.data
  if (error || !invoice) {
    return (
      <div className="flex flex-col items-center justify-center py-20">
        <FileText className="h-12 w-12 text-slate-300 mb-4" />
        <h3 className="text-lg font-semibold text-slate-600">Invoice not found</h3>
        <Button variant="outline" className="mt-4" asChild>
          <Link href="/dashboard/client/invoices"><ArrowLeft className="h-4 w-4 mr-2" /> Back</Link>
        </Button>
      </div>
    )
  }

  const payments = invoice.payments || []
  const totalPaid = payments.reduce((sum: number, p: any) => sum + Number(p.amount), 0)
  const balance = Number(invoice.amount) - totalPaid

  return (
    <div className="space-y-6 animate-in fade-in duration-500 max-w-3xl mx-auto">
      <div className="flex items-center gap-4">
        <Button variant="ghost" size="icon" asChild>
          <Link href="/dashboard/client/invoices"><ArrowLeft className="h-5 w-5" /></Link>
        </Button>
        <div className="flex-1">
          <h1 className="text-2xl font-bold">{invoice.invoice_number}</h1>
          <p className="text-sm text-muted-foreground">{invoice.case?.title || "N/A"}</p>
        </div>
        <Badge variant="outline" className={cn("text-sm px-4 py-1.5", statusColors[invoice.status] || "")}>
          {invoice.status?.toUpperCase()}
        </Badge>
      </div>

      <Card className="shadow-lg border-0 overflow-hidden">
        <div className="h-1 w-full bg-gradient-to-r from-emerald-400 to-blue-500" />
        <CardContent className="pt-6">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
            <div>
              <p className="text-xs text-muted-foreground uppercase tracking-wider">Amount</p>
              <p className="text-2xl font-black mt-1">${Number(invoice.amount).toFixed(2)}</p>
            </div>
            <div>
              <p className="text-xs text-muted-foreground uppercase tracking-wider">Paid</p>
              <p className="text-2xl font-black text-emerald-600 mt-1">${totalPaid.toFixed(2)}</p>
            </div>
            <div>
              <p className="text-xs text-muted-foreground uppercase tracking-wider">Balance</p>
              <p className={cn("text-2xl font-black mt-1", balance > 0 ? "text-amber-600" : "text-emerald-600")}>${balance.toFixed(2)}</p>
            </div>
            <div>
              <p className="text-xs text-muted-foreground uppercase tracking-wider">Due Date</p>
              <p className="text-lg font-bold mt-1 flex items-center gap-1">
                <Calendar className="h-4 w-4" />
                {invoice.due_date ? new Date(invoice.due_date).toLocaleDateString() : "N/A"}
              </p>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-lg flex items-center gap-2">
            <CreditCard className="h-5 w-5 text-primary" /> Payment History
          </CardTitle>
        </CardHeader>
        <CardContent>
          {payments.length === 0 ? (
            <p className="text-center py-8 text-sm text-muted-foreground">No payments recorded yet.</p>
          ) : (
            <div className="space-y-3">
              {payments.map((p: any) => (
                <div key={p.id} className="flex items-center justify-between p-4 border rounded-lg">
                  <div className="flex items-center gap-3">
                    <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                    <div>
                      <p className="text-sm font-bold">${Number(p.amount).toFixed(2)}</p>
                      <p className="text-xs text-muted-foreground capitalize">{p.payment_method?.replace("_", " ")}</p>
                    </div>
                  </div>
                  <p className="text-sm text-muted-foreground">{p.payment_date ? new Date(p.payment_date).toLocaleDateString() : ""}</p>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
