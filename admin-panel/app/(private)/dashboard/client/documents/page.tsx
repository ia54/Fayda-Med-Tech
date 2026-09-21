"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Skeleton } from "@/components/ui/skeleton"
import { FileText, Upload, Download, Eye, Search, PenTool, File, Image, FileType2 } from "lucide-react"
import { useGetClientDocumentsQuery } from "@/store/api/billingApiSlice"
import Link from "next/link"
import { cn } from "@/lib/utils"

const statusBadge: Record<string, { label: string; class: string }> = {
  draft: { label: "Draft", class: "bg-slate-100 text-slate-600 border-slate-200" },
  sent_for_signature: { label: "Pending Signature", class: "bg-amber-100 text-amber-700 border-amber-200" },
  signed: { label: "Signed", class: "bg-emerald-100 text-emerald-700 border-emerald-200" },
  cancelled: { label: "Cancelled", class: "bg-red-100 text-red-600 border-red-200" },
}

const signatureStatusBadge: Record<string, { label: string; class: string }> = {
  not_sent: { label: "Not Sent", class: "bg-slate-50 text-slate-500" },
  pending: { label: "Awaiting Signature", class: "bg-amber-50 text-amber-700" },
  completed: { label: "Completed", class: "bg-emerald-50 text-emerald-700" },
  declined: { label: "Declined", class: "bg-red-50 text-red-600" },
}

function getFileIcon(mimeType: string) {
  if (mimeType?.includes("pdf")) return <FileType2 className="h-5 w-5 text-red-500" />
  if (mimeType?.includes("image")) return <Image className="h-5 w-5 text-blue-500" />
  return <File className="h-5 w-5 text-slate-500" />
}

export default function ClientDocumentsPage() {
  const [searchTerm, setSearchTerm] = useState("")
  const { data, isLoading } = useGetClientDocumentsQuery({ per_page: 20 })

  const documents = data?.data?.documents || []

  const filteredDocs = documents.filter((doc: any) =>
    doc.title?.toLowerCase().includes(searchTerm.toLowerCase()) ||
    doc.original_name?.toLowerCase().includes(searchTerm.toLowerCase())
  )

  return (
    <div className="space-y-6 animate-in fade-in duration-500">
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-3">
            <FileText className="h-8 w-8 text-primary" />
            My Documents
          </h1>
          <p className="text-muted-foreground mt-1">
            View, upload, and sign documents related to your cases.
          </p>
        </div>
        <Button asChild className="bg-primary hover:bg-primary/90 shadow-lg shadow-primary/20">
          <Link href="/dashboard/client/documents/upload">
            <Upload className="h-4 w-4 mr-2" />
            Upload Document
          </Link>
        </Button>
      </div>

      <Card className="bg-card/80 backdrop-blur-sm border-border/50">
        <CardHeader>
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Search documents..."
              className="pl-10"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-4">
              {[1, 2, 3, 4].map((i) => (
                <Skeleton key={i} className="h-20 w-full rounded-xl" />
              ))}
            </div>
          ) : filteredDocs.length === 0 ? (
            <div className="text-center py-16">
              <FileText className="h-12 w-12 mx-auto text-slate-300 mb-4" />
              <h3 className="text-lg font-semibold text-slate-600 dark:text-slate-300">No documents found</h3>
              <p className="text-sm text-slate-400 mt-1">Upload your medical records or other documents.</p>
              <Button className="mt-4" asChild>
                <Link href="/dashboard/client/documents/upload"><Upload className="h-4 w-4 mr-2" /> Upload Document</Link>
              </Button>
            </div>
          ) : (
            <div className="space-y-3">
              {filteredDocs.map((doc: any) => {
                const docStatus = statusBadge[doc.document_status] || statusBadge.draft
                const sigStatus = signatureStatusBadge[doc.signature_status] || signatureStatusBadge.not_sent
                const needsSignature = doc.signature_status === "pending" || doc.document_status === "sent_for_signature"

                return (
                  <div
                    key={doc.id}
                    className={cn(
                      "flex items-center justify-between p-4 border rounded-xl transition-all duration-200 hover:shadow-md",
                      needsSignature
                        ? "border-amber-200 bg-amber-50/50 dark:bg-amber-950/20 dark:border-amber-800"
                        : "border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900/50"
                    )}
                  >
                    <div className="flex items-center gap-4 flex-1 min-w-0">
                      <div className="p-2.5 rounded-lg bg-slate-100 dark:bg-slate-800">
                        {getFileIcon(doc.mime_type)}
                      </div>
                      <div className="min-w-0 flex-1">
                        <p className="text-sm font-bold text-slate-900 dark:text-white truncate">{doc.title || doc.original_name}</p>
                        <div className="flex items-center gap-2 mt-1 flex-wrap">
                          <span className="text-xs text-muted-foreground">
                            {new Date(doc.created_at).toLocaleDateString()}
                          </span>
                          <span className="text-xs text-muted-foreground">·</span>
                          <span className="text-xs text-muted-foreground">
                            {(doc.size / 1024).toFixed(0)} KB
                          </span>
                          <Badge variant="outline" className={cn("text-[10px] uppercase font-bold", docStatus.class)}>
                            {docStatus.label}
                          </Badge>
                          {doc.signature_status !== "not_sent" && (
                            <Badge variant="outline" className={cn("text-[10px] uppercase font-bold", sigStatus.class)}>
                              {sigStatus.label}
                            </Badge>
                          )}
                        </div>
                      </div>
                    </div>
                    <div className="flex items-center gap-2 ml-4 shrink-0">
                      {needsSignature && (
                        <Button size="sm" className="bg-amber-600 hover:bg-amber-700 shadow-lg shadow-amber-600/20">
                          <PenTool className="h-3 w-3 mr-1" /> Sign
                        </Button>
                      )}
                      <Button variant="ghost" size="icon" className="text-muted-foreground hover:text-primary" title="Preview">
                        <Eye className="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                )
              })}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
