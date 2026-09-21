"use client"

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { FileText, CheckCircle2, Clock, Upload, Scale, Activity, DollarSign, ExternalLink, PenTool, Bell, CreditCard, FolderOpen } from "lucide-react"
import { useGetClientStatsQuery } from "@/store/api/billingApiSlice"
import { Skeleton } from "@/components/ui/skeleton"
import { cn } from "@/lib/utils"
import Link from "next/link"

export default function ClientDashboard() {
  const { data: clientData, isLoading } = useGetClientStatsQuery()

  const user = clientData?.data?.user || { name: "Valued Client" }
  const caseSummary = clientData?.data?.case_summary
  const stats = clientData?.data?.stats || { pending_tasks: 0, pending_signatures: 0, total_documents: 0, billing_summary: { total: "$0.00", paid: "$0.00" } }
  const recentDocuments = clientData?.data?.recent_documents || []
  const recentInvoices = clientData?.data?.recent_invoices || []
  const allCases = clientData?.data?.all_cases || []

  if (isLoading) {
    return (
      <div className="space-y-8 p-6">
        <div className="space-y-2">
          <Skeleton className="h-10 w-64" />
          <Skeleton className="h-4 w-96" />
        </div>
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
          {[1, 2, 3, 4].map(i => <Skeleton key={i} className="h-32 w-full rounded-xl" />)}
        </div>
        <div className="grid gap-6 md:grid-cols-2">
          <Skeleton className="h-[400px] rounded-xl" />
          <Skeleton className="h-[400px] rounded-xl" />
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-8 animate-in fade-in duration-500">
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 className="text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight">Welcome back, {user?.name?.split(' ')[0] || "Client"}!</h1>
          <p className="text-slate-500 dark:text-slate-400 mt-2 text-lg">Your recovery and case status at a glance.</p>
        </div>
        <div className="flex items-center gap-3">
          <Badge variant="outline" className="px-4 py-1.5 text-sm font-semibold bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-800">
            Client Portal Verified
          </Badge>
        </div>
      </div>

      {/* Stats Quick View */}
      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-5">
        <Card className="bg-white/50 backdrop-blur-sm border-slate-200 dark:border-slate-800 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Case Status</CardTitle>
            <Activity className="h-5 w-5 text-emerald-500" />
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-black text-slate-900 dark:text-white">
              {caseSummary ? caseSummary.status : "No Active Case"}
            </div>
            <p className="text-xs text-slate-400 mt-2 font-medium flex items-center gap-1">
              <Clock className="h-3 w-3" /> Updated {caseSummary?.last_update || "recently"}
            </p>
          </CardContent>
        </Card>

        <Card className="bg-white/50 backdrop-blur-sm border-slate-200 dark:border-slate-800 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Pending Signatures</CardTitle>
            <PenTool className="h-5 w-5 text-amber-500" />
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-black text-slate-900 dark:text-white">{stats.pending_signatures || 0}</div>
            <p className="text-xs text-amber-600 dark:text-amber-400 mt-2 font-bold">
              <Button variant="link" className="p-0 h-auto text-xs text-amber-600 font-bold" asChild>
                <Link href="/dashboard/client/signatures">View pending →</Link>
              </Button>
            </p>
          </CardContent>
        </Card>

        <Card className="bg-white/50 backdrop-blur-sm border-slate-200 dark:border-slate-800 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Billing</CardTitle>
            <DollarSign className="h-5 w-5 text-blue-500" />
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-black text-slate-900 dark:text-white">{stats.billing_summary.total}</div>
            <p className="text-xs text-slate-400 mt-2 font-medium">Associated with care</p>
          </CardContent>
        </Card>

        <Card className="bg-white/50 backdrop-blur-sm border-slate-200 dark:border-slate-800 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Net Recovery</CardTitle>
            <Scale className="h-5 w-5 text-emerald-500" />
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-black text-slate-900 dark:text-white">{stats.billing_summary.paid}</div>
            <p className="text-xs text-emerald-600 dark:text-emerald-400 mt-2 font-bold">Recovered to date</p>
          </CardContent>
        </Card>

        <Card className="bg-white/50 backdrop-blur-sm border-slate-200 dark:border-slate-800 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Lien Totals</CardTitle>
            <Scale className="h-5 w-5 text-purple-500" />
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-black text-slate-900 dark:text-white">{stats.billing_summary.total_liens || "$0.00"}</div>
            <p className="text-xs text-slate-400 mt-2 font-medium">Pending medical liens</p>
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-8 lg:grid-cols-2">
        {/* Recent Documents Section */}
        <Card className="bg-card shadow-lg border-0 overflow-hidden group">
          <div className="h-1 w-full bg-gradient-to-r from-emerald-400 to-blue-500" />
          <CardHeader>
            <div className="flex items-center justify-between">
              <div>
                <CardTitle className="text-xl font-bold flex items-center gap-2">
                   <FileText className="h-5 w-5 text-primary" />
                   Recent Documents
                </CardTitle>
                <CardDescription>Files and legal documents for your review</CardDescription>
              </div>
              <Button variant="ghost" size="sm" asChild className="text-primary hover:bg-primary/5">
                <Link href="/dashboard/client/documents">View All</Link>
              </Button>
            </div>
          </CardHeader>
          <CardContent className="space-y-4">
            {recentDocuments.length > 0 ? recentDocuments.map((doc: any) => (
              <div key={doc.id} className="flex items-center justify-between p-4 border border-slate-100 dark:border-slate-800 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-900 transition-colors">
                <div className="flex items-center gap-4">
                  <div className={`p-2.5 rounded-lg ${doc.action_required ? 'bg-amber-100 text-amber-600' : 'bg-slate-100 text-slate-600'}`}>
                    <FileText className="h-5 w-5" />
                  </div>
                  <div>
                    <p className="text-sm font-bold text-slate-900 dark:text-white">{doc.name}</p>
                    <p className="text-xs text-slate-500">Uploaded {doc.date}</p>
                  </div>
                </div>
                {doc.action_required ? (
                  <Button size="sm" asChild className="bg-amber-600 hover:bg-amber-700 shadow-lg shadow-amber-600/20">
                    <Link href="/dashboard/client/signatures">Sign Now</Link>
                  </Button>
                ) : (
                  <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200">Verified</Badge>
                )}
              </div>
            )) : (
              <div className="text-center py-12 text-slate-400 italic">No recent documents found.</div>
            )}
          </CardContent>
        </Card>

        {/* My Active Cases Section */}
        <Card className="bg-card shadow-lg border-0 overflow-hidden group">
          <div className="h-1 w-full bg-gradient-to-r from-blue-400 to-purple-500" />
          <CardHeader>
            <div className="flex items-center justify-between">
              <div>
                <CardTitle className="text-xl font-bold flex items-center gap-2">
                   <Scale className="h-5 w-5 text-blue-500" />
                   Legal Case Portfolio
                </CardTitle>
                <CardDescription>Status tracking for your active litigation</CardDescription>
              </div>
              <Button variant="ghost" size="sm" asChild className="text-primary hover:bg-primary/5">
                <Link href="/dashboard/client/cases">View All</Link>
              </Button>
            </div>
          </CardHeader>
          <CardContent className="space-y-4">
            {allCases.length > 0 ? allCases.map((c: any) => (
              <div key={c.id} className="p-5 border border-slate-100 dark:border-slate-800 rounded-xl hover:shadow-md transition-all">
                <div className="flex items-center justify-between mb-3">
                  <h3 className="font-bold text-slate-900 dark:text-white">{c.title}</h3>
                  <Badge className="bg-emerald-600">{c.status}</Badge>
                </div>
                <div className="flex items-center justify-between text-sm">
                  <span className="text-slate-500 font-medium">Current Valuation</span>
                  <span className="font-black text-slate-900 dark:text-white">{c.value}</span>
                </div>
                <Button variant="ghost" size="sm" asChild className="w-full mt-4 text-xs font-bold border-t rounded-none group-hover:text-primary transition-colors">
                  <Link href={`/dashboard/client/cases/${c.id}`}>
                    Full Case Details <ExternalLink className="h-3 w-3 ml-2" />
                  </Link>
                </Button>
              </div>
            )) : (
              <div className="text-center py-12 text-slate-400 italic">No cases found in your portfolio.</div>
            )}
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-8 lg:grid-cols-3">
        {/* Recent Invoices Card */}
        <Card className="lg:col-span-2 bg-card shadow-lg border-0 overflow-hidden">
          <CardHeader>
            <div className="flex items-center justify-between">
              <div>
                <CardTitle className="text-xl font-bold flex items-center gap-2">
                  <DollarSign className="h-5 w-5 text-emerald-600" />
                  Recent Invoices
                </CardTitle>
                <CardDescription>Financial summary of your legal and medical services</CardDescription>
              </div>
              <Button variant="ghost" size="sm" asChild className="text-primary">
                <Link href="/dashboard/client/invoices">View All Invoices</Link>
              </Button>
            </div>
          </CardHeader>
          <CardContent>
            {recentInvoices.length > 0 ? (
              <div className="space-y-3">
                {recentInvoices.map((inv: any) => (
                  <div key={inv.id} className="flex items-center justify-between p-4 border border-slate-50 dark:border-slate-900 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-900 transition-colors">
                    <div className="flex items-center gap-4">
                      <div className="p-2 bg-slate-100 dark:bg-slate-800 rounded-lg">
                        <FileText className="h-4 w-4 text-slate-500" />
                      </div>
                      <div>
                        <p className="text-sm font-bold text-slate-900 dark:text-white">{inv.invoice_number}</p>
                        <p className="text-[10px] text-slate-500">{inv.case_title}</p>
                      </div>
                    </div>
                    <div className="text-right">
                      <p className="text-sm font-black text-slate-900 dark:text-white">{inv.amount}</p>
                      <Badge variant="outline" className={cn(
                        "text-[10px] uppercase font-bold",
                        inv.status === 'paid' ? "text-emerald-600 border-emerald-200 bg-emerald-50" : "text-amber-600 border-amber-200 bg-amber-50"
                      )}>
                        {inv.status}
                      </Badge>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="text-center py-12 text-slate-400 italic">No invoices found.</div>
            )}
          </CardContent>
        </Card>

        {/* Quick Access Grid */}
        <div className="space-y-4">
          <h3 className="text-lg font-bold text-slate-900 dark:text-white px-1">Quick Actions</h3>
          {[
            { label: "Upload Medical Bill", icon: Upload, href: "/dashboard/client/documents/upload", desc: "Submit new service records", color: "text-blue-600", bg: "bg-blue-50" },
            { label: "View My Cases", icon: FolderOpen, href: "/dashboard/client/cases", desc: "Check case status", color: "text-emerald-600", bg: "bg-emerald-50" },
            { label: "Pay Invoice", icon: CreditCard, href: "/dashboard/client/invoices", desc: "Make a payment", color: "text-purple-600", bg: "bg-purple-50" },
            { label: "Pending Signatures", icon: PenTool, href: "/dashboard/client/signatures", desc: "Documents to sign", color: "text-amber-600", bg: "bg-amber-50" },
          ].map((action, i) => (
            <Button 
              key={i} 
              variant="outline" 
              asChild
              className="w-full h-auto p-4 flex flex-col items-start gap-3 hover:border-primary/50 hover:bg-slate-50 dark:hover:bg-slate-900 transition-all text-left border-slate-200 dark:border-slate-800"
            >
              <Link href={action.href}>
                <div className={`p-2 rounded-lg ${action.bg}`}>
                  <action.icon className={`h-5 w-5 ${action.color}`} />
                </div>
                <div>
                  <p className="font-black text-slate-900 dark:text-white text-sm">{action.label}</p>
                  <p className="text-xs text-slate-500 font-medium mt-0.5">{action.desc}</p>
                </div>
              </Link>
            </Button>
          ))}
        </div>
      </div>
    </div>
  )
}
