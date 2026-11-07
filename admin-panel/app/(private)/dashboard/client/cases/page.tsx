"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Skeleton } from "@/components/ui/skeleton"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Search, FolderOpen, ExternalLink, Calendar, Scale, Filter, Clock } from "lucide-react"
import { useGetClientCasesQuery } from "@/store/api/casesApiSlice"
import Link from "next/link"
import { cn } from "@/lib/utils"

const statusColors: Record<string, string> = {
  active: "bg-emerald-100 text-emerald-700 border-emerald-200",
  Active: "bg-emerald-100 text-emerald-700 border-emerald-200",
  open: "bg-blue-100 text-blue-700 border-blue-200",
  New: "bg-blue-100 text-blue-700 border-blue-200",
  Intake: "bg-violet-100 text-violet-700 border-violet-200",
  Demand: "bg-amber-100 text-amber-700 border-amber-200",
  pending_settlement: "bg-amber-100 text-amber-700 border-amber-200",
  Settlement: "bg-orange-100 text-orange-700 border-orange-200",
  settled: "bg-teal-100 text-teal-700 border-teal-200",
  Closed: "bg-slate-100 text-slate-600 border-slate-200",
  closed: "bg-slate-100 text-slate-600 border-slate-200",
}

export default function ClientCasesPage() {
  const [searchTerm, setSearchTerm] = useState("")
  const [statusFilter, setStatusFilter] = useState("")
  const [page, setPage] = useState(1)
  const { currentData: casesData, isFetching: isLoading, isError, refetch } = useGetClientCasesQuery({
    page,
    search: searchTerm || undefined,
    status: statusFilter || undefined,
  })

  const cases = casesData?.data || []

  return (
    <div className="space-y-6 animate-in fade-in duration-500">
      <div>
        <h1 className="text-3xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-3">
          <FolderOpen className="h-8 w-8 text-primary" />
          My Cases
        </h1>
        <p className="text-muted-foreground mt-1">
          Track the status and progress of your legal cases.
        </p>
      </div>

      <Card className="bg-card/80 backdrop-blur-sm border-border/50">
        <CardHeader>
          <div className="flex flex-col md:flex-row md:items-center gap-4">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search by case title or number..."
                className="pl-10"
                value={searchTerm}
                onChange={(e) => { setSearchTerm(e.target.value); setPage(1) }}
              />
            </div>
            <select aria-label="Case status"
              className="h-10 px-3 rounded-md border border-input bg-background text-sm"
              value={statusFilter}
              onChange={(e) => { setStatusFilter(e.target.value); setPage(1) }}
            >
              <option value="">All Statuses</option>
              <option value="New">New</option>
              <option value="Intake">Intake</option>
              <option value="Active">Active</option>
              <option value="Demand">Demand</option>
              <option value="Settlement">Settlement</option>
              <option value="Closed">Closed</option>
            </select>
          </div>
        </CardHeader>
        <CardContent>
          {isError ? <div role="alert">Could not load cases. <Button onClick={() => refetch()}>Try again</Button></div> : isLoading ? (
            <div className="space-y-4">
              {[1, 2, 3].map((i) => (
                <Skeleton key={i} className="h-20 w-full rounded-xl" />
              ))}
            </div>
          ) : cases.length === 0 ? (
            <div className="text-center py-16">
              <FolderOpen className="h-12 w-12 mx-auto text-slate-300 mb-4" />
              <h3 className="text-lg font-semibold text-slate-600 dark:text-slate-300">No cases found</h3>
              <p className="text-sm text-slate-400 mt-1">Your legal cases will appear here once assigned.</p>
            </div>
          ) : (
            <div className="space-y-3">
              {cases.map((c: any) => (
                <div key={c.id} className="block">
                  <div className="flex flex-col sm:flex-row items-start sm:items-center gap-4 justify-between p-5 border border-slate-100 dark:border-slate-800 rounded-xl hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 bg-white dark:bg-slate-900/50">
                    <div className="flex items-center gap-4 flex-1">
                      <div className="p-2.5 rounded-lg bg-primary/10">
                        <Scale className="h-5 w-5 text-primary" />
                      </div>
                      <Link href={`/dashboard/client/cases/${c.id}`} className="flex-1">
                        <div>
                          <h3 className="font-bold text-slate-900 dark:text-white hover:text-primary transition-colors">{c.title}</h3>
                          <div className="flex flex-wrap items-center gap-3 mt-1">
                            <span className="text-xs text-muted-foreground font-medium">{c.case_number}</span>
                            {c.accident_date && (
                              <span className="text-xs text-muted-foreground flex items-center gap-1">
                                <Calendar className="h-3 w-3" />
                                {c.accident_date}
                              </span>
                            )}
                          </div>
                        </div>
                      </Link>
                    </div>
                    <div className="flex items-center gap-4">
                      <div className="text-right hidden md:block mr-2">
                        <p className="text-sm font-black text-slate-900 dark:text-white">
                          ${Number(c.total_case_value || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                        </p>
                        <p className="text-xs text-muted-foreground">Case Value</p>
                      </div>
                      <Badge variant="outline" className={cn("text-[10px] font-bold uppercase", statusColors[c.status] || "bg-slate-100 text-slate-600")}>
                        {c.status}
                      </Badge>
                      <Link href={`/dashboard/client/cases/${c.id}/timeline`}>
                        <Button size="sm" variant="outline" className="gap-2 border-primary/20 text-primary hover:bg-primary/5">
                          <Clock className="h-3 w-3" />
                          Timeline
                        </Button>
                      </Link>
                      <Link href={`/dashboard/client/cases/${c.id}`}>
                        <Button aria-label={`View ${c.case_number}`} size="sm" variant="ghost" className="h-8 w-8 p-0">
                          <ExternalLink className="h-4 w-4 text-muted-foreground" />
                        </Button>
                      </Link>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
          <div className="mt-4 flex items-center justify-between gap-2"><Button variant="outline" disabled={page <= 1 || isLoading} onClick={() => setPage(p => p - 1)}>Previous</Button><span>Page {page} of {casesData?.meta.last_page || 1}</span><Button variant="outline" disabled={isError || isLoading || page >= (casesData?.meta.last_page || 1)} onClick={() => setPage(p => p + 1)}>Next</Button></div>
        </CardContent>
      </Card>
    </div>
  )
}
