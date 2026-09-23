"use client"

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Progress } from "@/components/ui/progress"
import { AlertTriangle, CheckCircle, TrendingUp, Bot, Upload, BarChart3 } from "lucide-react"
import Link from "next/link"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { useGetBillingStatsQuery } from "@/store/api/billingApiSlice"
import { Skeleton } from "@/components/ui/skeleton"

export default function BillingDashboard() {
  const { data: billingData, isLoading, isError, refetch } = useGetBillingStatsQuery()

  const statsData = billingData?.data?.stats || { work_queue: 0, appeals_generated: 0, claims_processed: 0, success_rate: "0%" }

  const stats = [
    {
      title: "Work Queue Items",
      value: statsData.work_queue,
      change: "+0",
      icon: AlertTriangle,
      color: "text-chart-3",
    },
    {
      title: "Appeals Generated",
      value: statsData.appeals_generated,
      change: "+0",
      icon: Bot,
      color: "text-primary",
    },
    {
      title: "Claims Processed",
      value: statsData.claims_processed,
      change: "+0",
      icon: CheckCircle,
      color: "text-chart-2",
    },
    {
      title: "Success Rate",
      value: statsData.success_rate,
      change: "+0%",
      icon: TrendingUp,
      color: "text-accent",
    },
  ]

  const workQueueItems = billingData?.data?.work_queue_preview || []
  const recentAppeals = billingData?.data?.recent_appeals || []
  const denialReasons = billingData?.data?.denial_reasons || []
  const payerPerformance = billingData?.data?.payer_performance || []

  if (isLoading) {
    return (
      <div className="space-y-8">
        <div className="space-y-2">
          <Skeleton className="h-10 w-64" />
          <Skeleton className="h-4 w-96" />
        </div>
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          {[1, 2, 3, 4].map((i) => <Skeleton key={i} className="h-32 w-full" />)}
        </div>
        <div className="grid gap-6 lg:grid-cols-3">
          <Skeleton className="lg:col-span-1 h-[400px]" />
          <Skeleton className="lg:col-span-2 h-[400px]" />
        </div>
      </div>
    )
  }

  if (isError) return <p role="alert">Could not load billing totals. <Button onClick={() => refetch()}>Try again</Button></p>

  const getPriorityColor = (priority: string) => {
    switch (priority.toLowerCase()) {
      case "high": return "bg-red-100 text-red-700 border-red-200"
      case "medium": return "bg-amber-100 text-amber-700 border-amber-200"
      case "low": return "bg-emerald-100 text-emerald-700 border-emerald-200"
      default: return "bg-slate-100 text-slate-700"
    }
  }

  const getStatusColor = (status: string) => {
    switch (status.toLowerCase()) {
      case "sent": return "bg-blue-100 text-blue-700 border-blue-200"
      case "draft": return "bg-slate-100 text-slate-700 border-slate-200"
      case "accepted": return "bg-emerald-100 text-emerald-700 border-emerald-200"
      case "rejected": return "bg-red-100 text-red-700 border-red-200"
      default: return "bg-slate-100 text-slate-700"
    }
  }

  return (
    <div className="space-y-8">
      {/* Header */}
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 className="text-3xl font-bold text-primary">Billing Operations</h1>
          <p className="text-muted-foreground">Process claims, handle denials, and generate AI-powered appeals</p>
        </div>
        <div className="flex gap-2">
          <Link href="/dashboard/billing/appeals/create">
            <Button variant="outline">
              <Bot className="h-4 w-4 mr-2" />
              AI Appeal
            </Button>
          </Link>
          <Link href="/dashboard/billing/upload">
            <Button>
              <Upload className="h-4 w-4 mr-2" />
              Bulk Upload
            </Button>
          </Link>
        </div>
      </div>

      {/* Stats Grid */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        {stats.map((stat, index) => (
          <Card key={index} className="hover:shadow-lg transition-all duration-300 bg-card/50 backdrop-blur-sm border-border/50">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{stat.title}</CardTitle>
              <stat.icon className={`h-4 w-4 ${stat.color}`} />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stat.value}</div>

            </CardContent>
          </Card>
        ))}
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        {/* Quick Actions */}
        <Card className="lg:col-span-1 bg-card/50 backdrop-blur-sm border-border/50">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <BarChart3 className="h-5 w-5" />
              Navigation
            </CardTitle>
            <CardDescription>Common billing modules</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {[
              { label: "Review Invoices", icon: CheckCircle, href: "/dashboard/billing/invoices" },
              { label: "Work Queue", icon: AlertTriangle, href: "/dashboard/billing/queue" },
              { label: "Code Validation", icon: CheckCircle, href: "/dashboard/billing/validation" },
              { label: "AI Appeals", icon: Bot, href: "/dashboard/billing/appeals" },
              { label: "Bulk Upload", icon: Upload, href: "/dashboard/billing/upload" },
              { label: "Billing Analytics", icon: BarChart3, href: "/dashboard/billing/analytics" },
            ].map((action, i) => (
              <Link key={i} href={action.href} className="block">
                <Button variant="outline" className="w-full justify-start border-border/40 hover:bg-muted/50 transition-colors">
                  <action.icon className="h-4 w-4 mr-2 text-primary" />
                  {action.label}
                </Button>
              </Link>
            ))}
          </CardContent>
        </Card>

        {/* Priority Work Queue Preview */}
        <Card className="lg:col-span-2 bg-card/50 backdrop-blur-sm border-border/50">
          <CardHeader className="flex flex-row items-center justify-between">
            <div>
              <CardTitle className="flex items-center gap-2 text-primary">
                <AlertTriangle className="h-5 w-5" />
                Priority Queue
              </CardTitle>
              <CardDescription>Items needing immediate action</CardDescription>
            </div>
            <Link href="/dashboard/billing/queue">
              <Button variant="ghost" size="sm">View All</Button>
            </Link>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {workQueueItems.length > 0 ? workQueueItems.slice(0, 4).map((item: any) => (
                <div key={item.id} className="flex items-center gap-4 p-4 rounded-xl bg-muted/30 border border-border/30 hover:border-primary/30 transition-all">
                  <Badge variant="outline" className={getPriorityColor(item.priority)}>{item.priority}</Badge>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <p className="text-sm font-semibold">{item.id}</p>
                      <span className="text-xs text-muted-foreground">• {item.type}</span>
                    </div>
                    <p className="text-sm font-medium truncate">{item.issue}</p>
                    <p className="text-xs text-muted-foreground">{item.patient} • {item.payer} • {item.amount}</p>
                  </div>
                  <div className="text-xs text-muted-foreground italic whitespace-nowrap">{item.assigned}</div>
                </div>
              )) : (
                <div className="text-center py-10 text-muted-foreground">No priority items found.</div>
              )}
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Recent Appeals */}
      <Card className="bg-card/50 backdrop-blur-sm border-border/50">
        <CardHeader className="flex flex-row items-center justify-between">
          <div>
            <CardTitle className="flex items-center gap-2 text-primary">
              <Bot className="h-5 w-5" />
              Recent AI Appeals
            </CardTitle>
            <CardDescription>Latest recovery efforts powered by AI</CardDescription>
          </div>
          <Link href="/dashboard/billing/appeals">
            <Button variant="ghost" size="sm">View History</Button>
          </Link>
        </CardHeader>
        <CardContent>
          <div className="rounded-lg border border-border/40 overflow-hidden">
            <Table>
              <TableHeader className="bg-muted/50">
                <TableRow>
                  <TableHead>Appeal ID</TableHead>
                  <TableHead>Patient</TableHead>
                  <TableHead>Payer</TableHead>
                  <TableHead>Reason</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Date</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {recentAppeals.map((appeal: any) => (
                  <TableRow key={appeal.id} className="hover:bg-muted/30 transition-colors">
                    <TableCell className="font-medium text-primary">{appeal.id}</TableCell>
                    <TableCell>{appeal.patient}</TableCell>
                    <TableCell>{appeal.payer}</TableCell>
                    <TableCell>{appeal.reason}</TableCell>
                    <TableCell>
                      <Badge variant="outline" className={getStatusColor(appeal.status)}>{appeal.status}</Badge>
                    </TableCell>
                    <TableCell className="text-muted-foreground">{appeal.generated}</TableCell>
                  </TableRow>
                ))}
                {recentAppeals.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={6} className="text-center py-10 text-muted-foreground">No recent appeals found.</TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>

      {/* Analytics Snapshot */}
      <div className="grid gap-6 lg:grid-cols-2">
        <Card className="bg-card/50 backdrop-blur-sm border-border/50">
          <CardHeader>
            <CardTitle>Common Denial Categories</CardTitle>
            <CardDescription>Denial reasons based on system-wide trends</CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            {denialReasons.length > 0 ? denialReasons.map((item: any, i: number) => (
              <div key={i} className="space-y-2">
                <div className="flex justify-between text-sm">
                  <span className="font-medium">{item.reason}</span>
                  <span className="text-muted-foreground">{item.percentage}%</span>
                </div>
                <Progress value={item.percentage} className="h-2" />
              </div>
            )) : (
              <div className="text-center py-6 text-muted-foreground">No denial data available.</div>
            )}
          </CardContent>
        </Card>

        <Card className="bg-card/50 backdrop-blur-sm border-border/50">
          <CardHeader>
            <CardTitle>Payer Performance</CardTitle>
            <CardDescription>Claim acceptance success rates</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {payerPerformance.length === 0 && <p className="text-sm text-muted-foreground">Payer acceptance data is not available.</p>}
            {payerPerformance.map((item: any, i: number) => (
              <div key={i} className="flex items-center justify-between p-3 rounded-lg hover:bg-muted/30 transition-colors">
                <div className="flex items-center gap-3">
                  <div className={`w-2.5 h-2.5 rounded-full ${item.color}`} />
                  <span className="text-sm font-medium">{item.payer}</span>
                </div>
                <div className="flex items-center gap-4">
                  <span className="text-sm font-bold text-primary">{item.rate}%</span>
                  <div className="w-24 h-1.5 bg-muted rounded-full overflow-hidden">
                    <div className={`h-full ${item.color}`} style={{ width: `${item.rate}%` }} />
                  </div>
                </div>
              </div>
            ))}
          </CardContent>
        </Card>
      </div>
    </div>
  )
}