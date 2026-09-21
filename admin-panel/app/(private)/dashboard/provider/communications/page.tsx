"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Badge } from "@/components/ui/badge"
import { Textarea } from "@/components/ui/textarea"
import { Search, Phone, Mail, MessageSquare, Plus, Clock } from "lucide-react"


const communications = [
  {
    id: 1,
    type: "Phone Call",
    contact: "John Smith (Patient)",
    subject: "Claim Status Inquiry",
    date: "2024-01-15 10:30 AM",
    duration: "8 minutes",
    notes:
      "Patient called to inquire about claim CLM-2024-001 status. Explained current processing stage and expected timeline.",
    claimId: "CLM-2024-001",
    staff: "Sarah Wilson",
  },
  {
    id: 2,
    type: "Email",
    contact: "attorney@lawfirm.com",
    subject: "Lien Documentation Request",
    date: "2024-01-14 2:15 PM",
    duration: null,
    notes: "Sent lien documentation for case #789. Requested e-signature on AOB forms.",
    claimId: "CLM-2024-003",
    staff: "Mike Johnson",
  },
  {
    id: 3,
    type: "SMS",
    contact: "Sarah Johnson (Patient)",
    subject: "Appointment Reminder",
    date: "2024-01-13 9:00 AM",
    duration: null,
    notes: "Sent reminder for follow-up appointment and document submission deadline.",
    claimId: "CLM-2024-005",
    staff: "Emily Davis",
  },
  {
    id: 4,
    type: "Phone Call",
    contact: "Blue Cross Claims Dept",
    subject: "Claim Denial Discussion",
    date: "2024-01-12 3:45 PM",
    duration: "15 minutes",
    notes: "Discussed denial reason for CLM-2024-007. Obtained additional requirements for appeal.",
    claimId: "CLM-2024-007",
    staff: "Sarah Wilson",
  },
]

export default function CommunicationsPage() {
  const [searchTerm, setSearchTerm] = useState("")
  const [selectedType, setSelectedType] = useState("All")

  const filteredCommunications = communications.filter((comm) => {
    const matchesSearch =
      comm.contact.toLowerCase().includes(searchTerm.toLowerCase()) ||
      comm.subject.toLowerCase().includes(searchTerm.toLowerCase()) ||
      comm.claimId.toLowerCase().includes(searchTerm.toLowerCase())
    const matchesType = selectedType === "All" || comm.type === selectedType
    return matchesSearch && matchesType
  })

  const getTypeIcon = (type: string) => {
    switch (type) {
      case "Phone Call":
        return <Phone className="h-4 w-4" />
      case "Email":
        return <Mail className="h-4 w-4" />
      case "SMS":
        return <MessageSquare className="h-4 w-4" />
      default:
        return <MessageSquare className="h-4 w-4" />
    }
  }

  const getTypeColor = (type: string) => {
    switch (type) {
      case "Phone Call":
        return "bg-blue-100 text-blue-800"
      case "Email":
        return "bg-green-100 text-green-800"
      case "SMS":
        return "bg-purple-100 text-purple-800"
      default:
        return "bg-gray-100 text-gray-800"
    }
  }

  return (
   
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-3xl font-bold text-gray-900">Communications</h1>
            <p className="text-gray-600">Track all patient and attorney communications with audit trail</p>
          </div>
          <Button className="bg-[#0A3B1E] hover:bg-[#2E7D32]">
            <Plus className="h-4 w-4 mr-2" />
            Log Communication
          </Button>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2">
            <Card className="backdrop-blur-sm bg-white/90 border-0 shadow-lg">
              <CardHeader>
                <CardTitle className="text-[#0A3B1E]">Communication Log</CardTitle>
                <CardDescription>Complete audit trail of all communications</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="flex items-center space-x-4 mb-6">
                  <div className="flex items-center space-x-2 flex-1">
                    <Search className="h-4 w-4 text-gray-400" />
                    <Input
                      placeholder="Search communications..."
                      value={searchTerm}
                      onChange={(e) => setSearchTerm(e.target.value)}
                      className="flex-1"
                    />
                  </div>
                  <div className="flex space-x-2">
                    {["All", "Phone Call", "Email", "SMS"].map((type) => (
                      <Button
                        key={type}
                        variant={selectedType === type ? "default" : "outline"}
                        size="sm"
                        onClick={() => setSelectedType(type)}
                        className={selectedType === type ? "bg-[#0A3B1E] hover:bg-[#2E7D32]" : ""}
                      >
                        {type}
                      </Button>
                    ))}
                  </div>
                </div>

                <div className="space-y-4">
                  {filteredCommunications.map((comm) => (
                    <Card key={comm.id} className="hover:shadow-md transition-shadow">
                      <CardContent className="p-4">
                        <div className="flex items-start justify-between mb-3">
                          <div className="flex items-center space-x-3">
                            <div className="p-2 bg-[#4CAF50]/10 rounded-lg">{getTypeIcon(comm.type)}</div>
                            <div>
                              <h3 className="font-semibold text-gray-900">{comm.subject}</h3>
                              <p className="text-sm text-gray-600">{comm.contact}</p>
                            </div>
                          </div>
                          <div className="text-right">
                            <Badge className={getTypeColor(comm.type)}>{comm.type}</Badge>
                            <div className="flex items-center space-x-1 mt-1 text-xs text-gray-500">
                              <Clock className="h-3 w-3" />
                              <span>{comm.date}</span>
                            </div>
                          </div>
                        </div>

                        <div className="space-y-2 text-sm">
                          <p className="text-gray-700">{comm.notes}</p>
                          <div className="flex items-center justify-between text-gray-500">
                            <div className="flex items-center space-x-4">
                              <span>
                                <strong>Claim:</strong> {comm.claimId}
                              </span>
                              <span>
                                <strong>Staff:</strong> {comm.staff}
                              </span>
                              {comm.duration && (
                                <span>
                                  <strong>Duration:</strong> {comm.duration}
                                </span>
                              )}
                            </div>
                          </div>
                        </div>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              </CardContent>
            </Card>
          </div>

          <div>
            <Card className="backdrop-blur-sm bg-white/90 border-0 shadow-lg">
              <CardHeader>
                <CardTitle className="text-[#0A3B1E]">Quick Log</CardTitle>
                <CardDescription>Add new communication entry</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div>
                  <label className="text-sm font-medium text-gray-700">Type</label>
                  <select className="w-full mt-1 p-2 border border-gray-300 rounded-md">
                    <option>Phone Call</option>
                    <option>Email</option>
                    <option>SMS</option>
                  </select>
                </div>

                <div>
                  <label className="text-sm font-medium text-gray-700">Contact</label>
                  <Input placeholder="Contact name or email" className="mt-1" />
                </div>

                <div>
                  <label className="text-sm font-medium text-gray-700">Subject</label>
                  <Input placeholder="Communication subject" className="mt-1" />
                </div>

                <div>
                  <label className="text-sm font-medium text-gray-700">Claim ID</label>
                  <Input placeholder="CLM-2024-XXX" className="mt-1" />
                </div>

                <div>
                  <label className="text-sm font-medium text-gray-700">Notes</label>
                  <Textarea placeholder="Detailed notes about the communication..." className="mt-1 min-h-[100px]" />
                </div>

                <Button className="w-full bg-[#0A3B1E] hover:bg-[#2E7D32]">
                  <Plus className="h-4 w-4 mr-2" />
                  Log Communication
                </Button>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
 
  )
}
