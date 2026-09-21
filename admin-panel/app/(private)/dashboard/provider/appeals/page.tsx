"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Badge } from "@/components/ui/badge"
import { Textarea } from "@/components/ui/textarea"
import { Search, Bot, Send, FileText, Clock, CheckCircle, Loader2, Download } from "lucide-react"
import { useGetAppealsQuery } from "@/store/api/billingApiSlice"
import { Skeleton } from "@/components/ui/skeleton"

export default function AppealsPage() {
  const [searchTerm, setSearchTerm] = useState("")
  const [selectedAppealId, setSelectedAppealId] = useState<number | null>(null)

  // Fetch real data
  const { data: appealsData, isLoading } = useGetAppealsQuery({
    search: searchTerm
  })

  const appeals = appealsData?.data?.data || []
  const selectedAppeal = appeals.find((a: any) => a.id === selectedAppealId)

  const getStatusColor = (status: string) => {
    switch (status.toLowerCase()) {
      case "accepted":
      case "approved":
        return "bg-emerald-100 text-emerald-800 border-emerald-200"
      case "sent":
      case "submitted":
        return "bg-blue-100 text-blue-800 border-blue-200"
      case "draft":
        return "bg-amber-100 text-amber-800 border-amber-200"
      case "rejected":
        return "bg-red-100 text-red-800 border-red-200"
      default:
        return "bg-slate-100 text-slate-800 border-slate-200"
    }
  }

  const getStatusIcon = (status: string) => {
    switch (status.toLowerCase()) {
      case "accepted":
      case "approved":
        return <CheckCircle className="h-3 w-3" />
      case "sent":
      case "submitted":
        return <Send className="h-3 w-3" />
      default:
        return <Clock className="h-3 w-3" />
    }
  }

  return (
    <div className="space-y-8">
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 className="text-3xl font-bold text-primary flex items-center gap-2">
            <Bot className="h-8 w-8 text-primary" />
            Denials & AI Appeals
          </h1>
          <p className="text-muted-foreground">Automated claim recovery and appeal letter generation</p>
        </div>
        <Button className="bg-primary hover:bg-primary/90">
          <Bot className="h-4 w-4 mr-2" />
          Generate AI Appeal
        </Button>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Appeals Queue */}
        <Card className="bg-card/50 backdrop-blur-sm border-border/50 shadow-lg">
          <CardHeader>
            <CardTitle className="text-primary">Appeals Queue</CardTitle>
            <CardDescription>Manage denial appeals and AI-generated letters</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="flex items-center space-x-2 mb-6">
              <div className="relative flex-1">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder="Search by claim ID or patient..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="pl-10 bg-white/50"
                />
              </div>
            </div>

            <div className="space-y-4">
              {isLoading ? (
                <div className="space-y-4">
                  {[1, 2, 3].map(i => <Skeleton key={i} className="h-24 w-full rounded-xl" />)}
                </div>
              ) : (
                appeals.map((appeal: any) => (
                  <Card
                    key={appeal.id}
                    className={`cursor-pointer hover:shadow-md transition-all border-border/40 bg-white/40 group ${
                      selectedAppealId === appeal.id ? "ring-2 ring-primary bg-primary/5" : ""
                    }`}
                    onClick={() => setSelectedAppealId(appeal.id)}
                  >
                    <CardContent className="p-4">
                      <div className="flex items-center justify-between mb-3">
                        <div className="flex items-center space-x-2">
                          <span className="font-bold text-primary">#{appeal.appeal_number}</span>
                          <Badge variant="outline" className="text-primary border-primary/30 bg-primary/5">
                            <Bot className="h-3 w-3 mr-1" />
                            AI Generated
                          </Badge>
                        </div>
                        <Badge className={getStatusColor(appeal.status)}>
                          <div className="flex items-center gap-1.5">
                            {getStatusIcon(appeal.status)}
                            <span className="capitalize">{appeal.status}</span>
                          </div>
                        </Badge>
                      </div>
                      <div className="space-y-1 text-sm">
                        <p className="flex justify-between text-muted-foreground">
                          <span>Patient / Case:</span> 
                          <span className="font-semibold text-emerald-950 dark:text-white">
                            {appeal.invoice?.case?.title || 'General'}
                          </span>
                        </p>
                        <p className="flex justify-between text-muted-foreground">
                          <span>Category:</span> 
                          <span className="text-slate-700 dark:text-slate-300 font-medium">{appeal.reason_category}</span>
                        </p>
                        <p className="flex justify-between text-muted-foreground">
                          <span>Billed Amount:</span> 
                          <span className="font-bold text-emerald-600">${Number(appeal.invoice?.amount || 0).toLocaleString()}</span>
                        </p>
                      </div>
                    </CardContent>
                  </Card>
                ))
              )}
              {!isLoading && appeals.length === 0 && (
                <div className="text-center py-20 bg-muted/20 rounded-xl border-dashed border-2 border-border/50">
                  <FileText className="h-8 w-8 text-muted-foreground mx-auto mb-3 opacity-50" />
                  <p className="text-muted-foreground italic">No appeals in the queue.</p>
                </div>
              )}
            </div>
          </CardContent>
        </Card>

        {/* Appeal Letter Editor */}
        <Card className="bg-card/50 backdrop-blur-sm border-border/50 shadow-lg">
          <CardHeader>
            <CardTitle className="text-primary">Appeal Document Editor</CardTitle>
            <CardDescription>
              {selectedAppeal ? `Editing appeal for #${selectedAppeal.appeal_number}` : "Select an appeal from the queue to view details"}
            </CardDescription>
          </CardHeader>
          <CardContent>
            {selectedAppeal ? (
              <div className="space-y-6">
                <div className="p-4 bg-primary/10 rounded-xl border border-primary/20">
                  <div className="flex items-center space-x-2 mb-2 text-primary">
                    <Bot className="h-4 w-4" />
                    <span className="font-bold">LLM Generated Draft</span>
                  </div>
                  <p className="text-xs text-muted-foreground leading-relaxed">
                    This document was automatically generated using AI analysis of the denial reasons and provided clinical documentation. Please review for accuracy before submission.
                  </p>
                </div>

                <Textarea
                  placeholder="Appeal letter content will appear here..."
                  className="min-h-[450px] bg-white/50 font-serif leading-relaxed text-slate-800 dark:text-slate-200"
                  defaultValue={selectedAppeal.content}
                />

                <div className="flex flex-wrap gap-2 pt-2">
                  <Button className="bg-primary hover:bg-primary/90">
                    <Send className="h-4 w-4 mr-2" />
                    Finalize & Send
                  </Button>
                  <Button variant="outline" className="border-border/40 hover:bg-primary/5">
                    <Download className="h-4 w-4 mr-2" />
                    Download PDF
                  </Button>
                  <Button variant="ghost" className="text-muted-foreground hover:text-primary">
                    <Bot className="h-4 w-4 mr-2" />
                    Regenerate Draft
                  </Button>
                </div>
              </div>
            ) : (
              <div className="text-center py-40 text-muted-foreground bg-muted/10 rounded-xl border-dashed border-2 border-border/30">
                <FileText className="h-16 w-16 mx-auto mb-4 opacity-20" />
                <p className="font-medium italic">Select an appeal record to begin editing</p>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
