"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Bot, Plus, Search, Filter, FileText, Send } from "lucide-react"
import { Input } from "@/components/ui/input"
import { useGetAppealsQuery } from "@/store/api/billingApiSlice"
import { Skeleton } from "@/components/ui/skeleton"
import Link from "next/link"

export default function AppealsPage() {
  const [searchTerm, setSearchTerm] = useState("")
  const { data: appealsData, isLoading } = useGetAppealsQuery({ search: searchTerm })

  const getStatusColor = (status: string) => {
    switch (status) {
      case "accepted": return "bg-emerald-100 text-emerald-700 border-emerald-200"
      case "sent": return "bg-blue-100 text-blue-700 border-blue-200"
      case "rejected": return "bg-red-100 text-red-700 border-red-200"
      case "draft": return "bg-slate-100 text-slate-700 border-slate-200"
      default: return "bg-slate-100 text-slate-700"
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 className="text-3xl font-bold text-primary">AI Appeal History</h1>
          <p className="text-muted-foreground">Manage and track AI-generated claim appeals</p>
        </div>
        <Link href="/dashboard/billing/appeals/create">
          <Button>
            <Plus className="h-4 w-4 mr-2" />
            New AI Appeal
          </Button>
        </Link>
      </div>

      <Card className="bg-card/80 backdrop-blur-sm">
        <CardHeader>
          <div className="flex flex-col md:flex-row md:items-center gap-4">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search by appeal number or invoice..."
                className="pl-10"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
              />
            </div>
            <Button variant="outline">
              <Filter className="h-4 w-4 mr-2" />
              Status
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-4">
              {[1, 2, 3, 4, 5].map((i) => (
                <Skeleton key={i} className="h-12 w-full" />
              ))}
            </div>
          ) : (
            <div className="rounded-md border">
              <Table>
                <TableHeader className="bg-muted/50">
                  <TableRow>
                    <TableHead>Appeal #</TableHead>
                    <TableHead>Invoice #</TableHead>
                    <TableHead>Category</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Generated At</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {appealsData?.data?.data?.map((appeal) => (
                    <TableRow key={appeal.id}>
                      <TableCell className="font-medium">{appeal.appeal_number}</TableCell>
                      <TableCell>{appeal.invoice?.invoice_number || "N/A"}</TableCell>
                      <TableCell>{appeal.reason_category}</TableCell>
                      <TableCell>
                        <Badge variant="outline" className={getStatusColor(appeal.status)}>
                          {appeal.status.toUpperCase()}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-xs text-muted-foreground">
                        {new Date(appeal.created_at).toLocaleDateString()}
                      </TableCell>
                      <TableCell className="text-right">
                        <Button variant="ghost" size="sm">
                          <FileText className="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="sm" className="text-primary">
                          <Send className="h-4 w-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                  {appealsData?.data?.data?.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={6} className="h-24 text-center">
                        No appeals found. Click "New AI Appeal" to start.
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
