"use client"

import { useState, useRef } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Badge } from "@/components/ui/badge"
import {
  Download,
  BarChart3,
  TrendingUp,
  FileText,
  Search,
  Users,
  DollarSign,
  Briefcase,
  History,
  CheckCircle2,
  AlertCircle
} from "lucide-react"
import {
  useGetReportHistoryQuery,
  useGetSettlementSummaryReportQuery,
  useGetRevenueByPeriodReportQuery,
  apiSlice
} from "@/store/api/apiSlice"
import { format } from "date-fns"
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from "@/components/ui/dialog"
import { reportRows, reportCsv } from "@/lib/reportExport"

const reportTemplates = [
  {
    id: "case_status",
    name: "Case Status Report",
    description: "Detailed breakdown of cases by status, assigned attorney, and age.",
    category: "Operations",
    icon: Briefcase,
    color: "text-blue-600",
    bgColor: "bg-blue-100"
  },
  {
    id: "attorney_production",
    name: "Attorney Production",
    description: "Productivity metrics including cases closed and fees generated per attorney.",
    category: "Performance",
    icon: Users,
    color: "text-purple-600",
    bgColor: "bg-purple-100"
  },
  {
    id: "settlement_summary",
    name: "Settlement Summary",
    description: "Overview of all settled cases, gross amounts, and net to client.",
    category: "Financial",
    icon: CheckCircle2,
    color: "text-emerald-600",
    bgColor: "bg-emerald-100"
  },
  {
    id: "revenue_by_period",
    name: "Revenue Analysis",
    description: "Invoiced vs. collected amounts with outstanding balance tracking.",
    category: "Financial",
    icon: DollarSign,
    color: "text-green-600",
    bgColor: "bg-green-100"
  },
  {
    id: "lien_summary",
    name: "Lien Reduction Report",
    description: "Analysis of medical liens vs. negotiated reductions per case.",
    category: "Analytics",
    icon: BarChart3,
    color: "text-amber-600",
    bgColor: "bg-amber-100"
  },
  {
    id: "referral_source",
    name: "Referral Source Report",
    description: "Track case volume by referral source (Doctors, Attorneys).",
    category: "Marketing",
    icon: TrendingUp,
    color: "text-rose-600",
    bgColor: "bg-rose-100"
  }
]

