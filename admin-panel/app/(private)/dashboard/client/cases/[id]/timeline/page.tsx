"use client"

import { Button } from "@/components/ui/button"
import Link from "next/link"
import { useParams } from "next/navigation"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Scale, CheckCircle2, Clock, FileText, MessageSquare, AlertCircle, Loader2 } from "lucide-react"
import { cn } from "@/lib/utils"
import { useGetClientCaseByIdQuery } from "@/store/api/casesApiSlice"
import { format } from "date-fns"

const getEventIcon = (type: string) => {
  switch ((type || "activity").toLowerCase()) {
    case 'milestone': return { icon: CheckCircle2, color: "text-emerald-500", bg: "bg-emerald-50" };
    case 'document': return { icon: FileText, color: "text-blue-500", bg: "bg-blue-50" };
    case 'legal': return { icon: Scale, color: "text-purple-500", bg: "bg-purple-50" };
    case 'communication': return { icon: MessageSquare, color: "text-indigo-500", bg: "bg-indigo-50" };
    default: return { icon: Clock, color: "text-amber-500", bg: "bg-amber-50" };
  }
}

export default function CaseTimelinePage() {
  const { id } = useParams()
  const caseId = parseInt(id as string)
  
  const { data: caseData, isLoading, error, refetch } = useGetClientCaseByIdQuery(caseId)

  if (isLoading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[400px] space-y-4">
        <Loader2 className="w-10 h-10 text-emerald-600 animate-spin" />
        <p className="text-slate-500 animate-pulse">Loading case journey...</p>
      </div>
    )
  }

  if (error || !caseData) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[400px] text-center p-6">
        <AlertCircle className="w-12 h-12 text-red-500 mb-4" />
        <h2 className="text-xl font-bold text-slate-900">Oops! Failed to load journey</h2>
        <p className="text-slate-500 mt-2">We couldn&apos;t retrieve the timeline for this case. Please try again.</p><Button onClick={() => refetch()}>Try again</Button>
      </div>
    )
  }

  const timelineEvents = caseData.timeline || []

  return (
    <div className="max-w-4xl mx-auto py-10 px-6 animate-in slide-in-from-bottom duration-700">
      <Button variant="outline" asChild><Link href={`/dashboard/client/cases/${caseId}`}>Back to case</Link></Button>
      <div className="mb-10 text-center">
        <h1 className="text-4xl font-black text-slate-900 dark:text-white tracking-tight">Case Journey</h1>
        <p className="text-slate-500 dark:text-slate-400 mt-2 text-lg">Timeline and milestones for {caseData.case_number}</p>
      </div>

      {timelineEvents.length > 0 ? (
        <div className="relative space-y-8 before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-slate-200 before:to-transparent">
          {timelineEvents.map((event: any, index: number) => {
            const { icon: Icon, color, bg } = getEventIcon(event.event_type)
            const eventDate = format(new Date(event.created_at), "MMM dd, yyyy")
            
            return (
              <div key={event.id} className="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group">
                {/* Icon */}
                <div className={cn(
                  "flex items-center justify-center w-10 h-10 rounded-full border border-white dark:border-slate-900 shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 z-10",
                  bg
                )}>
                  <Icon className={cn("h-5 w-5", color)} />
                </div>

                {/* Content */}
                <Card className="w-[calc(100%-4rem)] md:w-[45%] p-1 hover:shadow-xl transition-shadow duration-300 border-slate-100 dark:border-slate-800">
                  <div className={cn(
                    "h-1.5 w-full rounded-t-lg bg-emerald-400"
                  )} />
                  <CardHeader className="pb-2">
                    <div className="flex items-center justify-between mb-1">
                      <time className="text-xs font-bold text-slate-400 uppercase tracking-widest">{eventDate}</time>
                      <Badge className="text-[10px] font-bold uppercase bg-emerald-500 hover:bg-emerald-600">
                        Recorded
                      </Badge>
                    </div>
                    <CardTitle className="text-lg font-bold">{event.title}</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <p className="text-sm text-slate-500 leading-relaxed">{event.description}</p>
                  </CardContent>
                </Card>
              </div>
            )
          })}
        </div>
      ) : (
        <div className="text-center py-20 bg-slate-50 rounded-2xl border-2 border-dashed border-slate-200">
          <Clock className="w-12 h-12 text-slate-300 mx-auto mb-4" />
          <h3 className="text-lg font-semibold text-slate-900">No activity recorded</h3>
          <p className="text-slate-500 max-w-xs mx-auto mt-2">
            Updates recorded for this case will appear here.
          </p>
        </div>
      )}

      <div className="mt-16 text-center">
        <p className="text-sm text-slate-400 italic">
          Note: Dates and statuses are subject to change based on litigation progress.
        </p>
      </div>
    </div>
  )
}
