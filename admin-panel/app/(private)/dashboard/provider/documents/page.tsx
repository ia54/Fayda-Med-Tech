"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Badge } from "@/components/ui/badge"
import { Search, Upload, Download, Eye, FileText, ImageIcon, File } from "lucide-react"


const documents = [
  {
    id: 1,
    name: "HCFA-1500_Patient_001.pdf",
    type: "HCFA-1500",
    size: "2.4 MB",
    uploadDate: "2024-01-15",
    claimId: "CLM-2024-001",
    status: "Processed",
  },
  {
    id: 2,
    name: "Police_Report_Case_789.pdf",
    type: "Police Report",
    size: "1.8 MB",
    uploadDate: "2024-01-14",
    claimId: "CLM-2024-002",
    status: "Under Review",
  },
  {
    id: 3,
    name: "Medical_Records_Summary.pdf",
    type: "Medical Records",
    size: "3.2 MB",
    uploadDate: "2024-01-13",
    claimId: "CLM-2024-003",
    status: "Approved",
  },
  {
    id: 4,
    name: "Insurance_Card_Front.jpg",
    type: "Insurance Card",
    size: "0.8 MB",
    uploadDate: "2024-01-12",
    claimId: "CLM-2024-004",
    status: "Pending",
  },
]

export default function DocumentsPage() {
  const [searchTerm, setSearchTerm] = useState("")

  const filteredDocuments = documents.filter(
    (doc) =>
      doc.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      doc.type.toLowerCase().includes(searchTerm.toLowerCase()) ||
      doc.claimId.toLowerCase().includes(searchTerm.toLowerCase()),
  )

  const getFileIcon = (type: string) => {
    if (type.includes("Image") || type.includes("Card")) return <ImageIcon className="h-4 w-4" />
    if (type.includes("PDF") || type.includes("Report")) return <FileText className="h-4 w-4" />
    return <File className="h-4 w-4" />
  }

  const getStatusColor = (status: string) => {
    switch (status) {
      case "Approved":
        return "bg-green-100 text-green-800"
      case "Processed":
        return "bg-blue-100 text-blue-800"
      case "Under Review":
        return "bg-yellow-100 text-yellow-800"
      case "Pending":
        return "bg-gray-100 text-gray-800"
      default:
        return "bg-gray-100 text-gray-800"
    }
  }

  return (
  
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-3xl font-bold text-gray-900">Documents</h1>
            <p className="text-gray-600">Manage HCFA-1500 forms, police reports, and case documents</p>
          </div>
          <Button className="bg-[#0A3B1E] hover:bg-[#2E7D32]">
            <Upload className="h-4 w-4 mr-2" />
            Upload Document
          </Button>
        </div>

        <Card className="backdrop-blur-sm bg-white/90 border-0 shadow-lg">
          <CardHeader>
            <CardTitle className="text-[#0A3B1E]">Document Library</CardTitle>
            <CardDescription>Search and manage all case-related documents</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="flex items-center space-x-2 mb-6">
              <Search className="h-4 w-4 text-gray-400" />
              <Input
                placeholder="Search documents by name, type, or claim ID..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="flex-1"
              />
            </div>

            <div className="space-y-4">
              {filteredDocuments.map((doc) => (
                <Card key={doc.id} className="hover:shadow-md transition-shadow">
                  <CardContent className="p-4">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center space-x-4">
                        <div className="p-2 bg-[#4CAF50]/10 rounded-lg">{getFileIcon(doc.type)}</div>
                        <div>
                          <h3 className="font-semibold text-gray-900">{doc.name}</h3>
                          <div className="flex items-center space-x-4 text-sm text-gray-600">
                            <span>{doc.type}</span>
                            <span>•</span>
                            <span>{doc.size}</span>
                            <span>•</span>
                            <span>Uploaded {doc.uploadDate}</span>
                            <span>•</span>
                            <span className="text-[#2E7D32] font-medium">{doc.claimId}</span>
                          </div>
                        </div>
                      </div>
                      <div className="flex items-center space-x-3">
                        <Badge className={getStatusColor(doc.status)}>{doc.status}</Badge>
                        <div className="flex space-x-2">
                          <Button variant="outline" size="sm">
                            <Eye className="h-4 w-4" />
                          </Button>
                          <Button variant="outline" size="sm">
                            <Download className="h-4 w-4" />
                          </Button>
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
 
  )
}
