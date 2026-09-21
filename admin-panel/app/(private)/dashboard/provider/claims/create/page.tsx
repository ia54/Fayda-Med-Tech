"use client"

import { useState } from "react"
import { useRouter } from "next/navigation"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { FileText, Plus, ArrowLeft, Save, Send, AlertCircle, Loader2 } from "lucide-react"
import { useCreateInvoiceMutation } from "@/store/api/billingApiSlice"
import { useToast } from "@/hooks/use-toast"

export default function CreateClaimPage() {
  const router = useRouter()
  const { toast } = useToast()
  const [createInvoice, { isLoading }] = useCreateInvoiceMutation()

  const [formData, setFormData] = useState({
    patient_name: "",
    service_date: "",
    payer: "",
    amount: "",
    cpt_codes: "",
    diagnosis_codes: "",
    notes: "",
    case_id: ""
  })

  const handleSubmit = async (status: 'draft' | 'sent') => {
    if (!formData.patient_name || !formData.amount) {
      toast({ title: "Validation Error", description: "Patient name and amount are required.", variant: "destructive" })
      return
    }

    try {
      await createInvoice({
        amount: parseFloat(formData.amount),
        status: status,
        due_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
        case_id: parseInt(formData.case_id),
        // @ts-ignore
        metadata: {
            payer: formData.payer,
            cpt_codes: formData.cpt_codes,
            diagnosis_codes: formData.diagnosis_codes,
            patient_name: formData.patient_name,
            service_date: formData.service_date,
            notes: formData.notes
        }
      }).unwrap()

      toast({ title: "Claim Created", description: `Claim for ${formData.patient_name} has been saved as ${status}.` })
      router.push("/dashboard/provider/claims")
    } catch (err: any) {
      toast({ title: "Submission Failed", description: err.data?.message || "Something went wrong", variant: "destructive" })
    }
  }

  return (
    <div className="max-w-4xl mx-auto space-y-8">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Button variant="outline" size="icon" onClick={() => router.back()}>
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <div>
            <h1 className="text-3xl font-bold text-primary flex items-center gap-2">
              <Plus className="h-8 w-8" />
              New Claim Submission
            </h1>
            <p className="text-muted-foreground">Register a new medical service for insurance reimbursement</p>
          </div>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="lg:col-span-2 space-y-6">
          <Card className="bg-card/50 backdrop-blur-sm border-border/50">
            <CardHeader>
              <CardTitle>Patient & Service Information</CardTitle>
              <CardDescription>Enter the core details for this medical encounter</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label>Patient Name</Label>
                  <Input 
                    placeholder="Enter full name" 
                    value={formData.patient_name}
                    onChange={(e) => setFormData({...formData, patient_name: e.target.value})}
                  />
                </div>
                <div className="space-y-2">
                  <Label>Service Date</Label>
                  <Input 
                    type="date" 
                    value={formData.service_date}
                    onChange={(e) => setFormData({...formData, service_date: e.target.value})}
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label>Case ID</Label>
                  <Input 
                    placeholder="Enter Case ID" 
                    value={formData.case_id}
                    onChange={(e) => setFormData({...formData, case_id: e.target.value})}
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label>Insurance Payer</Label>
                  <Select onValueChange={(val) => setFormData({...formData, payer: val})}>
                    <SelectTrigger>
                      <SelectValue placeholder="Select Payer" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="Medicare">Medicare</SelectItem>
                      <SelectItem value="Blue Cross">Blue Cross Blue Shield</SelectItem>
                      <SelectItem value="Aetna">Aetna</SelectItem>
                      <SelectItem value="Cigna">Cigna</SelectItem>
                      <SelectItem value="UnitedHealth">UnitedHealthcare</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label>Billed Amount ($)</Label>
                  <Input 
                    type="number" 
                    placeholder="0.00" 
                    step="0.01"
                    value={formData.amount}
                    onChange={(e) => setFormData({...formData, amount: e.target.value})}
                  />
                </div>
              </div>
            </CardContent>
          </Card>

          <Card className="bg-card/50 backdrop-blur-sm border-border/50">
            <CardHeader>
              <CardTitle>Coding & Documentation</CardTitle>
              <CardDescription>Specify CPT and ICD-10 codes for processing</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label>CPT Codes</Label>
                  <Input 
                    placeholder="e.g., 99213, 90834" 
                    value={formData.cpt_codes}
                    onChange={(e) => setFormData({...formData, cpt_codes: e.target.value})}
                  />
                </div>
                <div className="space-y-2">
                  <Label>Diagnosis Codes (ICD-10)</Label>
                  <Input 
                    placeholder="e.g., F32.9, Z71.1" 
                    value={formData.diagnosis_codes}
                    onChange={(e) => setFormData({...formData, diagnosis_codes: e.target.value})}
                  />
                </div>
              </div>
              <div className="space-y-2">
                <Label>Clinical Notes (Internal)</Label>
                <Textarea 
                  placeholder="Provide additional context for the billing department..." 
                  className="min-h-30"
                  value={formData.notes}
                  onChange={(e) => setFormData({...formData, notes: e.target.value})}
                />
              </div>
            </CardContent>
          </Card>
        </div>

        <div className="space-y-6">
          <Card className="bg-primary/5 border-primary/20 backdrop-blur-sm">
            <CardHeader>
              <CardTitle className="text-sm font-semibold flex items-center gap-2">
                <AlertCircle className="h-4 w-4 text-primary" />
                Submission Guidelines
              </CardTitle>
            </CardHeader>
            <CardContent className="text-xs space-y-3 text-muted-foreground">
              <p>• Ensure patient name matches the insurance card exactly.</p>
              <p>• Double check CPT codes to avoid AI validation flags.</p>
              <p>• Claims saved as "Draft" will not be visible to billers.</p>
              <p>• "Submit" will push this claim directly to the OCR queue.</p>
            </CardContent>
          </Card>

          <div className="flex flex-col gap-3">
            <Button 
                className="w-full h-12 text-lg font-bold shadow-lg shadow-primary/20" 
                onClick={() => handleSubmit('sent')}
                disabled={isLoading}
            >
              {isLoading ? <Loader2 className="h-5 w-5 animate-spin" /> : <><Send className="mr-2 h-5 w-5" /> Submit Claim</>}
            </Button>
            <Button 
                variant="outline" 
                className="w-full h-12" 
                onClick={() => handleSubmit('draft')}
                disabled={isLoading}
            >
              <Save className="mr-2 h-5 w-5" /> Save as Draft
            </Button>
            <Button variant="ghost" className="w-full text-muted-foreground" onClick={() => router.push("/dashboard/provider/claims")}>
              Discard Changes
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}
