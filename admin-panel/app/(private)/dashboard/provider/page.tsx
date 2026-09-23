"use client"

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Progress } from "@/components/ui/progress"
import {
  FileText,
  DollarSign,
  Clock,
  CheckCircle,
  AlertTriangle,
  TrendingUp,
  Plus,
  Eye,
  Send,
  Download,
} from "lucide-react"
import Link from "next/link"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { useGetProviderStatsQuery } from "@/store/api/billingApiSlice"
import { Skeleton } from "@/components/ui/skeleton"

export default function ProviderDashboard() {
  const { data: providerData, isLoading, isError, refetch } = useGetProviderStatsQuery()

  const statsData = providerData?.data?.stats || {
    total_claims: 0,
    total_revenue: "$0.00",
    pending_claims: 0,
    success_rate: "0%"
  }

  const stats = [
    {
      title: "Claims Submitted",
      value: statsData.total_claims,
      change: "+0",
      icon: FileText,
      color: "text-primary",
    },
    {
      title: "Total Revenue",
      value: statsData.total_revenue,
      change: "+0",
      icon: DollarSign,
      color: "text-emerald-600",
    },
    {
      title: "Pending Claims",
      value: statsData.pending_claims,
      change: "+0",
      icon: Clock,
      color: "text-amber-500",
    },
    {
      title: "Success Rate",
      value: statsData.success_rate,
      change: "+0%",
      icon: CheckCircle,
      color: "text-blue-500",
    },
  ]

  const recentClaims = providerData?.data?.recent_claims || []
  const pendingTasks = providerData?.data?.pending_tasks || []
  const monthlyRevenue = providerData?.data?.monthly_revenue || []
  const statusDistribution = providerData?.data?.status_distribution || []

  if (isLoading) {
    return (
      <div className="space-y-8 p-6">
        <div className="flex justify-between items-center">
          <Skeleton className="h-10 w-64" />
          <Skeleton className="h-10 w-32" />
        </div>
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          {[1, 2, 3, 4].map(i => <Skeleton key={i} className="h-32 w-full" />)}
        </div>
        <div className="grid gap-6 lg:grid-cols-3">
          <Skeleton className="lg:col-span-1 h-[400px]" />
          <Skeleton className="lg:col-span-2 h-[400px]" />
        </div>
      </div>
    )
  }

  if (isError) return <div role="alert" className="space-y-3"><p>Could not load the provider dashboard.</p><Button onClick={() => refetch()}>Try again</Button></div>

  const getStatusColor = (status: string) => {
    switch (status.toLowerCase()) {
      case "paid": return "bg-emerald-100 text-emerald-700 border-emerald-200"
      case "sent": return "bg-blue-100 text-blue-700 border-blue-200"
      case "denied": return "bg-red-100 text-red-700 border-red-200"
      case "draft": return "bg-slate-100 text-slate-700 border-slate-200"
      default: return "bg-slate-100 text-slate-700"
    }
  }

  const getPriorityColor = (priority: string) => {
    switch (priority.toLowerCase()) {
      case "high": return "text-red-600"
      case "medium": return "text-amber-600"
      case "low": return "text-emerald-600"
      default: return "text-muted-foreground"
    }
  }

  return (
    <div className="space-y-8">
      {/* Header */}
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 className="text-3xl font-bold text-primary">Provider Portal</h1>
          <p className="text-muted-foreground">Clinical operations, claims management, and revenue tracking</p>
        </div>
        <div className="flex gap-2">
          <Link href="/dashboard/provider/claims/create">
            <Button className="bg-primary hover:bg-primary/90">
              <Plus className="h-4 w-4 mr-2" />
              New Claim
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
        {/* Navigation / Quick Actions */}
        <Card className="lg:col-span-1 bg-card/50 backdrop-blur-sm border-border/50">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <TrendingUp className="h-5 w-5" />
              Clinical Workflow
            </CardTitle>
            <CardDescription>Primary provider actions</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {[
              { label: "Submit New Claim", icon: Plus, href: "/dashboard/provider/claims/create" },
              { label: "View Patient Claims", icon: Eye, href: "/dashboard/provider/claims" },
              { label: "Lien & AOB Manager", icon: Send, href: "/dashboard/provider/liens" },
              { label: "Clinical Documents", icon: FileText, href: "/dashboard/provider/documents/upload" },
              { label: "Financial Reports", icon: Download, href: "/dashboard/provider/reports" },
            ].map((action, i) => (
              <Link key={i} href={action.href} className="block">
                <Button variant="outline" className="w-full justify-start border-border/40 hover:bg-primary/5 transition-colors">
                  <action.icon className="h-4 w-4 mr-2 text-primary" />
                  {action.label}
                </Button>
              </Link>
            ))}
          </CardContent>
        </Card>

        {/* Pending Tasks */}
        <Card className="lg:col-span-2 bg-card/50 backdrop-blur-sm border-border/50">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <AlertTriangle className="h-5 w-5" />
              Required Actions
            </CardTitle>
            <CardDescription>Items needing clinical or administrative review</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {pendingTasks.length > 0 ? pendingTasks.map((task: any) => (
                <div key={task.id} className="flex items-center gap-4 p-4 rounded-xl bg-muted/30 border border-border/30 hover:border-primary/30 transition-all">
                  <div className={`w-2 h-2 rounded-full ${getPriorityColor(task.priority).replace("text-", "bg-")}`} />
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-semibold">{task.task}</p>
                    <p className="text-xs text-muted-foreground italic">Due: {task.dueDate}</p>
                  </div>
                  <Badge variant="outline" className={getPriorityColor(task.priority)}>{task.priority}</Badge>
                </div>
              )) : (
                <div className="text-center py-10 text-muted-foreground italic">No pending tasks found.</div>
              )}
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Recent Claims Table */}
      <Card className="bg-card/50 backdrop-blur-sm border-border/50">
        <CardHeader className="flex flex-row items-center justify-between">
          <div>
            <CardTitle className="flex items-center gap-2">
              <FileText className="h-5 w-5 text-primary" />
              Clinical Submissions
            </CardTitle>
            <CardDescription>Recently processed patient claims and invoices</CardDescription>
          </div>
          <Link href="/dashboard/provider/claims">
            <Button variant="ghost" size="sm">View All History</Button>
          </Link>
        </CardHeader>
        <CardContent>
          <div className="rounded-lg border border-border/40 overflow-hidden">
            <Table>
              <TableHeader className="bg-muted/50">
                <TableRow>
                  <TableHead>Claim ID</TableHead>
                  <TableHead>Patient / Case</TableHead>
                  <TableHead>Billed Amount</TableHead>
                  <TableHead>Primary Payer</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Date</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {recentClaims.map((claim: any) => (
                  <TableRow key={claim.id} className="hover:bg-muted/30 transition-colors">
                    <TableCell className="font-medium text-primary">{claim.id}</TableCell>
                    <TableCell>{claim.patient}</TableCell>
                    <TableCell className="font-semibold">{claim.amount}</TableCell>
                    <TableCell>{claim.payer}</TableCell>
                    <TableCell>
                      <Badge variant="outline" className={getStatusColor(claim.status)}>{claim.status}</Badge>
                    </TableCell>
                    <TableCell className="text-muted-foreground">{claim.date}</TableCell>
                  </TableRow>
                ))}
                {recentClaims.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={6} className="text-center py-10 text-muted-foreground italic">No recent claims found.</TableCell>
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
            <CardTitle>Revenue Trends</CardTitle>
            <CardDescription>Payment collections over the last 6 months</CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            {monthlyRevenue.length > 0 ? monthlyRevenue.map((item: any, i: number) => (
              <div key={i} className="space-y-2">
                <div className="flex justify-between text-sm">
                  <span className="font-medium">{item.month}</span>
                  <span className="text-muted-foreground">${Number(item.total).toLocaleString()}</span>
                </div>
                <Progress value={(item.total / (Math.max(...monthlyRevenue.map((r:any) => r.total)) || 1)) * 100} className="h-2" />
              </div>
            )) : (
              <div className="text-center py-10 text-muted-foreground italic">Insufficient data for revenue trends.</div>
            )}
          </CardContent>
        </Card>

        <Card className="bg-card/50 backdrop-blur-sm border-border/50">
          <CardHeader>
            <CardTitle>Claim Status Distribution</CardTitle>
            <CardDescription>Breakdown of current clinical inventory</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {statusDistribution.map((item: any, i: number) => (
              <div key={i} className="flex items-center justify-between p-3 rounded-lg hover:bg-muted/30 transition-colors">
                <div className="flex items-center gap-3">
                  <div className={`w-2.5 h-2.5 rounded-full ${item.color}`} />
                  <span className="text-sm font-medium">{item.status}</span>
                </div>
                <div className="flex items-center gap-4">
                  <span className="text-sm font-bold text-primary">{item.percentage}%</span>
                  <div className="w-24 h-1.5 bg-muted rounded-full overflow-hidden">
                    <div className={`h-full ${item.color}`} style={{ width: `${item.percentage}%` }} />
                  </div>
                </div>
              </div>
            ))}
            {statusDistribution.length === 0 && (
              <div className="text-center py-10 text-muted-foreground italic">No distribution data available.</div>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  )
}