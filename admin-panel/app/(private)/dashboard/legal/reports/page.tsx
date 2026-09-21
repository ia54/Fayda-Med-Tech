"use client"

import { useState } from "react"
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
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { 
  useGetReportHistoryQuery, 
  useGetAttorneyProductionReportQuery,
  useGetSettlementSummaryReportQuery,
  useGetRevenueByPeriodReportQuery,
  useGetCaseStatusReportQuery
} from "@/store/api/apiSlice"
import { format } from "date-fns"
import { LoadingSpinner } from "@/components/loading-spinner"

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
  
  // Fetch real data for stats
  const { data: productionData, isLoading: prodLoading } = useGetAttorneyProductionReportQuery({})
  const { data: settlementData, isLoading: settLoading } = useGetSettlementSummaryReportQuery({})
  const { data: revenueData, isLoading: revLoading } = useGetRevenueByPeriodReportQuery({})
  const { data: reportHistory, isLoading: historyLoading } = useGetReportHistoryQuery({})

  const stats = [
    { 
      label: "Gross Settlements", 
      value: settlementData?.data?.result_summary?.total_gross_settlement ? `$${settlementData.data.result_summary.total_gross_settlement.toLocaleString()}` : "$0", 
      subtext: "Life-to-date",
      icon: DollarSign,
      color: "text-emerald-600",
      bg: "bg-emerald-50"
    },
    { 
      label: "Total Fees Generated", 
      value: settlementData?.data?.result_summary?.total_attorney_fees ? `$${settlementData.data.result_summary.total_attorney_fees.toLocaleString()}` : "$0", 
      subtext: "Total Revenue",
      icon: TrendingUp,
      color: "text-blue-600",
      bg: "bg-blue-50"
    },
    { 
      label: "Outstanding Revenue", 
      value: revenueData?.data?.result_summary?.total_outstanding ? `$${revenueData.data.result_summary.total_outstanding.toLocaleString()}` : "$0", 
      subtext: "Uncollected",
      icon: AlertCircle,
      color: "text-amber-600",
      bg: "bg-amber-50"
    },
    { 
      label: "Cases Settled", 
      value: settlementData?.data?.result_summary?.total_settlements || "0", 
      subtext: "Successfully Closed",
      icon: CheckCircle2,
      color: "text-purple-600",
      bg: "bg-purple-50"
    }
  ]

  const filteredTemplates = reportTemplates.filter(t => 
    t.name.toLowerCase().includes(searchTerm.toLowerCase()) || 
    t.description.toLowerCase().includes(searchTerm.toLowerCase())
  )

  if (historyLoading && prodLoading) return <div className="flex h-[400px] items-center justify-center"><LoadingSpinner /></div>

  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50/50 via-white to-green-50/30 dark:from-emerald-950/20 dark:via-slate-950 dark:to-green-950/20 p-6">
      <div className="max-w-7xl mx-auto space-y-8">
        {/* Header */}
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold text-emerald-900 dark:text-white">Firm Productivity & Reports</h1>
            <p className="text-emerald-700/70 dark:text-emerald-300/70 mt-1">Strategic business intelligence for law firm management</p>
          </div>
          <Button className="bg-emerald-600 hover:bg-emerald-700 text-white shadow-lg shadow-emerald-200 dark:shadow-none transition-all hover:-translate-y-0.5">
            <Download className="h-4 w-4 mr-2" />
            Export Firm Data
          </Button>
        </div>

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
              <CardHeader className="flex flex-row items-center justify-between">
                <div>
                  <CardTitle className="text-xl">Report Templates</CardTitle>
                  <CardDescription>Generate standardized PDF/Excel reports</CardDescription>
                </div>
                <div className="relative w-64">
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
                        <div className="flex-1">
                          <h4 className="font-bold text-slate-900 dark:text-white">{template.name}</h4>
                          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 line-clamp-2">
                            {template.description}
                          </p>
                          <div className="mt-4 flex items-center justify-between">
                            <Badge variant="outline" className="text-[10px] uppercase tracking-wider">{template.category}</Badge>
                            <Button size="sm" variant="ghost" className="text-emerald-600 hover:text-emerald-700 font-bold text-xs p-0 group-hover:translate-x-1 transition-transform">
                              Generate <TrendingUp className="ml-1 h-3 w-3" />
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
                {reportHistory?.data?.data?.length > 0 ? (
                  reportHistory.data.data.slice(0, 8).map((report: any) => (
                    <div key={report.id} className="flex items-center justify-between p-3 rounded-lg bg-white/50 dark:bg-slate-800/50 border border-emerald-50 dark:border-emerald-900/20">
                      <div className="min-w-0">
                        <p className="text-sm font-bold text-slate-900 dark:text-white truncate">{report.report_name}</p>
                        <p className="text-[10px] text-slate-500">{format(new Date(report.created_at), "MMM dd, yyyy · HH:mm")}</p>
                      </div>
                      <Button size="sm" variant="ghost" className="h-8 w-8 p-0">
                        <Download className="h-4 w-4 text-emerald-600" />
                      </Button>
                    </div>
                  ))
                ) : (
                  <div className="text-center py-12 opacity-50">
                    <FileText className="h-10 w-10 mx-auto mb-2" />
                    <p className="text-sm">No reports generated yet</p>
                  </div>
                )}
                {reportHistory?.data?.data?.length > 8 && (
                  <Button variant="link" className="w-full text-emerald-600 text-xs">View Full History</Button>
                )}
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </div>
  )
}
