"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
  DialogFooter,
} from "@/components/ui/dialog"
import { DollarSign, Calendar, FileText, Search, Filter, Edit, CheckCircle, Loader2 } from "lucide-react"
import { 
  useGetSettlementsQuery, 
  useCreateSettlementMutation, 
  useUpdateSettlementMutation 
} from "@/store/api/apiSlice"
import { useGetCasesQuery } from "@/store/api/casesApiSlice"
import { useToast } from "@/hooks/use-toast"
import { format } from "date-fns"

interface Case {
  id: number;
  case_number: string;
  title: string;
}

interface Settlement {
  id: number;
  case_id: number;
  settlement_amount: string;
  settlement_date: string;
  status: string;
  notes: string;
  case?: Case;
}

export default function SettlementsPage() {
  const [searchTerm, setSearchTerm] = useState("")
  const [statusFilter, setStatusFilter] = useState("all")
  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const { toast } = useToast()

  // Queries
  const { data: settlementsData, isLoading: isSettlementsLoading } = useGetSettlementsQuery({ 
    search: searchTerm,
    status: statusFilter === "all" ? undefined : statusFilter 
  })
  const { data: casesData } = useGetCasesQuery({ per_page: 100 })
  
  const [createSettlement, { isLoading: isCreating }] = useCreateSettlementMutation()
  const [updateSettlement] = useUpdateSettlementMutation()

  const settlements = (settlementsData?.data?.data || []) as Settlement[]
  const cases = (casesData?.data || []) as Case[]

  // Form State
  const [formData, setFormData] = useState({
    case_id: "",
    settlement_amount: "",
    settlement_date: new Date().toISOString().split('T')[0],
    status: "completed",
    notes: ""
  })

  const handleMarkSettlement = async () => {
    try {
      if (!formData.case_id || !formData.settlement_amount || !formData.settlement_date) {
        toast({
          title: "Missing fields",
          description: "Please fill in all required fields.",
          variant: "destructive"
        })
        return
      }

      await createSettlement({
        case_id: parseInt(formData.case_id),
        settlement_amount: parseFloat(formData.settlement_amount),
        settlement_date: formData.settlement_date,
        status: formData.status,
        notes: formData.notes
      }).unwrap()

      toast({
        title: "Success",
        description: "Settlement recorded successfully.",
      })
      setIsDialogOpen(false)
      setFormData({
        case_id: "",
        settlement_amount: "",
        settlement_date: new Date().toISOString().split('T')[0],
        status: "completed",
        notes: ""
      })
    } catch (error) {
      toast({
        title: "Error",
        description: "Failed to record settlement.",
        variant: "destructive"
      })
    }
  }

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'completed': return <Badge className="bg-emerald-600">Completed</Badge>
      case 'pending': return <Badge variant="secondary">Pending</Badge>
      case 'in-negotiation': return <Badge variant="outline">In Negotiation</Badge>
      default: return <Badge variant="outline">{status}</Badge>
    }
  }

  const totalSettlementValue = settlements.reduce((acc: number, s: Settlement) => acc + parseFloat(s.settlement_amount), 0)

  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50/50 via-white to-green-50/30 dark:from-emerald-950/20 dark:via-slate-950 dark:to-green-950/20 p-6">
      <div className="max-w-7xl mx-auto space-y-8">
        {/* Header */}
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold text-gray-900 dark:text-white text-balance">Settlement Notes</h1>
            <p className="text-gray-600 dark:text-slate-300 mt-2">Mark settled dates and amounts with detailed notes</p>
          </div>
          <div className="flex gap-3">
            <Button variant="outline" className="border-emerald-200 dark:border-emerald-800 hover:bg-emerald-50 dark:hover:bg-emerald-900/50 bg-transparent text-emerald-700 dark:text-white">
              <Filter className="w-4 h-4 mr-2" />
              Filter
            </Button>
            <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
              <DialogTrigger asChild>
                <Button className="bg-emerald-600 dark:bg-emerald-500 hover:bg-emerald-700 dark:hover:bg-emerald-600 text-white dark:text-slate-950 font-medium">
                  <CheckCircle className="w-4 h-4 mr-2" />
                  Mark Settlement
                </Button>
              </DialogTrigger>
              <DialogContent className="max-w-2xl">
                <DialogHeader>
                  <DialogTitle>Mark Case Settlement</DialogTitle>
                  <DialogDescription>Record settlement details and final notes for the case</DialogDescription>
                </DialogHeader>
                <div className="space-y-4">
                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-2">
                      <Label htmlFor="case-number">Case *</Label>
                      <Select 
                        value={formData.case_id} 
                        onValueChange={(value) => setFormData({...formData, case_id: value})}
                      >
                        <SelectTrigger>
                          <SelectValue placeholder="Select case" />
                        </SelectTrigger>
                        <SelectContent>
                          {cases.map((c: Case) => (
                            <SelectItem key={c.id} value={c.id.toString()}>{c.case_number} - {c.title}</SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="settlement-date">Settlement Date *</Label>
                      <Input 
                        id="settlement-date" 
                        type="date" 
                        value={formData.settlement_date}
                        onChange={(e) => setFormData({...formData, settlement_date: e.target.value})}
                      />
                    </div>
                  </div>
                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-2">
                      <Label htmlFor="settlement-amount">Settlement Amount ($) *</Label>
                      <Input 
                        id="settlement-amount" 
                        type="number"
                        placeholder="0.00" 
                        value={formData.settlement_amount}
                        onChange={(e) => setFormData({...formData, settlement_amount: e.target.value})}
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="status">Status *</Label>
                      <Select 
                        value={formData.status} 
                        onValueChange={(value) => setFormData({...formData, status: value})}
                      >
                        <SelectTrigger>
                          <SelectValue placeholder="Select status" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="completed">Completed</SelectItem>
                          <SelectItem value="pending">Pending</SelectItem>
                          <SelectItem value="in-negotiation">In Negotiation</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="settlement-notes">Settlement Notes</Label>
                    <Textarea
                      id="settlement-notes"
                      placeholder="Enter detailed notes about the settlement..."
                      rows={6}
                      value={formData.notes}
                      onChange={(e) => setFormData({...formData, notes: e.target.value})}
                    />
                  </div>
                  <DialogFooter>
                    <Button variant="outline" onClick={() => setIsDialogOpen(false)}>Cancel</Button>
                    <Button 
                      className="bg-emerald-600 hover:bg-emerald-700" 
                      onClick={handleMarkSettlement}
                      disabled={isCreating}
                    >
                      {isCreating && <Loader2 className="w-4 h-4 mr-2 animate-spin" />}
                      Save Settlement
                    </Button>
                  </DialogFooter>
                </div>
              </DialogContent>
            </Dialog>
          </div>
        </div>

        {/* Overview Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 dark:text-white flex items-center gap-2">
                <DollarSign className="w-4 h-4 text-emerald-600 dark:text-white" />
                Total Settlements
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-900 dark:text-white">
                ${totalSettlementValue.toLocaleString()}
              </div>
              <p className="text-xs text-emerald-600 dark:text-white mt-1">Life-to-date</p>
            </CardContent>
          </Card>

          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 dark:text-white flex items-center gap-2">
                <CheckCircle className="w-4 h-4 text-green-600 dark:text-green-400" />
                Completed Settlements
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-900 dark:text-white">
                {settlements.filter(s => s.status === 'completed').length}
              </div>
              <p className="text-xs text-green-600 dark:text-green-400 mt-1">Cases closed</p>
            </CardContent>
          </Card>

          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 dark:text-white flex items-center gap-2">
                <Calendar className="w-4 h-4 text-blue-600 dark:text-blue-400" />
                Pending Settlements
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-900 dark:text-white">
                {settlements.filter(s => s.status === 'pending').length}
              </div>
              <p className="text-xs text-blue-600 dark:text-blue-400 mt-1">In progress</p>
            </CardContent>
          </Card>

          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-gray-600 dark:text-white flex items-center gap-2">
                <FileText className="w-4 h-4 text-amber-600 dark:text-amber-400" />
                Active Negotiations
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-gray-900 dark:text-white">
                {settlements.filter(s => s.status === 'in-negotiation').length}
              </div>
              <p className="text-xs text-amber-600 dark:text-amber-400 mt-1">Current discussions</p>
            </CardContent>
          </Card>
        </div>

        {/* Settlements Table */}
        <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50">
          <CardHeader>
            <CardTitle className="text-xl font-semibold text-gray-900 dark:text-white">Settlement Records</CardTitle>
            <CardDescription className="dark:text-slate-300">Track all case settlements and their details</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="flex flex-col md:flex-row gap-4 mb-6">
              <div className="relative flex-1">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <Input 
                  placeholder="Search settlements..." 
                  className="pl-10" 
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                />
              </div>
              <Select value={statusFilter} onValueChange={setStatusFilter}>
                <SelectTrigger className="w-full md:w-48">
                  <SelectValue placeholder="Filter by status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Status</SelectItem>
                  <SelectItem value="completed">Completed</SelectItem>
                  <SelectItem value="pending">Pending</SelectItem>
                  <SelectItem value="in-negotiation">In Negotiation</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Case Number</TableHead>
                    <TableHead>Case Title</TableHead>
                    <TableHead>Settlement Amount</TableHead>
                    <TableHead>Settlement Date</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Notes</TableHead>
                    <TableHead>Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {isSettlementsLoading ? (
                    <TableRow>
                      <TableCell colSpan={7} className="text-center py-10">
                        <Loader2 className="w-8 h-8 animate-spin mx-auto text-emerald-600" />
                      </TableCell>
                    </TableRow>
                  ) : settlements.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={7} className="text-center py-10 text-muted-foreground">
                        No settlement records found.
                      </TableCell>
                    </TableRow>
                  ) : (
                    settlements.map((settlement) => (
                      <TableRow key={settlement.id} className="hover:bg-emerald-50/50 dark:hover:bg-emerald-900/20 border-emerald-100 dark:border-emerald-900/30">
                        <TableCell className="font-medium">{settlement.case?.case_number || "N/A"}</TableCell>
                        <TableCell>{settlement.case?.title || "N/A"}</TableCell>
                        <TableCell className="font-medium text-green-600">
                          ${parseFloat(settlement.settlement_amount).toLocaleString()}
                        </TableCell>
                        <TableCell>{format(new Date(settlement.settlement_date), "MMM dd, yyyy")}</TableCell>
                        <TableCell>
                          {getStatusBadge(settlement.status)}
                        </TableCell>
                        <TableCell className="max-w-xs truncate">{settlement.notes}</TableCell>
                        <TableCell>
                          <Button variant="ghost" size="sm">
                            <Edit className="w-4 h-4" />
                          </Button>
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
