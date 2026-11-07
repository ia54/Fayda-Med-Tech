"use client"

import { useParams } from "next/navigation"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { Separator } from "@/components/ui/separator"
import { ArrowLeft, Calendar, MapPin, Scale, Users, FileText, DollarSign, Clock, ChevronRight } from "lucide-react"
import { useGetClientCaseByIdQuery } from "@/store/api/casesApiSlice"
import Link from "next/link"

const statusColors: Record<string, string> = {
  active: "bg-emerald-100 text-emerald-700 border-emerald-200",
  Active: "bg-emerald-100 text-emerald-700 border-emerald-200",
  open: "bg-blue-100 text-blue-700 border-blue-200",
  New: "bg-blue-100 text-blue-700 border-blue-200",
  Intake: "bg-violet-100 text-violet-700 border-violet-200",
  Demand: "bg-amber-100 text-amber-700 border-amber-200",
  pending_settlement: "bg-amber-100 text-amber-700 border-amber-200",
  Settlement: "bg-orange-100 text-orange-700 border-orange-200",
  Closed: "bg-slate-100 text-slate-600 border-slate-200",
}

export default function ClientCaseDetailPage() {
  const params = useParams()
  const caseId = Number(params.id)
  const { data: caseData, isLoading, error, refetch } = useGetClientCaseByIdQuery(caseId)

  if (isLoading) {
    return (
      <div className="space-y-6 p-2">
        <Skeleton className="h-10 w-48" />
        <Skeleton className="h-64 w-full rounded-xl" />
        <div className="grid gap-6 md:grid-cols-2">
          <Skeleton className="h-48 rounded-xl" />
          <Skeleton className="h-48 rounded-xl" />
        </div>
      </div>
    )
  }

  if (error || !caseData) {
    return (
      <div className="flex flex-col items-center justify-center py-20">
        <Scale className="h-12 w-12 text-slate-300 mb-4" />
        <h3 className="text-lg font-semibold text-slate-600">Case unavailable</h3>
        <p className="text-sm text-slate-400 mt-1">The case could not be loaded, or you may not have access.</p>
        <Button className="mt-4" onClick={() => refetch()}>Try again</Button>
        <Button variant="outline" className="mt-4" asChild>
          <Link href="/dashboard/client/cases"><ArrowLeft className="h-4 w-4 mr-2" /> Back to Cases</Link>
        </Button>
      </div>
    )
  }

  const c = caseData

  return (
    <div className="space-y-6 animate-in fade-in duration-500">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div className="flex items-center gap-4">
          <Button variant="ghost" size="icon" asChild>
            <Link href="/dashboard/client/cases"><ArrowLeft className="h-5 w-5" /></Link>
          </Button>
          <div>
            <h1 className="text-2xl font-bold text-slate-900 dark:text-white">{c.title}</h1>
            <p className="text-sm text-muted-foreground font-medium">{c.case_number}</p>
          </div>
        </div>
        <Badge variant="outline" className={`text-sm px-4 py-1.5 ${statusColors[c.status] || "bg-slate-100"}`}>
          {c.status}
        </Badge>
      </div>

      {/* Case Info Cards */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card className="bg-white/50 dark:bg-slate-900/50 backdrop-blur-sm">
          <CardContent className="pt-6">
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-blue-50 dark:bg-blue-950"><Calendar className="h-4 w-4 text-blue-600" /></div>
              <div>
                <p className="text-xs text-muted-foreground font-medium">Accident Date</p>
                <p className="text-sm font-bold">{c.accident_date ? new Date(c.accident_date).toLocaleDateString(undefined, { timeZone: "UTC" }) : "N/A"}</p>
              </div>
            </div>
          </CardContent>
        </Card>
        <Card className="bg-white/50 dark:bg-slate-900/50 backdrop-blur-sm">
          <CardContent className="pt-6">
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-amber-50 dark:bg-amber-950"><Clock className="h-4 w-4 text-amber-600" /></div>
              <div>
                <p className="text-xs text-muted-foreground font-medium">Statute of Limitations</p>
                <p className="text-sm font-bold">{c.sol_date ? new Date(c.sol_date).toLocaleDateString(undefined, { timeZone: "UTC" }) : "N/A"}</p>
              </div>
            </div>
          </CardContent>
        </Card>
        <Card className="bg-white/50 dark:bg-slate-900/50 backdrop-blur-sm">
          <CardContent className="pt-6">
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-950"><DollarSign className="h-4 w-4 text-emerald-600" /></div>
              <div>
                <p className="text-xs text-muted-foreground font-medium">Case Value</p>
                <p className="text-sm font-bold">${Number(c.total_case_value || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
              </div>
            </div>
          </CardContent>
        </Card>
        <Card className="bg-white/50 dark:bg-slate-900/50 backdrop-blur-sm">
          <CardContent className="pt-6">
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-purple-50 dark:bg-purple-950"><MapPin className="h-4 w-4 text-purple-600" /></div>
              <div>
                <p className="text-xs text-muted-foreground font-medium">Jurisdiction</p>
                <p className="text-sm font-bold">{c.jurisdiction || "N/A"}</p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Description */}
      {c.description && (
        <Card>
          <CardHeader>
            <CardTitle className="text-lg">Case Description</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-muted-foreground leading-relaxed">{c.description}</p>
          </CardContent>
        </Card>
      )}

      <div className="grid gap-6 md:grid-cols-2">
        {/* Parties */}
        <Card className="bg-card shadow-sm">
          <CardHeader>
            <CardTitle className="text-lg flex items-center gap-2">
              <Users className="h-5 w-5 text-primary" /> Case Parties
            </CardTitle>
            <CardDescription>People and entities involved in this case</CardDescription>
          </CardHeader>
          <CardContent>
            {c.parties && c.parties.length > 0 ? (
              <div className="space-y-3">
                {c.parties.map((party: any) => (
                  <div key={party.id} className="flex items-center justify-between p-3 rounded-lg border border-slate-100 dark:border-slate-800">
                    <div>
                      <p className="text-sm font-bold">{party.name}</p>
                      <p className="text-xs text-muted-foreground">{party.email}</p>
                    </div>
                    <Badge variant="outline" className="capitalize text-xs">{party.role_in_case}</Badge>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-sm text-muted-foreground italic text-center py-6">No parties listed.</p>
            )}
          </CardContent>
        </Card>

        {/* Quick Navigation */}
        <Card className="bg-card shadow-sm">
          <CardHeader>
            <CardTitle className="text-lg flex items-center gap-2">
              <FileText className="h-5 w-5 text-primary" /> Related
            </CardTitle>
            <CardDescription>View your document and invoice libraries</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <Button variant="outline" className="w-full justify-between" asChild>
              <Link href="/dashboard/client/documents">
                <span className="flex items-center gap-2"><FileText className="h-4 w-4" /> View My Documents</span>
                <ChevronRight className="h-4 w-4" />
              </Link>
            </Button>
            <Button variant="outline" className="w-full justify-between" asChild>
              <Link href="/dashboard/client/invoices">
                <span className="flex items-center gap-2"><DollarSign className="h-4 w-4" /> View My Invoices</span>
                <ChevronRight className="h-4 w-4" />
              </Link>
            </Button>
            <Button variant="outline" className="w-full justify-between" asChild>
              <Link href="/dashboard/client/signatures">
                <span className="flex items-center gap-2"><Scale className="h-4 w-4" /> Pending Signatures</span>
                <ChevronRight className="h-4 w-4" />
              </Link>
            </Button>
          </CardContent>
        </Card>
      </div>

      {/* Timeline */}
      {c.timeline && c.timeline.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="text-lg flex items-center gap-2">
              <Clock className="h-5 w-5 text-primary" /> Case Timeline
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="relative pl-6 space-y-6">
              {c.timeline.map((event: any, i: number, timeline) => (
                <div key={event.id || i} className="relative">
                  <div className="absolute -left-6 top-1 h-3 w-3 rounded-full bg-primary border-2 border-white dark:border-slate-900" />
                  {i < timeline.length - 1 && (
                    <div className="absolute -left-[18px] top-4 h-full w-0.5 bg-slate-200 dark:bg-slate-700" />
                  )}
                  <div>
                    <p className="text-sm font-bold text-slate-900 dark:text-white">{event.title || event.event}</p>
                    <p className="text-xs text-muted-foreground mt-0.5">{event.description}</p>
                    <p className="text-xs text-muted-foreground mt-1">
                      {event.created_at ? new Date(event.created_at).toLocaleDateString() : ""}
                      {event.user && ` · ${event.user.name || event.user.first_name}`}
                    </p>
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  )
}
