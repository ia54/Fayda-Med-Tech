"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { 
  BarChart3, 
  TrendingUp, 
  FileText, 
  Bot, 
  DollarSign, 
  AlertTriangle,
  ArrowRight,
  Download
} from "lucide-react"
import { useGetBillingStatsQuery, type BillingStats } from "@/store/api/billingApiSlice"
import { LoadingSpinner } from "@/components/loading-spinner"
import { Progress } from "@/components/ui/progress"

export default function BillingReportsPage() {
  const { data: billingData, isLoading } = useGetBillingStatsQuery()
  
  if (isLoading) return <LoadingSpinner />

  const stats = (billingData?.data?.stats || {}) as BillingStats
  const denialReasons = billingData?.data?.denial_reasons || []
  const payerPerformance = billingData?.data?.payer_performance || []

  return (
    <div className="space-y-8 p-6">
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-primary">Billing & Denial Analytics</h1>
          <p className="text-muted-foreground mt-1">Strategic insights into claim success and recovery performance</p>
        </div>
        <Button className="bg-primary hover:bg-primary/90 shadow-md transition-all hover:-translate-y-0.5">
          <Download className="h-4 w-4 mr-2" />
          Export Billing Report
        </Button>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
        {[
          { label: "Claims Processed", value: stats.claims_processed, icon: FileText, color: "text-blue-600", bg: "bg-blue-50" },
          { label: "AI Appeals", value: stats.appeals_generated, icon: Bot, color: "text-purple-600", bg: "bg-purple-50" },
          { label: "Success Rate", value: stats.success_rate, icon: TrendingUp, color: "text-emerald-600", bg: "bg-emerald-50" },
          { label: "Work Queue", value: stats.work_queue, icon: AlertTriangle, color: "text-amber-600", bg: "bg-amber-50" },
        ].map((item, idx) => (
          <Card key={idx} className="border-border/40 bg-white/50 backdrop-blur-sm shadow-sm">
            <CardContent className="p-6">
              <div className={`p-2 w-fit rounded-lg ${item.bg} mb-3`}>
                <item.icon className={`h-5 w-5 ${item.color}`} />
              </div>
              <p className="text-sm font-medium text-muted-foreground uppercase tracking-tight">{item.label}</p>
              <h3 className="text-2xl font-bold mt-1">{item.value || 0}</h3>
            </CardContent>
          </Card>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {/* Denial Distribution */}
        <Card className="border-border/40 bg-white/50 backdrop-blur-sm shadow-sm">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <AlertTriangle className="h-5 w-5 text-amber-500" />
              Denial Reason Analysis
            </CardTitle>
            <CardDescription>Primary causes for claim rejections across all payers</CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            {denialReasons.length > 0 ? denialReasons.map((item: any, i: number) => (
              <div key={i} className="space-y-2">
                <div className="flex justify-between text-sm">
                  <span className="font-bold text-gray-700">{item.reason}</span>
                  <span className="font-medium text-primary">{item.percentage}%</span>
                </div>
                <Progress value={item.percentage} className="h-2 bg-slate-100" />
              </div>
            )) : (
              <div className="py-12 text-center text-muted-foreground italic">No denial data available for analysis</div>
            )}
          </CardContent>
        </Card>

        {/* Payer Performance */}
        <Card className="border-border/40 bg-white/50 backdrop-blur-sm shadow-sm">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <BarChart3 className="h-5 w-5 text-blue-500" />
              Payer Acceptance Benchmarks
            </CardTitle>
            <CardDescription>Claim success rates by insurance carrier</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {payerPerformance.map((item: any, i: number) => (
              <div key={i} className="flex items-center justify-between p-4 rounded-xl bg-slate-50/50 border border-border/20 hover:border-primary/30 transition-all cursor-pointer group">
                <div className="flex items-center gap-3">
                  <div className={`w-3 h-3 rounded-full ${item.color} shadow-sm`} />
                  <span className="font-bold text-gray-800">{item.payer}</span>
                </div>
                <div className="flex items-center gap-6">
                  <div className="text-right">
                    <span className="text-lg font-bold text-primary">{item.rate}%</span>
                    <p className="text-[10px] text-muted-foreground uppercase tracking-wider">Acceptance</p>
                  </div>
                  <ArrowRight className="h-4 w-4 text-slate-300 group-hover:translate-x-1 transition-transform" />
                </div>
              </div>
            ))}
          </CardContent>
        </Card>
      </div>

      {/* Advanced Billing Templates */}
      <Card className="border-border/40 bg-white/50 backdrop-blur-sm shadow-sm">
        <CardHeader>
          <CardTitle>Specialized Billing Reports</CardTitle>
          <CardDescription>Deep-dive analytics for revenue cycle management</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {[
              { title: "Aging A/R Summary", desc: "Breakdown of outstanding claims by age (30, 60, 90+ days).", icon: FileText },
              { title: "Appeal Success Tracker", desc: "Performance of AI-generated appeals vs. traditional methods.", icon: Bot },
              { title: "Revenue Leakage Audit", desc: "Identification of underpaid claims and missed billing opportunities.", icon: DollarSign },
            ].map((template, idx) => (
              <Button key={idx} variant="outline" className="h-auto p-4 flex flex-col items-start gap-2 border-border/40 hover:bg-primary/5 hover:border-primary/20 transition-all text-left group">
                <div className="p-2 rounded-lg bg-primary/10 text-primary mb-2 group-hover:scale-110 transition-transform">
                  <template.icon className="h-5 w-5" />
                </div>
                <h4 className="font-bold text-gray-900">{template.title}</h4>
                <p className="text-xs text-muted-foreground leading-relaxed">{template.desc}</p>
              </Button>
            ))}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