export default function FirmReportsPage() {
  const [searchTerm, setSearchTerm] = useState("")

  const [historyPage, setHistoryPage] = useState(1)
  const [open, setOpen] = useState(false)
  const [title, setTitle] = useState("")
  const [result, setResult] = useState<any>(null)
  const [busy, setBusy] = useState(false)
  const [failure, setFailure] = useState(false)
  const retryRequest = useRef<(() => Promise<any>) | null>(null)
  const requestVersion = useRef(0)
  const [caseReport] = apiSlice.endpoints.getCaseStatusReport.useLazyQuery()
  const [settlementReport] = apiSlice.endpoints.getSettlementSummaryReport.useLazyQuery()
  const [productionReport] = apiSlice.endpoints.getAttorneyProductionReport.useLazyQuery()
  const [revenueReport] = apiSlice.endpoints.getRevenueByPeriodReport.useLazyQuery()
  const [lienReport] = apiSlice.endpoints.getLienSummaryReport.useLazyQuery()
  const [referralReport] = apiSlice.endpoints.getReferralSourceReport.useLazyQuery()
  const [savedReport] = apiSlice.endpoints.getReportById.useLazyQuery()
  const generators: Record<string, () => Promise<any>> = {
    case_status: () => caseReport({}).unwrap(),
    settlement_summary: () => settlementReport({}).unwrap(),
    attorney_production: () => productionReport({}).unwrap(),
    revenue_by_period: () => revenueReport({}).unwrap(),
    lien_summary: () => lienReport({}).unwrap(),
    referral_source: () => referralReport({}).unwrap(),
  }
  const showReport = async (name: string, request: () => Promise<any>) => {
    const version = ++requestVersion.current
    retryRequest.current = request
    setTitle(name); setResult(null); setFailure(false); setBusy(true); setOpen(true)
    try {
      const response = await request()
      if (!response?.data?.result_summary || typeof response.data.result_summary !== "object") throw new Error("Missing report")
      if (version !== requestVersion.current) return
      setResult(response.data)
      void retryHistory()
    } catch {
      if (version === requestVersion.current) setFailure(true)
    } finally {
      if (version === requestVersion.current) setBusy(false)
    }
  }
  const downloadReport = () => {
    if (!result) return
    const url = URL.createObjectURL(new Blob([reportCsv(result.result_summary)], { type: "text/csv;charset=utf-8" }))
    const link = document.createElement("a")
    link.href = url; link.download = `faydamed-report-${result.id}.csv`; link.click()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  }
  const money = (value: unknown, loading: boolean, failed: boolean) => loading ? "Loading…" : failed ? "Unavailable" : value == null ? "Not recorded" : Number(value).toLocaleString("en-US", { style: "currency", currency: "USD" })

  // Fetch real data for stats
  const { data: settlementData, isFetching: settLoading, isError: settError, refetch: retrySettlements } = useGetSettlementSummaryReportQuery({})
  const { data: revenueData, isFetching: revLoading, isError: revError, refetch: retryRevenue } = useGetRevenueByPeriodReportQuery({})
  const { data: reportHistory, isFetching: historyLoading, isError: historyError, refetch: retryHistory } = useGetReportHistoryQuery({ page: historyPage, per_page: 8 })

  const stats = [
    {
      label: "Gross Settlements",
      value: money(settlementData?.data?.result_summary?.total_gross_settlement, settLoading, settError),
      subtext: "Current completed records",
      icon: DollarSign,
      color: "text-emerald-600",
      bg: "bg-emerald-50"
    },
    {
      label: "Recorded Attorney Fees",
      value: money(settlementData?.data?.result_summary?.total_attorney_fees, settLoading, settError),
      subtext: "Not verified cash receipts",
      icon: TrendingUp,
      color: "text-blue-600",
      bg: "bg-blue-50"
    },
    {
      label: "Outstanding Revenue",
      value: money(revenueData?.data?.result_summary?.total_outstanding, revLoading, revError),
      subtext: "Uncollected",
      icon: AlertCircle,
      color: "text-amber-600",
      bg: "bg-amber-50"
    },
    {
      label: "Completed Settlement Records",
      value: settLoading ? "Loading…" : settError ? "Unavailable" : settlementData?.data?.result_summary?.total_settlements ?? "Not recorded",
      subtext: "Excludes replaced records",
      icon: CheckCircle2,
      color: "text-purple-600",
      bg: "bg-purple-50"
    }
  ]

  const filteredTemplates = reportTemplates.filter(t =>
    t.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    t.description.toLowerCase().includes(searchTerm.toLowerCase())
  )


  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50/50 via-white to-green-50/30 dark:from-emerald-950/20 dark:via-slate-950 dark:to-green-950/20 p-6">
      <div className="max-w-7xl mx-auto space-y-8">
        {/* Header */}
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold text-emerald-900 dark:text-white">Firm Productivity & Reports</h1>
            <p className="text-emerald-700/70 dark:text-emerald-300/70 mt-1">Strategic business intelligence for law firm management</p>
          </div>

        </div>

        {(settError || revError) && <div role="alert">Some totals are unavailable. <Button variant="outline" onClick={() => { void retrySettlements(); void retryRevenue() }}>Retry totals</Button></div>}
        {/* Stats Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {stats.map((stat, idx) => (
            <Card key={idx} className="border-emerald-100 dark:border-emerald-900/50 bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm hover:shadow-md transition-all">
              <CardContent className="p-6">
                <div className="flex items-center justify-between mb-4">
                  <div className={`p-2 rounded-lg ${stat.bg}`}>
                    <stat.icon className={`h-5 w-5 ${stat.color}`} />
                  </div>
                </div>
                <div>
                  <p className="text-sm font-medium text-slate-500 dark:text-slate-400">{stat.label}</p>
                  <h3 className="text-2xl font-bold text-slate-900 dark:text-white mt-1">{stat.value}</h3>
                  <p className="text-xs text-emerald-600 dark:text-emerald-400 mt-1 font-medium">{stat.subtext}</p>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Report Templates (Left 2 Columns) */}
          <div className="lg:col-span-2 space-y-6">
            <Card className="border-emerald-100 dark:border-emerald-900/50 bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm">
              <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <CardTitle className="text-xl">Report Templates</CardTitle>
                  <CardDescription>Open report results and download a CSV copy</CardDescription>
                </div>
                <div className="relative w-full sm:w-64">
                  <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                  <Input
                    placeholder="Search templates..."
                    className="pl-9 bg-white/50 border-emerald-50"
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                  />
                </div>
              </CardHeader>
              <CardContent>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {filteredTemplates.map((template) => (
                    <div key={template.id} className="p-4 rounded-xl border border-emerald-50 dark:border-emerald-900/30 bg-white/50 dark:bg-slate-800/50 hover:bg-emerald-50/50 transition-colors group">
                      <div className="flex gap-4">
                        <div className={`p-3 rounded-lg ${template.bgColor} shrink-0`}>
                          <template.icon className={`h-6 w-6 ${template.color}`} />
                        </div>
                        <div className="min-w-0 flex-1">
                          <h4 className="font-bold text-slate-900 dark:text-white">{template.name}</h4>
                          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 line-clamp-2">
                            {template.description}
                          </p>
                          <div className="mt-4 flex flex-wrap items-center justify-between gap-2">
                            <Badge variant="outline" className="text-[10px] uppercase tracking-wider">{template.category}</Badge>
                            <Button aria-label={`Open ${template.name}`} onClick={() => void showReport(template.name, generators[template.id])} size="sm" variant="ghost" className="text-emerald-600 hover:text-emerald-700 font-bold text-xs p-0 group-hover:translate-x-1 transition-transform">
                              Open report <TrendingUp className="ml-1 h-3 w-3" />
                            </Button>
                          </div>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Report History (Right Column) */}
          <div className="space-y-6">
            <Card className="border-emerald-100 dark:border-emerald-900/50 bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm h-full">
              <CardHeader>
                <div className="flex items-center gap-2">
                  <History className="h-5 w-5 text-emerald-600" />
                  <CardTitle className="text-xl">Generation History</CardTitle>
                </div>
                <CardDescription>Recently accessed records</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                {historyLoading ? <p role="status">Loading report history…</p> : historyError ? <div role="alert">Report history is unavailable. <Button variant="outline" onClick={() => void retryHistory()}>Retry history</Button></div> : reportHistory?.data?.data?.length > 0 ? (
                  reportHistory.data.data.slice(0, 8).map((report: any) => (
                    <div key={report.id} className="flex items-center justify-between p-3 rounded-lg bg-white/50 dark:bg-slate-800/50 border border-emerald-50 dark:border-emerald-900/20">
                      <div className="min-w-0">
                        <p className="text-sm font-bold text-slate-900 dark:text-white truncate">{report.report_name}</p>
                        <p className="text-[10px] text-slate-500">{format(new Date(report.created_at), "MMM dd, yyyy · HH:mm")}</p>
                      </div>
                      <Button aria-label={`Open saved ${report.report_name}`} onClick={() => void showReport(report.report_name, () => savedReport(report.id).unwrap())} size="sm" variant="ghost" className="h-8 w-8 p-0">
                        <FileText className="h-4 w-4 text-emerald-600" />
                      </Button>
                    </div>
                  ))
                ) : (
                  <div className="text-center py-12 opacity-50">
                    <FileText className="h-10 w-10 mx-auto mb-2" />
                    <p className="text-sm">No reports generated yet</p>
                  </div>
                )}
                <div className="flex items-center justify-between gap-2">
                  <Button variant="outline" disabled={historyLoading || historyPage <= 1} onClick={() => setHistoryPage(p => p - 1)}>Previous history</Button>
                  <span>Page {historyPage}</span>
                  <Button variant="outline" disabled={historyLoading || historyError || !reportHistory?.data?.next_page_url} onClick={() => setHistoryPage(p => p + 1)}>Next history</Button>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
      <Dialog open={open} onOpenChange={(value) => { setOpen(value); if (!value) ++requestVersion.current }}>
        <DialogContent className="w-[calc(100vw-2rem)] sm:max-w-3xl max-h-[90dvh] overflow-y-auto">
          <DialogHeader><DialogTitle>{title}</DialogTitle><DialogDescription>Recorded application data. Settlement amounts and recorded receipts do not establish a bank transfer.</DialogDescription></DialogHeader>
          {busy ? <p role="status">Loading report…</p> : failure ? <div role="alert">Could not load this report. <Button onClick={() => retryRequest.current && void showReport(title, retryRequest.current)}>Try again</Button></div> : result && <>
            <p className="text-sm text-muted-foreground">Report #{result.id} · {result.completed_at ? new Date(result.completed_at).toLocaleString() : "Date unavailable"}</p>
            <Button onClick={downloadReport}><Download className="mr-2 h-4 w-4" />Download CSV</Button>
            <dl className="min-w-0 divide-y">{reportRows(result.result_summary).map(([field, value], index) => <div key={index} className="grid min-w-0 grid-cols-1 gap-1 py-3 sm:grid-cols-2 sm:gap-4"><dt className="min-w-0 break-words text-sm text-muted-foreground">{field}</dt><dd className="min-w-0 break-words text-sm">{value}</dd></div>)}</dl>
          </>}
        </DialogContent>
      </Dialog>
    </div>
  )
}
