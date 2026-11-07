"use client"

import { useState } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { AuthenticatedDocumentPreview } from "@/components/AuthenticatedDocumentPreview"
import { useGetDocumentsQuery, useUploadDocumentMutation } from "@/store/api/billingApiSlice"
import { useGetCasesQuery } from "@/store/api/casesApiSlice"
import type { Document } from "@/store/api/documentsApiSlice"

export default function ProviderDocumentsPage() {
  const [caseId, setCaseId] = useState("")
  const [caseSearch, setCaseSearch] = useState("")
  const { data: cases, isFetching: loadingCases, isError: casesFailed, refetch: retryCases } = useGetCasesQuery({ search: caseSearch, per_page: 50 })
  const [page, setPage] = useState(1)
  const [message, setMessage] = useState("")
  const [error, setError] = useState("")
  const { currentData: data, isFetching, isError, refetch } = useGetDocumentsQuery({ page, per_page: 10 })
  const [upload, { isLoading: uploading }] = useUploadDocumentMutation()
  const documents: Document[] = data?.data?.documents || []
  const pagination = data?.data?.pagination

  async function uploadFile(event: React.ChangeEvent<HTMLInputElement>) {
    const input = event.currentTarget
    const file = input.files?.[0]
    if (!file) return
    setMessage(""); setError("")
    if (file.size > 20 * 1024 * 1024) { setError("Choose a file no larger than 20 MB."); input.value = ""; return }
    const body = new FormData()
    body.append("file", file); body.append("title", file.name)
    body.append("metadata", JSON.stringify({ category: "clinical_record", ...(caseId ? { case_id: Number(caseId) } : {}) }))
    try {
      await upload(body).unwrap()
      setPage(1); setMessage(`${file.name} uploaded. No OCR or insurer submission was started.`)
    } catch (err: any) {
      setError(Object.values(err.data?.errors || {}).flat().join(" ") || err.data?.message || "Upload failed. Please choose the file again to retry.")
    } finally { input.value = "" }
  }

  return <div className="space-y-6">
    <div><h1 className="text-3xl font-bold text-primary">Clinical Documents</h1><p className="text-muted-foreground">Upload and review documents available to your organization.</p></div>
    <Card><CardHeader><CardTitle>Upload a document</CardTitle></CardHeader><CardContent className="space-y-3">
      <Label htmlFor="document-case-search">Find a patient case (optional)</Label>
      <Input id="document-case-search" value={caseSearch} onChange={e => setCaseSearch(e.target.value)} placeholder="Search case number or title" disabled={uploading} />
      <Label htmlFor="document-case">Link upload to case</Label>
      <select id="document-case" className="w-full rounded-md border bg-background p-2" disabled={uploading || loadingCases || casesFailed} value={caseId} onChange={e => setCaseId(e.target.value)}>
        <option value="">No case link</option>
        {caseId && !cases?.data.some(c => String(c.id) === caseId) && <option value={caseId}>Selected case #{caseId}</option>}
        {cases?.data.map(c => <option key={c.id} value={c.id}>{c.case_number} — {c.title}</option>)}
      </select>
      {casesFailed && <p role="alert">Could not load cases. <Button variant="outline" onClick={() => retryCases()}>Retry cases</Button></p>}
      <Label htmlFor="clinical-file">PDF, JPG, PNG, DOC or DOCX, up to 20 MB</Label>
      <Input id="clinical-file" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" disabled={uploading || loadingCases || casesFailed} onChange={uploadFile} />
      <p className="text-sm text-muted-foreground">Uploaded files are available to authorized users. Uploading does not start OCR or submit a claim.</p>
      {uploading && <p role="status">Uploading…</p>}{message && <p role="status">{message}</p>}{error && <p role="alert" className="text-destructive">{error}</p>}
    </CardContent></Card>
    <Card><CardHeader><CardTitle>Document library</CardTitle></CardHeader><CardContent className="space-y-4">
      {isError ? <p role="alert">Could not load documents. <Button variant="outline" onClick={() => refetch()}>Try again</Button></p> : isFetching ? <p role="status">Loading documents…</p> : documents.length === 0 ? <p>No documents available.</p> : documents.map(doc => <div key={doc.id} className="flex items-center justify-between gap-3 rounded-md border p-4">
        <div className="min-w-0"><p className="font-medium break-words">{doc.title || doc.original_name}</p><p className="text-sm text-muted-foreground">{new Date(doc.created_at).toLocaleDateString()} · {(doc.size / 1024).toFixed(1)} KB</p><p className="text-sm">Case: {doc.metadata?.case_id ? cases?.data.find(c => c.id === Number(doc.metadata?.case_id))?.case_number || `Linked case #${doc.metadata.case_id}` : "Not linked"}</p><p className="text-sm">OCR: {(doc.ocr_status || "not_processed").replaceAll("_", " ")}</p></div>
        <AuthenticatedDocumentPreview id={doc.id} title={doc.title || doc.original_name} />
      </div>)}
      <div className="flex items-center justify-between gap-3"><Button variant="outline" disabled={page <= 1 || isFetching} onClick={() => setPage(p => p - 1)}>Previous</Button><span>Page {page} of {pagination?.last_page || 1}</span><Button variant="outline" disabled={isFetching || isError || !pagination || page >= pagination.last_page} onClick={() => setPage(p => p + 1)}>Next</Button></div>
    </CardContent></Card>
  </div>
}
