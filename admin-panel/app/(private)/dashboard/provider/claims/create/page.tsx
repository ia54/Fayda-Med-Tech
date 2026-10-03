"use client"

import { useState } from "react"
import { useRouter } from "next/navigation"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Plus, ArrowLeft, Save, Send, AlertCircle, Loader2 } from "lucide-react"
import { useCreateInvoiceMutation } from "@/store/api/billingApiSlice"
import { useGetCasesQuery } from "@/store/api/casesApiSlice"
import { useToast } from "@/hooks/use-toast"

export default function CreateClaimPage() {
  const router = useRouter()
  const { toast } = useToast()
  const [createInvoice, { isLoading }] = useCreateInvoiceMutation()

  const [caseSearch, setCaseSearch] = useState("")
  const [error, setError] = useState<string | null>(null)
  const { data: caseData, isFetching: loadingCases, isError: casesFailed, refetch: retryCases } = useGetCasesQuery({ search: caseSearch, per_page: 50 })

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
    setError(null)
    if (!formData.patient_name.trim() || !formData.service_date || !formData.case_id || !/^\d+(\.\d{1,2})?$/.test(formData.amount) || Number(formData.amount) <= 0) {
      setError("Select a case and enter the patient name, service date, and a positive amount with at most two decimal places.")
      return
    }

    try {
      await createInvoice({
        amount: parseFloat(formData.amount),
        status: status,
        due_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
        case_id: parseInt(formData.case_id),
        metadata: {
            payer: formData.payer,
            cpt_codes: formData.cpt_codes,
            diagnosis_codes: formData.diagnosis_codes,
            patient_name: formData.patient_name.trim(),
            service_date: formData.service_date,
            notes: formData.notes
        }
      }).unwrap()

      toast({ title: "Billing record saved", description: `Billing record for ${formData.patient_name} saved. No insurer submission was made.` })
      router.push("/dashboard/provider/claims")
    } catch (err: any) {
      setError(Object.values(err.data?.errors || {}).flat().join(" ") || err.data?.message || "Could not save this billing record. Please try again.")
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
              New Medical Billing Record
            </h1>
            <p className="text-muted-foreground">Record services against an existing patient case for billing review</p>
          </div>
        </div>
      </div>

      {error && <p role="alert" className="text-destructive">{error}</p>}
      <div className="grid gap-6 lg:grid-cols-3">
        <div className="lg:col-span-2 space-y-6">
          <Card className="bg-card/50 backdrop-blur-sm border-border/50">
            <CardHeader>
              <CardTitle>Patient & Service Information</CardTitle>
              <CardDescription>Enter the core details for this medical encounter</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="patient_name">Patient Name</Label>
                  <Input 
                    placeholder="Enter full name" 
                    id="patient_name" value={formData.patient_name}
                    onChange={(e) => setFormData({...formData, patient_name: e.target.value})}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="service_date">Service Date</Label>
                  <Input 
                    type="date" 
                    id="service_date" value={formData.service_date}
                    onChange={(e) => setFormData({...formData, service_date: e.target.value})}
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="case-search">Find a case</Label>
                  <Input id="case-search" placeholder="Search by case number or title" value={caseSearch} onChange={e => setCaseSearch(e.target.value)} />
                  <Label htmlFor="case-id">Case</Label>
                  <select id="case-id" className="w-full rounded-md border bg-background p-2" value={formData.case_id} onChange={e => setFormData({...formData, case_id: e.target.value})} disabled={loadingCases || casesFailed}>
                    <option value="">Select a case</option>
                    {formData.case_id && !caseData?.data.some(c => String(c.id) === formData.case_id) && <option value={formData.case_id}>Selected case #{formData.case_id}</option>}
                    {caseData?.data.map(c => <option key={c.id} value={c.id}>{c.case_number} — {c.title}</option>)}
                  </select>
                  {casesFailed ? <p role="alert">Could not load cases. <button type="button" className="underline" onClick={() => retryCases()}>Try again</button></p> : loadingCases ? <p className="text-sm">Loading cases…</p> : !caseData?.data.length ? <p className="text-sm">No matching cases. Ask your organization administrator to create the patient case first.</p> : <p className="text-sm text-muted-foreground">Search to narrow the available cases.</p>}
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="payer">Insurance Payer</Label>
                  <Select onValueChange={(val) => setFormData({...formData, payer: val})}>
                    <SelectTrigger id="payer">
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
                  <Label htmlFor="amount">Billed Amount ($)</Label>
                  <Input 
                    type="number" 
                    placeholder="0.00" 
                    step="0.01"
                    id="amount" value={formData.amount}
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
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="cpt_codes">CPT Codes</Label>
                  <Input 
                    placeholder="e.g., 99213, 90834" 
                    id="cpt_codes" value={formData.cpt_codes}
                    onChange={(e) => setFormData({...formData, cpt_codes: e.target.value})}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="diagnosis_codes">Diagnosis Codes (ICD-10)</Label>
                  <Input 
                    placeholder="e.g., F32.9, Z71.1" 
                    id="diagnosis_codes" value={formData.diagnosis_codes}
                    onChange={(e) => setFormData({...formData, diagnosis_codes: e.target.value})}
                  />
                </div>
              </div>
              <div className="space-y-2">
                <Label htmlFor="notes">Clinical Notes (Internal)</Label>
                <Textarea 
                  placeholder="Provide additional context for the billing department..." 
                  className="min-h-30"
                  id="notes" value={formData.notes}
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
                Billing review
              </CardTitle>
            </CardHeader>
            <CardContent className="text-xs space-y-3 text-muted-foreground">
              <p>• Ensure patient name matches the insurance card exactly.</p>
              <p>• Review CPT and diagnosis codes before saving.</p>
              <p>• Draft records remain available to authorized billing staff.</p>
              <p>• Saving creates an internal billing record. It does not send a claim to an insurer or start OCR.</p>
            </CardContent>
          </Card>

          <div className="flex flex-col gap-3">
            <Button 
                className="w-full h-12 text-lg font-bold shadow-lg shadow-primary/20" 
                onClick={() => handleSubmit('sent')}
                disabled={isLoading}
            >
              {isLoading ? <Loader2 className="h-5 w-5 animate-spin" /> : <><Send className="mr-2 h-5 w-5" /> Save for Billing Review</>}
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
