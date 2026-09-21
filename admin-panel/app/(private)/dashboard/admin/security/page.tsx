"use client"

import { useState, useEffect } from "react"
import { ProtectedRoute } from "@/components/protected-route"
import { ROLES } from "@/lib/roleConstants"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Shield, Lock, AlertTriangle, CheckCircle, Users, Trash2, Plus } from "lucide-react"
import {
  useGetSecuritySettingsQuery,
  useUpdateSecuritySettingsMutation,
  useGetSecurityStatsQuery,
  useGetSecurityEventsQuery,
  useGetIpAllowlistQuery,
  useAddIpToAllowlistMutation,
  useUpdateIpAllowlistMutation,
  useDeleteIpFromAllowlistMutation,
} from "@/store/api/securityApiSlice"
import { format } from "date-fns"

export default function AdminSecurityPage() {
  const [newIp, setNewIp] = useState({ ip_address: "", description: "" })

  // API Queries
  const { data: settingsData, isLoading: settingsLoading } = useGetSecuritySettingsQuery({})
  const { data: statsData, isLoading: statsLoading } = useGetSecurityStatsQuery({}, { pollingInterval: 30000 })
  const { data: eventsData, isLoading: eventsLoading } = useGetSecurityEventsQuery({}, { pollingInterval: 30000 })
  const { data: ipData, isLoading: ipLoading } = useGetIpAllowlistQuery({})

  // API Mutations
  const [updateSettings] = useUpdateSecuritySettingsMutation()
  const [addIp] = useAddIpToAllowlistMutation()
  const [updateIp] = useUpdateIpAllowlistMutation()
  const [deleteIp] = useDeleteIpFromAllowlistMutation()

  const settings = settingsData?.settings || {}
  const stats = statsData?.stats || {
    security_score: 0,
    two_fa_percentage: 0,
    users_with_2fa: 0,
    total_users: 0,
    failed_logins_24h: 0,
    active_sessions: 0
  }
  const events = eventsData?.events || []
  const ipAllowlist = ipData?.ips || []

  const handleUpdatePolicy = async (updates: any) => {
    try {
      await updateSettings({ ...settings, ...updates }).unwrap()
    } catch (err) {
      console.error("Failed to update security policy:", err)
    }
  }

  const handleAddIp = async () => {
    if (!newIp.ip_address) return
    try {
      await addIp(newIp).unwrap()
      setNewIp({ ip_address: "", description: "" })
    } catch (err) {
      console.error("Failed to add IP:", err)
    }
  }

  const handleDeleteIp = async (id: number) => {
    if (confirm("Are you sure you want to remove this IP?")) {
      await deleteIp(id).unwrap()
    }
  }

  if (settingsLoading || statsLoading) {
    return <div className="p-8">Loading security data...</div>
  }

  return (
    <ProtectedRoute requiredRole={ROLES.ADMIN}>
      <div className="min-h-screen bg-transparent overflow-x-hidden">
        <div className="relative z-10 py-6 md:py-8 px-4 sm:px-6 lg:px-8 space-y-8 max-w-full overflow-x-hidden">
          {/* Header */}
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-3xl font-bold text-primary">Security Settings</h1>
              <p className="text-muted-foreground mt-2">Manage security policies and access controls</p>
            </div>
            <Button className="bg-primary hover:bg-primary/90">
              <Shield className="h-4 w-4 mr-2" />
              Security Scan
            </Button>
          </div>

          {/* Stats Cards */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <Card className="bg-card/50 backdrop-blur-sm border-border/50 hover:shadow-lg transition-all duration-300">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Security Score</CardTitle>
                <Shield className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-primary">{stats.security_score}%</div>
                <p className="text-xs text-muted-foreground">Overall protection level</p>
              </CardContent>
            </Card>

            <Card className="bg-card/50 backdrop-blur-sm border-border/50 hover:shadow-lg transition-all duration-300">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">2FA Coverage</CardTitle>
                <Lock className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-primary">{stats.two_fa_percentage}%</div>
                <p className="text-xs text-muted-foreground">{stats.users_with_2fa} out of {stats.total_users} users</p>
              </CardContent>
            </Card>

            <Card className="bg-card/50 backdrop-blur-sm border-border/50 hover:shadow-lg transition-all duration-300">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Failed Logins</CardTitle>
                <AlertTriangle className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-primary">{stats.failed_logins_24h}</div>
                <p className="text-xs text-muted-foreground">Events in last 24 hours</p>
              </CardContent>
            </Card>

            <Card className="bg-card/50 backdrop-blur-sm border-border/50 hover:shadow-lg transition-all duration-300">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Active Sessions</CardTitle>
                <Users className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-primary">{stats.active_sessions}</div>
                <p className="text-xs text-muted-foreground">Total connected devices</p>
              </CardContent>
            </Card>
          </div>

          {/* Main Content */}
          <Tabs defaultValue="policies" className="space-y-6">
            <TabsList className="grid w-full grid-cols-4">
              <TabsTrigger value="policies">Security Policies</TabsTrigger>
              <TabsTrigger value="access">Access Control</TabsTrigger>
              <TabsTrigger value="events">Security Events</TabsTrigger>
              <TabsTrigger value="compliance">Compliance</TabsTrigger>
            </TabsList>

            <TabsContent value="policies" className="space-y-6">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <Card className="bg-card/50 backdrop-blur-sm border-border/50">
                  <CardHeader>
                    <CardTitle>Password Policy</CardTitle>
                    <CardDescription>Configure password requirements</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="space-y-2">
                      <Label htmlFor="min-length">Minimum Length</Label>
                      <Select 
                        value={String(settings.min_password_length || "8")}
                        onValueChange={(val) => handleUpdatePolicy({ min_password_length: parseInt(val) })}
                      >
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="6">6 characters</SelectItem>
                          <SelectItem value="8">8 characters</SelectItem>
                          <SelectItem value="12">12 characters</SelectItem>
                          <SelectItem value="16">16 characters</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="flex items-center space-x-2">
                      <Switch 
                        id="require-uppercase" 
                        checked={!!settings.require_uppercase}
                        onCheckedChange={(val) => handleUpdatePolicy({ require_uppercase: val })}
                      />
                      <Label htmlFor="require-uppercase">Require uppercase letters</Label>
                    </div>
                    <div className="flex items-center space-x-2">
                      <Switch 
                        id="require-numbers" 
                        checked={!!settings.require_numbers}
                        onCheckedChange={(val) => handleUpdatePolicy({ require_numbers: val })}
                      />
                      <Label htmlFor="require-numbers">Require numbers</Label>
                    </div>
                    <div className="flex items-center space-x-2">
                      <Switch 
                        id="require-symbols" 
                        checked={!!settings.require_symbols}
                        onCheckedChange={(val) => handleUpdatePolicy({ require_symbols: val })}
                      />
                      <Label htmlFor="require-symbols">Require special characters</Label>
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="session-timeout">Session Timeout (Minutes)</Label>
                      <Input 
                        type="number"
                        value={settings.session_timeout || 120}
                        onChange={(e) => handleUpdatePolicy({ session_timeout: parseInt(e.target.value) })}
                      />
                    </div>
                  </CardContent>
                </Card>

                <Card className="bg-card/50 backdrop-blur-sm border-border/50">
                  <CardHeader>
                    <CardTitle>Two-Factor Authentication</CardTitle>
                    <CardDescription>Configure 2FA requirements</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="flex items-center space-x-2">
                      <Switch 
                        id="enforce-2fa" 
                        checked={!!settings.enforce_2fa_all}
                        onCheckedChange={(val) => handleUpdatePolicy({ enforce_2fa_all: val })}
                      />
                      <Label htmlFor="enforce-2fa">Enforce 2FA for all users</Label>
                    </div>
                    <div className="flex items-center space-x-2">
                      <Switch 
                        id="admin-2fa" 
                        checked={!!settings.enforce_2fa_admin}
                        onCheckedChange={(val) => handleUpdatePolicy({ enforce_2fa_admin: val })}
                      />
                      <Label htmlFor="admin-2fa">Require 2FA for admin users</Label>
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="2fa-grace">Grace period for new users</Label>
                      <Select 
                        value={String(settings.two_fa_grace_period || "7")}
                        onValueChange={(val) => handleUpdatePolicy({ two_fa_grace_period: parseInt(val) })}
                      >
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="0">No grace period</SelectItem>
                          <SelectItem value="3">3 days</SelectItem>
                          <SelectItem value="7">7 days</SelectItem>
                          <SelectItem value="14">14 days</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </CardContent>
                </Card>
              </div>
            </TabsContent>

            <TabsContent value="access" className="space-y-6">
              <Card className="bg-card/50 backdrop-blur-sm border-border/50">
                <CardHeader>
                  <CardTitle>IP Allowlist</CardTitle>
                  <CardDescription>Manage allowed IP addresses and ranges</CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="space-y-4 mb-6">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                      <Input 
                        placeholder="IP Address or Range" 
                        value={newIp.ip_address}
                        onChange={(e) => setNewIp({...newIp, ip_address: e.target.value})}
                      />
                      <Input 
                        placeholder="Description" 
                        value={newIp.description}
                        onChange={(e) => setNewIp({...newIp, description: e.target.value})}
                      />
                      <Button onClick={handleAddIp} className="bg-primary hover:bg-primary/90">
                        <Plus className="h-4 w-4 mr-2" />
                        Add IP
                      </Button>
                    </div>
                  </div>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>IP Address/Range</TableHead>
                        <TableHead>Description</TableHead>
                        <TableHead>Added</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {ipAllowlist.map((ip: any) => (
                        <TableRow key={ip.id}>
                          <TableCell className="font-medium">{ip.ip_address}</TableCell>
                          <TableCell>{ip.description}</TableCell>
                          <TableCell>{format(new Date(ip.created_at), "yyyy-MM-dd")}</TableCell>
                          <TableCell>
                            <Badge variant="secondary" className="bg-accent/20 text-accent">
                              {ip.is_active ? "Active" : "Disabled"}
                            </Badge>
                          </TableCell>
                          <TableCell>
                            <Button variant="ghost" size="sm" onClick={() => handleDeleteIp(ip.id)}>
                              <Trash2 className="h-4 w-4 text-destructive" />
                            </Button>
                          </TableCell>
                        </TableRow>
                      ))}
                      {ipAllowlist.length === 0 && (
                        <TableRow>
                          <TableCell colSpan={5} className="text-center py-8 text-muted-foreground">
                            No IPs in allowlist.
                          </TableCell>
                        </TableRow>
                      )}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="events" className="space-y-6">
              <Card className="bg-card/50 backdrop-blur-sm border-border/50">
                <CardHeader>
                  <CardTitle>Recent Security Events</CardTitle>
                  <CardDescription>Monitor security-related activities from audit logs</CardDescription>
                </CardHeader>
                <CardContent>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Event</TableHead>
                        <TableHead>User</TableHead>
                        <TableHead>Organization</TableHead>
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
                            <TableCell>{event.organization?.org_name || 'Platform'}</TableCell>
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
                          <TableCell colSpan={6} className="text-center py-8 text-muted-foreground">
                            No recent security events.
                          </TableCell>
                        </TableRow>
                      )}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="compliance" className="space-y-6">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <Card className="bg-card/50 backdrop-blur-sm border-border/50">
                  <CardHeader>
                    <CardTitle>HIPAA Compliance</CardTitle>
                    <CardDescription>Healthcare data protection status</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="flex items-center justify-between">
                      <span>Data Encryption</span>
                      <CheckCircle className="h-5 w-5 text-accent" />
                    </div>
                    <div className="flex items-center justify-between">
                      <span>Access Logging</span>
                      <CheckCircle className="h-5 w-5 text-accent" />
                    </div>
                    <div className="flex items-center justify-between">
                      <span>User Authentication</span>
                      <CheckCircle className="h-5 w-5 text-accent" />
                    </div>
                    <div className="flex items-center justify-between">
                      <span>Data Backup</span>
                      <CheckCircle className="h-5 w-5 text-accent" />
                    </div>
                  </CardContent>
                </Card>

                <Card className="bg-card/50 backdrop-blur-sm border-border/50">
                  <CardHeader>
                    <CardTitle>SOC 2 Compliance</CardTitle>
                    <CardDescription>Security controls assessment</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="flex items-center justify-between">
                      <span>Security Controls</span>
                      <CheckCircle className="h-5 w-5 text-accent" />
                    </div>
                    <div className="flex items-center justify-between">
                      <span>Availability</span>
                      <CheckCircle className="h-5 w-5 text-accent" />
                    </div>
                    <div className="flex items-center justify-between">
                      <span>Processing Integrity</span>
                      <CheckCircle className="h-5 w-5 text-accent" />
                    </div>
                    <div className="flex items-center justify-between">
                      <span>Confidentiality</span>
                      <AlertTriangle className="h-5 w-5 text-yellow-500" />
                    </div>
                  </CardContent>
                </Card>
              </div>
            </TabsContent>
          </Tabs>
        </div>
      </div>
    </ProtectedRoute>
  )
}