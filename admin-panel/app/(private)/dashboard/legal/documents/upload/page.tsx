"use client"

import { useState, useRef } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Progress } from "@/components/ui/progress"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Upload, FileText, CheckCircle, Clock, Trash2, Loader2, FolderOpen, Scale, AlertCircle } from "lucide-react"
import { useGetDocumentsQuery, useUploadDocumentMutation } from "@/store/api/billingApiSlice"
import { useGetCasesQuery } from "@/store/api/casesApiSlice"
import { useToast } from "@/hooks/use-toast"
import { Skeleton } from "@/components/ui/skeleton"
import { cn } from "@/lib/utils"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Label } from "@/components/ui/label"

export default function LegalDocumentUploadPage() {
  const { toast } = useToast()
  const fileInputRef = useRef<HTMLInputElement>(null)
  const [selectedCase, setSelectedCase] = useState<string>("")
  const [documentCategory, setDocumentCategory] = useState<string>("general")
  const [uploadingFiles, setUploadingFiles] = useState<{ name: string; progress: number }[]>([])
  
  const { data: documentsData, isLoading } = useGetDocumentsQuery({})
  const { data: casesData } = useGetCasesQuery({ per_page: 100 })
  const [uploadDocument] = useUploadDocumentMutation()

  const handleFileSelect = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files
    if (!files || files.length === 0) return

    if (!selectedCase) {
      toast({ 
        title: "Selection Required", 
        description: "Please select a case before uploading documents.",
        variant: "destructive" 
      })
      return
    }

    const newUploads = Array.from(files).map(f => ({ name: f.name, progress: 10 }))
    setUploadingFiles(prev => [...prev, ...newUploads])

    for (let i = 0; i < files.length; i++) {
      const file = files[i]
      const formData = new FormData()
      formData.append("file", file)
      formData.append("title", file.name)
      
      // Pass case_id and category in metadata
      const caseInfo = casesData?.data?.find((c: any) => c.id.toString() === selectedCase)
      const metadata = { 
        type: 'legal_document', 
        category: documentCategory,
        case_id: selectedCase,
        case_number: caseInfo?.case_number,
        case_title: caseInfo?.title,
        context: 'firm_upload' 
      }
      formData.append("metadata", JSON.stringify(metadata))

      try {
        await uploadDocument(formData).unwrap()
        setUploadingFiles(prev => prev.filter(f => f.name !== file.name))
        toast({ title: "Upload Success", description: `${file.name} uploaded successfully.` })
      } catch (err: any) {
        setUploadingFiles(prev => prev.filter(f => f.name !== file.name))
        toast({ title: "Upload Failed", description: `Failed to upload ${file.name}`, variant: "destructive" })
      }
    }
    
    if (fileInputRef.current) fileInputRef.current.value = ""
  }

  return (
    <div className="space-y-8">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-primary flex items-center gap-2">
            <Scale className="h-8 w-8 text-emerald-600" />
            Legal Document Management
          </h1>
          <p className="text-muted-foreground">Securely upload and manage legal filings, medical records, and correspondence</p>
        </div>
        <input
          type="file"
          multiple
          className="hidden"
          ref={fileInputRef}
          onChange={handleFileSelect}
        />
        <Button onClick={() => fileInputRef.current?.click()} className="bg-emerald-600 hover:bg-emerald-700">
          <Upload className="h-4 w-4 mr-2" />
          Upload Document
        </Button>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        {/* Upload Configuration */}
        <div className="lg:col-span-1 space-y-4">
          <Card className="bg-card/50 backdrop-blur-sm border-emerald-100">
            <CardHeader>
              <CardTitle className="text-sm font-semibold">Step 1: File Context</CardTitle>
              <CardDescription>Select where this document belongs</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="case-select">Related Case</Label>
                <Select value={selectedCase} onValueChange={setSelectedCase}>
                  <SelectTrigger id="case-select" className="bg-white/50 border-emerald-100">
                    <SelectValue placeholder="Choose a case..." />
                  </SelectTrigger>
                  <SelectContent>
                    {casesData?.data?.map((c: any) => (
                      <SelectItem key={c.id} value={c.id.toString()}>
                        {c.case_number} - {c.title}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <Label htmlFor="category-select">Document Category</Label>
                <Select value={documentCategory} onValueChange={setDocumentCategory}>
                  <SelectTrigger id="category-select" className="bg-white/50 border-emerald-100">
                    <SelectValue placeholder="Select category" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="medical_record">Medical Record</SelectItem>
                    <SelectItem value="police_report">Police Report</SelectItem>
                    <SelectItem value="legal_filing">Legal Filing</SelectItem>
                    <SelectItem value="correspondence">Correspondence</SelectItem>
                    <SelectItem value="settlement_demand">Settlement Demand</SelectItem>
                    <SelectItem value="insurance_policy">Insurance Policy</SelectItem>
                    <SelectItem value="general">General/Other</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              {!selectedCase && (
                <div className="flex items-center gap-2 text-[10px] text-amber-600 bg-amber-50 p-2 rounded-md border border-amber-100">
                  <AlertCircle className="h-3 w-3" />
                  Please select a case to enable uploading.
                </div>
              )}
            </CardContent>
          </Card>
          
          <Card className="bg-card/50 backdrop-blur-sm border-emerald-100">
            <CardHeader>
              <CardTitle className="text-sm font-semibold flex items-center gap-2">
                <CheckCircle className="h-4 w-4 text-emerald-600" />
                Upload Guidelines
              </CardTitle>
            </CardHeader>
            <CardContent className="text-sm space-y-4 text-muted-foreground">
              <p>• Ensure all medical records are clearly legible for AI processing.</p>
              <p>• Settlement letters should include the claim number in the file name.</p>
              <p>• Maximum file size is 50MB per document.</p>
              <p>• Documents are automatically encrypted upon upload.</p>
            </CardContent>
          </Card>
        </div>

        {/* Upload Area */}
        <div className="lg:col-span-2 space-y-6">
          {uploadingFiles.length > 0 && (
            <Card className="border-emerald-200 bg-emerald-50/30 backdrop-blur-sm">
              <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium flex items-center gap-2">
                  <Loader2 className="h-4 w-4 animate-spin text-emerald-600" />
                  Processing {uploadingFiles.length} documents...
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                {uploadingFiles.map((file, idx) => (
                  <div key={idx} className="space-y-1">
                    <div className="flex justify-between text-xs font-medium">
                      <span>{file.name}</span>
                      <span>{file.progress}%</span>
                    </div>
                    <Progress value={file.progress} className="h-1 bg-emerald-100" indicatorClassName="bg-emerald-600" />
                  </div>
                ))}
              </CardContent>
            </Card>
          )}

          <Card 
            className={cn(
              "bg-card/50 backdrop-blur-sm border-dashed border-2 transition-all cursor-pointer group",
              selectedCase 
                ? "border-emerald-200 hover:border-emerald-500" 
                : "border-slate-200 opacity-50 cursor-not-allowed"
            )}
            onClick={() => selectedCase && fileInputRef.current?.click()}
          >
            <CardContent className="py-20 text-center">
              <div className={cn(
                "p-4 rounded-full w-fit mx-auto mb-6 transition-transform",
                selectedCase ? "bg-emerald-100 group-hover:scale-110" : "bg-slate-100"
              )}>
                <FolderOpen className={cn("h-12 w-12", selectedCase ? "text-emerald-600" : "text-slate-400")} />
              </div>
              <h3 className="text-xl font-semibold mb-2">Secure Document Intake</h3>
              <p className="text-muted-foreground text-sm max-w-sm mx-auto">
                {selectedCase 
                  ? "Drag and drop your legal files here, or click to browse." 
                  : "Please select a case above to begin uploading documents."}
              </p>
            </CardContent>
          </Card>
        </div>
      </div>

      {/* Upload History */}
      <Card className="bg-card/80 backdrop-blur-sm border-emerald-100">
        <CardHeader className="flex flex-row items-center justify-between">
          <div>
            <CardTitle>Recent Legal Filings</CardTitle>
            <CardDescription>A centralized history of all documents uploaded to this firm</CardDescription>
          </div>
          <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200">
            {documentsData?.data?.documents?.length || 0} Total Files
          </Badge>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-4">
              {[1, 2, 3, 4].map(i => <Skeleton key={i} className="h-12 w-full" />)}
            </div>
          ) : (
            <div className="rounded-xl border border-emerald-100 overflow-hidden">
              <Table>
                <TableHeader className="bg-emerald-50/50">
                  <TableRow>
                    <TableHead>Filename</TableHead>
                    <TableHead>Case</TableHead>
                    <TableHead>Category</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Date Uploaded</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {documentsData?.data?.documents?.map((doc: any) => (
                    <TableRow key={doc.id} className="hover:bg-emerald-50/30 transition-colors">
                      <TableCell>
                        <div className="flex items-center gap-3">
                          <div className="bg-primary/10 p-2 rounded-md">
                            <FileText className="h-4 w-4 text-primary" />
                          </div>
                          <span className="font-medium text-emerald-950">{doc.original_name}</span>
                        </div>
                      </TableCell>
                      <TableCell>
                        <div className="flex flex-col">
                          <span className="text-xs font-semibold">{doc.metadata?.case_number || 'N/A'}</span>
                          <span className="text-[10px] text-muted-foreground truncate max-w-[150px]">{doc.metadata?.case_title}</span>
                        </div>
                      </TableCell>
                      <TableCell>
                        <Badge variant="secondary" className="bg-slate-100 text-slate-700 capitalize">
                          {(doc.metadata?.category || 'General').replace('_', ' ')}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center gap-2">
                          <div className="h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
                          <span className="text-sm capitalize font-medium">{doc.document_status || 'available'}</span>
                        </div>
                      </TableCell>
                      <TableCell className="text-sm text-muted-foreground">
                        {new Date(doc.created_at).toLocaleString()}
                      </TableCell>
                      <TableCell className="text-right">
                        <Button variant="ghost" size="sm" className="text-muted-foreground hover:text-destructive">
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                  {(!documentsData?.data?.documents || documentsData.data.documents.length === 0) && (
                    <TableRow>
                      <TableCell colSpan={5} className="h-32 text-center text-muted-foreground italic">
                        No legal documents found in your history.
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
