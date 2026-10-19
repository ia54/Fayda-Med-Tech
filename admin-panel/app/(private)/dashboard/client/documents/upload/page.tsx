"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Upload, ArrowLeft, FileUp, CheckCircle2, Loader2 } from "lucide-react"
import { useGetClientCasesQuery } from "@/store/api/casesApiSlice"
import { useUploadDocumentMutation } from "@/store/api/documentsApiSlice"
import { useToast } from "@/hooks/use-toast"
import Link from "next/link"
import { useRouter } from "next/navigation"

export default function ClientDocumentUploadPage() {
  const { toast } = useToast()
  const router = useRouter()
  const [title, setTitle] = useState("")
  const [selectedCase, setSelectedCase] = useState("")
  const [file, setFile] = useState<File | null>(null)
  const [dragActive, setDragActive] = useState(false)

  const [caseSearch, setCaseSearch] = useState("")
  const [error, setError] = useState("")
  const { data: casesData, isFetching: casesLoading, isError: casesFailed, refetch } = useGetClientCasesQuery({search: caseSearch, per_page: 50})
  const [uploadDocument, { isLoading: isUploading }] = useUploadDocumentMutation()

  const cases = casesData?.data || []

  const handleDrag = (e: React.DragEvent) => {
    e.preventDefault()
    e.stopPropagation()
    if (e.type === "dragenter" || e.type === "dragover") {
      setDragActive(true)
    } else if (e.type === "dragleave") {
      setDragActive(false)
    }
  }

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault()
    e.stopPropagation()
    setDragActive(false)
    if (isUploading) return
    if (e.dataTransfer.files?.[0]) {
      setFile(e.dataTransfer.files[0])
      if (!title) setTitle(e.dataTransfer.files[0].name.replace(/\.[^/.]+$/, ""))
    }
  }

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files?.[0]) {
      setFile(e.target.files[0])
      if (!title) setTitle(e.target.files[0].name.replace(/\.[^/.]+$/, ""))
    }
  }

  const handleUpload = async () => {
    setError("")
    if (isUploading) return
    if (!file || !title.trim()) {
      toast({ title: "Missing fields", description: "Please provide a title and select a file.", variant: "destructive" })
      return
    }

    if (file.size > 20 * 1024 * 1024) { setError("Choose a file no larger than 20 MB."); return }
    if (!/\.(pdf|jpe?g|png|docx?)$/i.test(file.name)) { setError("Choose a PDF, JPG, PNG, DOC or DOCX file."); return }

    const formData = new FormData()
    formData.append("file", file)
    formData.append("title", title.trim())
    if (selectedCase && selectedCase !== "none") {
      formData.append("metadata[case_id]", selectedCase)
    }

    try {
      await uploadDocument(formData).unwrap()
      toast({ title: "Success", description: "Document uploaded successfully!" })
      router.push("/dashboard/client/documents")
    } catch (err: any) {
      setError(Object.values(err.data?.errors || {}).flat().join(" ") || err.data?.message || "Upload failed. Please try again.")
      toast({
        title: "Upload failed",
        description: err.data?.message || "Failed to upload document. Please try again.",
        variant: "destructive",
      })
    }
  }

  return (
    <div className="space-y-6 animate-in fade-in duration-500 max-w-2xl mx-auto">
      <div className="flex items-center gap-4">
        <Button variant="ghost" size="icon" asChild>
          <Link href="/dashboard/client/documents"><ArrowLeft className="h-5 w-5" /></Link>
        </Button>
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Upload Document</h1>
          <p className="text-sm text-muted-foreground">Upload medical records, bills, or other case documents.</p>
        </div>
      </div>

      <Card className="bg-card shadow-lg border-0">
        <div className="h-1 w-full bg-gradient-to-r from-primary to-blue-500" />
        <CardHeader>
          <CardTitle>Document Details</CardTitle>
          <CardDescription>Provide information about your document upload.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          {/* File Drop Zone */}
          <div
            onDragEnter={handleDrag}
            onDragLeave={handleDrag}
            onDragOver={handleDrag}
            onDrop={handleDrop}
            className={`relative border-2 border-dashed rounded-xl p-8 text-center transition-all duration-200 ${
              dragActive
                ? "border-primary bg-primary/5"
                : file
                ? "border-emerald-300 bg-emerald-50/50 dark:bg-emerald-950/20"
                : "border-slate-200 dark:border-slate-700 hover:border-primary/50"
            }`}
          >
            {file ? (
              <div className="flex flex-col items-center gap-3">
                <CheckCircle2 className="h-10 w-10 text-emerald-500" />
                <div>
                  <p className="font-bold text-slate-900 dark:text-white">{file.name}</p>
                  <p className="text-sm text-muted-foreground">{(file.size / 1024 / 1024).toFixed(2)} MB</p>
                </div>
                <Button variant="outline" size="sm" disabled={isUploading} onClick={() => setFile(null)}>
                  Remove
                </Button>
              </div>
            ) : (
              <div className="flex flex-col items-center gap-3">
                <FileUp className="h-10 w-10 text-slate-400" />
                <div>
                  <p className="font-bold text-slate-700 dark:text-slate-300">
                    Drag & drop your file here
                  </p>
                  <p className="text-sm text-muted-foreground mt-1">or click to browse</p>
                </div>
                <Input
                  type="file" aria-label="Choose document" disabled={isUploading}
                  className="absolute inset-0 opacity-0 cursor-pointer"
                  onChange={handleFileSelect}
                  accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                />
              </div>
            )}
          </div>

          {/* Title */}
          <div className="space-y-2">
            <Label htmlFor="title">Document Title *</Label>
            <Input
              id="title" maxLength={255} disabled={isUploading}
              placeholder="e.g. MRI Results - March 2026"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
            />
          </div>

          {/* Case Association */}
          <div className="space-y-2">
            <Label htmlFor="case-search">Find your case (optional)</Label>
            <Input id="case-search" value={caseSearch} disabled={isUploading} onChange={e => setCaseSearch(e.target.value)} placeholder="Search case number or title" />
            <Label htmlFor="upload-case">Associate with Case (Optional)</Label>
            <Select disabled={isUploading || casesLoading || casesFailed} value={selectedCase} onValueChange={setSelectedCase}>
              <SelectTrigger id="upload-case">
                <SelectValue placeholder="Select a case..." />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="none">No specific case</SelectItem>
                {selectedCase && selectedCase !== "none" && !cases.some(c => String(c.id) === selectedCase) && <SelectItem value={selectedCase}>Selected case #{selectedCase}</SelectItem>}
                {cases.map((c: any) => (
                  <SelectItem key={c.id} value={String(c.id)}>
                    {c.case_number} — {c.title}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <p className="text-sm text-muted-foreground">PDF, JPG, PNG, DOC or DOCX, up to 20 MB. Uploading does not submit a claim or request a signature.</p>
          {casesFailed && <p role="alert">Could not load cases. <Button variant="outline" onClick={() => refetch()}>Retry cases</Button></p>}
          {error && <p role="alert" className="text-destructive">{error}</p>}
          <div className="flex gap-3 pt-2">
            <Button variant="outline" asChild className="flex-1">
              <Link href="/dashboard/client/documents">Cancel</Link>
            </Button>
            <Button
              className="flex-1 bg-primary hover:bg-primary/90"
              onClick={handleUpload}
              disabled={isUploading || casesLoading || casesFailed || !file || !title.trim()}
            >
              {isUploading ? (
                <><Loader2 className="h-4 w-4 mr-2 animate-spin" /> Uploading...</>
              ) : (
                <><Upload className="h-4 w-4 mr-2" /> Upload Document</>
              )}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
