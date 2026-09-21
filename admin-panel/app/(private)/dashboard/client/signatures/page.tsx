"use client"

import { useState } from "react"
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { PenTool, FileText, Clock, CheckCircle2, XCircle } from "lucide-react"
import { useGetClientPendingSignaturesQuery } from "@/store/api/billingApiSlice"
import { useSignInAppMutation } from "@/store/api/documentsApiSlice"
import { SignatureModal } from "@/components/SignatureModal"
import { cn } from "@/lib/utils"
import { useSelector } from "react-redux"
import { RootState } from "@/store/store"
import { PDFDocument } from "pdf-lib"
import { toast } from "sonner"

const signerStatusMap: Record<string, { label: string; icon: any; class: string }> = {
  pending: { label: "Awaiting Signature", icon: Clock, class: "text-amber-600 bg-amber-50 border-amber-200" },
  sent: { label: "Sent for Signing", icon: PenTool, class: "text-blue-600 bg-blue-50 border-blue-200" },
  signed: { label: "Signed", icon: CheckCircle2, class: "text-emerald-600 bg-emerald-50 border-emerald-200" },
  declined: { label: "Declined", icon: XCircle, class: "text-red-600 bg-red-50 border-red-200" },
}

export default function ClientSignaturesPage() {
  const { data, isLoading } = useGetClientPendingSignaturesQuery({})
  const [signInApp] = useSignInAppMutation()
  const documents = data?.data?.data || []
  
  const token = useSelector((state: RootState) => state.auth.token?.access_token)
  const user = useSelector((state: RootState) => state.auth.user)
  
  const [selectedDoc, setSelectedDoc] = useState<any>(null)
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [isProcessing, setIsProcessing] = useState(false)

  const handleSignClick = (doc: any) => {
    setSelectedDoc(doc)
    setIsModalOpen(true)
  }

  const handleSaveSignature = async (signatureData: string, signerInfo: { name: string; email: string }) => {
    if (!selectedDoc) return

    setIsProcessing(true)
    try {
      // 1. Fetch original PDF
      const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api'
      const response = await fetch(`${baseUrl}/documents/${selectedDoc.id}/preview`, {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      })
      
      if (!response.ok) {
        throw new Error('Failed to fetch document for preview')
      }
      
      const pdfBytes = await response.arrayBuffer()
      
      // 2. Load the PDF with pdf-lib
      const pdfDoc = await PDFDocument.load(pdfBytes)
      
      // 3. Embed signature image
      const pngImageBytes = signatureData.split(',')[1]
      const pngImage = await pdfDoc.embedPng(Uint8Array.from(atob(pngImageBytes), c => c.charCodeAt(0)))
      
      // 4. Draw image on last page
      const pages = pdfDoc.getPages()
      const lastPage = pages[pages.length - 1]
      const { width, height } = lastPage.getSize()
      
      const pngDims = pngImage.scale(0.3)
      lastPage.drawImage(pngImage, {
        x: 50,
        y: 50,
        width: pngDims.width,
        height: pngDims.height,
      })
      
      // 5. Save stamped PDF
      const modifiedPdfBytes = await pdfDoc.save()
      const blob = new Blob([modifiedPdfBytes], { type: 'application/pdf' })
      
      const formData = new FormData()
      formData.append('file', blob, selectedDoc.original_name || 'signed_document.pdf')
      formData.append('signer_email', signerInfo.email)

      // 6. Upload
      await signInApp({
        documentId: selectedDoc.id,
        formData
      }).unwrap()

      toast.success("Document signed successfully")
    } catch (err: any) {
      console.error(err)
      toast.error(err.data?.message || err.message || "Failed to sign document")
    } finally {
      setIsProcessing(false)
      setIsModalOpen(false)
      setSelectedDoc(null)
    }
  }

  return (
    <div className="space-y-6 animate-in fade-in duration-500">
      <div>
        <h1 className="text-3xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-3">
          <PenTool className="h-8 w-8 text-primary" />
          Pending Signatures
        </h1>
        <p className="text-muted-foreground mt-1">
          Documents that require your signature.
        </p>
      </div>

      {isLoading ? (
        <div className="space-y-4">
          {[1, 2, 3].map((i) => <Skeleton key={i} className="h-24 w-full rounded-xl" />)}
        </div>
      ) : documents.length === 0 ? (
        <Card className="bg-card/80 backdrop-blur-sm border-border/50">
          <CardContent className="pt-6">
            <div className="text-center py-16">
              <CheckCircle2 className="h-12 w-12 mx-auto text-emerald-400 mb-4" />
              <h3 className="text-lg font-semibold text-slate-600 dark:text-slate-300">All caught up!</h3>
              <p className="text-sm text-slate-400 mt-1">No documents require your signature at this time.</p>
            </div>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-4">
          {documents.map((doc: any) => {
            const signers = doc.signers || []

            return (
              <Card key={doc.id} className="bg-card shadow-sm hover:shadow-lg transition-all duration-200 border-amber-200/50">
                <CardContent className="pt-6">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4 flex-1 min-w-0">
                      <div className="p-3 rounded-lg bg-amber-50 dark:bg-amber-950">
                        <PenTool className="h-5 w-5 text-amber-600" />
                      </div>
                      <div className="min-w-0 flex-1">
                        <h3 className="text-sm font-bold text-slate-900 dark:text-white truncate">
                          {doc.title || doc.original_name}
                        </h3>
                        <p className="text-xs text-muted-foreground mt-0.5">
                          Uploaded {new Date(doc.created_at).toLocaleDateString()}
                        </p>
                        {signers.length > 0 && (
                          <div className="flex items-center gap-2 mt-2 flex-wrap">
                            {signers.map((s: any) => {
                              const st = signerStatusMap[s.status] || signerStatusMap.pending
                              const StIcon = st.icon
                              return (
                                <Badge key={s.id} variant="outline" className={cn("text-[10px] gap-1", st.class)}>
                                  <StIcon className="h-3 w-3" /> {s.name}
                                </Badge>
                              )
                            })}
                          </div>
                        )}
                      </div>
                    </div>
                    <Button 
                      size="sm" 
                      onClick={() => handleSignClick(doc)}
                      className="bg-amber-600 hover:bg-amber-700 shadow-lg shadow-amber-600/20 ml-4 shrink-0"
                    >
                      <PenTool className="h-3 w-3 mr-1" /> Sign Now
                    </Button>
                  </div>
                </CardContent>
              </Card>
            )
          })}
        </div>
      )}

      {selectedDoc && (
        <SignatureModal
          isOpen={isModalOpen}
          onClose={() => setIsModalOpen(false)}
          onSave={handleSaveSignature}
          documentTitle={selectedDoc.title || selectedDoc.original_name}
          initialSignerName={user?.first_name ? `${user.first_name} ${user.last_name}` : ""}
          initialSignerEmail={user?.email || ""}
          isLoading={isProcessing}
        />
      )}
    </div>
  )
}
