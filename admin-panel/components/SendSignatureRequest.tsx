"use client"

import { useState } from "react"
import { useGetDocumentsQuery, useAssignSignersMutation, useGetEligibleSignersQuery } from "@/store/api/documentsApiSlice"
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from "@/components/ui/card"
import { Label } from "@/components/ui/label"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { toast } from "sonner"
import { PenTool, Send, FileText } from "lucide-react"

export function SendSignatureRequest() {
  const { data: documentsData, isLoading: isLoadingDocs } = useGetDocumentsQuery({ per_page: 50 })
  const documents = documentsData?.data?.documents || []

  const [assignSigners, { isLoading: isAssigning }] = useAssignSignersMutation()

  const [selectedDocId, setSelectedDocId] = useState<string>("")
  const [signerId, setSignerId] = useState("")
  const { data: eligible, isFetching: loadingSigners } = useGetEligibleSignersQuery(Number(selectedDocId), { skip: !selectedDocId })
  const [signerName, setSignerName] = useState("")
  const [signerEmail, setSignerEmail] = useState("")

  const handleSendRequest = async () => {
    if (!selectedDocId || !signerId || !signerName || !signerEmail) {
      toast.error("Please fill in all fields")
      return
    }

    try {
      await assignSigners({
        documentId: Number(selectedDocId),
        signers: [
          {
            user_id: Number(signerId),
            name: signerName,
            email: signerEmail,
            signing_order: 1
          }
        ]
      }).unwrap()

      toast.success("Signature request assigned successfully")
      setSignerId("")
      setSignerName("")
      setSignerEmail("")
      setSelectedDocId("")
    } catch (err: any) {
      toast.error(err.data?.message || "Failed to assign signer")
    }
  }

  return (
    <Card className="max-w-2xl bg-card border-border/50">
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-xl">
          <Send className="h-5 w-5 text-primary" />
          Send for Signature
        </CardTitle>
        <CardDescription>
          Assign a document to a client or party for digital signature. They will see it in their pending signatures.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-6 pt-4">
        <div className="space-y-2">
          <Label htmlFor="document" className="text-sm font-medium">Select Document</Label>
          <Select value={selectedDocId} onValueChange={value => { setSelectedDocId(value); setSignerId(""); setSignerName(""); setSignerEmail(""); }}>
            <SelectTrigger id="document" className="w-full">
              <SelectValue placeholder="Select a document..." />
            </SelectTrigger>
            <SelectContent>
              {isLoadingDocs ? (
                <SelectItem value="loading" disabled>Loading documents...</SelectItem>
              ) : documents.length === 0 ? (
                <SelectItem value="none" disabled>No documents available</SelectItem>
              ) : (
                documents.map(doc => (
                  <SelectItem key={doc.id} value={doc.id.toString()}>
                    {doc.original_name || doc.title}
                  </SelectItem>
                ))
              )}
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-2">
          <Label htmlFor="signer-account">Signer account</Label>
          <Select value={signerId} onValueChange={value => {
            const signer = eligible?.data.find(user => String(user.id) === value)
            setSignerId(value); setSignerName(signer ? `${signer.first_name} ${signer.last_name}` : ""); setSignerEmail(signer?.email || "")
          }} disabled={!selectedDocId || loadingSigners}>
            <SelectTrigger id="signer-account"><SelectValue placeholder={loadingSigners ? "Loading accounts…" : "Select an existing account"} /></SelectTrigger>
            <SelectContent>{eligible?.data.map(user => <SelectItem key={user.id} value={String(user.id)}>{user.first_name} {user.last_name} — {user.email}</SelectItem>)}</SelectContent>
          </Select>
          <p className="text-sm text-muted-foreground">Only the selected account can sign in the portal. External recipients must use the DocuSign workflow.</p>
        </div>
      </CardContent>
      <CardFooter className="bg-muted/50 px-6 py-4 border-t border-border/50">
        <Button 
          onClick={handleSendRequest} 
          disabled={isAssigning || !selectedDocId || !signerId || !signerName || !signerEmail}
          className="ml-auto"
        >
          {isAssigning ? "Sending..." : "Send Request"}
        </Button>
      </CardFooter>
    </Card>
  )
}
