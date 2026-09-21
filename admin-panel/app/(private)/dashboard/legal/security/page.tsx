"use client"

import { useState } from "react"
import { ProtectedRoute } from "@/components/protected-route"
import { ROLES } from "@/lib/roleConstants"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Shield, Lock, AlertTriangle, Users } from "lucide-react"
import {
  useGetSecurityStatsQuery,
  useGetSecurityEventsQuery,
} from "@/store/api/securityApiSlice"
import { format } from "date-fns"

export default function FirmSecurityPage() {
  // API Queries
  const { data: statsData, isLoading: statsLoading } = useGetSecurityStatsQuery({}, { pollingInterval: 30000 })
  const { data: eventsData, isLoading: eventsLoading } = useGetSecurityEventsQuery({}, { pollingInterval: 30000 })

  const stats = statsData?.stats || {
    security_score: 0,
    two_fa_percentage: 0,
    users_with_2fa: 0,
    total_users: 0,
    failed_logins_24h: 0,
    active_sessions: 0
  }
  const events = eventsData?.events || []

  if (statsLoading || eventsLoading) {
    return <div className="p-8">Loading security data...</div>
  }

  return (
    <ProtectedRoute requiredRole={ROLES.FIRM_ADMIN}>
      <div className="min-h-screen bg-gradient-to-br from-emerald-50/50 via-white to-green-50/30 dark:from-emerald-950/20 dark:via-slate-950 dark:to-green-950/20 overflow-x-hidden">
        <div className="relative z-10 py-6 md:py-8 px-4 sm:px-6 lg:px-8 space-y-8 max-w-full overflow-x-hidden">
          {/* Header */}
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-3xl font-bold text-emerald-900 dark:text-white">Firm Security</h1>
              <p className="text-emerald-600 dark:text-slate-300 mt-2">Monitor security status and events for your firm</p>
            </div>
          </div>

          {/* Stats Cards */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium text-emerald-700 dark:text-white">Security Score</CardTitle>
                <Shield className="h-4 w-4 text-emerald-600 dark:text-slate-300" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-emerald-900 dark:text-white">{stats.security_score}%</div>
                <p className="text-xs text-emerald-600 dark:text-slate-300">Overall protection level</p>
              </CardContent>
            </Card>
 
            <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium text-emerald-700 dark:text-white">2FA Coverage</CardTitle>
                <Lock className="h-4 w-4 text-emerald-600 dark:text-slate-300" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-emerald-900 dark:text-white">{stats.two_fa_percentage}%</div>
                <p className="text-xs text-emerald-600 dark:text-slate-300">{stats.users_with_2fa} out of {stats.total_users} users</p>
              </CardContent>
            </Card>
 
            <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium text-emerald-700 dark:text-white">Failed Logins</CardTitle>
                <AlertTriangle className="h-4 w-4 text-emerald-600 dark:text-slate-300" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-emerald-900 dark:text-white">{stats.failed_logins_24h}</div>
                <p className="text-xs text-emerald-600 dark:text-slate-300">Events in last 24 hours</p>
              </CardContent>
            </Card>
 
            <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium text-emerald-700 dark:text-white">Active Sessions</CardTitle>
                <Users className="h-4 w-4 text-emerald-600 dark:text-slate-300" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-emerald-900 dark:text-white">{stats.active_sessions}</div>
                <p className="text-xs text-emerald-600 dark:text-slate-300">Total connected devices</p>
              </CardContent>
            </Card>
          </div>

          {/* Main Content */}
          <Tabs defaultValue="events" className="space-y-6">
            <TabsList className="grid w-full grid-cols-1 bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border border-emerald-100 dark:border-emerald-900/50 p-1">
              <TabsTrigger value="events">Security Events</TabsTrigger>
            </TabsList>

            <TabsContent value="events" className="space-y-6">
              <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50">
                <CardHeader>
                  <CardTitle className="dark:text-white">Recent Security Events</CardTitle>
                  <CardDescription className="dark:text-slate-300">Monitor security-related activities for your firm</CardDescription>
                </CardHeader>
                <CardContent>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Event</TableHead>
                        <TableHead>User</TableHead>
                        <TableHead>IP Address</TableHead>
                        <TableHead>Time</TableHead>
                        <TableHead>Severity</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {events.map((event: any) => {
                        const severity = event.event.includes('failed') ? 'High' : 'Low';
                        return (
                          <TableRow key={event.id}>
                            <TableCell className="font-medium uppercase">{event.event.replace('_', ' ')}</TableCell>
                            <TableCell>{event.user ? `${event.user.first_name} ${event.user.last_name}` : 'System'}</TableCell>
                            <TableCell>{event.ip_address}</TableCell>
                            <TableCell>{format(new Date(event.created_at), "MMM dd, HH:mm:ss")}</TableCell>
                            <TableCell>
                              <Badge
                                variant={severity === "High" ? "destructive" : "secondary"}
                                className={severity === "Low" ? "bg-accent/20 text-accent" : ""}
                              >
                                {severity}
                              </Badge>
                            </TableCell>
                          </TableRow>
                        )
                      })}
                      {events.length === 0 && (
                        <TableRow>
                          <TableCell colSpan={5} className="text-center py-8 text-muted-foreground">
                            No recent security events.
                          </TableCell>
                        </TableRow>
                      )}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            </TabsContent>
          </Tabs>
        </div>
      </div>
    </ProtectedRoute>
  )
}
