"use client"

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { AlertTriangle, ArrowRight, Clock, CheckCircle2, Inbox, RefreshCw } from "lucide-react"
import { useGetInvoicesQuery } from "@/store/api/billingApiSlice"
import { Skeleton } from "@/components/ui/skeleton"
import Link from "next/link"
import { useToast } from "@/hooks/use-toast"

export default function WorkQueuePage() {
  const { data: queueData, isLoading, refetch, isFetching } = useGetInvoicesQuery({ status: "denied,draft" })
  const { toast } = useToast()

  const handleRefetch = () => {
    refetch()
    toast({
      title: "Refreshing",
      description: "Checking for new priority items...",
    })
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-primary tracking-tight">Work Queue</h1>
          <p className="text-muted-foreground">High priority items requiring billing intervention (Drafts & Denials)</p>
        </div>
        <Button variant="outline" size="sm" onClick={handleRefetch} disabled={isFetching}>
          <RefreshCw className={`h-4 w-4 mr-2 ${isFetching ? 'animate-spin' : ''}`} />
          Refresh
        </Button>
      </div>

      {isLoading ? (
        <div className="grid gap-4">
          {[1, 2, 3].map((i) => (
            <Skeleton key={i} className="h-32 w-full rounded-xl" />
          ))}
        </div>
      ) : (
        <div className="grid gap-4">
          {queueData?.data?.data?.map((item: any) => (
            <Card key={item.id} className="hover:shadow-lg transition-all duration-300 border-l-4 border-l-emerald-500 bg-white/50 backdrop-blur-sm group">
              <CardContent className="p-6">
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-6">
                  <div className="space-y-2 flex-1">
                    <div className="flex items-center gap-2">
                      <Badge variant="outline" className={item.status === 'denied' ? 'bg-rose-50 text-rose-700 border-rose-200 font-bold' : 'bg-amber-50 text-amber-700 border-amber-200 font-bold'}>
                        {item.status.toUpperCase()}
                      </Badge>
                      <span className="text-sm font-mono text-muted-foreground">{item.invoice_number}</span>
                    </div>
                    <h3 className="font-bold text-xl text-gray-900 group-hover:text-emerald-700 transition-colors">
                      {item.case?.title || "Unnamed Case"}
                    </h3>
                    <div className="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                      <span className="flex items-center gap-1.5 bg-slate-100 px-2 py-1 rounded">
                        <Clock className="h-3.5 w-3.5" /> Due: {item.due_date || "Not Set"}
                      </span>
                      <span className="flex items-center gap-1.5 font-bold text-emerald-700 bg-emerald-50 px-2 py-1 rounded">
                        Amount: ${Number(item.amount).toLocaleString()}
                      </span>
                    </div>
                  </div>
                  
                    <div className="flex items-center gap-3">
                    {item.status === 'denied' ? (
                      <Link href={`/dashboard/billing/appeals/create?invoice_id=${item.id}`}>
                        <Button className="bg-emerald-600 hover:bg-emerald-700 text-white shadow-md">
                          Start Appeal
                          <ArrowRight className="ml-2 h-4 w-4" />
                        </Button>
                      </Link>
                    ) : (
                      <Button className="bg-primary hover:bg-primary/90 text-white shadow-md">
                        Submit Claim
                        <CheckCircle2 className="ml-2 h-4 w-4" />
                      </Button>
                    )}
                    <Button variant="ghost" className="hover:bg-slate-100">View Details</Button>
                  </div>
                </div>
              </CardContent>
            </Card>
          ))}
          
          {(queueData?.data?.data?.length === 0 || !queueData?.data?.data) && (
            <Card className="p-16 flex flex-col items-center justify-center text-center bg-slate-50/50 border-dashed border-2">
              <div className="bg-white p-4 rounded-full shadow-sm mb-4">
                <Inbox className="h-10 w-10 text-slate-300" />
              </div>
              <h3 className="text-xl font-bold text-slate-900">Your Queue is Empty</h3>
              <p className="text-muted-foreground max-w-sm mt-2">
                Great job! There are currently no invoices with <span className="font-semibold text-rose-600">Denied</span> or <span className="font-semibold text-amber-600">Draft</span> status requiring your attention.
              </p>
              <div className="mt-8 p-4 bg-blue-50 border border-blue-100 rounded-lg text-left max-w-md">
                <p className="text-xs text-blue-800 font-medium flex items-center gap-2">
                  <AlertTriangle className="h-3.5 w-3.5" />
                  Testing Tip:
                </p>
                <p className="text-xs text-blue-700 mt-1 leading-relaxed">
                  To test this page, navigate to <Link href="/dashboard/billing" className="underline font-bold">Billing</Link> and create an invoice with status "Draft" or mark an existing one as "Denied".
                </p>
              </div>
            </Card>
          )}
        </div>
      )}
    </div>
  )
}
