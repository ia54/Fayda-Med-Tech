"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Skeleton } from "@/components/ui/skeleton"
import { CreditCard, DollarSign, CheckCircle2, Calendar } from "lucide-react"
import { useGetClientPaymentsQuery } from "@/store/api/billingApiSlice"

export default function ClientPaymentsPage() {
  const [page, setPage] = useState(1)
  const { currentData: data, isFetching: isLoading, isError, refetch } = useGetClientPaymentsQuery({page})
  const payments = data?.data?.data || []

  return (
    <div className="space-y-6 animate-in fade-in duration-500">
      <div>
        <h1 className="text-3xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-3">
          <CreditCard className="h-8 w-8 text-primary" />
          Payment History
        </h1>
        <p className="text-muted-foreground mt-1">View receipts and accounting corrections recorded against your cases.</p>
      </div>

      <Card className="bg-card/80 backdrop-blur-sm border-border/50">
        <CardContent className="pt-6">
          {isError ? <p role="alert">Could not load payment history. <Button onClick={() => refetch()}>Try again</Button></p> : isLoading ? (
            <div className="space-y-4">
              {[1, 2, 3].map((i) => <Skeleton key={i} className="h-20 w-full rounded-xl" />)}
            </div>
          ) : payments.length === 0 ? (
            <div className="text-center py-16">
              <DollarSign className="h-12 w-12 mx-auto text-slate-300 mb-4" />
              <h3 className="text-lg font-semibold text-slate-600 dark:text-slate-300">No payments found</h3>
              <p className="text-sm text-slate-400 mt-1">Recorded receipts will appear here after your billing team verifies them.</p>
            </div>
          ) : (
            <div className="space-y-3">
              {payments.map((payment: any) => (
                <div key={payment.id} className="flex flex-col sm:flex-row gap-3 sm:items-center justify-between p-5 border border-slate-100 dark:border-slate-800 rounded-xl bg-white dark:bg-slate-900/50 hover:shadow-md transition-all">
                  <div className="flex items-center gap-4">
                    <div className="p-2.5 rounded-full bg-emerald-50 dark:bg-emerald-950">
                      <CheckCircle2 className="h-5 w-5 text-emerald-600" />
                    </div>
                    <div>
                      <p className="text-sm font-black text-slate-900 dark:text-white">
                        ${Number(payment.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                      </p>
                      <p className="text-xs text-muted-foreground capitalize mt-0.5">
                        {payment.payment_method?.replace("_", " ")} · Invoice #{payment.invoice?.invoice_number || "N/A"}
                      </p>
                      <p className="text-sm">{payment.reversal_of_id ? "Correction entry (not a refund)" : payment.reversal ? "Receipt reversed" : "Recorded receipt"}</p>
                      {payment.invoice?.case?.title && (
                        <p className="text-xs text-muted-foreground mt-0.5">Case: {payment.invoice.case.title}</p>
                      )}
                    </div>
                  </div>
                  <div className="text-right break-all">
                    <p className="text-sm font-medium text-slate-700 dark:text-slate-300 flex items-center gap-1 justify-end">
                      <Calendar className="h-3 w-3" />
                      {payment.payment_date ? new Date(payment.payment_date).toLocaleDateString(undefined, { timeZone: "UTC" }) : "N/A"}
                    </p>
                    {payment.transaction_id && (
                      <p className="text-xs text-muted-foreground mt-0.5">Ref: {payment.transaction_id}</p>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
      <div className="flex items-center justify-between"><Button variant="outline" disabled={page <= 1 || isLoading} onClick={() => setPage(p => p-1)}>Previous</Button><span>Page {page} of {data?.data.last_page || 1}</span><Button variant="outline" disabled={isLoading || isError || !data || page >= data.data.last_page} onClick={() => setPage(p => p+1)}>Next</Button></div>
    </div>
  )
}
