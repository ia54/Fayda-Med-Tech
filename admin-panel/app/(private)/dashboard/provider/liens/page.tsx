"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Badge } from "@/components/ui/badge"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Textarea } from "@/components/ui/textarea"
import { Send, Plus, Search, MoreHorizontal, Edit, Eye, Download, FileSignature, Clock } from "lucide-react"

import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu"

export default function LiensPage() {
  const [searchTerm, setSearchTerm] = useState("")
  const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false)

  const liens = [
    {
      id: "LIEN-2024-001",
      patient: "John Smith",
      attorney: "Smith & Associates Law",
      amount: "$1,250.00",
      status: "Signed",
      type: "Medical Lien",
      created: "2024-03-15",
      signed: "2024-03-16",
      email: "john.smith@smithlaw.com",
    },
    {
      id: "AOB-2024-002",
      patient: "Sarah Johnson",
      attorney: "Johnson Legal Group",
      amount: "$890.50",
      status: "Pending Signature",
      type: "Assignment of Benefits",
      created: "2024-03-14",
      signed: "-",
      email: "sarah@johnsonlegal.com",
    },
    {
      id: "LIEN-2024-003",
      patient: "Michael Brown",
      attorney: "Brown & Partners",
      amount: "$2,100.00",
      status: "Draft",
      type: "Medical Lien",
      created: "2024-03-13",
      signed: "-",
      email: "michael@brownpartners.com",
    },
    {
      id: "AOB-2024-004",
      patient: "Emily Davis",
      attorney: "Davis Law Firm",
      amount: "$675.25",
      status: "Sent for Signature",
      type: "Assignment of Benefits",
      created: "2024-03-12",
      signed: "-",
      email: "emily@davislawfirm.com",
    },
  ]

  const filteredLiens = liens.filter(
    (lien) =>
      lien.patient.toLowerCase().includes(searchTerm.toLowerCase()) ||
      lien.id.toLowerCase().includes(searchTerm.toLowerCase()) ||
      lien.attorney.toLowerCase().includes(searchTerm.toLowerCase()),
  )

  const getStatusColor = (status: string) => {
    switch (status) {
      case "Signed":
        return "bg-chart-2 text-white"
      case "Sent for Signature":
        return "bg-primary text-white"
      case "Pending Signature":
        return "bg-chart-3 text-white"
      case "Draft":
        return "bg-muted text-muted-foreground"
      default:
        return "bg-muted"
    }
  }

  return (
 
      <div className="space-y-8">
        {/* Header */}
        <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <h1 className="text-3xl font-bold text-primary flex items-center gap-2">
              <Send className="h-8 w-8" />
              Liens & AOB Management
            </h1>
            <p className="text-muted-foreground">Generate and manage medical liens and assignment of benefits</p>
          </div>
          <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
            <DialogTrigger asChild>
              <Button>
                <Plus className="h-4 w-4 mr-2" />
                Create Lien/AOB
              </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-[600px] max-h-[80vh] overflow-y-auto">
              <DialogHeader>
                <DialogTitle>Create New Lien/AOB</DialogTitle>
                <DialogDescription>Generate a medical lien or assignment of benefits document.</DialogDescription>
              </DialogHeader>
              <div className="grid gap-4 py-4">
                <div className="grid gap-2">
                  <Label htmlFor="documentType">Document Type</Label>
                  <Select>
                    <SelectTrigger>
                      <SelectValue placeholder="Select document type" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="lien">Medical Lien</SelectItem>
                      <SelectItem value="aob">Assignment of Benefits</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div className="grid gap-2">
                    <Label htmlFor="patientName">Patient Name</Label>
                    <Input id="patientName" placeholder="Enter patient name" />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="patientDOB">Date of Birth</Label>
                    <Input id="patientDOB" type="date" />
                  </div>
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="attorneyFirm">Attorney/Law Firm</Label>
                  <Input id="attorneyFirm" placeholder="Enter attorney or law firm name" />
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div className="grid gap-2">
                    <Label htmlFor="attorneyEmail">Attorney Email</Label>
                    <Input id="attorneyEmail" type="email" placeholder="attorney@lawfirm.com" />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="attorneyPhone">Attorney Phone</Label>
                    <Input id="attorneyPhone" placeholder="(555) 123-4567" />
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div className="grid gap-2">
                    <Label htmlFor="treatmentAmount">Treatment Amount</Label>
                    <Input id="treatmentAmount" type="number" placeholder="0.00" step="0.01" />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="incidentDate">Incident Date</Label>
                    <Input id="incidentDate" type="date" />
                  </div>
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="caseDescription">Case Description</Label>
                  <Textarea id="caseDescription" placeholder="Brief description of the case or incident" />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="specialInstructions">Special Instructions</Label>
                  <Textarea id="specialInstructions" placeholder="Any special instructions or notes" />
                </div>
              </div>
              <div className="flex justify-end gap-2">
                <Button variant="outline" onClick={() => setIsCreateDialogOpen(false)}>
                  Save as Draft
                </Button>
                <Button onClick={() => setIsCreateDialogOpen(false)}>Generate & Send</Button>
              </div>
            </DialogContent>
          </Dialog>
        </div>

        {/* Stats Cards */}
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <Card className="hover:shadow-lg transition-all duration-300 hover:-translate-y-1 bg-card/80 backdrop-blur-sm">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Total Liens/AOBs</CardTitle>
              <Send className="h-4 w-4 text-primary" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">47</div>
              <p className="text-xs text-muted-foreground">+5 from last month</p>
            </CardContent>
          </Card>
          <Card className="hover:shadow-lg transition-all duration-300 hover:-translate-y-1 bg-card/80 backdrop-blur-sm">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Signed</CardTitle>
              <FileSignature className="h-4 w-4 text-chart-2" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">32</div>
              <p className="text-xs text-muted-foreground">68% completion rate</p>
            </CardContent>
          </Card>
          <Card className="hover:shadow-lg transition-all duration-300 hover:-translate-y-1 bg-card/80 backdrop-blur-sm">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Pending</CardTitle>
              <Clock className="h-4 w-4 text-chart-3" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">12</div>
              <p className="text-xs text-muted-foreground">Awaiting signatures</p>
            </CardContent>
          </Card>
          <Card className="hover:shadow-lg transition-all duration-300 hover:-translate-y-1 bg-card/80 backdrop-blur-sm">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Total Value</CardTitle>
              <Badge className="h-4 w-4 bg-primary" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">$89,450</div>
              <p className="text-xs text-muted-foreground">Protected amount</p>
            </CardContent>
          </Card>
        </div>

        {/* Liens Table */}
        <Card className="hover:shadow-lg transition-all duration-300 bg-card/80 backdrop-blur-sm">
          <CardHeader>
            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
              <div>
                <CardTitle>Liens & AOB List</CardTitle>
                <CardDescription>Manage all medical liens and assignment of benefits</CardDescription>
              </div>
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder="Search liens..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="pl-10 w-full md:w-80"
                />
              </div>
            </div>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Document ID</TableHead>
                  <TableHead>Patient</TableHead>
                  <TableHead>Attorney/Firm</TableHead>
                  <TableHead>Type</TableHead>
                  <TableHead>Amount</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Created</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {filteredLiens.map((lien) => (
                  <TableRow key={lien.id} className="hover:bg-muted/50">
                    <TableCell className="font-medium">{lien.id}</TableCell>
                    <TableCell>{lien.patient}</TableCell>
                    <TableCell>
                      <div>
                        <div className="font-medium">{lien.attorney}</div>
                        <div className="text-sm text-muted-foreground">{lien.email}</div>
                      </div>
                    </TableCell>
                    <TableCell>
                      <Badge variant="outline">{lien.type}</Badge>
                    </TableCell>
                    <TableCell className="font-medium">{lien.amount}</TableCell>
                    <TableCell>
                      <Badge className={getStatusColor(lien.status)}>{lien.status}</Badge>
                    </TableCell>
                    <TableCell>{lien.created}</TableCell>
                    <TableCell className="text-right">
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" className="h-8 w-8 p-0">
                            <MoreHorizontal className="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem>
                            <Eye className="mr-2 h-4 w-4" />
                            View Document
                          </DropdownMenuItem>
                          <DropdownMenuItem>
                            <Edit className="mr-2 h-4 w-4" />
                            Edit Document
                          </DropdownMenuItem>
                          <DropdownMenuItem>
                            <Send className="mr-2 h-4 w-4" />
                            Send for Signature
                          </DropdownMenuItem>
                          <DropdownMenuItem>
                            <Download className="mr-2 h-4 w-4" />
                            Download PDF
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>
 
  )
}
