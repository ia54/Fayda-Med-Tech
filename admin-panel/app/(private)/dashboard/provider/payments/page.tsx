"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Badge } from "@/components/ui/badge"
import { Search, DollarSign, Calendar, AlertCircle, Loader2 } from "lucide-react"
import { useGetPaymentsQuery } from "@/store/api/billingApiSlice"
import { Skeleton } from "@/components/ui/skeleton"

export default function PaymentsPage() {
  const [searchTerm, setSearchTerm] = useState("")

  // Fetch real payment data
  const { data: paymentsData, isLoading } = useGetPaymentsQuery({
    search: searchTerm
  })

  const payments = paymentsData?.data?.data || []

  // Mock aging buckets for now, until backend provides it
  const agingBuckets = [
    { range: "0-30 days", count: payments.filter((p:any) => !p.payment_date).length, amount: "$0.00", color: "bg-emerald-100 text-emerald-800" },
    { range: "31-60 days", count: 0, amount: "$0.00", color: "bg-amber-100 text-amber-800" },
    { range: "61-90 days", count: 0, amount: "$0.00", color: "bg-orange-100 text-orange-800" },
    { range: "90+ days", count: 0, amount: "$0.00", color: "bg-red-100 text-red-800" },
  ]

  const getStatusColor = (status: string) => {
    switch (status.toLowerCase()) {
      case "posted":
      case "paid":
        return "bg-emerald-100 text-emerald-800 border-emerald-200"
      case "pending":
        return "bg-amber-100 text-amber-800 border-amber-200"
      case "outstanding":
      case "failed":
        return "bg-red-100 text-red-800 border-red-200"
      default:
        return "bg-slate-100 text-slate-800 border-slate-200"
    }
  }

  return (
    <div className="space-y-8">
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 className="text-3xl font-bold text-primary flex items-center gap-2">
            <DollarSign className="h-8 w-8 text-primary" />
            Payments & AR Management
          </h1>
          <p className="text-muted-foreground">Track clinical reimbursements and accounts receivable aging</p>
        </div>
        <Button className="bg-primary hover:bg-primary/90">
          <DollarSign className="h-4 w-4 mr-2" />
          Post New Payment
        </Button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        {agingBuckets.map((bucket, index) => (
          <Card key={index} className="bg-card/50 backdrop-blur-sm border-border/50 hover:shadow-md transition-all">
            <CardContent className="p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-xs font-medium text-muted-foreground uppercase tracking-wider">{bucket.range}</p>
                  <p className="text-2xl font-bold text-primary">{bucket.count}</p>
                  <p className="text-sm text-emerald-600 font-medium">{bucket.amount}</p>
                </div>
                <Badge variant="outline" className={`${bucket.color} border-none`}>{bucket.count} Active</Badge>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      <Card className="bg-card/50 backdrop-blur-sm border-border/50">
        <CardHeader>
          <CardTitle className="text-primary">Payment Tracking History</CardTitle>
          <CardDescription>Comprehensive audit log of all clinical payments and electronic transfers</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="flex items-center space-x-2 mb-6">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search by claim ID, patient, or check number..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-10 bg-white/50"
              />
            </div>
          </div>

          <div className="space-y-4">
            {isLoading ? (
              <div className="space-y-4">
                {[1, 2, 3].map(i => <Skeleton key={i} className="h-24 w-full rounded-xl" />)}
              </div>
            ) : (
              payments.map((payment: any) => (
                <Card key={payment.id} className="hover:shadow-md transition-all border-border/40 bg-white/40 backdrop-blur-none group">
                  <CardContent className="p-5">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center space-x-4">
                        <div className="p-3 bg-primary/10 rounded-xl group-hover:bg-primary/20 transition-colors">
                          <DollarSign className="h-5 w-5 text-primary" />
                        </div>
                        <div>
                          <h3 className="font-bold text-emerald-950 dark:text-white flex items-center gap-2">
                            {payment.invoice?.invoice_number || `PMT-${payment.id}`}
                            <Badge variant="secondary" className="text-[10px] py-0">#{payment.id}</Badge>
                          </h3>
                          <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground mt-1">
                            <span className="font-medium text-slate-700 dark:text-slate-300">Patient: {payment.invoice?.case?.title || 'General Patient'}</span>
                            <span className="hidden sm:inline">•</span>
                            <span>Method: {payment.payment_method?.toUpperCase()}</span>
                            <span className="hidden sm:inline">•</span>
                            <span className="font-bold text-emerald-600">${Number(payment.amount).toLocaleString()}</span>
                          </div>
                          <div className="flex items-center gap-3 text-xs text-muted-foreground mt-2">
                            <Calendar className="h-3 w-3" />
                            <span>Processed: {new Date(payment.payment_date).toLocaleDateString()}</span>
                            {payment.transaction_id && (
                              <>
                                <span className="hidden sm:inline">•</span>
                                <span className="font-mono">Ref: {payment.transaction_id}</span>
                              </>
                            )}
                          </div>
                        </div>
                      </div>
                      <div className="flex flex-col items-end gap-2">
                        <Badge variant="outline" className={getStatusColor('paid')}>SUCCESS</Badge>
                        <Button variant="ghost" size="sm" className="h-8 text-xs">View Receipt</Button>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              ))
            )}
            {!isLoading && payments.length === 0 && (
              <div className="text-center py-20 bg-muted/20 rounded-xl border-dashed border-2 border-border/50">
                <AlertCircle className="h-8 w-8 text-muted-foreground mx-auto mb-3 opacity-50" />
                <p className="text-muted-foreground font-medium italic">No payment records found matching your search.</p>
              </div>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
