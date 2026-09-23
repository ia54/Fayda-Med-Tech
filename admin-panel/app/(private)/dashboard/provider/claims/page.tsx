"use client"

import { useState } from "react"
import { useRouter } from "next/navigation"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Badge } from "@/components/ui/badge"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Textarea } from "@/components/ui/textarea"
import { FileText, Plus, Search, MoreHorizontal, Edit, Eye, Send, Download, Filter, Loader2, Clock, CheckCircle, DollarSign } from "lucide-react"
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu"
import { useGetInvoicesQuery, useGetProviderStatsQuery } from "@/store/api/billingApiSlice"
import { Skeleton } from "@/components/ui/skeleton"

export default function ClaimsPage() {
  const router = useRouter()
  const [searchTerm, setSearchTerm] = useState("")
  const [statusFilter, setStatusFilter] = useState("all")
  const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false)

  // Fetch real data
  const { data: claimsData, isLoading: isClaimsLoading, isError: claimsFailed, refetch: retryClaims } = useGetInvoicesQuery({
    search: searchTerm,
    status: statusFilter === "all" ? undefined : statusFilter
  })
  const { data: statsData, isLoading: isStatsLoading, isError: statsFailed, refetch: retryStats } = useGetProviderStatsQuery()

  const claims = claimsData?.data?.data || []
  const stats = statsData?.data?.stats || {
    total_claims: 0,
    pending_claims: 0,
    success_rate: "0%"
  }

  const getStatusColor = (status: string) => {
    switch (status.toLowerCase()) {
      case "paid": return "bg-emerald-100 text-emerald-700 border-emerald-200"
      case "sent": return "bg-blue-100 text-blue-700 border-blue-200"
      case "denied": return "bg-red-100 text-red-700 border-red-200"
      case "draft": return "bg-slate-100 text-slate-700 border-slate-200"
      default: return "bg-slate-100 text-slate-700"
    }
  }

  return (
    <div className="space-y-8">
        {/* Header */}
        <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <h1 className="text-3xl font-bold text-primary flex items-center gap-2">
              <FileText className="h-8 w-8 text-primary" />
              Claims Management
            </h1>
            <p className="text-muted-foreground">Track internal medical billing records and recorded payment status</p>
          </div>
          <Button 
            className="bg-primary hover:bg-primary/90 shadow-md"
            onClick={() => router.push("/dashboard/provider/claims/create")}
          >
            <Plus className="h-4 w-4 mr-2" />
            New Billing Record
          </Button>
        </div>

        {statsFailed && <p role="alert">Could not load billing totals. <button className="underline" onClick={() => retryStats()}>Try again</button></p>}
        {/* Stats Cards */}
        <div className={statsFailed ? "hidden" : "grid gap-4 md:grid-cols-2 lg:grid-cols-4"}>
          <Card className="hover:shadow-lg transition-all duration-300 bg-card/50 backdrop-blur-sm border-border/50">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Total Inventory</CardTitle>
              <FileText className="h-4 w-4 text-primary" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{isStatsLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : stats.total_claims}</div>
              <p className="text-xs text-muted-foreground mt-1">All billing records</p>
            </CardContent>
          </Card>
          <Card className="hover:shadow-lg transition-all duration-300 bg-card/50 backdrop-blur-sm border-border/50">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Pending Review</CardTitle>
              <Clock className="h-4 w-4 text-amber-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{isStatsLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : stats.pending_claims}</div>
              <p className="text-xs text-muted-foreground mt-1">Awaiting verification</p>
            </CardContent>
          </Card>
          <Card className="hover:shadow-lg transition-all duration-300 bg-card/50 backdrop-blur-sm border-border/50">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Reimbursement Success</CardTitle>
              <CheckCircle className="h-4 w-4 text-emerald-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{isStatsLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : stats.success_rate}</div>
              <p className="text-xs text-muted-foreground mt-1">Paid vs Denied ratio</p>
            </CardContent>
          </Card>
          <Card className="hover:shadow-lg transition-all duration-300 bg-card/50 backdrop-blur-sm border-border/50">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Total Revenue</CardTitle>
              <DollarSign className="h-4 w-4 text-emerald-600" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{isStatsLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : stats.total_revenue}</div>
              <p className="text-xs text-muted-foreground mt-1">Lifetime recovery</p>
            </CardContent>
          </Card>
        </div>

        {/* Claims Table */}
        <Card className="hover:shadow-lg transition-all duration-300 bg-card/50 backdrop-blur-sm border-border/50 overflow-hidden">
          <CardHeader>
            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
              <div>
                <CardTitle>Clinical Claims Inventory</CardTitle>
                <CardDescription>Internal billing records; saving does not submit a claim to an insurer</CardDescription>
              </div>
              <div className="flex gap-2">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                  <Input
                    placeholder="Search by claim ID or patient..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="pl-10 w-full md:w-80 bg-white/50"
                  />
                </div>
                <Select value={statusFilter} onValueChange={setStatusFilter}>
                  <SelectTrigger className="w-32 bg-white/50">
                    <Filter className="h-4 w-4 mr-2" />
                    <SelectValue placeholder="Filter" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All Status</SelectItem>
                    <SelectItem value="draft">Draft</SelectItem>
                    <SelectItem value="sent">Billing Review</SelectItem>
                    <SelectItem value="paid">Paid</SelectItem>
                    <SelectItem value="denied">Denied</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            {claimsFailed ? <p role="alert">Could not load billing records. <button className="underline" onClick={() => retryClaims()}>Try again</button></p> : isClaimsLoading ? (
              <div className="space-y-4">
                {[1, 2, 3, 4, 5].map(i => <Skeleton key={i} className="h-16 w-full rounded-lg" />)}
              </div>
            ) : (
              <div className="rounded-lg border border-border/40 overflow-hidden">
                <Table>
                  <TableHeader className="bg-muted/50">
                    <TableRow>
                      <TableHead>Claim ID</TableHead>
                      <TableHead>Patient / Case</TableHead>
                      <TableHead>Billed Amount</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Date Created</TableHead>
                      <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {claims.map((claim: any) => (
                      <TableRow key={claim.id} className="hover:bg-muted/30 transition-colors">
                        <TableCell className="font-semibold text-primary">#{claim.invoice_number}</TableCell>
                        <TableCell>
                          <div className="font-medium text-emerald-950 dark:text-white">{claim.case?.title || 'General Service'}</div>
                          <div className="text-xs text-muted-foreground">#{claim.case?.case_number || 'N/A'}</div>
                        </TableCell>
                        <TableCell className="font-bold text-slate-900 dark:text-slate-100">${Number(claim.amount).toLocaleString()}</TableCell>
                        <TableCell>
                          <Badge variant="outline" className={getStatusColor(claim.status)}>{claim.status === 'sent' ? 'BILLING REVIEW' : claim.status.toUpperCase()}</Badge>
                        </TableCell>
                        <TableCell className="text-muted-foreground text-sm">{new Date(claim.created_at).toLocaleDateString()}</TableCell>
                        <TableCell className="text-right">
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button variant="ghost" className="h-8 w-8 p-0">
                                <MoreHorizontal className="h-4 w-4" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-48">
                              <DropdownMenuItem className="cursor-pointer">
                                <Eye className="mr-2 h-4 w-4" /> View Detail
                              </DropdownMenuItem>
                              <DropdownMenuItem className="cursor-pointer">
                                <Edit className="mr-2 h-4 w-4" /> Edit Record
                              </DropdownMenuItem>
                              <DropdownMenuItem className="cursor-pointer text-emerald-600">
                                <Send className="mr-2 h-4 w-4" /> Push to Billing
                              </DropdownMenuItem>
                              <DropdownMenuItem className="cursor-pointer">
                                <Download className="mr-2 h-4 w-4" /> Export PDF
                              </DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </TableCell>
                      </TableRow>
                    ))}
                    {claims.length === 0 && (
                      <TableRow>
                        <TableCell colSpan={6} className="text-center py-20 text-muted-foreground italic">
                          No claims found matching your criteria.
                        </TableCell>
                      </TableRow>
                    )}
                  </TableBody>
                </Table>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
  )
}