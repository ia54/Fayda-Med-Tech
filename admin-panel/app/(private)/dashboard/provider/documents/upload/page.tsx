"use client"

import { useState, useRef } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Progress } from "@/components/ui/progress"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Upload, FileText, CheckCircle, Trash2, Loader2, FolderOpen, Activity } from "lucide-react"
import { useGetDocumentsQuery, useUploadDocumentMutation } from "@/store/api/billingApiSlice"
import { useToast } from "@/hooks/use-toast"
import { Skeleton } from "@/components/ui/skeleton"

export default function ProviderDocumentUploadPage() {
  const { toast } = useToast()
  const fileInputRef = useRef<HTMLInputElement>(null)
  const [uploadingFiles, setUploadingFiles] = useState<{ name: string; progress: number }[]>([])
  
  const { data: documentsData, isLoading } = useGetDocumentsQuery({})
  const [uploadDocument] = useUploadDocumentMutation()

  const handleFileSelect = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files
    if (!files || files.length === 0) return

    const newUploads = Array.from(files).map(f => ({ name: f.name, progress: 10 }))
    setUploadingFiles(prev => [...prev, ...newUploads])

    for (let i = 0; i < files.length; i++) {
      const file = files[i]
      const formData = new FormData()
      formData.append("file", file)
      formData.append("title", file.name)
      formData.append("metadata", JSON.stringify({ type: 'clinical_record', context: 'provider_portal' }))

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
            <Activity className="h-8 w-8 text-blue-500" />
            Clinical Documentation Intake
          </h1>
          <p className="text-muted-foreground">Upload medical records, Superbills, and patient encounter notes</p>
        </div>
        <input
          type="file"
          multiple
          className="hidden"
          ref={fileInputRef}
          onChange={handleFileSelect}
        />
        <Button onClick={() => fileInputRef.current?.click()} className="bg-primary hover:bg-primary/90">
          <Upload className="h-4 w-4 mr-2" />
          Upload Clinical File
        </Button>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        {/* Upload Area */}
        <div className="lg:col-span-2 space-y-6">
          {uploadingFiles.length > 0 && (
            <Card className="border-blue-200 bg-blue-50/30 backdrop-blur-sm">
              <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium flex items-center gap-2">
                  <Loader2 className="h-4 w-4 animate-spin text-blue-600" />
                  Syncing {uploadingFiles.length} medical records...
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                {uploadingFiles.map((file, idx) => (
                  <div key={idx} className="space-y-1">
                    <div className="flex justify-between text-xs font-medium">
                      <span>{file.name}</span>
                      <span>{file.progress}%</span>
                    </div>
                    <Progress value={file.progress} className="h-1 bg-blue-100" indicatorClassName="bg-blue-600" />
                  </div>
                ))}
              </CardContent>
            </Card>
          )}

          <Card 
            className="bg-card/50 backdrop-blur-sm border-dashed border-2 border-blue-200 hover:border-blue-500 transition-all cursor-pointer group"
            onClick={() => fileInputRef.current?.click()}
          >
            <CardContent className="py-20 text-center">
              <div className="bg-blue-100 p-4 rounded-full w-fit mx-auto mb-6 group-hover:scale-110 transition-transform">
                <FolderOpen className="h-12 w-12 text-blue-600" />
              </div>
              <h3 className="text-xl font-semibold mb-2">Clinical Data Repository</h3>
              <p className="text-muted-foreground text-sm max-w-sm mx-auto">
                Drag patient charts or encounter notes here. Files are automatically processed for medical coding analysis.
              </p>
            </CardContent>
          </Card>
        </div>

        {/* HIPAA Notice */}
        <Card className="bg-card/50 backdrop-blur-sm border-blue-100">
          <CardHeader>
            <CardTitle className="text-sm font-semibold flex items-center gap-2 text-blue-700">
              <CheckCircle className="h-4 w-4" />
              Clinical Guidelines
            </CardTitle>
          </CardHeader>
          <CardContent className="text-sm space-y-4 text-muted-foreground">
            <p>• Ensure patient PHI is clearly visible for OCR accuracy.</p>
            <p>• Categorize files by patient name for faster billing.</p>
            <p>• All uploads are HIPAA-compliant and end-to-end encrypted.</p>
            <p>• Superbills should be uploaded as high-resolution images or PDFs.</p>
          </CardContent>
        </Card>
      </div>

      {/* Upload History */}
      <Card className="bg-card/80 backdrop-blur-sm border-blue-100">
        <CardHeader className="flex flex-row items-center justify-between">
          <div>
            <CardTitle>Clinical Upload History</CardTitle>
            <CardDescription>View and manage recently uploaded medical documentation</CardDescription>
          </div>
          <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200">
            {documentsData?.data?.documents?.length || 0} Files Synced
          </Badge>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-4">
              {[1, 2, 3, 4].map(i => <Skeleton key={i} className="h-12 w-full" />)}
            </div>
          ) : (
            <div className="rounded-xl border border-blue-100 overflow-hidden">
              <Table>
                <TableHeader className="bg-blue-50/50">
                  <TableRow>
                    <TableHead>Clinical File</TableHead>
                    <TableHead>Type</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Upload Date</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {documentsData?.data?.documents?.map((doc: any) => (
                    <TableRow key={doc.id} className="hover:bg-blue-50/30 transition-colors">
                      <TableCell>
                        <div className="flex items-center gap-3">
                          <div className="bg-blue-100 p-2 rounded-md">
                            <FileText className="h-4 w-4 text-blue-600" />
                          </div>
                          <span className="font-medium text-slate-900">{doc.original_name}</span>
                        </div>
                      </TableCell>
                      <TableCell>
                        <Badge variant="secondary" className="bg-slate-100 text-slate-700">
                          {doc.metadata?.type || 'Medical Record'}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center gap-2">
                          <div className="h-2 w-2 rounded-full bg-blue-500 animate-pulse" />
                          <span className="text-sm capitalize font-medium">{doc.document_status || 'analyzing'}</span>
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
                      <TableCell colSpan={5} className="text-center py-32 text-muted-foreground italic">
                        No clinical documents found.
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
