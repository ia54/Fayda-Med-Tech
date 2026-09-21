"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Input } from "@/components/ui/input"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { FileText, Send, Plus, Search, Filter, MoreVertical, FileDown, Eye, Loader2 } from "lucide-react"
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
import { Textarea } from "@/components/ui/textarea"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { 
  useGetDemandLettersQuery, 
  useCreateDemandLetterMutation, 
  useUpdateDemandLetterMutation 
} from "@/store/api/demandLettersApiSlice"
import { useGetCasesQuery } from "@/store/api/casesApiSlice"
import { useToast } from "@/hooks/use-toast"
import { format } from "date-fns"

export default function DemandLettersPage() {
  const [searchTerm, setSearchTerm] = useState("")
  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const { toast } = useToast()

  // Queries
  const { data: demandLettersData, isLoading: isDemandsLoading } = useGetDemandLettersQuery({ search: searchTerm })
  const { data: casesData } = useGetCasesQuery({ per_page: 100 })
  const [createDemandLetter, { isLoading: isCreating }] = useCreateDemandLetterMutation()
  const [updateDemandLetter] = useUpdateDemandLetterMutation()

  const demandLetters = demandLettersData?.data?.data || []
  const cases = casesData?.data || []

  // Form State
  const [formData, setFormData] = useState({
    case_id: "",
    recipient_name: "",
    recipient_company: "",
    demand_amount: "",
    content: ""
  })

  const handleCreateDemand = async () => {
    try {
      if (!formData.case_id || !formData.recipient_name || !formData.demand_amount) {
        toast({
          title: "Missing fields",
          description: "Please fill in all required fields.",
          variant: "destructive"
        })
        return
      }

      await createDemandLetter({
        case_id: parseInt(formData.case_id),
        recipient_name: formData.recipient_name,
        recipient_company: formData.recipient_company,
        demand_amount: parseFloat(formData.demand_amount),
        content: formData.content,
        status: 'draft'
      }).unwrap()

      toast({
        title: "Success",
        description: "Demand letter generated successfully.",
      })
      setIsDialogOpen(false)
      setFormData({
        case_id: "",
        recipient_name: "",
        recipient_company: "",
        demand_amount: "",
        content: ""
      })
    } catch (error) {
      toast({
        title: "Error",
        description: "Failed to generate demand letter.",
        variant: "destructive"
      })
    }
  }

  const handleSendToPayer = async (id: number) => {
    try {
      await updateDemandLetter({ id, status: 'sent' }).unwrap()
      toast({
        title: "Sent",
        description: "Demand letter marked as sent.",
      })
    } catch (error) {
      toast({
        title: "Error",
        description: "Failed to update demand letter status.",
        variant: "destructive"
      })
    }
  }

  const getStatusBadge = (status: string) => {
    switch (status.toLowerCase()) {
      case 'draft': return <Badge variant="outline" className="bg-slate-50 text-slate-700 border-slate-200 font-bold uppercase text-[10px]">Draft</Badge>
      case 'sent': return <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200 font-bold uppercase text-[10px]">Sent</Badge>
      case 'accepted': return <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 font-bold uppercase text-[10px]">Accepted</Badge>
      case 'rejected': return <Badge variant="outline" className="bg-rose-50 text-rose-700 border-rose-200 font-bold uppercase text-[10px]">Rejected</Badge>
      default: return <Badge variant="outline">{status}</Badge>
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold tracking-tight text-primary">Demand Letters</h1>
          <p className="text-muted-foreground">Manage and track legal demand letters for all active cases.</p>
        </div>
        
        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
          <DialogTrigger asChild>
            <Button className="bg-emerald-600 hover:bg-emerald-700 text-white shadow-lg shadow-emerald-200">
              <Plus className="h-4 w-4 mr-2" />
              Generate New Demand
            </Button>
          </DialogTrigger>
          <DialogContent className="sm:max-w-[500px]">
            <DialogHeader>
              <DialogTitle>Generate New Demand Letter</DialogTitle>
              <DialogDescription>
                Create a formal demand letter for an active case.
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
              <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                  <Label htmlFor="recipient">Recipient Name *</Label>
                  <Input 
                    id="recipient" 
                    placeholder="e.g. John Doe" 
                    value={formData.recipient_name}
                    onChange={(e) => setFormData({...formData, recipient_name: e.target.value})}
                  />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="company">Company</Label>
                  <Input 
                    id="company" 
                    placeholder="e.g. State Farm" 
                    value={formData.recipient_company}
                    onChange={(e) => setFormData({...formData, recipient_company: e.target.value})}
                  />
                </div>
              </div>
              <div className="grid gap-2">
                <Label htmlFor="amount">Demand Amount ($) *</Label>
                <Input 
                  id="amount" 
                  type="number" 
                  placeholder="0.00" 
                  value={formData.demand_amount}
                  onChange={(e) => setFormData({...formData, demand_amount: e.target.value})}
                />
              </div>
              <div className="grid gap-2">
                <Label htmlFor="content">Letter Content</Label>
                <Textarea 
                  id="content" 
                  placeholder="Write the letter details here..." 
                  className="min-h-[100px]"
                  value={formData.content}
                  onChange={(e) => setFormData({...formData, content: e.target.value})}
                />
              </div>
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => setIsDialogOpen(false)}>Cancel</Button>
              <Button 
                onClick={handleCreateDemand} 
                className="bg-emerald-600 hover:bg-emerald-700 text-white"
                disabled={isCreating}
              >
                {isCreating ? <Loader2 className="h-4 w-4 animate-spin mr-2" /> : null}
                Generate Demand
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>

      <Card className="bg-card/50 backdrop-blur-sm border-border/50">
        <CardHeader className="pb-3">
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div className="flex items-center gap-2">
              <FileText className="h-5 w-5 text-emerald-600" />
              <CardTitle className="text-lg">Recent Demands</CardTitle>
            </div>
            <div className="flex items-center gap-2">
              <div className="relative">
                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder="Search demands..."
                  className="pl-9 bg-background/50 border-border/50 h-9 w-[200px] lg:w-[300px]"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                />
              </div>
              <Button variant="outline" size="sm" className="h-9">
                <Filter className="h-4 w-4 mr-2" />
                Filter
              </Button>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div className="rounded-xl border border-border/50 overflow-hidden">
            <Table>
              <TableHeader className="bg-muted/30">
                <TableRow>
                  <TableHead className="font-bold">Case #</TableHead>
                  <TableHead className="font-bold">Recipient</TableHead>
                  <TableHead className="font-bold">Demand Amount</TableHead>
                  <TableHead className="font-bold">Status</TableHead>
                  <TableHead className="font-bold">Last Updated</TableHead>
                  <TableHead className="text-right font-bold">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {isDemandsLoading ? (
                  <TableRow>
                    <TableCell colSpan={6} className="text-center py-10">
                      <Loader2 className="h-8 w-8 animate-spin mx-auto text-emerald-600" />
                    </TableCell>
                  </TableRow>
                ) : demandLetters.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={6} className="text-center py-10 text-muted-foreground">
                      No demand letters found.
                    </TableCell>
                  </TableRow>
                ) : (
                  demandLetters.map((letter: any) => (
                    <TableRow key={letter.id} className="hover:bg-primary/5 transition-colors">
                      <TableCell className="font-medium text-emerald-900">
                        {letter.case?.case_number || "N/A"}
                      </TableCell>
                      <TableCell>
                        <div className="flex flex-col">
                          <span className="font-medium">{letter.recipient_name}</span>
                          <span className="text-xs text-muted-foreground">{letter.recipient_company}</span>
                        </div>
                      </TableCell>
                      <TableCell className="font-semibold">${letter.demand_amount.toLocaleString()}</TableCell>
                      <TableCell>{getStatusBadge(letter.status)}</TableCell>
                      <TableCell className="text-muted-foreground text-sm">
                        {format(new Date(letter.updated_at), 'MMM dd, yyyy')}
                      </TableCell>
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
                              View Letter
                            </DropdownMenuItem>
                            <DropdownMenuItem>
                              <FileDown className="h-4 w-4 mr-2" />
                              Download PDF
                            </DropdownMenuItem>
                            {letter.status.toLowerCase() === 'draft' && (
                              <DropdownMenuItem 
                                className="text-emerald-600 font-bold"
                                onClick={() => handleSendToPayer(letter.id)}
                              >
                                <Send className="h-4 w-4 mr-2" />
                                Send to Payer
                              </DropdownMenuItem>
                            )}
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
  )
}
