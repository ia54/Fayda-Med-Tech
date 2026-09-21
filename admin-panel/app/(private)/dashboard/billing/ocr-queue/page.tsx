"use client"

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Zap, Clock, CheckCircle2, FileText, Search, Filter, RefreshCw, Eye } from "lucide-react"
import { useGetDocumentsQuery } from "@/store/api/billingApiSlice"
import { LoadingSpinner } from "@/components/loading-spinner"
import { Input } from "@/components/ui/input"
import { useState } from "react"
import { useToast } from "@/hooks/use-toast"

export default function OcrQueuePage() {
  const { data: documentsResponse, isLoading, refetch, isFetching } = useGetDocumentsQuery({ ocr_status: "processing,processed,failed" })
  const { toast } = useToast()
  const [searchTerm, setSearchTerm] = useState("")

  const handleRefresh = () => {
    refetch()
    toast({ title: "Refreshing Queue", description: "Checking for OCR updates..." })
  }

  if (isLoading) return <LoadingSpinner />

  const documents = documentsResponse?.data?.data || []
  const filteredDocs = documents.filter((doc: any) => 
    doc.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    doc.case?.title?.toLowerCase().includes(searchTerm.toLowerCase())
  )

  const getStatusBadge = (status: string) => {
    switch (status?.toLowerCase()) {
      case 'processed': return <Badge className="bg-emerald-600">Processed</Badge>
      case 'processing': return <Badge className="bg-blue-500 animate-pulse">Processing</Badge>
      case 'failed': return <Badge variant="destructive">Failed</Badge>
      default: return <Badge variant="secondary">Pending</Badge>
    }
  }

  return (
    <div className="space-y-6 p-6">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-primary flex items-center gap-3">
            <Zap className="h-8 w-8 text-amber-500" />
            AI OCR Queue
          </h1>
          <p className="text-muted-foreground mt-1">Automated text extraction from scanned medical records and bills</p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" size="sm" onClick={handleRefresh} disabled={isFetching}>
            <RefreshCw className={`h-4 w-4 mr-2 ${isFetching ? 'animate-spin' : ''}`} />
            Refresh Queue
          </Button>
        </div>
      </div>

      <Card className="border-border/40 bg-white/50 backdrop-blur-sm">
        <CardHeader className="pb-3 border-b">
          <div className="flex flex-col sm:flex-row sm:items-center gap-4">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input 
                placeholder="Search by file name or case..." 
                className="pl-10" 
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
              />
            </div>
            <div className="flex gap-2">
              <Button variant="outline" size="icon">
                <Filter className="h-4 w-4" />
              </Button>
            </div>
          </div>
        </CardHeader>
        <CardContent className="p-0">
          <div className="divide-y divide-border/40">
            {filteredDocs.length > 0 ? filteredDocs.map((doc: any) => (
              <div key={doc.id} className="p-4 hover:bg-slate-50/50 transition-colors group">
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                  <div className="flex items-start gap-4">
                    <div className="p-3 rounded-xl bg-slate-100 text-slate-600 group-hover:bg-primary/10 group-hover:text-primary transition-colors">
                      <FileText className="h-6 w-6" />
                    </div>
                    <div>
                      <div className="flex items-center gap-2 mb-1">
                        <h3 className="font-bold text-slate-900 truncate max-w-[300px]">{doc.name}</h3>
                        {getStatusBadge(doc.ocr_status)}
                      </div>
                      <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                        <span className="flex items-center gap-1 font-medium text-slate-700">
                          Case: {doc.case?.title || 'Unassigned'}
                        </span>
                        <span className="flex items-center gap-1">
                          <Clock className="h-3 w-3" /> {new Date(doc.created_at).toLocaleDateString()}
                        </span>
                        <span className="text-slate-400 capitalize">{doc.category || 'Uncategorized'}</span>
                      </div>
                    </div>
                  </div>
                  
                  <div className="flex items-center gap-2">
                    <Button variant="outline" size="sm" className="h-9">
                      <Eye className="h-4 w-4 mr-2" />
                      View Text
                    </Button>
                    <Button size="sm" className="h-9 bg-primary" disabled={doc.ocr_status === 'processing'}>
                      Validate Data
                    </Button>
                  </div>
                </div>
              </div>
            )) : (
              <div className="py-20 text-center space-y-3 opacity-50">
                <FileText className="h-12 w-12 mx-auto text-slate-300" />
                <p className="text-sm text-muted-foreground">No documents found in the processing queue</p>
              </div>
            )}
          </div>
        </CardContent>
      </Card>

      {/* OCR Performance Snapshot */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
        {[
          { label: "Extraction Accuracy", value: "98.4%", icon: CheckCircle2, color: "text-emerald-600" },
          { label: "Avg. Processing Time", value: "4.2s", icon: Clock, color: "text-blue-600" },
          { label: "Manual Review Needed", value: "12", icon: AlertTriangle, color: "text-amber-600" },
        ].map((stat, i) => (
          <Card key={i} className="bg-white/50 border-border/40 shadow-sm">
            <CardContent className="p-4 flex items-center justify-between">
              <div>
                <p className="text-xs text-muted-foreground font-medium uppercase tracking-wider">{stat.label}</p>
                <h4 className="text-xl font-bold mt-1">{stat.value}</h4>
              </div>
              <stat.icon className={`h-8 w-8 opacity-20 ${stat.color}`} />
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  )
}

import { AlertTriangle } from "lucide-react"
