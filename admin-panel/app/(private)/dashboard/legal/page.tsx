"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Search, Download, FileText, DollarSign, Clock, CheckCircle, Scale, Briefcase, Bell } from "lucide-react"
import Link from "next/link"
// Role protection is handled by the layout file

import { useGetFirmStatsQuery } from "@/store/api/firmApiSlice"
import { useGetCasesQuery, useUpdateCaseMutation } from "@/store/api/casesApiSlice"
import { Skeleton } from "@/components/ui/skeleton"
import { Loader2 } from "lucide-react"
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from "recharts"
import { useToast } from "@/hooks/use-toast"

export default function LegalDashboard() {
  const [searchTerm, setSearchTerm] = useState("")

  // Fetch real stats
  const {
    data: statsData,
    isLoading: isStatsLoading,
    error: statsError
  } = useGetFirmStatsQuery()

  // Fetch real cases
  const {
    data: casesData,
    isLoading: isCasesLoading,
    error: casesError
  } = useGetCasesQuery({
    search: searchTerm,
    per_page: 10
  })

  const [updateCase, { isLoading: isSettling }] = useUpdateCaseMutation()
  const { toast } = useToast()

  const handleMarkAsSettled = async (id: number) => {
    try {
      await updateCase({ id, data: { status: 'Settlement' } }).unwrap()
      toast({
        title: "Success",
        description: "Case marked as settled",
      })
    } catch (error) {
      toast({
        title: "Error",
        description: "Failed to update case status",
        variant: "destructive",
      })
    }
  }

  const cases = casesData?.data || []
  const stats = statsData?.data?.stats || {
    active_cases: 0,
    total_recovery: 0,
    pending_amount: 0,
    settlements_ready: 0
  }
  const recentNotifications = statsData?.data?.recent_activity || []
  const hasError = !!(statsError || casesError)

  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50/30 via-white to-green-50/20 dark:from-emerald-950/20 dark:via-slate-950 dark:to-green-950/20">
      <div className="container mx-auto p-6 space-y-8">
        {hasError && (
          <div className="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
            <p className="text-amber-800 dark:text-amber-200 text-sm font-medium">
              ⚠️ Some data could not be loaded. Showing available information.
            </p>
          </div>
        )}
        {/* Header */}
        <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold text-emerald-900 dark:text-white text-balance">Legal Dashboard</h1>
            <p className="text-emerald-700 dark:text-slate-300 mt-2">Case management and settlement tracking</p>
          </div>
          <div className="flex items-center gap-4">
            <Badge variant="outline" className="bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-white border-emerald-200 dark:border-emerald-800">
              <Scale className="w-4 h-4 mr-2" />
              Legal Access
            </Badge>
          </div>
        </div>

        {/* Quick Stats */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-emerald-700 dark:text-white">Active Cases</CardTitle>
              <Briefcase className="h-4 w-4 text-emerald-600 dark:text-slate-300" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-emerald-900 dark:text-white">
                {isStatsLoading ? <Skeleton className="h-8 w-16" /> : stats.active_cases}
              </div>
              <p className="text-xs text-emerald-600 dark:text-slate-300 mt-1">
                {isStatsLoading ? <Skeleton className="h-3 w-20" /> : "+2 this month"}
              </p>
            </CardContent>
          </Card>

          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-emerald-700 dark:text-white">Total Recovery</CardTitle>
              <DollarSign className="h-4 w-4 text-emerald-600 dark:text-slate-300" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-emerald-900 dark:text-white">
                {isStatsLoading ? <Skeleton className="h-8 w-24" /> : `$${stats.total_recovery.toLocaleString()}`}
              </div>
              <p className="text-xs text-emerald-600 dark:text-slate-300 mt-1">
                {isStatsLoading ? <Skeleton className="h-3 w-24" /> : statsData?.data?.recovery_growth || "+0% this month"}
              </p>
            </CardContent>
          </Card>

          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-emerald-700 dark:text-white">Pending Amount</CardTitle>
              <Clock className="h-4 w-4 text-emerald-600 dark:text-slate-300" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-emerald-900 dark:text-white">
                {isStatsLoading ? <Skeleton className="h-8 w-24" /> : `$${stats.pending_amount.toLocaleString()}`}
              </div>
              <p className="text-xs text-emerald-600 dark:text-slate-300 mt-1">
                {isStatsLoading ? <Skeleton className="h-3 w-20" /> : `Across ${stats.active_cases} cases`}
              </p>
            </CardContent>
          </Card>

          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-emerald-700 dark:text-white">Settlements</CardTitle>
              <CheckCircle className="h-4 w-4 text-emerald-600 dark:text-slate-300" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-emerald-900 dark:text-white">
                {isStatsLoading ? <Skeleton className="h-8 w-12" /> : stats.settlements_ready}
              </div>
              <p className="text-xs text-emerald-600 dark:text-slate-300 mt-1">Ready for processing</p>
            </CardContent>
          </Card>
        </div>

        {/* Analytics Section */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <Card className="lg:col-span-2 bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50">
            <CardHeader>
              <CardTitle className="text-emerald-900 dark:text-white">Revenue & Collections</CardTitle>
              <CardDescription className="dark:text-slate-300">Monthly recovery totals across all cases</CardDescription>
            </CardHeader>
            <CardContent className="h-[300px]">
              {isStatsLoading ? (
                <div className="flex items-center justify-center h-full">
                  <Loader2 className="h-8 w-8 animate-spin text-emerald-600" />
                </div>
              ) : (
                <div className="w-full h-full">
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={statsData?.data?.revenue_data || []}>
                      <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" />
                      <XAxis 
                        dataKey="month" 
                        axisLine={false} 
                        tickLine={false} 
                        tick={{ fill: '#64748b', fontSize: 12 }}
                      />
                      <YAxis 
                        axisLine={false} 
                        tickLine={false} 
                        tick={{ fill: '#64748b', fontSize: 12 }}
                        tickFormatter={(value) => `$${value}`}
                      />
                      <Tooltip 
                        contentStyle={{ backgroundColor: '#fff', borderRadius: '8px', border: 'none', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)' }}
                        formatter={(value: number) => [`$${value.toLocaleString()}`, 'Revenue']}
                      />
                      <Bar dataKey="amount" fill="#059669" radius={[4, 4, 0, 0]} />
                    </BarChart>
                  </ResponsiveContainer>
                </div>
              )}
            </CardContent>
          </Card>

          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50">
            <CardHeader>
              <CardTitle className="text-emerald-900 dark:text-white">Team Workload</CardTitle>
              <CardDescription className="dark:text-slate-300">Active cases per attorney</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-4">
                {isStatsLoading ? (
                  Array(3).fill(0).map((_, i) => (
                    <div key={i} className="flex items-center justify-between">
                      <Skeleton className="h-4 w-32" />
                      <Skeleton className="h-4 w-8" />
                    </div>
                  ))
                ) : (
                  statsData?.data?.team_workload?.map((attorney, i) => (
                    <div key={i} className="flex items-center justify-between group">
                      <div className="flex items-center gap-3">
                        <div className="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-700 dark:text-emerald-300 font-bold text-xs">
                          {attorney.name.split(' ').map(n => n[0]).join('')}
                        </div>
                        <span className="text-sm font-medium text-emerald-900 dark:text-white">{attorney.name}</span>
                      </div>
                      <Badge variant="outline" className="bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800">
                        {attorney.cases} Cases
                      </Badge>
                    </div>
                  ))
                )}
                {(!isStatsLoading && (!statsData?.data?.team_workload || statsData.data.team_workload.length === 0)) && (
                  <div className="text-center py-4 text-sm text-slate-500">No workload data available</div>
                )}
              </div>
              <Button variant="ghost" className="w-full mt-6 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 text-xs" asChild>
                <Link href="/dashboard/legal/users">Manage Team</Link>
              </Button>
            </CardContent>
          </Card>
        </div>

        {/* Main Content */}
        <Tabs defaultValue="cases" className="space-y-6">
          <TabsList className="grid w-full grid-cols-4 bg-emerald-50/50 dark:bg-emerald-900/20 backdrop-blur-sm">
            <TabsTrigger value="cases" className="data-[state=active]:bg-emerald-600 dark:data-[state=active]:bg-emerald-500 data-[state=active]:text-white">
              My Cases
            </TabsTrigger>
            <TabsTrigger value="liens" className="data-[state=active]:bg-emerald-600 dark:data-[state=active]:bg-emerald-500 data-[state=active]:text-white">
              Liens & AOB
            </TabsTrigger>
            <TabsTrigger
              value="settlements"
              className="data-[state=active]:bg-emerald-600 dark:data-[state=active]:bg-emerald-500 data-[state=active]:text-white"
            >
              Settlement Notes
            </TabsTrigger>
            <TabsTrigger
              value="notifications"
              className="data-[state=active]:bg-emerald-600 dark:data-[state=active]:bg-emerald-500 data-[state=active]:text-white"
            >
              Notifications
            </TabsTrigger>
          </TabsList>

          <TabsContent value="cases" className="space-y-6">
            <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50">
              <CardHeader>
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                  <div>
                    <CardTitle className="text-emerald-900 dark:text-white">Case Management</CardTitle>
                    <CardDescription className="dark:text-slate-300">View and track all your active cases</CardDescription>
                  </div>
                  <div className="flex items-center gap-2">
                    <div className="relative">
                      <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-emerald-500 dark:text-slate-300 h-4 w-4" />
                      <Input
                        placeholder="Search cases..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="pl-10 bg-white/50 dark:bg-slate-800/50 border-emerald-200 dark:border-emerald-800 focus:border-emerald-400 dark:focus:border-emerald-500"
                      />
                    </div>
                  </div>
                </div>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  {isCasesLoading ? (
                    <div className="flex justify-center py-8">
                      <Loader2 className="h-8 w-8 animate-spin text-emerald-600" />
                    </div>
                  ) : cases.length === 0 ? (
                    <div className="text-center py-8 text-emerald-600">No cases found.</div>
                  ) : (
                    cases.map((case_) => (
                      <Card
                        key={case_.id}
                        className="bg-gradient-to-r from-white/80 to-emerald-50/30 dark:from-slate-900/80 dark:to-emerald-900/20 border-emerald-100 dark:border-emerald-900/50 hover:shadow-md transition-all duration-300"
                      >
                        <CardContent className="p-6">
                          <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                            <div className="space-y-2">
                              <div className="flex items-center gap-3">
                                <h3 className="font-semibold text-emerald-900 dark:text-white">{case_.title}</h3>
                                <Badge variant="outline" className="bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-white border-emerald-200 dark:border-emerald-800">
                                  {case_.case_number}
                                </Badge>
                                <Badge
                                  variant={case_.status === "active" ? "default" : "secondary"}
                                  className={case_.status === "active" ? "bg-emerald-600 dark:bg-emerald-500" : "bg-amber-500"}
                                >
                                  {case_.status}
                                </Badge>
                              </div>
                              <div className="text-sm text-emerald-600 dark:text-slate-300 space-y-1">
                                <p>Accident Date: {case_.accident_date ? new Date(case_.accident_date).toLocaleDateString() : "N/A"}</p>
                                <p>Created At: {new Date(case_.created_at).toLocaleDateString()}</p>
                              </div>
                            </div>
                            <div className="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                              <div className="text-right space-y-1">
                                <div className="text-sm text-emerald-600 dark:text-slate-300">
                                  Total Value:{" "}
                                  <span className="font-semibold text-emerald-900 dark:text-white">
                                    ${case_.total_case_value.toLocaleString()}
                                  </span>
                                </div>
                              </div>
                              <Link href={`/dashboard/legal/cases/${case_.id}`}>
                                <Button
                                  variant="outline"
                                  size="sm"
                                  className="border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-white hover:bg-emerald-50 dark:hover:bg-emerald-900/50 bg-transparent"
                                >
                                  <FileText className="w-4 h-4 mr-2" />
                                  View Details
                                </Button>
                              </Link>
                            </div>
                          </div>
                        </CardContent>
                      </Card>
                    )))}
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="liens" className="space-y-6">
            <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50">
              <CardHeader>
                <CardTitle className="text-emerald-900 dark:text-white">Liens & Assignment of Benefits</CardTitle>
                <CardDescription className="dark:text-slate-300">Download and manage lien documentation</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  {cases.map((case_) => (
                    <Card key={case_.id} className="bg-gradient-to-r from-white/80 to-emerald-50/30 dark:from-slate-900/80 dark:to-emerald-900/20 border-emerald-100 dark:border-emerald-900/50">
                      <CardContent className="p-6">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                          <div>
                            <h3 className="font-semibold text-emerald-900 dark:text-white">{case_.title}</h3>
                            <p className="text-sm text-emerald-600 dark:text-slate-300">
                              {case_.case_number} • {case_.status}
                            </p>
                          </div>
                          <div className="flex items-center gap-2">
                            <Button
                              variant="outline"
                              size="sm"
                              className="border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-white hover:bg-emerald-50 dark:hover:bg-emerald-900/50 bg-transparent"
                            >
                              <Download className="w-4 h-4 mr-2" />
                              Download Lien Pack
                            </Button>
                          </div>
                        </div>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="settlements" className="space-y-6">
            <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50">
              <CardHeader>
                <CardTitle className="text-emerald-900 dark:text-white">Settlement Notes</CardTitle>
                <CardDescription className="dark:text-slate-300">Mark settlement dates and amounts</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  {cases.map((case_) => (
                    <Card key={case_.id} className="bg-gradient-to-r from-white/80 to-emerald-50/30 dark:from-slate-900/80 dark:to-emerald-900/20 border-emerald-100 dark:border-emerald-900/50">
                      <CardContent className="p-6">
                        <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                          <div>
                            <h3 className="font-semibold text-emerald-900 dark:text-white">{case_.title}</h3>
                            <p className="text-sm text-emerald-600 dark:text-slate-300">
                              {case_.case_number} • Total Value: ${case_.total_case_value.toLocaleString()}
                            </p>
                          </div>
                          <div className="flex items-center gap-2">
                            {case_.status === "Settlement" || case_.status === "settled" ? (
                              <Badge className="bg-emerald-600">Settled</Badge>
                            ) : (
                              <Button
                                variant="outline"
                                size="sm"
                                disabled={isSettling}
                                onClick={() => handleMarkAsSettled(case_.id)}
                                className="border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-white hover:bg-emerald-50 dark:hover:bg-emerald-900/50 bg-transparent"
                              >
                                {isSettling ? <Loader2 className="h-4 w-4 animate-spin mr-2" /> : null}
                                Mark as Settled
                              </Button>
                            )}
                          </div>
                        </div>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="notifications" className="space-y-6">
            <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50">
              <CardHeader>
                <CardTitle className="text-emerald-900 dark:text-white">Notifications</CardTitle>
                <CardDescription className="dark:text-slate-300">Messages and updates from providers</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  {recentNotifications.map((notification) => (
                    <Card
                      key={notification.id}
                      className="bg-gradient-to-r from-white/80 to-emerald-50/30 dark:from-slate-900/80 dark:to-emerald-900/20 border-emerald-100 dark:border-emerald-900/50"
                    >
                      <CardContent className="p-4">
                        <div className="flex items-start gap-4">
                          <div className="p-2 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400">
                            <Bell className="w-4 h-4" />
                          </div>
                          <div className="flex-1">
                            <p className="text-emerald-900 dark:text-white font-medium">{notification.description || notification.event}</p>
                            <div className="flex items-center gap-2 mt-1">
                              <span className="text-xs font-semibold text-emerald-700 dark:text-emerald-300 capitalize">{notification.event}</span>
                              <span className="text-xs text-emerald-600 dark:text-slate-400">• {notification.timestamp}</span>
                            </div>
                          </div>
                          <Badge
                            variant="outline"
                            className="border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-white bg-emerald-50 dark:bg-emerald-900/30"
                          >
                            {notification.user}
                          </Badge>
                        </div>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </div>
    </div>
  )
}