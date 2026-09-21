"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Input } from "@/components/ui/input"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { 
  FileText, Download, Search, Filter, Eye, CheckCircle, 
  Clock, AlertTriangle, Loader2, Plus, Trash2, Edit, MoreVertical 
} from "lucide-react"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { 
  useGetLiensQuery, 
  useCreateLienMutation, 
  useUpdateLienMutation, 
  useDeleteLienMutation 
} from "@/store/api/liensApiSlice"
import { useGetCasesQuery } from "@/store/api/casesApiSlice"
import { useGetProvidersQuery } from "@/store/api/apiSlice"
import { useToast } from "@/hooks/use-toast"
import { format } from "date-fns"

export default function LiensPage() {
  const [searchTerm, setSearchTerm] = useState("")
  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const { toast } = useToast()

  // Queries
  const { data: liensData, isLoading: isLiensLoading } = useGetLiensQuery({ search: searchTerm })
  const { data: casesData } = useGetCasesQuery({ per_page: 100 })
  const { data: providersData } = useGetProvidersQuery({})
  
  const [createLien, { isLoading: isCreating }] = useCreateLienMutation()
  const [updateLien] = useUpdateLienMutation()
  const [deleteLien] = useDeleteLienMutation()

  const liens = liensData?.data?.data || []
  const cases = casesData?.data || []
  const providers = providersData?.data?.data || []

  // Form State
  const [formData, setFormData] = useState({
    case_id: "",
    provider_id: "",
    lien_type: "medical",
    amount: "",
    status: "pending",
    notes: ""
  })

  const handleCreateLien = async () => {
    try {
      if (!formData.case_id || !formData.amount) {
        toast({
          title: "Missing fields",
          description: "Please fill in all required fields.",
          variant: "destructive"
        })
        return
      }

      await createLien({
        case_id: parseInt(formData.case_id),
        provider_id: formData.provider_id ? parseInt(formData.provider_id) : undefined,
        lien_type: formData.lien_type as any,
        amount: parseFloat(formData.amount),
        status: formData.status as any,
        notes: formData.notes
      }).unwrap()

      toast({
        title: "Success",
        description: "Lien record created successfully.",
      })
      setIsDialogOpen(false)
      setFormData({
        case_id: "",
        provider_id: "",
        lien_type: "medical",
        amount: "",
        status: "pending",
        notes: ""
      })
    } catch (error) {
      toast({
        title: "Error",
        description: "Failed to create lien record.",
        variant: "destructive"
      })
    }
  }

  const handleDeleteLien = async (id: number) => {
    if (!confirm("Are you sure you want to delete this lien?")) return
    try {
      await deleteLien(id).unwrap()
      toast({
        title: "Deleted",
        description: "Lien record deleted successfully.",
      })
    } catch (error) {
      toast({
        title: "Error",
        description: "Failed to delete lien record.",
        variant: "destructive"
      })
    }
  }

  const getStatusBadge = (status: string) => {
    switch (status.toLowerCase()) {
      case 'settled':
      case 'released':
      case 'signed': return <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 font-bold uppercase text-[10px]">Settled</Badge>
      case 'pending': return <Badge variant="outline" className="bg-amber-50 text-amber-700 border-amber-200 font-bold uppercase text-[10px]">Pending</Badge>
      case 'negotiated': return <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200 font-bold uppercase text-[10px]">Negotiated</Badge>
      case 'expired': return <Badge variant="outline" className="bg-rose-50 text-rose-700 border-rose-200 font-bold uppercase text-[10px]">Expired</Badge>
      default: return <Badge variant="outline">{status}</Badge>
    }
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50/50 via-white to-green-50/30 dark:from-emerald-950/20 dark:via-slate-950 dark:to-green-950/20 p-6">
      <div className="max-w-7xl mx-auto space-y-8">
        {/* Header */}
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold text-gray-900 dark:text-white text-balance">Liens & AOB Documents</h1>
            <p className="text-gray-600 dark:text-slate-300 mt-2">Download lien agreements and assignment of benefits forms</p>
          </div>
          <div className="flex gap-3">
            <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
              <DialogTrigger asChild>
                <Button className="bg-emerald-600 hover:bg-emerald-700 text-white shadow-lg shadow-emerald-200">
                  <Plus className="h-4 w-4 mr-2" />
                  Create New Lien
                </Button>
              </DialogTrigger>
              <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                  <DialogTitle>Create New Lien Record</DialogTitle>
                  <DialogDescription>
                    Add a new lien or LOP to a specific case.
                  </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 py-4">
                  <div className="grid gap-2">
                    <Label htmlFor="case">Case *</Label>
                    <Select 
                      value={formData.case_id} 
                      onValueChange={(value) => setFormData({...formData, case_id: value})}
                    >
                      <SelectTrigger>
                        <SelectValue placeholder="Select a case" />
                      </SelectTrigger>
                      <SelectContent>
                        {cases.map((c) => (
                          <SelectItem key={c.id} value={c.id.toString()}>
                            {c.case_number} - {c.title}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="provider">Provider</Label>
                    <Select 
                      value={formData.provider_id} 
                      onValueChange={(value) => setFormData({...formData, provider_id: value})}
                    >
                      <SelectTrigger>
                        <SelectValue placeholder="Select a provider (optional)" />
                      </SelectTrigger>
                      <SelectContent>
                        {providers.map((p: any) => (
                          <SelectItem key={p.id} value={p.id.toString()}>
                            {p.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                      <Label htmlFor="type">Lien Type *</Label>
                      <Select 
                        value={formData.lien_type} 
                        onValueChange={(value) => setFormData({...formData, lien_type: value})}
                      >
                        <SelectTrigger>
                          <SelectValue placeholder="Select type" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="medical">Medical</SelectItem>
                          <SelectItem value="attorney">Attorney</SelectItem>
                          <SelectItem value="government_medicare">Medicare</SelectItem>
                          <SelectItem value="government_medicaid">Medicaid</SelectItem>
                          <SelectItem value="health_insurance">Health Insurance</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="grid gap-2">
                      <Label htmlFor="amount">Lien Amount ($) *</Label>
                      <Input 
                        id="amount" 
                        type="number" 
                        placeholder="0.00" 
                        value={formData.amount}
                        onChange={(e) => setFormData({...formData, amount: e.target.value})}
                      />
                    </div>
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="notes">Notes</Label>
                    <Input 
                      id="notes" 
                      placeholder="Additional details..." 
                      value={formData.notes}
                      onChange={(e) => setFormData({...formData, notes: e.target.value})}
                    />
                  </div>
                </div>
                <DialogFooter>
                  <Button variant="outline" onClick={() => setIsDialogOpen(false)}>Cancel</Button>
                  <Button 
                    onClick={handleCreateLien} 
                    className="bg-emerald-600 hover:bg-emerald-700 text-white"
                    disabled={isCreating}
                  >
                    {isCreating ? <Loader2 className="h-4 w-4 animate-spin mr-2" /> : null}
                    Create Lien
                  </Button>
                </DialogFooter>
              </DialogContent>
            </Dialog>

            <Button variant="outline" className="border-emerald-200 dark:border-emerald-800 hover:bg-emerald-50 dark:hover:bg-emerald-900/50 bg-transparent text-emerald-700 dark:text-white">
              <Download className="w-4 h-4 mr-2" />
              Bulk Download
            </Button>
          </div>
        </div>

        {/* Overview Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 dark:text-white flex items-center gap-2">
                <FileText className="w-4 h-4 text-emerald-600 dark:text-slate-300" />
                Total Liens
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-900 dark:text-white">
                {isLiensLoading ? "..." : liens.length}
              </div>
              <p className="text-xs text-emerald-600 dark:text-slate-300 mt-1">Across all active cases</p>
            </CardContent>
          </Card>

          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 dark:text-white flex items-center gap-2">
                <CheckCircle className="w-4 h-4 text-green-600 dark:text-green-400" />
                Settled Liens
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-900 dark:text-white">
                {isLiensLoading ? "..." : liens.filter((l: any) => l.status === 'settled' || l.status === 'released').length}
              </div>
              <p className="text-xs text-green-600 dark:text-green-400 mt-1">Released and closed</p>
            </CardContent>
          </Card>

          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 dark:text-white flex items-center gap-2">
                <Clock className="w-4 h-4 text-amber-600 dark:text-amber-400" />
                Pending Liens
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-900 dark:text-white">
                {isLiensLoading ? "..." : liens.filter((l: any) => l.status === 'pending').length}
              </div>
              <p className="text-xs text-amber-600 dark:text-amber-400 mt-1">In negotiation</p>
            </CardContent>
          </Card>

          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 dark:text-white flex items-center gap-2">
                <AlertTriangle className="w-4 h-4 text-red-600 dark:text-red-400" />
                Total Amount
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-900 dark:text-white">
                ${isLiensLoading ? "0" : liens.reduce((acc: number, l: any) => acc + parseFloat(l.amount), 0).toLocaleString()}
              </div>
              <p className="text-xs text-red-600 dark:text-red-400 mt-1">Total outstanding value</p>
            </CardContent>
          </Card>
        </div>

        {/* Lien Agreements */}
        <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50">
          <CardHeader>
            <CardTitle className="text-xl font-semibold text-gray-900 dark:text-white">Lien Agreements</CardTitle>
            <CardDescription className="dark:text-white">Download and manage lien agreement documents</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="flex flex-col md:flex-row gap-4 mb-6">
              <div className="relative flex-1">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <Input 
                  placeholder="Search liens..." 
                  className="pl-10" 
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                />
              </div>
              <Select defaultValue="all">
                <SelectTrigger className="w-full md:w-48">
                  <SelectValue placeholder="Filter by status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Status</SelectItem>
                  <SelectItem value="signed">Signed</SelectItem>
                  <SelectItem value="pending">Pending</SelectItem>
                  <SelectItem value="expired">Expired</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Case Number</TableHead>
                    <TableHead>Lien Type</TableHead>
                    <TableHead>Provider</TableHead>
                    <TableHead>Amount</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Created Date</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {isLiensLoading ? (
                    <TableRow>
                      <TableCell colSpan={7} className="text-center py-10">
                        <Loader2 className="h-8 w-8 animate-spin mx-auto text-emerald-600" />
                      </TableCell>
                    </TableRow>
                  ) : liens.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={7} className="text-center py-10 text-muted-foreground">
                        No lien records found.
                      </TableCell>
                    </TableRow>
                  ) : (
                    liens.map((lien: any) => (
                      <TableRow key={lien.id} className="hover:bg-emerald-50/50">
                        <TableCell className="font-medium">{lien.case?.case_number || "N/A"}</TableCell>
                        <TableCell className="capitalize">{lien.lien_type.replace('_', ' ')}</TableCell>
                        <TableCell>{lien.provider?.name || "Multiple Providers"}</TableCell>
                        <TableCell className="font-medium">${parseFloat(lien.amount).toLocaleString()}</TableCell>
                        <TableCell>
                          {getStatusBadge(lien.status)}
                        </TableCell>
                        <TableCell>{format(new Date(lien.created_at), 'MMM dd, yyyy')}</TableCell>
                        <TableCell className="text-right">
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button variant="ghost" size="icon">
                                <MoreVertical className="h-4 w-4" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="bg-card/95 backdrop-blur-md">
                              <DropdownMenuItem>
                                <Eye className="h-4 w-4 mr-2" />
                                View Details
                              </DropdownMenuItem>
                              <DropdownMenuItem>
                                <Download className="h-4 w-4 mr-2" />
                                Download PDF
                              </DropdownMenuItem>
                              <DropdownMenuItem 
                                className="text-rose-600"
                                onClick={() => handleDeleteLien(lien.id)}
                              >
                                <Trash2 className="h-4 w-4 mr-2" />
                                Delete Lien
                              </DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
