"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Badge } from "@/components/ui/badge"
import { 
  Scale, 
  Plus, 
  DollarSign, 
  TrendingDown, 
  Building2,
  Calendar,
  MoreVertical,
  CheckCircle2
} from "lucide-react"
import { useGetLiensQuery, useCreateLienMutation, useUpdateLienMutation } from "@/store/api/apiSlice"
import { useToast } from "@/hooks/use-toast"
import { LoadingSpinner } from "@/components/loading-spinner"
import { 
  Table, 
  TableBody, 
  TableCell, 
  TableHead, 
  TableHeader, 
  TableRow 
} from "@/components/ui/table"
import { 
  Dialog, 
  DialogContent, 
  DialogHeader, 
  DialogTitle, 
  DialogTrigger,
  DialogFooter
} from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import { Progress } from "@/components/ui/progress"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"

export function CaseLienTab({ caseId }: { caseId: number }) {
  const { data: liensResponse, isLoading, refetch } = useGetLiensQuery({ case_id: caseId })
  const [createLien, { isLoading: isCreating }] = useCreateLienMutation()
  const [updateLien] = useUpdateLienMutation()
  const { toast } = useToast()
  const [isDialogOpen, setIsDialogOpen] = useState(false)

  const [formData, setFormData] = useState({
    provider_name: "",
    amount: "",
    reduction_amount: "0",
    lien_type: "medical",
    status: "pending", // pending, negotiated, settled, released
    notes: ""
  })

  const liens = liensResponse?.data?.data || []
  
  const totalLienAmount = liens.reduce((sum: number, l: any) => sum + parseFloat(l.amount || 0), 0)
  const totalReductions = liens.reduce((sum: number, l: any) => sum + parseFloat(l.reduction_amount || 0), 0)
  const netLienAmount = totalLienAmount - totalReductions

  const handleAddLien = async () => {
    try {
      await createLien({ ...formData, case_id: caseId }).unwrap()
      toast({ title: "Success", description: "Lien recorded successfully" })
      setIsDialogOpen(false)
      refetch()
    } catch (error) {
      toast({ title: "Error", description: "Failed to record lien", variant: "destructive" })
    }
  }

  if (isLoading) return <LoadingSpinner />

  return (
    <div className="space-y-6">
      {/* Lien Summary Header */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Card className="bg-emerald-900 text-white border-0 shadow-lg overflow-hidden relative">
          <div className="absolute top-0 right-0 p-4 opacity-10">
            <DollarSign className="w-16 h-16" />
          </div>
          <CardHeader className="pb-2">
            <CardDescription className="text-emerald-100 uppercase text-[10px] font-bold tracking-widest">Total Medical Liens</CardDescription>
            <CardTitle className="text-3xl font-bold">${totalLienAmount.toLocaleString()}</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-xs text-emerald-200">Before negotiations</p>
          </CardContent>
        </Card>

        <Card className="bg-white border-emerald-100 shadow-sm overflow-hidden">
          <CardHeader className="pb-2">
            <CardDescription className="text-slate-500 uppercase text-[10px] font-bold tracking-widest">Total Reductions</CardDescription>
            <CardTitle className="text-3xl font-bold text-emerald-600">-${totalReductions.toLocaleString()}</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-xs text-slate-400">Negotiated savings</p>
          </CardContent>
        </Card>

        <Card className="bg-white border-emerald-100 shadow-sm overflow-hidden border-l-4 border-l-blue-500">
          <CardHeader className="pb-2">
            <CardDescription className="text-slate-500 uppercase text-[10px] font-bold tracking-widest">Net Liens Due</CardDescription>
            <CardTitle className="text-3xl font-bold text-slate-900">${netLienAmount.toLocaleString()}</CardTitle>
          </CardHeader>
          <CardContent>
             <Progress value={totalLienAmount > 0 ? (netLienAmount / totalLienAmount) * 100 : 0} className="h-1 bg-slate-100" />
          </CardContent>
        </Card>
      </div>

      <div className="flex items-center justify-between">
        <h3 className="text-lg font-bold text-emerald-900 dark:text-white">Provider Liens</h3>
        <Button className="bg-emerald-600 hover:bg-emerald-700" onClick={() => setIsDialogOpen(true)}>
          <Plus className="w-4 h-4 mr-2" />
          Record Lien
        </Button>
      </div>

      <Card className="border-emerald-100 overflow-hidden bg-white/70 backdrop-blur-sm shadow-sm">
        <Table>
          <TableHeader className="bg-emerald-50/50">
            <TableRow>
              <TableHead>Provider</TableHead>
              <TableHead>Type</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className="text-right">Original Amount</TableHead>
              <TableHead className="text-right">Reduction</TableHead>
              <TableHead className="text-right">Balance Due</TableHead>
              <TableHead className="w-[100px]"></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {liens.length > 0 ? liens.map((lien: any) => {
              const balance = parseFloat(lien.amount) - parseFloat(lien.reduction_amount || 0)
              return (
                <TableRow key={lien.id} className="hover:bg-emerald-50/30 transition-colors">
                  <TableCell>
                    <div className="flex items-center gap-3">
                      <div className="p-2 bg-slate-100 rounded-lg text-slate-600">
                        <Building2 className="w-4 h-4" />
                      </div>
                      <span className="font-bold text-slate-900">{lien.provider_name}</span>
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge variant="outline" className="capitalize text-[10px]">
                      {lien.lien_type?.replace('_', ' ')}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <Badge variant={lien.status === 'settled' ? 'default' : 'secondary'} className="capitalize text-[10px]">
                      {lien.status}
                    </Badge>
                  </TableCell>
                  <TableCell className="text-right font-medium text-slate-500">${parseFloat(lien.amount).toLocaleString()}</TableCell>
                  <TableCell className="text-right text-emerald-600 font-bold">
                    {parseFloat(lien.reduction_amount) > 0 ? `-$${parseFloat(lien.reduction_amount).toLocaleString()}` : "—"}
                  </TableCell>
                  <TableCell className="text-right font-bold text-slate-900">${balance.toLocaleString()}</TableCell>
                  <TableCell>
                    <Button variant="ghost" size="icon" className="h-8 w-8">
                      <MoreVertical className="w-4 h-4" />
                    </Button>
                  </TableCell>
                </TableRow>
              )
            }) : (
              <TableRow>
                <TableCell colSpan={7} className="text-center py-12 text-slate-400">
                  No liens recorded for this case yet.
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </Card>

      <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
        <DialogContent className="sm:max-w-[500px]">
          <DialogHeader>
            <DialogTitle>Record Provider Lien</DialogTitle>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label>Provider Name *</Label>
              <Input 
                placeholder="e.g. City Hospital, Dr. Smith" 
                value={formData.provider_name}
                onChange={e => setFormData({...formData, provider_name: e.target.value})}
              />
            </div>
            <div className="space-y-2">
              <Label>Lien Type *</Label>
              <Select value={formData.lien_type} onValueChange={v => setFormData({...formData, lien_type: v})}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="medical">Medical Provider</SelectItem>
                  <SelectItem value="attorney">Prior Attorney</SelectItem>
                  <SelectItem value="government_medicare">Medicare</SelectItem>
                  <SelectItem value="government_medicaid">Medicaid</SelectItem>
                  <SelectItem value="health_insurance">Health Insurance Subro</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>Original Lien Amount ($) *</Label>
                <Input 
                  type="number"
                  placeholder="0.00" 
                  value={formData.amount}
                  onChange={e => setFormData({...formData, amount: e.target.value})}
                />
              </div>
              <div className="space-y-2">
                <Label>Reduction Amount ($)</Label>
                <Input 
                  type="number"
                  placeholder="0.00" 
                  value={formData.reduction_amount}
                  onChange={e => setFormData({...formData, reduction_amount: e.target.value})}
                />
              </div>
            </div>
            <div className="space-y-2">
              <Label>Status</Label>
              <Select value={formData.status} onValueChange={v => setFormData({...formData, status: v})}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="pending">Pending</SelectItem>
                  <SelectItem value="negotiated">Negotiated</SelectItem>
                  <SelectItem value="settled">Settled</SelectItem>
                  <SelectItem value="released">Released</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>Notes</Label>
              <Input 
                placeholder="Negotiation details, contact person..." 
                value={formData.notes}
                onChange={e => setFormData({...formData, notes: e.target.value})}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setIsDialogOpen(false)}>Cancel</Button>
            <Button className="bg-emerald-600" onClick={handleAddLien} disabled={isCreating}>Record Lien</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
