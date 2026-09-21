"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Badge } from "@/components/ui/badge"
import { 
  Shield, 
  Plus, 
  User, 
  Phone, 
  Mail, 
  DollarSign, 
  FileCheck,
  AlertCircle
} from "lucide-react"
import { 
  useGetInsuranceClaimsQuery, 
  useCreateInsuranceClaimMutation,
  useGetInsuranceCompaniesQuery
} from "@/store/api/apiSlice"
import { useToast } from "@/hooks/use-toast"
import { LoadingSpinner } from "@/components/loading-spinner"
import { 
  Dialog, 
  DialogContent, 
  DialogHeader, 
  DialogTitle, 
  DialogTrigger,
  DialogFooter
} from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"

export function CaseInsuranceTab({ caseId }: { caseId: number }) {
  const { data: claimsResponse, isLoading, refetch } = useGetInsuranceClaimsQuery({ case_id: caseId })
  const { data: companiesResponse } = useGetInsuranceCompaniesQuery({ per_page: 100 })
  const [createClaim, { isLoading: isCreating }] = useCreateInsuranceClaimMutation()
  const { toast } = useToast()
  const [isDialogOpen, setIsDialogOpen] = useState(false)

  const [formData, setFormData] = useState({
    insurance_company_id: "",
    claim_number: "",
    coverage_limit: "",
    coverage_type: "liability", // liability, pip, medpay, uninsured_motorist
    claim_status: "open", // open, pending, settled, denied
  })

  const claims = claimsResponse?.data?.data || []
  const companies = companiesResponse?.data?.data || []

  const handleAddClaim = async () => {
    try {
      if (!formData.insurance_company_id || !formData.claim_number) {
        toast({ title: "Validation Error", description: "Please select a company and enter a claim number", variant: "destructive" })
        return
      }

      await createClaim({ 
        ...formData, 
        case_id: caseId,
        coverage_limit: parseFloat(formData.coverage_limit) || 0
      }).unwrap()
      
      toast({ title: "Success", description: "Insurance claim added successfully" })
      setIsDialogOpen(false)
      refetch()
    } catch (error) {
      toast({ title: "Error", description: "Failed to add insurance claim", variant: "destructive" })
    }
  }

  if (isLoading) return <LoadingSpinner />

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-lg font-bold text-emerald-900 dark:text-white">Insurance & Coverage</h3>
          <p className="text-sm text-slate-500">Manage policies, limits, and adjuster contact info</p>
        </div>
        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
          <DialogTrigger asChild>
            <Button className="bg-emerald-600 hover:bg-emerald-700">
              <Plus className="w-4 h-4 mr-2" />
              Add Coverage
            </Button>
          </DialogTrigger>
          <DialogContent className="sm:max-w-[500px]">
            <DialogHeader>
              <DialogTitle>Add Insurance Coverage</DialogTitle>
            </DialogHeader>
            <div className="space-y-4 py-4">
              <div className="space-y-2">
                <Label>Insurance Carrier *</Label>
                <Select value={formData.insurance_company_id} onValueChange={v => setFormData({...formData, insurance_company_id: v})}>
                  <SelectTrigger><SelectValue placeholder="Select Carrier" /></SelectTrigger>
                  <SelectContent>
                    {companies.map((c: any) => (
                      <SelectItem key={c.id} value={c.id.toString()}>{c.name}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label>Coverage Type *</Label>
                  <Select value={formData.coverage_type} onValueChange={v => setFormData({...formData, coverage_type: v})}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="liability">Liability</SelectItem>
                      <SelectItem value="pip">PIP</SelectItem>
                      <SelectItem value="medpay">MedPay</SelectItem>
                      <SelectItem value="uninsured_motorist">UM/UIM</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label>Policy Limit ($)</Label>
                  <Input 
                    placeholder="0.00" 
                    type="number"
                    value={formData.coverage_limit}
                    onChange={e => setFormData({...formData, coverage_limit: e.target.value})}
                  />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label>Claim Number *</Label>
                  <Input 
                    placeholder="Enter claim #"
                    value={formData.claim_number}
                    onChange={e => setFormData({...formData, claim_number: e.target.value})}
                  />
                </div>
                <div className="space-y-2">
                  <Label>Status</Label>
                  <Select value={formData.claim_status} onValueChange={v => setFormData({...formData, claim_status: v})}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="open">Open</SelectItem>
                      <SelectItem value="pending">Pending</SelectItem>
                      <SelectItem value="settled">Settled</SelectItem>
                      <SelectItem value="denied">Denied</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => setIsDialogOpen(false)}>Cancel</Button>
              <Button className="bg-emerald-600" onClick={handleAddClaim} disabled={isCreating}>Save Coverage</Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {claims.length > 0 ? claims.map((claim: any) => (
          <Card key={claim.id} className="border-emerald-100 bg-white/70 backdrop-blur-sm shadow-sm overflow-hidden group">
            <div className="h-1 bg-emerald-500" />
            <CardHeader className="pb-3">
              <div className="flex items-start justify-between">
                <div className="flex items-center gap-3">
                  <div className="p-2 bg-emerald-100 rounded-lg">
                    <Shield className="w-5 h-5 text-emerald-600" />
                  </div>
                  <div>
                    <CardTitle className="text-base">{claim.insurance_company?.name || 'Unknown Carrier'}</CardTitle>
                    <Badge variant="outline" className="text-[10px] uppercase mt-1">
                      {claim.coverage_type || 'Liability'}
                    </Badge>
                  </div>
                </div>
                <Badge className="bg-emerald-600 uppercase text-[10px]">{claim.claim_status}</Badge>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <p className="text-[10px] uppercase text-slate-500 font-bold">Policy Limits</p>
                  <p className="font-bold text-emerald-900">${parseFloat(claim.coverage_limit || 0).toLocaleString()}</p>
                </div>
                <div>
                  <p className="text-[10px] uppercase text-slate-500 font-bold">Claim #</p>
                  <p className="font-medium">{claim.claim_number || 'N/A'}</p>
                </div>
              </div>

              <div className="pt-3 border-t border-emerald-50 space-y-2">
                <p className="text-[10px] uppercase text-emerald-700 font-bold flex items-center gap-1">
                  <User className="w-3 h-3" /> Adjuster Info
                </p>
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">{claim.adjuster?.first_name ? `${claim.adjuster.first_name} ${claim.adjuster.last_name}` : 'Not assigned'}</span>
                  <div className="flex gap-2">
                    {claim.adjuster?.phone && (
                      <Button size="icon" variant="ghost" className="h-7 w-7 text-emerald-600">
                        <Phone className="w-3.5 h-3.5" />
                      </Button>
                    )}
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        )) : (
          <Card className="col-span-2 border-dashed border-2 border-emerald-100 bg-emerald-50/20 py-12 flex flex-col items-center justify-center text-center">
            <Shield className="h-12 w-12 text-emerald-200 mb-4" />
            <h4 className="font-bold text-emerald-900">No Coverage Recorded</h4>
            <p className="text-sm text-emerald-600/70 mt-1">Add insurance policies involved in this case to track limits and claims.</p>
            <Button variant="outline" className="mt-4 border-emerald-200 text-emerald-700" onClick={() => setIsDialogOpen(true)}>
              <Plus className="w-4 h-4 mr-2" /> Record First Policy
            </Button>
          </Card>
        )}
      </div>
    </div>
  )
}
