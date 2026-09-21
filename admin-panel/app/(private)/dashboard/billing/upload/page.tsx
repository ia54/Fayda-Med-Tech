"use client"

import { useState, useRef } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Progress } from "@/components/ui/progress"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Upload, FileText, CheckCircle, Clock, Trash2, Loader2 } from "lucide-react"
import { useGetDocumentsQuery, useUploadDocumentMutation } from "@/store/api/billingApiSlice"
import { useToast } from "@/hooks/use-toast"
import { Skeleton } from "@/components/ui/skeleton"

export default function BulkUploadPage() {
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
          <h1 className="text-3xl font-bold text-primary">Bulk Upload</h1>
          <p className="text-muted-foreground">Upload and process medical records or claim forms</p>
        </div>
        <input
          type="file"
          multiple
          className="hidden"
          ref={fileInputRef}
          onChange={handleFileSelect}
        />
        <Button onClick={() => fileInputRef.current?.click()}>
          <Upload className="h-4 w-4 mr-2" />
          New Upload
        </Button>
      </div>

      {/* Upload Progress Area */}
      {uploadingFiles.length > 0 && (
        <Card className="border-accent/20 bg-accent/5">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium flex items-center gap-2">
              <Loader2 className="h-4 w-4 animate-spin text-accent" />
              Uploading {uploadingFiles.length} files...
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {uploadingFiles.map((file, idx) => (
              <div key={idx} className="space-y-1">
                <div className="flex justify-between text-xs font-medium">
                  <span>{file.name}</span>
                  <span>{file.progress}%</span>
                </div>
                <Progress value={file.progress} className="h-1" />
              </div>
            ))}
          </CardContent>
        </Card>
      )}

      {/* Upload Dropzone */}
      <Card 
        className="bg-card/50 backdrop-blur-sm border-dashed border-2 hover:border-primary/50 transition-colors cursor-pointer"
        onClick={() => fileInputRef.current?.click()}
      >
        <CardContent className="py-12 text-center">
          <Upload className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
          <h3 className="text-lg font-semibold mb-2">Drop files here or click to browse</h3>
          <p className="text-muted-foreground text-sm">Supports PDF, CSV, and Image files (Max 50MB)</p>
        </CardContent>
      </Card>

      {/* Upload History */}
      <Card className="bg-card/80 backdrop-blur-sm">
        <CardHeader>
          <CardTitle>Recent Uploads</CardTitle>
          <CardDescription>History of uploaded documents for processing</CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-4">
              {[1, 2, 3].map(i => <Skeleton key={i} className="h-12 w-full" />)}
            </div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Filename</TableHead>
                  <TableHead>Size</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Date</TableHead>
                  <TableHead className="text-right">Action</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {documentsData?.data?.documents?.map((doc: any) => (
                  <TableRow key={doc.id}>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        <FileText className="h-4 w-4 text-primary" />
                        <span className="font-medium">{doc.original_name}</span>
                      </div>
                    </TableCell>
                    <TableCell className="text-xs text-muted-foreground">
                      {(doc.size / 1024 / 1024).toFixed(2)} MB
                    </TableCell>
                    <TableCell>
                      <Badge variant="secondary" className="capitalize">
                        {doc.document_status || 'uploaded'}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-xs">
                      {new Date(doc.created_at).toLocaleString()}
                    </TableCell>
                    <TableCell className="text-right">
                      <Button variant="ghost" size="sm">
                        <Trash2 className="h-4 w-4 text-muted-foreground" />
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
                {documentsData?.data?.documents?.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={5} className="h-24 text-center">No upload history found.</TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
