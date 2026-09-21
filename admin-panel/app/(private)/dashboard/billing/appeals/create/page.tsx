"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Bot, FileText, Send, Sparkles, ChevronLeft } from "lucide-react"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Textarea } from "@/components/ui/textarea"
import { useSearchParams } from "next/navigation"
import { useGetInvoicesQuery, useGenerateAppealMutation } from "@/store/api/billingApiSlice"
import { useToast } from "@/hooks/use-toast"
import Link from "next/link"
import { useEffect } from "react"

export default function CreateAppealPage() {
  const searchParams = useSearchParams()
  const invoiceIdParam = searchParams.get('invoice_id')
  
  const { toast } = useToast()
  const [selectedInvoice, setSelectedInvoice] = useState<string>("")
  const [reason, setReason] = useState<string>("")
  const [details, setDetails] = useState<string>("")
  const [generatedLetter, setGeneratedLetter] = useState<string>("")

  const { data: deniedInvoices } = useGetInvoicesQuery({ status: "denied" })
  const [generateAppeal, { isLoading }] = useGenerateAppealMutation()

  useEffect(() => {
    if (invoiceIdParam) {
      setSelectedInvoice(invoiceIdParam)
    }
  }, [invoiceIdParam])

  const handleGenerate = async () => {
    if (!selectedInvoice || !reason) {
      toast({
        title: "Missing Information",
        description: "Please select an invoice and a reason for the appeal.",
        variant: "destructive",
      })
      return
    }

    try {
      const result = await generateAppeal({
        invoice_id: Number(selectedInvoice),
        reason_category: reason,
        additional_details: details,
      }).unwrap()

      setGeneratedLetter(result.data.content)
      toast({
        title: "Appeal Generated",
        description: "The AI has successfully drafted the appeal letter.",
      })
    } catch (err: any) {
      toast({
        title: "Generation Failed",
        description: err.data?.message || "An error occurred while generating the appeal.",
        variant: "destructive",
      })
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-4">
        <Link href="/dashboard/billing/appeals">
          <Button variant="ghost" size="icon">
            <ChevronLeft className="h-4 w-4" />
          </Button>
        </Link>
        <div>
          <h1 className="text-3xl font-bold text-primary">New AI Appeal</h1>
          <p className="text-muted-foreground">Draft a claim appeal letter using artificial intelligence</p>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card className="bg-card/80 backdrop-blur-sm h-fit">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Sparkles className="h-5 w-5 text-accent" />
              Appeal Configuration
            </CardTitle>
            <CardDescription>Select a denied claim and provide context for the AI</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-2">
              <label className="text-sm font-medium">Select Denied Invoice</label>
              <Select value={selectedInvoice} onValueChange={setSelectedInvoice}>
                <SelectTrigger>
                  <SelectValue placeholder="Choose an invoice" />
                </SelectTrigger>
                <SelectContent>
                  {deniedInvoices?.data?.data?.map((inv) => (
                    <SelectItem key={inv.id} value={String(inv.id)}>
                      {inv.invoice_number} - {inv.case?.title} (${inv.amount})
                    </SelectItem>
                  ))}
                  {deniedInvoices?.data?.data?.length === 0 && (
                    <div className="p-2 text-xs text-center text-muted-foreground">No denied invoices found</div>
                  )}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <label className="text-sm font-medium">Denial Reason Category</label>
              <Select value={reason} onValueChange={setReason}>
                <SelectTrigger>
                  <SelectValue placeholder="Select category" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="Medical Necessity">Medical Necessity</SelectItem>
                  <SelectItem value="Coding Error">Coding Error</SelectItem>
                  <SelectItem value="Documentation Missing">Documentation Missing</SelectItem>
                  <SelectItem value="Timely Filing">Timely Filing</SelectItem>
                  <SelectItem value="Other">Other</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <label className="text-sm font-medium">Additional Context (Optional)</label>
              <Textarea
                placeholder="Add any specific details the AI should include in the letter..."
                value={details}
                onChange={(e) => setDetails(e.target.value)}
                rows={4}
              />
            </div>

            <Button className="w-full" onClick={handleGenerate} disabled={isLoading}>
              {isLoading ? "Generating..." : "Generate Appeal Letter"}
              <Bot className="ml-2 h-4 w-4" />
            </Button>
          </CardContent>
        </Card>

        <Card className="bg-card/80 backdrop-blur-sm min-h-[400px]">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <FileText className="h-5 w-5" />
              Generated Letter
            </CardTitle>
            <CardDescription>Review and refine the AI-generated draft</CardDescription>
          </CardHeader>
          <CardContent>
            {generatedLetter ? (
              <div className="space-y-4">
                <div className="p-4 bg-muted/50 rounded-lg whitespace-pre-wrap font-mono text-sm border">
                  {generatedLetter}
                </div>
                <div className="flex gap-2">
                  <Button variant="outline" className="flex-1">
                    Edit Content
                  </Button>
                  <Button className="flex-1">
                    Send to Payer
                    <Send className="ml-2 h-4 w-4" />
                  </Button>
                </div>
              </div>
            ) : (
              <div className="flex flex-col items-center justify-center py-20 text-center space-y-3 opacity-50">
                <Bot className="h-12 w-12" />
                <p className="text-sm text-muted-foreground max-w-[250px]">
                  Select an invoice and click generate to see the AI-crafted appeal letter here.
                </p>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
